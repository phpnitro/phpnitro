package com.phpnitro.engine

import android.animation.ValueAnimator
import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapShader
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.LinearGradient
import android.graphics.Matrix
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.Shader
import android.graphics.Rect
import android.graphics.Typeface
import android.os.Bundle
import android.util.Log
import android.view.GestureDetector
import android.view.HapticFeedbackConstants
import android.view.MotionEvent
import android.view.VelocityTracker
import android.view.View
import android.view.ViewConfiguration
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityNodeInfo
import android.view.accessibility.AccessibilityNodeProvider
import android.view.animation.DecelerateInterpolator
import org.json.JSONArray
import org.json.JSONObject
import kotlin.math.abs

/**
 * Phase 0 of docs/proposals/moteur-rendu-natif.md: proof that PHP-driven
 * draw commands can be replayed against a REAL native Canvas (Skia at the
 * OS level, no WebView involved) — before investing in a layout engine,
 * hit-testing, or anything else. Deliberately isolated from MainActivity/
 * WebAppInterface: nothing about the existing WebView-based app changes or
 * risks regressing while this is built out in parallel.
 *
 * Draw commands are a flat JSON array, each one shaped like
 * {"type": "rect"|"text", ...params in absolute pixel coordinates}. No
 * layout engine yet (phase 2) — positions are whatever the caller passes,
 * hardcoded for this phase.
 *
 * Phase 3 (hit-testing/actions): setCommands() now expects
 * {"commands": [...], "hitRegions": [{"x","y","width","height","action"}]}
 * — Canvas.php's paint pass, not the frozen Phase 0 protocol. A tap
 * inside a hit region fires onAction with that region's action string; the
 * caller (NativeRenderPocActivity) is the one that actually talks to PHP
 * about it, this view only knows about pixels and rects.
 *
 * Phase 5 (animation): PHP has no concept of "the previous frame" — every
 * response is a fresh full draw-command list, computed from scratch. A
 * server-driven UI update would otherwise be a hard cut (old frame this
 * vsync, entirely new one the next). setCommands() keeps the outgoing
 * frame around and ValueAnimator (itself Choreographer-driven — every
 * update tick is a real vsync-synced frame callback, not a timer) blends
 * old-fading-out under new-fading-in over ~220ms, which is what makes a
 * counter update or a re-render read as "the UI changed" instead of "the
 * screen flickered".
 *
 * Density: every coordinate in a draw command (position, font size,
 * radius, stroke width...) is authored as a dp-like number on the PHP
 * side (Tokens::TEXT_BODY = 15, a button height of 54, etc.) — the same
 * mental model as Flutter/Android's own dp system. NativeRenderPocActivity
 * passes a dp-space screen width to /native/layout-demo (not the raw
 * pixel width), and this view scales its Canvas by the real device
 * density before replaying anything, so "15" ends up the same physical
 * size a Flutter app's 15dp text would be. Getting this wrong (drawing
 * dp-authored numbers as raw pixels) is why an early version of this
 * screen rendered with everything roughly half the intended size on a 2x
 * density device — a real bug, not a style choice.
 */
class NativeCanvasView(context: Context) : View(context) {

    /** Set by the host Activity from resources.displayMetrics.density. */
    var density: Float = 1f

    init {
        // Paint.setShadowLayer (used for elevation below) only renders
        // reliably on a software-composited layer — hardware acceleration
        // silently drops arbitrary-shape blur shadows on many API levels.
        // This view is a handful of rects/text per frame, not a
        // performance-sensitive scroll surface, so the software cost is a
        // non-issue.
        setLayerType(LAYER_TYPE_SOFTWARE, null)
        isClickable = true
        // The Canvas draws pixels, not real Views — with nothing else, a
        // screen reader has one giant unlabeled surface to announce.
        // getAccessibilityNodeProvider() below exposes a virtual node per
        // hitRegion/text command instead, so TalkBack's explore-by-touch
        // and swipe navigation work the way they would over real widgets.
        importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_YES
    }

    private var commands: JSONArray = JSONArray()
    private var hitRegions: JSONArray = JSONArray()
    private var previousCommands: JSONArray? = null
    private var fadeAnimator: ValueAnimator? = null
    private var fadeProgress: Float = 1f

    // Hero FLIP transition (Canvas::beginHero()/heroRegions in the
    // JSON payload): heroRegions is this render's {tag: rect} map;
    // previousHeroRegions is the prior render's. A tag present in both at
    // two DIFFERENT rects means that subtree should fly from the old rect
    // to the new one instead of just crossfading in place like everything
    // else — activeHeroFlights holds (oldRect, newRect) per flying tag for
    // the duration of heroAnimator, read by drawHeroTransition().
    private var heroRegions: Map<String, RectF> = emptyMap()
    private var previousHeroRegions: Map<String, RectF>? = null
    // Curve::name (a per-tag choice — see Canvas::beginHero()'s
    // $curve) or null for the default. heroAnimator itself always runs
    // linear time (see startHeroTransition()); drawHeroTransition()
    // reshapes heroProgress through this tag's own Interpolator, so
    // several concurrently-flying tags can each ease differently without
    // needing one ValueAnimator per tag.
    private var heroCurves: Map<String, String?> = emptyMap()
    private var heroAnimator: ValueAnimator? = null

    // Drives drawSpinnerCommand()'s continuous rotation — started the
    // first time a "spinner" command appears anywhere in the current
    // commands list, stopped the moment none remain, so an app with no
    // indeterminate spinner on screen never pays for a perpetual redraw
    // loop.
    private var spinnerAnimator: ValueAnimator? = null

    private fun updateSpinnerAnimator() {
        val hasSpinner = (0 until commands.length()).any { commands.getJSONObject(it).optString("type") == "spinner" }
        if (hasSpinner) {
            if (spinnerAnimator == null) {
                spinnerAnimator = ValueAnimator.ofFloat(0f, 1f).apply {
                    duration = 16
                    repeatCount = ValueAnimator.INFINITE
                    addUpdateListener { invalidate() }
                    start()
                }
            }
        } else {
            spinnerAnimator?.cancel()
            spinnerAnimator = null
        }
    }

    override fun onDetachedFromWindow() {
        super.onDetachedFromWindow()
        spinnerAnimator?.cancel()
        spinnerAnimator = null
    }
    private var heroProgress: Float = 1f
    private var activeHeroFlights: Map<String, Pair<RectF, RectF>> = emptyMap()
    // regionDp is the tapped hit region's own rect, in the same dp space
    // as every draw command — NativeRenderPocActivity needs it for
    // "focus:" actions, to position a real EditText overlay exactly over
    // the tapped field (see its showTextInput()).
    var onAction: ((action: String, regionDp: RectF, meta: JSONObject?) -> Unit)? = null

    // Scrolling: page-level only (the whole screen scrolls together, not
    // independent nested lists — see docs/proposals/moteur-rendu-natif.md's
    // phased plan for what a real per-widget ListView would need beyond
    // this). PHP reports how tall the laid-out content actually is
    // (contentHeight); scrollY is clamped to [0, contentHeight - viewport].
    private var contentHeight: Float = 0f
    private var scrollY: Float = 0f

    // LazyList support: PHP only builds/paints the items within a
    // window around the scrollY it was given (Canvas::
    // setScrollFollow()), so scrolling far enough from where that window
    // was centered needs a re-fetch to load the next window — otherwise
    // the user scrolls into blank space past whatever was last built.
    // lastFetchedScrollY is the scrollY that produced the CURRENT
    // commands; a re-fetch is triggered once actual scroll drifts more
    // than one viewport-height away from it, well inside
    // LazyList's default 2-viewport buffer so the new window is
    // very likely already loaded by the time the user reaches its edge.
    private var scrollFollow: Boolean = false
    private var lastFetchedScrollY: Float = 0f
    var onScrollFollow: ((scrollYDp: Float) -> Unit)? = null

    /** Current scroll position in dp — NativeRenderPocActivity reports this on every fetch. */
    val currentScrollYDp: Float get() = scrollY

    private fun checkScrollFollow() {
        if (!scrollFollow) return
        val viewportDp = if (density > 0) height / density else 0f
        if (viewportDp <= 0f) return
        if (abs(scrollY - lastFetchedScrollY) > viewportDp) {
            lastFetchedScrollY = scrollY
            onScrollFollow?.invoke(scrollY)
        }
    }

    // Dismissible (Canvas::dismissible()/beginDismiss()/
    // endDismiss()) — the one genuinely continuous gesture in this
    // pipeline. The drag itself is tracked entirely here, no round-trip
    // per frame: pendingDismiss is a hit-tested candidate the moment a
    // finger goes down on a dismissible rect, promoted to activeDismiss
    // once the first decisive move sample confirms a horizontal drag
    // (a vertical one falls through to the normal page-scroll path
    // instead, same axis-detection idea the scroll/tap split already
    // uses). dismissedKeys is this render generation's "already swiped
    // away" set — cleared on the next setCommands(), since a confirmed
    // dismiss's own server round-trip will re-render without that item
    // anyway.
    private data class DismissRegion(val key: String, val rect: RectF, val action: String)
    private var dismissRegions: List<DismissRegion> = emptyList()
    private var pendingDismiss: DismissRegion? = null
    private var activeDismiss: DismissRegion? = null
    private var dismissOffsetX = 0f
    private var dismissSettleAnimator: ValueAnimator? = null
    private val dismissedKeys = mutableSetOf<String>()

    // Reorderable (Canvas::reorderItem()/beginReorder()/
    // endReorder()) — a long-press on an item commits to a drag (a plain
    // tap or a vertical scroll starting on the same item must NOT
    // trigger it, hence waiting for GestureDetector's own long-press
    // timer rather than reacting to the first move sample like dismiss
    // does). reorderOrder holds the CURRENT key order per group, mutated
    // live as the dragged item's center crosses a neighbor's — every
    // other key's slot is whatever original rect that position in the
    // order now maps to, and reorderAnimatedY eases each displaced key
    // into its new slot instead of snapping.
    private data class ReorderItem(val group: String, val key: String, val rect: RectF, val action: String)
    private var reorderItems: List<ReorderItem> = emptyList()
    private val reorderOrder = mutableMapOf<String, MutableList<String>>()
    private var reorderLongPressCandidate: ReorderItem? = null
    private var activeReorder: ReorderItem? = null
    private var reorderDragOffsetY = 0f
    private val reorderAnimatedY = mutableMapOf<String, Float>()
    private val reorderSlotAnimators = mutableMapOf<String, ValueAnimator>()
    private var reorderSettleAnimator: ValueAnimator? = null

    // Lottie (Canvas::lottieRegion()) — read by
    // NativeRenderPocActivity after every setCommands() to reconcile a
    // real LottieAnimationView overlay per key (added/repositioned/
    // removed), the same "no Canvas concept for this, overlay a real
    // View" idiom as the video/map overlays, just synced every render
    // instead of only on tap since a Lottie animation autoplays.
    data class LottieRegion(val key: String, val rect: RectF, val url: String, val loop: Boolean, val autoplay: Boolean)
    var lottieRegions: List<LottieRegion> = emptyList()
        private set

    // ClientTabs' whole point — which panel is selected per group
    // key lives here, on the client, and NOTHING else. Seeded once from
    // whichever panel declared itself initiallyActive; a later render of
    // the same screen (a completely unrelated refetch) must never reset a
    // tab the user has already switched away from, so seedClientTabState()
    // only fills in keys this map doesn't already have.
    private val clientTabState = mutableMapOf<String, Int>()

    // HorizontalScroll (Canvas::horizontalScroll()) — a nested scroll
    // region with its own local offset, independent of the outer page
    // scroll (scrollY below). hScrollOffsets follows clientTabState's exact
    // pattern applied to a continuous drag instead of a discrete tab
    // index: seeded to 0 the first time a key is seen, never reset by a
    // later unrelated render. Only ONE level of nesting is tracked — a
    // HorizontalScroll containing another independently-scrollable region
    // isn't supported.
    private data class HScrollRegion(val key: String, val rect: RectF, val contentWidth: Float, val viewportWidth: Float)
    private var hScrollRegions: List<HScrollRegion> = emptyList()
    private val hScrollOffsets = mutableMapOf<String, Float>()
    private var pendingHScroll: HScrollRegion? = null
    private var activeHScroll: HScrollRegion? = null

    private fun seedClientTabState(list: JSONArray) {
        for (index in 0 until list.length()) {
            val command = list.getJSONObject(index)
            if (command.optString("type") != "clientPanel") continue
            val key = command.getString("key")
            if (clientTabState.containsKey(key)) continue
            if (command.optBoolean("initiallyActive", false)) {
                clientTabState[key] = command.getInt("index")
            }
        }
    }

    /** Called by NativeRenderPocActivity for a "clientTab:key:index" tap — no refetch, ever. */
    fun setClientTab(key: String, index: Int) {
        clientTabState[key] = index
        invalidate()
    }

    // DevTools overlay readouts — set at the end of setCommands(), read
    // (never written) by NativeRenderPocActivity's updateDevToolsPanel().
    var lastCommandCount: Int = 0
        private set
    var lastHitRegionCount: Int = 0
        private set
    var lastInvalidateWasPartial: Boolean = false
        private set

    private var scrollAnimator: ValueAnimator? = null
    private var velocityTracker: VelocityTracker? = null
    private var touchDownX = 0f
    private var touchDownY = 0f
    private var lastTouchX = 0f
    private var lastTouchY = 0f
    private var isDragging = false
    private val touchSlop = ViewConfiguration.get(context).scaledTouchSlop

    // GestureDetector's double-tap/swipe — a real
    // android.view.GestureDetector run alongside the manual scroll
    // tracking above (which only ever cared about vertical drags), not a
    // second reimplementation of tap-timing/fling-velocity math.
    // gestureConsumedThisTouch suppresses handleTap()'s single-tap
    // dispatch for a touch sequence a gesture callback already handled —
    // otherwise ACTION_UP would ALSO fire a plain tap at the release
    // point after a double-tap or swipe.
    private var gestureConsumedThisTouch = false
    private val androidGestureDetector = GestureDetector(context, object : GestureDetector.SimpleOnGestureListener() {
        override fun onDoubleTap(e: MotionEvent): Boolean {
            dispatchGestureAction(e, "onDoubleClick")
            return true
        }

        override fun onFling(e1: MotionEvent?, e2: MotionEvent, velocityX: Float, velocityY: Float): Boolean {
            if (e1 == null) return false
            val dx = e2.x - e1.x
            if (abs(dx) > abs(e2.y - e1.y) && abs(dx) > touchSlop * 3) {
                dispatchGestureAction(e1, if (dx > 0) "onSwipeRight" else "onSwipeLeft")
                return true
            }
            return false
        }

        override fun onLongPress(e: MotionEvent) {
            if (activeDismiss != null || activeReorder != null) return
            val candidate = hitTestReorder(e) ?: return
            reorderSettleAnimator?.cancel()
            reorderSlotAnimators.values.forEach { it.cancel() }
            reorderSlotAnimators.clear()
            val order = reorderOrder.getOrPut(candidate.group) {
                reorderItems.filter { it.group == candidate.group }
                    .sortedBy { it.rect.top }
                    .map { it.key }
                    .toMutableList()
            }
            order.forEach { key -> reorderAnimatedY[key] = slotRectFor(candidate.group, key)?.top ?: 0f }
            activeReorder = candidate
            reorderDragOffsetY = 0f
            gestureConsumedThisTouch = true
            performHapticFeedback(HapticFeedbackConstants.LONG_PRESS)
            invalidate()
        }
    })

    // Set alongside commands from the same request that produced them
    // (NativeRenderPocActivity.fetchDrawCommands already computes this dp
    // width to ask PHP to lay out against) — NativePrintAdapter needs it to
    // know what dp-space width the commands' coordinates were authored
    // for, the same way onDraw() above needs `density` to scale them.
    var lastScreenWidthDp: Float = 360f

    fun setCommands(json: String, screenWidthDp: Float = lastScreenWidthDp, isNavigation: Boolean = false) {
        // A PHP warning/notice ahead of the JSON (a bad file path, an
        // undefined-variable notice in debug mode, etc.) turns this into
        // plain HTML — that's a server-side bug to fix, but the app
        // crashing outright over one bad response is strictly worse than
        // logging it and leaving the last good frame on screen.
        try {
            val payload = JSONObject(json)
            val newCommands = payload.getJSONArray("commands")
            hitRegions = payload.optJSONArray("hitRegions") ?: JSONArray()
            contentHeight = payload.optDouble("contentHeight", 0.0).toFloat()
            lastScreenWidthDp = screenWidthDp
            scrollFollow = payload.optBoolean("scrollFollow", false)
            scrollY = scrollY.coerceIn(0f, maxScrollY())
            lastFetchedScrollY = scrollY

            previousCommands = if (commands.length() > 0) commands else null
            commands = newCommands
            updateSpinnerAnimator()

            previousHeroRegions = if (heroRegions.isNotEmpty()) heroRegions else null
            heroRegions = parseHeroRegions(payload.optJSONArray("heroRegions"))
            heroCurves = parseHeroCurves(payload.optJSONArray("heroRegions"))
            dismissRegions = parseDismissRegions(payload.optJSONArray("dismissRegions"))
            dismissedKeys.clear()
            reorderItems = parseReorderItems(payload.optJSONArray("reorderRegions"))
            reorderOrder.clear()
            reorderAnimatedY.clear()
            lottieRegions = parseLottieRegions(payload.optJSONArray("lottieRegions"))
            hScrollRegions = parseHScrollRegions(commands)
            seedClientTabState(commands)
            rebuildAccessibilityNodes()
            lastCommandCount = commands.length()
            lastHitRegionCount = hitRegions.length()
            Log.i("NativeCanvasView", "setCommands: ${commands.length()} commands, ${hitRegions.length()} hit regions, contentHeight=$contentHeight, view size ${width}x${height}")
            // Only an actual scene change (navigate:/tab:/back, a
            // redirect, or the very first load) gets the whole-screen
            // crossfade — a same-screen refetch (a counter increment, a
            // toggle, a dismiss/reorder settling) updates instantly
            // instead, so tapping "+" doesn't read as "the screen just
            // reloaded". Hero/Animated's own per-element
            // transitions are separate and always run either way.
            if (isNavigation) {
                lastInvalidateWasPartial = false
                startCrossfade()
            } else {
                fadeAnimator?.cancel()
                fadeProgress = 1f
                val dirtyPixelRect = computeDirtyRects(previousCommands, commands)?.let { toPixelRect(it) }
                lastInvalidateWasPartial = dirtyPixelRect != null
                if (dirtyPixelRect != null) {
                    invalidate(dirtyPixelRect)
                } else {
                    invalidate()
                }
            }
            startHeroTransition()
        } catch (e: org.json.JSONException) {
            Log.e("NativeCanvasView", "setCommands: response wasn't valid JSON: $json", e)
        }
    }

    private fun maxScrollY(): Float {
        val viewportDp = if (density > 0) height / density else 0f
        return (contentHeight - viewportDp).coerceAtLeast(0f)
    }

    private fun startCrossfade() {
        fadeAnimator?.cancel()
        fadeProgress = 0f
        fadeAnimator = ValueAnimator.ofFloat(0f, 1f).apply {
            duration = 220
            interpolator = DecelerateInterpolator()
            addUpdateListener {
                fadeProgress = it.animatedValue as Float
                invalidate()
            }
            start()
        }
    }

    private fun parseHeroRegions(array: JSONArray?): Map<String, RectF> {
        if (array == null) return emptyMap()
        val result = mutableMapOf<String, RectF>()
        for (index in 0 until array.length()) {
            val region = array.getJSONObject(index)
            val left = region.getDouble("x").toFloat()
            val top = region.getDouble("y").toFloat()
            result[region.getString("tag")] = RectF(left, top, left + region.getDouble("width").toFloat(), top + region.getDouble("height").toFloat())
        }
        return result
    }

    private fun parseHeroCurves(array: JSONArray?): Map<String, String?> {
        if (array == null) return emptyMap()
        val result = mutableMapOf<String, String?>()
        for (index in 0 until array.length()) {
            val region = array.getJSONObject(index)
            result[region.getString("tag")] = region.optString("curve", "").ifEmpty { null }
        }
        return result
    }

    /** Curve::name -> the closest built-in Android Interpolator (see Curve.php's docblock for ELASTIC's caveat). */
    private fun curveInterpolator(name: String?): android.view.animation.Interpolator = when (name) {
        "LINEAR" -> android.view.animation.LinearInterpolator()
        "EASE_IN" -> android.view.animation.AccelerateInterpolator()
        "EASE_IN_OUT" -> android.view.animation.AccelerateDecelerateInterpolator()
        "BOUNCE" -> android.view.animation.BounceInterpolator()
        "ELASTIC" -> android.view.animation.OvershootInterpolator(2f)
        else -> DecelerateInterpolator() // EASE_OUT and the no-curve default — matches this pipeline's original fixed behavior.
    }

    private fun parseDismissRegions(array: JSONArray?): List<DismissRegion> {
        if (array == null) return emptyList()
        val result = mutableListOf<DismissRegion>()
        for (index in 0 until array.length()) {
            val region = array.getJSONObject(index)
            val left = region.getDouble("x").toFloat()
            val top = region.getDouble("y").toFloat()
            val rect = RectF(left, top, left + region.getDouble("width").toFloat(), top + region.getDouble("height").toFloat())
            result.add(DismissRegion(region.getString("key"), rect, region.getString("action")))
        }
        return result
    }

    private fun parseReorderItems(array: JSONArray?): List<ReorderItem> {
        if (array == null) return emptyList()
        val result = mutableListOf<ReorderItem>()
        for (index in 0 until array.length()) {
            val item = array.getJSONObject(index)
            val left = item.getDouble("x").toFloat()
            val top = item.getDouble("y").toFloat()
            val rect = RectF(left, top, left + item.getDouble("width").toFloat(), top + item.getDouble("height").toFloat())
            result.add(ReorderItem(item.getString("group"), item.getString("key"), rect, item.getString("action")))
        }
        return result
    }

    private fun parseLottieRegions(array: JSONArray?): List<LottieRegion> {
        if (array == null) return emptyList()
        val result = mutableListOf<LottieRegion>()
        for (index in 0 until array.length()) {
            val item = array.getJSONObject(index)
            val left = item.getDouble("x").toFloat()
            val top = item.getDouble("y").toFloat()
            val rect = RectF(left, top, left + item.getDouble("width").toFloat(), top + item.getDouble("height").toFloat())
            result.add(LottieRegion(item.getString("key"), rect, item.getString("url"), item.getBoolean("loop"), item.getBoolean("autoplay")))
        }
        return result
    }

    /** hScroll commands live inline in `commands` (see drawHScrollCommand()), not a dedicated top-level array like dismissRegions/reorderRegions. */
    private fun parseHScrollRegions(list: JSONArray): List<HScrollRegion> {
        val result = mutableListOf<HScrollRegion>()
        for (index in 0 until list.length()) {
            val command = list.getJSONObject(index)
            if (command.optString("type") != "hScroll") continue
            val left = command.getDouble("x").toFloat()
            val top = command.getDouble("y").toFloat()
            val width = command.getDouble("width").toFloat()
            val height = command.getDouble("height").toFloat()
            val rect = RectF(left, top, left + width, top + height)
            result.add(HScrollRegion(command.getString("key"), rect, command.getDouble("contentWidth").toFloat(), width))
        }
        return result
    }

    private fun startHeroTransition() {
        val previous = previousHeroRegions
        heroAnimator?.cancel()
        activeHeroFlights = if (previous == null) {
            emptyMap()
        } else {
            heroRegions.mapNotNull { (tag, newRect) ->
                val oldRect = previous[tag]
                if (oldRect != null && oldRect != newRect) tag to (oldRect to newRect) else null
            }.toMap()
        }
        if (activeHeroFlights.isEmpty()) return
        heroProgress = 0f
        // Linear time on purpose — drawHeroTransition() reshapes this raw
        // 0..1 fraction through each tag's OWN Interpolator
        // (curveInterpolator()), so several tags flying at once can each
        // ease differently without needing a separate animator per tag.
        heroAnimator = ValueAnimator.ofFloat(0f, 1f).apply {
            duration = 280
            interpolator = android.view.animation.LinearInterpolator()
            addUpdateListener {
                heroProgress = it.animatedValue as Float
                invalidate()
            }
            start()
        }
    }

    /**
     * Flies each tagged subtree from its old rect to its new one — a real
     * FLIP transition, not a crossfade: the new render's own commands for
     * that tag are replayed through a Matrix mapping their authored (new)
     * rect onto this frame's interpolated rect, so the element visibly
     * translates+scales across the screen instead of just fading. Drawn in
     * screen space (density scale only, no scroll translate) since a Hero
     * flight crosses a navigation boundary where the two screens' scroll
     * positions aren't comparable.
     */
    private fun drawHeroTransition(canvas: Canvas) {
        if (activeHeroFlights.isEmpty()) return
        val saved = canvas.save()
        canvas.scale(density, density)
        val previous = previousCommands
        for ((tag, flight) in activeHeroFlights) {
            val (oldRect, newRect) = flight
            val eased = curveInterpolator(heroCurves[tag]).getInterpolation(heroProgress)
            val interpRect = RectF(
                lerp(oldRect.left, newRect.left, eased),
                lerp(oldRect.top, newRect.top, eased),
                lerp(oldRect.right, newRect.right, eased),
                lerp(oldRect.bottom, newRect.bottom, eased),
            )
            val matrix = Matrix()
            matrix.postTranslate(-newRect.left, -newRect.top)
            if (newRect.width() > 0 && newRect.height() > 0) {
                matrix.postScale(interpRect.width() / newRect.width(), interpRect.height() / newRect.height())
            }
            matrix.postTranslate(interpRect.left, interpRect.top)

            // Not just the subtree's outer rect flies — each individual
            // command's own geometry/color eases too (drawInterpolated()),
            // so a background color or corner radius change animates
            // alongside the position/size, not just snaps on arrival.
            // Paired by index within the tag, the same "structure doesn't
            // change, only property values do" assumption Flutter's own
            // implicit animations make about a widget's identity.
            val newTagged = collectByField(commands, "hero", tag)
            val oldTagged = previous?.let { collectByField(it, "hero", tag) } ?: emptyList()

            val innerSaved = canvas.save()
            canvas.concat(matrix)
            for (index in newTagged.indices) {
                drawInterpolated(canvas, oldTagged.getOrNull(index), newTagged[index], eased)
            }
            canvas.restoreToCount(innerSaved)
        }
        canvas.restoreToCount(saved)
    }

    private fun collectByField(list: JSONArray, field: String, value: String): List<JSONObject> {
        val result = mutableListOf<JSONObject>()
        for (index in 0 until list.length()) {
            val command = list.getJSONObject(index)
            if (command.optString(field, "") == value) result.add(command)
        }
        return result
    }

    private val numericFieldsByType = mapOf(
        "rect" to listOf("x", "y", "width", "height", "radius", "borderWidth", "elevation"),
        "circle" to listOf("cx", "cy", "radius", "borderWidth"),
        "arc" to listOf("cx", "cy", "radius", "startDegrees", "sweepDegrees", "strokeWidth"),
        "line" to listOf("x1", "y1", "x2", "y2", "width"),
        "text" to listOf("x", "y", "size", "letterSpacing"),
        "icon" to listOf("x", "y", "size"),
        "image" to listOf("x", "y", "width", "height", "radius"),
    )
    private val colorFieldsByType = mapOf(
        "rect" to listOf("color", "borderColor", "gradientFrom", "gradientTo"),
        "circle" to listOf("color", "borderColor"),
        "arc" to listOf("color"),
        "line" to listOf("color"),
        "text" to listOf("color"),
        "icon" to listOf("color"),
    )
    private val argbEvaluator = android.animation.ArgbEvaluator()

    /**
     * Builds a synthetic command with old->new numeric fields lerped and
     * color-like fields ARGB-blended, then draws it through the same
     * draw*Command functions everything else uses — an interpolated frame
     * is just a command with different numbers, not a special draw path.
     * Falls back to drawing `new` as-is when there's no old counterpart
     * (first appearance) or the type changed (nothing sane to interpolate
     * between a rect and a circle).
     */
    private fun drawInterpolated(canvas: Canvas, old: JSONObject?, new: JSONObject, progress: Float) {
        val type = new.getString("type")
        if (old == null || old.optString("type") != type) {
            drawSingleCommand(canvas, new, 1f)
            return
        }
        val blended = JSONObject(new.toString())
        numericFieldsByType[type]?.forEach { field ->
            if (old.has(field) && new.has(field)) {
                blended.put(field, lerp(old.getDouble(field).toFloat(), new.getDouble(field).toFloat(), progress).toDouble())
            }
        }
        colorFieldsByType[type]?.forEach { field ->
            if (old.has(field) && new.has(field)) {
                val fromColor = Color.parseColor(old.getString(field))
                val toColor = Color.parseColor(new.getString(field))
                val evaluated = argbEvaluator.evaluate(progress, fromColor, toColor) as Int
                blended.put(field, String.format("#%08X", evaluated))
            }
        }
        drawSingleCommand(canvas, blended, 1f)
    }

    private fun drawSingleCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        when (command.getString("type")) {
            "rect" -> drawRectCommand(canvas, command, alpha)
            "text" -> drawTextCommand(canvas, command, alpha)
            "icon" -> drawIconCommand(canvas, command, alpha)
            "image" -> drawImageCommand(canvas, command, alpha)
            "circle" -> drawCircleCommand(canvas, command, alpha)
            "line" -> drawLineCommand(canvas, command, alpha)
            "arc" -> drawArcCommand(canvas, command, alpha)
            "clientPanel" -> drawClientPanelCommand(canvas, command, alpha)
            "hScroll" -> drawHScrollCommand(canvas, command, alpha)
            "spinner" -> drawSpinnerCommand(canvas, command, alpha)
        }
    }

    // No rotation angle travels with this command at all (see
    // Canvas::spinner()'s docblock) — computed fresh from the
    // system clock every single frame, driven by spinnerAnimator's
    // continuous invalidate() ticks rather than a fresh setCommands().
    private fun drawSpinnerCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val x = command.getDouble("x").toFloat()
        val y = command.getDouble("y").toFloat()
        val size = command.getDouble("size").toFloat()
        val strokeWidth = command.getDouble("strokeWidth").toFloat()
        val center = size / 2
        val radius = center - strokeWidth / 2
        val cx = x + center
        val cy = y + center
        val rect = RectF(cx - radius, cy - radius, cx + radius, cy + radius)

        val trackPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            style = Paint.Style.STROKE
            this.strokeWidth = strokeWidth
            color = Color.parseColor(command.getString("trackColor"))
            this.alpha = (this.alpha * alpha).toInt()
        }
        canvas.drawArc(rect, 0f, 360f, false, trackPaint)

        val sweepPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            style = Paint.Style.STROKE
            this.strokeWidth = strokeWidth
            strokeCap = Paint.Cap.ROUND
            color = Color.parseColor(command.getString("color"))
            this.alpha = (this.alpha * alpha).toInt()
        }
        val periodMs = 1100f
        val rotation = (android.os.SystemClock.uptimeMillis() % periodMs.toLong()) / periodMs * 360f
        canvas.drawArc(rect, rotation, 110f, false, sweepPaint)
    }

    // Only the panel whose index matches this group's current local
    // selection draws — every other panel this same command list carries
    // (there's one clientPanel command per ClientTabs panel, all
    // sharing the same rect) is skipped outright, same idea as the
    // dismiss/reorder key checks in drawCommands() just above.
    private fun drawClientPanelCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        if (clientTabState[command.getString("key")] != command.getInt("index")) return
        val saved = canvas.save()
        canvas.translate(command.getDouble("x").toFloat(), command.getDouble("y").toFloat())
        val nested = command.getJSONArray("commands")
        for (index in 0 until nested.length()) {
            drawSingleCommand(canvas, nested.getJSONObject(index), alpha)
        }
        canvas.restoreToCount(saved)
    }

    // The outer scrollable pass has already translated by -scrollY before
    // this runs (same as drawClientPanelCommand), so only this region's OWN
    // local offset needs handling here — clip to the viewport rect so
    // content dragged past its edge doesn't paint over neighboring rows,
    // then shift by -offset along the local drag axis only.
    private fun drawHScrollCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        // Coordinates here are dp, same as every other draw command — the
        // canvas passed in already has the density scale applied by the
        // caller (see onDraw()'s canvas.scale(density, density)), so unlike
        // hit-testing (raw pixels in, dp compared) nothing here multiplies
        // by density itself — matches drawClientPanelCommand exactly.
        val key = command.getString("key")
        val x = command.getDouble("x").toFloat()
        val y = command.getDouble("y").toFloat()
        val w = command.getDouble("width").toFloat()
        val h = command.getDouble("height").toFloat()
        val offset = hScrollOffsets[key] ?: 0f
        val saved = canvas.save()
        canvas.clipRect(x, y, x + w, y + h)
        canvas.translate(x - offset, y)
        val nested = command.getJSONArray("commands")
        for (index in 0 until nested.length()) {
            drawSingleCommand(canvas, nested.getJSONObject(index), alpha)
        }
        canvas.restoreToCount(saved)
    }

    private fun lerp(from: Float, to: Float, progress: Float): Float = from + (to - from) * progress

    override fun onTouchEvent(event: MotionEvent): Boolean {
        val maxScroll = maxScrollY()
        // Reset BEFORE androidGestureDetector.onTouchEvent() below — it
        // can call onDoubleTap() synchronously from right here on a
        // second tap's ACTION_DOWN, which must survive until this same
        // touch's ACTION_UP checks it further down.
        if (event.action == MotionEvent.ACTION_DOWN) {
            gestureConsumedThisTouch = false
        }
        androidGestureDetector.onTouchEvent(event)

        when (event.action) {
            MotionEvent.ACTION_DOWN -> {
                scrollAnimator?.cancel()
                dismissSettleAnimator?.cancel()
                reorderSettleAnimator?.cancel()
                touchDownX = event.x
                touchDownY = event.y
                lastTouchX = event.x
                lastTouchY = event.y
                isDragging = false
                pendingDismiss = if (activeDismiss == null && activeReorder == null) hitTestDismiss(event) else null
                pendingHScroll = if (activeHScroll == null && activeReorder == null) hitTestHScroll(event) else null
                velocityTracker?.recycle()
                velocityTracker = VelocityTracker.obtain().also { it.addMovement(event) }
            }

            MotionEvent.ACTION_MOVE -> {
                velocityTracker?.addMovement(event)
                val totalDeltaY = event.y - touchDownY
                val totalDeltaX = event.x - touchDownX

                if (activeReorder != null) {
                    updateReorderDrag(totalDeltaY)
                    return true
                }

                // hScroll takes priority over dismiss for the same first-
                // decisive-horizontal-move disambiguation — a region can't
                // sensibly be both, but checking this first means a
                // HorizontalScroll nested inside a Dismissible row (not a
                // supported combination, but not one worth crashing over
                // either) resolves to scrolling rather than dismissing.
                if (activeHScroll == null && pendingHScroll != null && !isDragging &&
                    abs(totalDeltaX) > touchSlop && abs(totalDeltaX) > abs(totalDeltaY)
                ) {
                    activeHScroll = pendingHScroll
                    pendingHScroll = null
                    pendingDismiss = null
                } else if (activeDismiss == null && pendingDismiss != null && !isDragging &&
                    abs(totalDeltaX) > touchSlop && abs(totalDeltaX) > abs(totalDeltaY)
                ) {
                    // First decisive move was horizontal over a dismissible
                    // rect — commit to a dismiss drag instead of a page
                    // scroll for the rest of this gesture.
                    activeDismiss = pendingDismiss
                    pendingDismiss = null
                } else if (!isDragging && (pendingDismiss != null || pendingHScroll != null) &&
                    abs(totalDeltaY) > touchSlop && abs(totalDeltaY) > abs(totalDeltaX)
                ) {
                    // Vertical instead — this was never a dismiss/hScroll gesture.
                    pendingDismiss = null
                    pendingHScroll = null
                }

                if (activeHScroll != null) {
                    val region = activeHScroll!!
                    val deltaXDp = (lastTouchX - event.x) / density
                    val maxOffset = (region.contentWidth - region.viewportWidth).coerceAtLeast(0f)
                    hScrollOffsets[region.key] = ((hScrollOffsets[region.key] ?: 0f) + deltaXDp).coerceIn(0f, maxOffset)
                    lastTouchX = event.x
                    invalidate()
                } else if (activeDismiss != null) {
                    dismissOffsetX = totalDeltaX / density
                    invalidate()
                } else {
                    if (!isDragging && maxScroll > 0f && abs(totalDeltaY) > touchSlop && abs(totalDeltaY) > abs(totalDeltaX)) {
                        isDragging = true
                    }
                    if (isDragging) {
                        val deltaDp = (lastTouchY - event.y) / density
                        scrollY = (scrollY + deltaDp).coerceIn(0f, maxScroll)
                        lastTouchY = event.y
                        checkScrollFollow()
                        invalidate()
                    }
                }
            }

            MotionEvent.ACTION_UP -> {
                if (activeReorder != null) {
                    settleReorder()
                } else if (activeDismiss != null) {
                    settleDismiss()
                } else if (activeHScroll != null) {
                    activeHScroll = null
                } else if (isDragging) {
                    velocityTracker?.let {
                        it.addMovement(event)
                        it.computeCurrentVelocity(1000)
                        flingScroll(-it.yVelocity / density, maxScroll)
                    }
                } else if (!gestureConsumedThisTouch) {
                    handleTap(event)
                }
                pendingDismiss = null
                pendingHScroll = null
                velocityTracker?.recycle()
                velocityTracker = null
            }

            MotionEvent.ACTION_CANCEL -> {
                if (activeReorder != null) {
                    cancelReorder()
                }
                activeHScroll = null
                pendingHScroll = null
                if (activeDismiss != null) {
                    springBackDismiss()
                }
                pendingDismiss = null
                velocityTracker?.recycle()
                velocityTracker = null
            }
        }

        return true
    }

    private fun hitTestReorder(event: MotionEvent): ReorderItem? {
        val touchX = event.x / density
        val touchY = event.y / density + scrollY
        return reorderItems.firstOrNull { item ->
            touchX >= item.rect.left && touchX <= item.rect.right &&
                touchY >= item.rect.top && touchY <= item.rect.bottom
        }
    }

    private fun originalSlotRects(group: String): List<RectF> =
        reorderItems.filter { it.group == group }.sortedBy { it.rect.top }.map { it.rect }

    private fun slotRectFor(group: String, key: String): RectF? {
        val order = reorderOrder[group] ?: return null
        val index = order.indexOf(key)
        if (index < 0) return null
        return originalSlotRects(group).getOrNull(index)
    }

    private fun animateReorderSlot(key: String, from: Float, to: Float) {
        reorderSlotAnimators[key]?.cancel()
        val animator = ValueAnimator.ofFloat(from, to).apply {
            duration = 200
            interpolator = DecelerateInterpolator()
            addUpdateListener {
                reorderAnimatedY[key] = it.animatedValue as Float
                invalidate()
            }
        }
        reorderSlotAnimators[key] = animator
        animator.start()
    }

    // Called on every move sample while a reorder drag is active: finds
    // which slot the dragged item's CENTER now falls past (comparing
    // against each slot's own original center — the layout PHP already
    // authored, never recomputed mid-drag) and, if that's a different
    // slot than the dragged key currently occupies, swaps it into the
    // order and eases every displaced key into its new slot.
    private fun updateReorderDrag(totalDeltaY: Float) {
        val active = activeReorder ?: return
        reorderDragOffsetY = totalDeltaY / density
        invalidate()

        val order = reorderOrder[active.group] ?: return
        val slotRects = originalSlotRects(active.group)
        if (slotRects.isEmpty()) return
        val draggedCenterY = active.rect.top + active.rect.height() / 2 + reorderDragOffsetY

        var targetIndex = slotRects.indexOfLast { draggedCenterY >= it.top + it.height() / 2 }
        if (targetIndex < 0) targetIndex = 0
        val currentIndex = order.indexOf(active.key)
        if (targetIndex == currentIndex) return

        order.removeAt(currentIndex)
        order.add(targetIndex, active.key)
        order.forEachIndexed { index, key ->
            if (key == active.key) return@forEachIndexed
            val targetY = slotRects[index].top
            val currentY = reorderAnimatedY[key] ?: targetY
            if (abs(currentY - targetY) > 0.5f) {
                animateReorderSlot(key, currentY, targetY)
            }
        }
    }

    // Past release: the dragged item eases from wherever the finger left
    // it into its final slot, THEN — once that animation the user
    // actually watched has finished — the group's action fires with the
    // final key order as a comma-separated suffix. PHP sees the outcome,
    // never the gesture, same split as settleDismiss().
    private fun settleReorder() {
        val active = activeReorder ?: return
        val order = reorderOrder[active.group]?.toList() ?: run { activeReorder = null; return }
        val finalIndex = order.indexOf(active.key)
        val targetRect = originalSlotRects(active.group).getOrNull(finalIndex) ?: active.rect
        val targetOffset = targetRect.top - active.rect.top

        reorderSettleAnimator?.cancel()
        reorderSettleAnimator = ValueAnimator.ofFloat(reorderDragOffsetY, targetOffset).apply {
            duration = 200
            interpolator = DecelerateInterpolator()
            addUpdateListener {
                reorderDragOffsetY = it.animatedValue as Float
                invalidate()
            }
            addListener(object : android.animation.AnimatorListenerAdapter() {
                override fun onAnimationEnd(animation: android.animation.Animator) {
                    activeReorder = null
                    reorderDragOffsetY = 0f
                    invalidate()
                    performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                    onAction?.invoke("${active.action}:${order.joinToString(",")}", active.rect, null)
                }
            })
            start()
        }
    }

    private fun cancelReorder() {
        reorderSlotAnimators.values.forEach { it.cancel() }
        reorderSlotAnimators.clear()
        activeReorder = null
        reorderDragOffsetY = 0f
        invalidate()
    }

    private fun hitTestDismiss(event: MotionEvent): DismissRegion? {
        val touchX = event.x / density
        val touchY = event.y / density + scrollY
        return dismissRegions.firstOrNull { region ->
            touchX >= region.rect.left && touchX <= region.rect.right &&
                touchY >= region.rect.top && touchY <= region.rect.bottom
        }
    }

    private fun hitTestHScroll(event: MotionEvent): HScrollRegion? {
        val touchX = event.x / density
        val touchY = event.y / density + scrollY
        return hScrollRegions.firstOrNull { region ->
            touchX >= region.rect.left && touchX <= region.rect.right &&
                touchY >= region.rect.top && touchY <= region.rect.bottom
        }
    }

    // Past threshold: finish the swipe off-screen, hide the item locally
    // (dismissedKeys), THEN fire $action — PHP only hears about the
    // outcome once the animation the user actually watched has completed,
    // never mid-drag.
    private fun settleDismiss() {
        val region = activeDismiss ?: return
        val threshold = region.rect.width() * 0.35f
        if (abs(dismissOffsetX) <= threshold) {
            springBackDismiss()
            return
        }
        val direction = if (dismissOffsetX >= 0) 1f else -1f
        val target = direction * region.rect.width() * 1.2f
        dismissSettleAnimator?.cancel()
        dismissSettleAnimator = ValueAnimator.ofFloat(dismissOffsetX, target).apply {
            duration = 200
            interpolator = DecelerateInterpolator()
            addUpdateListener {
                dismissOffsetX = it.animatedValue as Float
                invalidate()
            }
            addListener(object : android.animation.AnimatorListenerAdapter() {
                override fun onAnimationEnd(animation: android.animation.Animator) {
                    dismissedKeys.add(region.key)
                    activeDismiss = null
                    dismissOffsetX = 0f
                    invalidate()
                    performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                    onAction?.invoke(region.action, region.rect, null)
                }
            })
            start()
        }
    }

    private fun springBackDismiss() {
        activeDismiss ?: return
        dismissSettleAnimator?.cancel()
        dismissSettleAnimator = ValueAnimator.ofFloat(dismissOffsetX, 0f).apply {
            duration = 200
            interpolator = DecelerateInterpolator()
            addUpdateListener {
                dismissOffsetX = it.animatedValue as Float
                invalidate()
            }
            addListener(object : android.animation.AnimatorListenerAdapter() {
                override fun onAnimationEnd(animation: android.animation.Animator) {
                    activeDismiss = null
                    dismissOffsetX = 0f
                    invalidate()
                }
            })
            start()
        }
    }

    // Momentum scrolling: a decelerating ValueAnimator from the release
    // velocity down to 0, same shape as Flutter's default ScrollPhysics
    // (a real fling, not a hard stop the instant the finger lifts).
    private fun flingScroll(velocityDpPerSec: Float, maxScroll: Float) {
        if (abs(velocityDpPerSec) < 50f || maxScroll <= 0f) return

        scrollAnimator?.cancel()
        val startScroll = scrollY
        val distance = velocityDpPerSec * 0.35f
        val target = (startScroll + distance).coerceIn(0f, maxScroll)
        scrollAnimator = ValueAnimator.ofFloat(startScroll, target).apply {
            duration = 350
            interpolator = DecelerateInterpolator()
            addUpdateListener {
                scrollY = it.animatedValue as Float
                checkScrollFollow()
                invalidate()
            }
            start()
        }
    }

    // GestureDetector's region carries its actions under named meta
    // keys ("onDoubleClick"/"onSwipeLeft"/"onSwipeRight") instead of the
    // plain "action" field every other hit region uses — a bare tap
    // inside the region does nothing (matches Engine\GestureDetector's
    // HTML <div> with no onclick), only a real double-tap/fling fires one
    // of these.
    private fun dispatchGestureAction(event: MotionEvent, key: String) {
        val touchX = event.x / density
        val rawTouchY = event.y / density

        for (index in 0 until hitRegions.length()) {
            val region = hitRegions.getJSONObject(index)
            val meta = region.optJSONObject("meta") ?: continue
            if (!meta.has(key)) continue

            val fixed = region.optBoolean("fixed", false)
            val touchY = if (fixed) rawTouchY else rawTouchY + scrollY
            val left = region.getDouble("x")
            val top = region.getDouble("y")
            val right = left + region.getDouble("width")
            val bottom = top + region.getDouble("height")

            if (touchX >= left && touchX <= right && touchY >= top && touchY <= bottom) {
                gestureConsumedThisTouch = true
                performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onAction?.invoke(meta.getString(key), RectF(left.toFloat(), top.toFloat(), right.toFloat(), bottom.toFloat()), meta)
                return
            }
        }
    }

    private fun handleTap(event: MotionEvent) {
        // Touch coordinates arrive in real device pixels; hitRegions are in
        // the same dp space the draw commands use, so this has to undo the
        // same scale onDraw applies before comparing. A "fixed" region
        // (AppBar/BottomNavigation/Fab, see Canvas::beginFixed()) is
        // screen-relative like it's drawn, so it's hit-tested against raw
        // touchY with no scrollY added — everything else undoes the scroll
        // offset same as before.
        val touchX = event.x / density
        val rawTouchY = event.y / density

        for (index in 0 until hitRegions.length()) {
            val region = hitRegions.getJSONObject(index)
            val fixed = region.optBoolean("fixed", false)
            val touchY = if (fixed) rawTouchY else rawTouchY + scrollY
            val left = region.getDouble("x")
            val top = region.getDouble("y")
            val right = left + region.getDouble("width")
            val bottom = top + region.getDouble("height")

            if (touchX >= left && touchX <= right && touchY >= top && touchY <= bottom) {
                Log.i("NativeCanvasView", "tap hit region: ${region.getString("action")}")
                performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onAction?.invoke(region.getString("action"), RectF(left.toFloat(), top.toFloat(), right.toFloat(), bottom.toFloat()), region.optJSONObject("meta"))
                return
            }
        }

        handleClientPanelTap(touchX, rawTouchY)
        handleHScrollTap(touchX, rawTouchY)
    }

    // ClientTabs panels carry their own hitRegions embedded inside
    // their "clientPanel" command (see Canvas::clientTabPanel()),
    // not merged into the top-level hitRegions array above — only the
    // currently selected panel of each group is a real tap target, and
    // "currently selected" is purely local state, so PHP has no way to
    // have pre-merged the right subset itself. Regions are stored relative
    // to the panel's own nested canvas, so the panel's (x, y) is added back
    // before comparing against the touch point.
    private fun handleClientPanelTap(touchX: Float, rawTouchY: Float) {
        for (index in 0 until commands.length()) {
            val command = commands.getJSONObject(index)
            if (command.optString("type") != "clientPanel") continue
            if (clientTabState[command.getString("key")] != command.getInt("index")) continue

            val fixed = command.optBoolean("fixed", false)
            val touchY = if (fixed) rawTouchY else rawTouchY + scrollY
            val offsetX = command.getDouble("x")
            val offsetY = command.getDouble("y")
            val nestedRegions = command.getJSONArray("hitRegions")

            for (regionIndex in 0 until nestedRegions.length()) {
                val region = nestedRegions.getJSONObject(regionIndex)
                val left = offsetX + region.getDouble("x")
                val top = offsetY + region.getDouble("y")
                val right = left + region.getDouble("width")
                val bottom = top + region.getDouble("height")

                if (touchX >= left && touchX <= right && touchY >= top && touchY <= bottom) {
                    Log.i("NativeCanvasView", "tap hit region (client panel): ${region.getString("action")}")
                    performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                    onAction?.invoke(region.getString("action"), RectF(left.toFloat(), top.toFloat(), right.toFloat(), bottom.toFloat()), region.optJSONObject("meta"))
                    return
                }
            }
        }
    }

    // hScroll's nested hitRegions are authored relative to the ROW's own
    // local content origin (see HorizontalScroll.php's $childOffsets), not
    // the viewport — so unlike handleClientPanelTap (whose panel never
    // scrolls), this also has to subtract the region's CURRENT drag offset
    // to land back in the same local space a tap's screen position was
    // taken from. A tap outside the viewport rect itself is skipped
    // outright: nested content commonly extends far past what's visible.
    private fun handleHScrollTap(touchX: Float, rawTouchY: Float) {
        for (index in 0 until commands.length()) {
            val command = commands.getJSONObject(index)
            if (command.optString("type") != "hScroll") continue

            val fixed = command.optBoolean("fixed", false)
            val touchY = if (fixed) rawTouchY else rawTouchY + scrollY
            val viewportX = command.getDouble("x")
            val viewportY = command.getDouble("y")
            val viewportWidth = command.getDouble("width")
            val viewportHeight = command.getDouble("height")
            if (touchX < viewportX || touchX > viewportX + viewportWidth ||
                touchY < viewportY || touchY > viewportY + viewportHeight
            ) continue

            val offset = hScrollOffsets[command.getString("key")] ?: 0f
            val offsetX = viewportX - offset
            val nestedRegions = command.getJSONArray("hitRegions")

            for (regionIndex in 0 until nestedRegions.length()) {
                val region = nestedRegions.getJSONObject(regionIndex)
                val left = offsetX + region.getDouble("x")
                val top = viewportY + region.getDouble("y")
                val right = left + region.getDouble("width")
                val bottom = top + region.getDouble("height")

                if (touchX >= left && touchX <= right && touchY >= top && touchY <= bottom) {
                    Log.i("NativeCanvasView", "tap hit region (hScroll): ${region.getString("action")}")
                    performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                    onAction?.invoke(region.getString("action"), RectF(left.toFloat(), top.toFloat(), right.toFloat(), bottom.toFloat()), region.optJSONObject("meta"))
                    return
                }
            }
        }
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)

        val previous = previousCommands

        val flyingTags = activeHeroFlights.keys

        // Scrollable pass: translated by -scrollY, fixed commands excluded.
        // Commands belonging to a tag currently in flight are skipped here
        // (in both the outgoing and incoming lists) — drawHeroTransition()
        // draws that tag's own pass instead, so it isn't drawn twice.
        var savedState = canvas.save()
        canvas.scale(density, density)
        canvas.translate(0f, -scrollY)
        if (previous != null && fadeProgress < 1f) {
            drawCommands(canvas, previous, 1f - fadeProgress, fixed = false, excludeHeroTags = flyingTags)
        }
        drawCommands(canvas, commands, fadeProgress, fixed = false, excludeHeroTags = flyingTags)
        canvas.restoreToCount(savedState)

        // Fixed pass: same density scale, no scroll translate — an
        // AppBar/BottomNavigation/Fab painted via Fixed stays pinned
        // to the viewport while the pass above scrolls underneath it.
        savedState = canvas.save()
        canvas.scale(density, density)
        if (previous != null && fadeProgress < 1f) {
            drawCommands(canvas, previous, 1f - fadeProgress, fixed = true, excludeHeroTags = flyingTags)
        }
        drawCommands(canvas, commands, fadeProgress, fixed = true, excludeHeroTags = flyingTags)
        canvas.restoreToCount(savedState)

        drawHeroTransition(canvas)
        drawDismissOverlay(canvas)
        drawReorderOverlay(canvas)
    }

    /**
     * Every item in the group currently being dragged — not just the
     * dragged item itself, ALL of them, since a neighbor whose slot
     * changed needs to be redrawn at its eased position too. The dragged
     * key follows the finger (its own original rect + the live drag
     * offset); every other key in the group draws at reorderAnimatedY,
     * which animateReorderSlot() eases toward whenever the order changes.
     */
    private fun drawReorderOverlay(canvas: Canvas) {
        val active = activeReorder ?: return
        val order = reorderOrder[active.group] ?: return
        val saved = canvas.save()
        canvas.scale(density, density)
        canvas.translate(0f, -scrollY)
        for (key in order) {
            val offsetY = if (key == active.key) {
                active.rect.top + reorderDragOffsetY
            } else {
                reorderAnimatedY[key] ?: slotRectFor(active.group, key)?.top ?: continue
            }
            val itemRect = reorderItems.firstOrNull { it.group == active.group && it.key == key }?.rect ?: continue
            val innerSaved = canvas.save()
            canvas.translate(0f, offsetY - itemRect.top)
            for (command in collectByField(commands, "reorder", key)) {
                drawSingleCommand(canvas, command, 1f)
            }
            canvas.restoreToCount(innerSaved)
        }
        canvas.restoreToCount(saved)
    }

    /**
     * The item currently being swiped (or settling back/away after
     * release) — excluded from the normal passes above, drawn here
     * instead so a live horizontal offset (and a fade as it nears
     * threshold) can be applied without touching every other command's
     * draw path. Still respects the page's own scroll translate — a
     * dismissible is body content, not fixed — so it stays aligned with
     * whatever it's sitting next to while being dragged sideways.
     */
    private fun drawDismissOverlay(canvas: Canvas) {
        val active = activeDismiss ?: return
        val saved = canvas.save()
        canvas.scale(density, density)
        canvas.translate(0f, -scrollY)
        canvas.translate(dismissOffsetX, 0f)
        val progress = (abs(dismissOffsetX) / active.rect.width()).coerceIn(0f, 1f)
        val alpha = 1f - progress * 0.7f
        for (command in collectByField(commands, "dismiss", active.key)) {
            drawSingleCommand(canvas, command, alpha)
        }
        canvas.restoreToCount(saved)
    }

    /**
     * The current commands/contentHeight, for NativePrintAdapter to replay
     * onto a PdfDocument.Page's own Canvas — a document has no scroll
     * position and no viewport to pin "fixed" commands against, so
     * drawForPrint() below just paints the whole flat list once, laid out
     * at the dp coordinates PHP already computed.
     */
    fun printSnapshot(): Pair<JSONArray, Float> = commands to contentHeight

    /**
     * scale: device-independent px per dp for the target page (so PHP's
     * dp-authored coordinates land at the right physical size on paper,
     * same idea as onDraw()'s `density` scale). pageOffsetDp: how far down
     * this page starts into the full contentHeight-tall document, for
     * simple top-to-bottom pagination across multiple pages.
     */
    fun drawForPrint(canvas: Canvas, list: JSONArray, scale: Float, pageOffsetDp: Float) {
        val saved = canvas.save()
        canvas.scale(scale, scale)
        canvas.translate(0f, -pageOffsetDp)
        drawCommands(canvas, list, 1f, fixed = false)
        drawCommands(canvas, list, 1f, fixed = true)
        canvas.restoreToCount(saved)
    }

    private fun drawCommands(
        canvas: Canvas,
        list: JSONArray,
        alpha: Float,
        fixed: Boolean,
        excludeHeroTags: Set<String> = emptySet(),
    ) {
        for (index in 0 until list.length()) {
            val command = list.getJSONObject(index)
            if (command.optBoolean("fixed", false) != fixed) continue
            val hero = command.optString("hero", "").ifEmpty { null }
            if (hero != null && excludeHeroTags.contains(hero)) continue
            val dismiss = command.optString("dismiss", "").ifEmpty { null }
            if (dismiss != null && (dismissedKeys.contains(dismiss) || dismiss == activeDismiss?.key)) continue
            val reorder = command.optString("reorder", "").ifEmpty { null }
            if (reorder != null && activeReorder != null && reorderOrder[activeReorder!!.group]?.contains(reorder) == true) continue
            // Canvas is already scaled/translated into the same dp space
            // these bounds are computed in (see onDraw's scale+translate
            // before calling this), so quickReject can compare them
            // directly — skips the Paint/typeface/drawXxx cost for
            // anything outside the current clip, the same win a partial
            // invalidate(Rect) is meant to buy, but it also helps for free
            // during an ordinary full invalidate while scrolled (content
            // above/below the viewport never needed drawing either).
            val bounds = commandBoundsDp(command)
            if (bounds != null && canvas.quickReject(bounds.left, bounds.top, bounds.right, bounds.bottom, Canvas.EdgeType.BW)) continue
            drawSingleCommand(canvas, command, alpha)
        }
    }

    /**
     * Exact or deliberately generous bounds for every command type that
     * can appear outside a clientPanel (rect/image/circle/arc/line/icon
     * are exact; text pads measureTextWidth() since no explicit width
     * travelled with the command) — used both to quickReject off-clip
     * commands above and to build a dirty rect below. Returns null for
     * anything not covered (clientPanel today), which both call sites
     * treat as "can't be sure, don't skip/don't shrink the invalidate".
     */
    private fun commandBoundsDp(command: JSONObject): RectF? = when (command.optString("type")) {
        "rect", "image" -> RectF(
            command.getDouble("x").toFloat(),
            command.getDouble("y").toFloat(),
            (command.getDouble("x") + command.getDouble("width")).toFloat(),
            (command.getDouble("y") + command.getDouble("height")).toFloat(),
        )
        "circle" -> {
            val cx = command.getDouble("cx").toFloat()
            val cy = command.getDouble("cy").toFloat()
            val r = command.getDouble("radius").toFloat()
            RectF(cx - r, cy - r, cx + r, cy + r)
        }
        "arc" -> {
            val cx = command.getDouble("cx").toFloat()
            val cy = command.getDouble("cy").toFloat()
            val r = command.getDouble("radius").toFloat() + command.getDouble("strokeWidth").toFloat()
            RectF(cx - r, cy - r, cx + r, cy + r)
        }
        "line" -> {
            val pad = command.optDouble("width", 1.0).toFloat() + 1f
            val x1 = command.getDouble("x1").toFloat()
            val y1 = command.getDouble("y1").toFloat()
            val x2 = command.getDouble("x2").toFloat()
            val y2 = command.getDouble("y2").toFloat()
            RectF(minOf(x1, x2) - pad, minOf(y1, y2) - pad, maxOf(x1, x2) + pad, maxOf(y1, y2) + pad)
        }
        "icon" -> {
            val x = command.getDouble("x").toFloat()
            val y = command.getDouble("y").toFloat()
            val size = command.getDouble("size").toFloat()
            RectF(x, y, x + size, y + size)
        }
        "text" -> {
            val x = command.getDouble("x").toFloat()
            val y = command.getDouble("y").toFloat()
            val size = command.optDouble("size", 16.0).toFloat()
            val width = measureTextWidth(command) * 1.2f
            RectF(x, y - size * 1.1f, x + width, y + size * 0.4f)
        }
        else -> null
    }

    private class DirtyRects(val scrollable: RectF?, val fixed: RectF?)

    /**
     * Same-length, index-aligned diff against the previous render's
     * commands — correct only because PHP emits a stable, deterministic
     * command order for the same screen/state shape every time (no
     * randomness, no unordered map iteration in the layout engine), so
     * "command i is now different" reliably means "widget i changed",
     * not "the list got reshuffled". A structural change (an item added/
     * removed, e.g. a list growing) makes the lengths differ, which bails
     * out to null — full invalidate, always correct, just not partial.
     */
    private fun computeDirtyRects(old: JSONArray?, new: JSONArray): DirtyRects? {
        if (old == null || old.length() != new.length()) return null

        var scrollable: RectF? = null
        var fixed: RectF? = null
        for (index in 0 until new.length()) {
            val previous = old.getJSONObject(index)
            val current = new.getJSONObject(index)
            if (previous.toString() == current.toString()) continue

            for (command in listOf(previous, current)) {
                val bounds = commandBoundsDp(command) ?: return null
                if (command.optBoolean("fixed", false)) {
                    fixed = fixed?.apply { union(bounds) } ?: RectF(bounds)
                } else {
                    scrollable = scrollable?.apply { union(bounds) } ?: RectF(bounds)
                }
            }
        }

        return if (scrollable == null && fixed == null) null else DirtyRects(scrollable, fixed)
    }

    /**
     * dp -> device pixels, matching onDraw's own two passes: the
     * scrollable pass is scaled by density and translated by -scrollY,
     * the fixed pass only scaled. A couple pixels of padding absorb
     * antialiasing/rounding slop at the rect's edges.
     */
    private fun toPixelRect(dirty: DirtyRects): Rect? {
        var union: RectF? = null
        dirty.scrollable?.let {
            val r = RectF(it.left * density, (it.top - scrollY) * density, it.right * density, (it.bottom - scrollY) * density)
            union = union?.apply { union(r) } ?: r
        }
        dirty.fixed?.let {
            val r = RectF(it.left * density, it.top * density, it.right * density, it.bottom * density)
            union = union?.apply { union(r) } ?: r
        }
        val result = union ?: return null
        result.inset(-2f, -2f)

        return Rect(result.left.toInt(), result.top.toInt(), result.right.toInt(), result.bottom.toInt())
    }

    private fun drawRectCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val rect = RectF(
            command.getDouble("x").toFloat(),
            command.getDouble("y").toFloat(),
            (command.getDouble("x") + command.getDouble("width")).toFloat(),
            (command.getDouble("y") + command.getDouble("height")).toFloat(),
        )
        val radius = command.optDouble("radius", 0.0).toFloat()

        // Canvas.php (the layout-engine paint target) omits "color"
        // entirely for a border-only box — a Container with borderColor but
        // no background shouldn't paint a fake fill underneath the stroke.
        if (command.has("color") || command.has("gradientFrom")) {
            val fillPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                style = Paint.Style.FILL
                if (command.has("gradientFrom")) {
                    // Top-left to bottom-right diagonal reads as "premium
                    // surface" (the direction most design systems default
                    // to for a subtle brand gradient) without needing a
                    // per-gradient angle parameter from PHP.
                    val from = Color.parseColor(command.getString("gradientFrom"))
                    val to = Color.parseColor(command.optString("gradientTo", command.getString("gradientFrom")))
                    shader = LinearGradient(rect.left, rect.top, rect.right, rect.bottom, from, to, Shader.TileMode.CLAMP)
                    this.alpha = (255 * alpha).toInt()
                } else {
                    color = Color.parseColor(command.getString("color"))
                    this.alpha = (this.alpha * alpha).toInt()
                }
            }

            val elevation = command.optDouble("elevation", 0.0).toFloat()
            if (elevation > 0) {
                // setShadowLayer draws the shadow behind whatever this same
                // paint's draw call renders, in the same pass — no second
                // canvas op needed. Blur/offset scale with elevation so
                // higher values read as "further off the page", same
                // convention as Flutter's Material elevation.
                val shadowAlpha = ((40 + elevation * 5).toInt().coerceAtMost(140) * alpha).toInt()
                fillPaint.setShadowLayer(elevation * 2.2f, 0f, elevation * 0.9f, Color.argb(shadowAlpha, 0, 0, 0))
            }

            if (radius > 0) canvas.drawRoundRect(rect, radius, radius, fillPaint) else canvas.drawRect(rect, fillPaint)
        }

        if (command.has("borderColor")) {
            val borderWidth = command.optDouble("borderWidth", 0.0).toFloat()
            val borderPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = Color.parseColor(command.getString("borderColor"))
                style = Paint.Style.STROKE
                strokeWidth = borderWidth
                this.alpha = (this.alpha * alpha).toInt()
            }
            // Stroke is centered on the rect's edge — inset by half the
            // stroke width so the border is fully contained within the box
            // the layout engine computed, matching how Android's border
            // drawables (and Flutter's BoxDecoration) render it.
            val inset = borderWidth / 2
            val strokeRect = RectF(rect.left + inset, rect.top + inset, rect.right - inset, rect.bottom - inset)
            if (radius > 0) canvas.drawRoundRect(strokeRect, radius, radius, borderPaint) else canvas.drawRect(strokeRect, borderPaint)
        }
    }

    // Same fill/stroke split as drawRectCommand — a circle with only a
    // borderColor (no color) is a ring, not a filled disc, same
    // "color absent means don't fake a fill" convention.
    private fun drawCircleCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val cx = command.getDouble("cx").toFloat()
        val cy = command.getDouble("cy").toFloat()
        val radius = command.getDouble("radius").toFloat()

        if (command.has("color")) {
            val fillPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                style = Paint.Style.FILL
                color = Color.parseColor(command.getString("color"))
                this.alpha = (this.alpha * alpha).toInt()
            }
            canvas.drawCircle(cx, cy, radius, fillPaint)
        }

        if (command.has("borderColor")) {
            val borderWidth = command.optDouble("borderWidth", 0.0).toFloat()
            val borderPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                style = Paint.Style.STROKE
                strokeWidth = borderWidth
                color = Color.parseColor(command.getString("borderColor"))
                this.alpha = (this.alpha * alpha).toInt()
            }
            canvas.drawCircle(cx, cy, radius - borderWidth / 2, borderPaint)
        }
    }

    // CircularProgress draws its track as a full-sweep arc and its
    // filled portion as a partial-sweep one on top — RectF bounding box
    // matches Android's Canvas.drawArc() convention (0° = 3 o'clock,
    // clockwise), same as the PHP side's docblock promises.
    private fun drawArcCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val cx = command.getDouble("cx").toFloat()
        val cy = command.getDouble("cy").toFloat()
        val radius = command.getDouble("radius").toFloat()
        val rect = RectF(cx - radius, cy - radius, cx + radius, cy + radius)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            style = Paint.Style.STROKE
            strokeCap = Paint.Cap.ROUND
            strokeWidth = command.getDouble("strokeWidth").toFloat()
            color = Color.parseColor(command.getString("color"))
            this.alpha = (this.alpha * alpha).toInt()
        }
        canvas.drawArc(rect, command.getDouble("startDegrees").toFloat(), command.getDouble("sweepDegrees").toFloat(), false, paint)
    }

    private fun drawLineCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            style = Paint.Style.STROKE
            strokeWidth = command.optDouble("width", 1.0).toFloat()
            color = Color.parseColor(command.getString("color"))
            this.alpha = (this.alpha * alpha).toInt()
        }
        canvas.drawLine(
            command.getDouble("x1").toFloat(),
            command.getDouble("y1").toFloat(),
            command.getDouble("x2").toFloat(),
            command.getDouble("y2").toFloat(),
            paint,
        )
    }

    private fun drawTextCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val bold = command.optBoolean("bold", false)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = Color.parseColor(command.optString("color", "#000000"))
            textSize = command.optDouble("size", 16.0).toFloat()
            typeface = robotoTypeface(context, bold)
            letterSpacing = command.optDouble("letterSpacing", 0.0).toFloat()
            this.alpha = (this.alpha * alpha).toInt()
        }
        canvas.drawText(
            command.getString("text"),
            command.getDouble("x").toFloat(),
            command.getDouble("y").toFloat(),
            paint,
        )
    }

    // An icon is a single glyph drawn against the bundled Material Icons
    // font — the same technique Flutter's own Icons class uses
    // internally — rather than a bitmap or a hand-drawn path, which is
    // what makes ~2235 icons (packages/ui/src/Native/MaterialIcons.php)
    // available for the cost of one font file instead of one Kotlin
    // function per icon.
    private fun drawIconCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val baseColor = Color.parseColor(command.optString("color", "#111827"))
        val color = Color.argb((Color.alpha(baseColor) * alpha).toInt(), Color.red(baseColor), Color.green(baseColor), Color.blue(baseColor))
        val size = command.getDouble("size").toFloat()
        val glyph = String(Character.toChars(command.getInt("codepoint")))

        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            this.color = color
            textSize = size
            typeface = materialIconsTypeface(context)
            textAlign = Paint.Align.CENTER
        }

        val x = command.getDouble("x").toFloat()
        val y = command.getDouble("y").toFloat()
        val cx = x + size / 2
        // Material Icons glyphs are drawn to roughly fill their em box —
        // baseline = top + ~86% of size centers them well inside the
        // requested size x size box, the standard trick for treating an
        // icon font as a square icon instead of as running text.
        val baselineY = y + size * 0.86f

        canvas.drawText(glyph, cx, baselineY, paint)
    }

    // ImageLoader owns the actual network fetch + decode + cache; this
    // just asks for whatever's cached and draws it if present, or kicks
    // off a load and redraws once ImageLoader has it. BitmapShader (not
    // a clip path) for rounded corners — cheaper, and avoids a second
    // offscreen layer on top of the software layer this view already
    // uses for shadows.
    private fun drawImageCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val url = command.getString("url")
        val x = command.getDouble("x").toFloat()
        val y = command.getDouble("y").toFloat()
        val width = command.getDouble("width").toFloat()
        val height = command.getDouble("height").toFloat()
        val radius = command.optDouble("radius", 0.0).toFloat()
        val rect = RectF(x, y, x + width, y + height)

        val bitmap = ImageLoader.get(url)
        if (bitmap == null) {
            ImageLoader.load(url) { invalidate() }
            return
        }

        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            this.alpha = (255 * alpha).toInt()
            if (radius > 0) {
                shader = BitmapShader(bitmap, Shader.TileMode.CLAMP, Shader.TileMode.CLAMP).apply {
                    val scale = maxOf(width / bitmap.width, height / bitmap.height)
                    setLocalMatrix(Matrix().apply {
                        setScale(scale, scale)
                        postTranslate(x, y)
                    })
                }
            }
        }

        if (radius > 0) {
            canvas.drawRoundRect(rect, radius, radius, paint)
        } else {
            val srcRect = android.graphics.Rect(0, 0, bitmap.width, bitmap.height)
            canvas.drawBitmap(bitmap, srcRect, rect, paint)
        }
    }

    // Accessibility: a virtual node tree built from hitRegions
    // (interactive — gets ACTION_CLICK) plus any "text" draw command not
    // already covered by one (read-only — a plain label/heading TalkBack
    // should still announce). Rebuilt on every setCommands() since the
    // whole point of this pipeline is that both change on every render.
    // Content description resolution, in order: an explicit "label" in
    // the hitRegion's meta (see Tappable's $label param) > any text
    // command whose baseline falls inside the region's rect (covers
    // Button/ListTile/SelectBox for free, no per-widget
    // wiring needed) > a humanized version of the action string itself
    // (last resort for an icon-only region with no nearby text).
    private data class AccessibilityNode(
        val id: Int,
        val rect: RectF,
        val fixed: Boolean,
        val description: String,
        val clickable: Boolean,
        val action: String?,
    )

    private var accessibilityNodes: List<AccessibilityNode> = emptyList()
    private val accessibilityNodeProvider by lazy { CanvasAccessibilityNodeProvider() }

    override fun getAccessibilityNodeProvider(): AccessibilityNodeProvider = accessibilityNodeProvider

    private fun humanizeAction(action: String): String {
        val token = action.substringAfterLast(':').substringBefore(':')
        val words = token.replace('_', ' ').replace('-', ' ').trim()
        return if (words.isEmpty()) action else words.replaceFirstChar { it.uppercase() }
    }

    private fun measureTextWidth(command: JSONObject): Float {
        val paint = Paint().apply {
            textSize = command.optDouble("size", 16.0).toFloat()
            typeface = robotoTypeface(context, command.optBoolean("bold", false))
        }
        return paint.measureText(command.optString("text", "")) / density
    }

    /** Text commands whose baseline falls inside `rect` (same fixed-ness), concatenated in document order. */
    private fun inferLabelFromCommands(rect: RectF, fixed: Boolean, claimed: MutableSet<Int>): String? {
        val parts = mutableListOf<String>()
        for (index in 0 until commands.length()) {
            if (index in claimed) continue
            val command = commands.getJSONObject(index)
            if (command.optString("type") != "text") continue
            if (command.optBoolean("fixed", false) != fixed) continue
            val x = command.getDouble("x").toFloat()
            val y = command.getDouble("y").toFloat()
            val size = command.optDouble("size", 16.0).toFloat()
            // "text"'s (x, y) is a baseline point, not a box top-left —
            // same ~80%-of-size baseline offset used everywhere else in
            // this file approximates the glyph's actual top back out.
            val top = y - size * 0.8f
            if (x < rect.left - 1f || x > rect.right + 1f) continue
            if (top < rect.top - 1f || top > rect.bottom + 1f) continue
            parts.add(command.getString("text"))
            claimed.add(index)
        }
        return if (parts.isEmpty()) null else parts.joinToString(" ")
    }

    private fun rebuildAccessibilityNodes() {
        val nodes = mutableListOf<AccessibilityNode>()
        val claimed = mutableSetOf<Int>()
        var nextId = 1

        for (index in 0 until hitRegions.length()) {
            val region = hitRegions.getJSONObject(index)
            val fixed = region.optBoolean("fixed", false)
            val left = region.getDouble("x").toFloat()
            val top = region.getDouble("y").toFloat()
            val rect = RectF(left, top, left + region.getDouble("width").toFloat(), top + region.getDouble("height").toFloat())
            val action = region.getString("action")
            val explicitLabel = region.optJSONObject("meta")?.optString("label", "")?.takeIf { it.isNotEmpty() }
            val label = explicitLabel ?: inferLabelFromCommands(rect, fixed, claimed) ?: humanizeAction(action)
            nodes.add(AccessibilityNode(nextId++, rect, fixed, label, clickable = true, action = action))
        }

        for (index in 0 until commands.length()) {
            if (index in claimed) continue
            val command = commands.getJSONObject(index)
            if (command.optString("type") != "text") continue
            val text = command.optString("text", "")
            if (text.isBlank()) continue
            val fixed = command.optBoolean("fixed", false)
            val x = command.getDouble("x").toFloat()
            val y = command.getDouble("y").toFloat()
            val size = command.optDouble("size", 16.0).toFloat()
            val top = y - size * 0.8f
            val rect = RectF(x, top, x + measureTextWidth(command), top + size * 1.25f)
            nodes.add(AccessibilityNode(nextId++, rect, fixed, text, clickable = false, action = null))
        }

        accessibilityNodes = nodes
        sendAccessibilityEvent(AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED)
    }

    private fun dpRectToLocalPixels(rect: RectF, fixed: Boolean): Rect {
        val scrollOffsetDp = if (fixed) 0f else scrollY
        return Rect(
            (rect.left * density).toInt(),
            ((rect.top - scrollOffsetDp) * density).toInt(),
            (rect.right * density).toInt(),
            ((rect.bottom - scrollOffsetDp) * density).toInt(),
        )
    }

    private fun dpRectToScreenPixels(rect: RectF, fixed: Boolean): Rect {
        val location = IntArray(2)
        getLocationOnScreen(location)
        val local = dpRectToLocalPixels(rect, fixed)
        return Rect(
            location[0] + local.left,
            location[1] + local.top,
            location[0] + local.right,
            location[1] + local.bottom,
        )
    }

    // AccessibilityNodeInfo.obtain()/AccessibilityEvent.obtain() are
    // deprecated in favor of their own constructors, but that constructor
    // path is a recent addition (API 26+/33+ depending on the overload) —
    // this app's minSdk is 24, and .obtain() remains fully functional (not
    // scheduled for removal), the same pattern AOSP's own accessibility
    // samples still use for a virtual node provider like this one.
    @Suppress("DEPRECATION")
    private inner class CanvasAccessibilityNodeProvider : AccessibilityNodeProvider() {
        override fun createAccessibilityNodeInfo(virtualViewId: Int): AccessibilityNodeInfo? {
            if (virtualViewId == AccessibilityNodeProvider.HOST_VIEW_ID) {
                val info = AccessibilityNodeInfo.obtain(this@NativeCanvasView)
                info.className = NativeCanvasView::class.java.name
                // Without these three, this root node is missing fields the
                // individual virtual children below all set — several
                // accessibility services (confirmed via uiautomator dump on
                // a real device) silently drop the WHOLE virtual subtree
                // rather than tolerate an incomplete host node, so this
                // isn't cosmetic: it's why no hitRegion ever reached
                // TalkBack/uiautomator despite the children being built
                // correctly.
                info.packageName = context.packageName
                info.isVisibleToUser = true
                info.isEnabled = true
                info.setBoundsInParent(Rect(0, 0, width, height))
                val location = IntArray(2)
                getLocationOnScreen(location)
                info.setBoundsInScreen(Rect(location[0], location[1], location[0] + width, location[1] + height))
                val viewport = Rect(0, 0, width, height)
                accessibilityNodes.forEach { node ->
                    if (Rect.intersects(dpRectToLocalPixels(node.rect, node.fixed), viewport)) {
                        info.addChild(this@NativeCanvasView, node.id)
                    }
                }
                return info
            }

            val node = accessibilityNodes.firstOrNull { it.id == virtualViewId } ?: return null
            val info = AccessibilityNodeInfo.obtain(this@NativeCanvasView, virtualViewId)
            info.className = if (node.clickable) "android.widget.Button" else "android.widget.TextView"
            info.packageName = context.packageName
            info.text = node.description
            info.contentDescription = node.description
            info.setBoundsInParent(dpRectToLocalPixels(node.rect, node.fixed))
            info.setBoundsInScreen(dpRectToScreenPixels(node.rect, node.fixed))
            info.isClickable = node.clickable
            info.isFocusable = true
            info.isVisibleToUser = true
            info.isEnabled = true
            info.setParent(this@NativeCanvasView)
            if (node.clickable) {
                info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
            }
            info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_ACCESSIBILITY_FOCUS)
            info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLEAR_ACCESSIBILITY_FOCUS)
            return info
        }

        override fun performAction(virtualViewId: Int, action: Int, arguments: Bundle?): Boolean {
            val node = accessibilityNodes.firstOrNull { it.id == virtualViewId }
            Log.i("NativeCanvasView", "performAction($virtualViewId, $action) node=${node?.description}")
            return when (action) {
                AccessibilityNodeInfo.ACTION_CLICK -> {
                    if (node == null || !node.clickable || node.action == null) {
                        false
                    } else {
                        performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                        onAction?.invoke(node.action, node.rect, null)
                        true
                    }
                }
                AccessibilityNodeInfo.ACTION_ACCESSIBILITY_FOCUS -> {
                    sendAccessibilityEventForVirtualView(virtualViewId, AccessibilityEvent.TYPE_VIEW_ACCESSIBILITY_FOCUSED)
                    true
                }
                AccessibilityNodeInfo.ACTION_CLEAR_ACCESSIBILITY_FOCUS -> {
                    sendAccessibilityEventForVirtualView(virtualViewId, AccessibilityEvent.TYPE_VIEW_ACCESSIBILITY_FOCUS_CLEARED)
                    true
                }
                else -> false
            }
        }

        override fun findAccessibilityNodeInfosByText(text: String?, virtualViewId: Int): List<AccessibilityNodeInfo> = emptyList()

        private fun sendAccessibilityEventForVirtualView(virtualViewId: Int, eventType: Int) {
            val node = accessibilityNodes.firstOrNull { it.id == virtualViewId } ?: return
            val event = AccessibilityEvent.obtain(eventType)
            event.packageName = context.packageName
            event.className = NativeCanvasView::class.java.name
            event.contentDescription = node.description
            event.setSource(this@NativeCanvasView, virtualViewId)
            (parent as? android.view.ViewGroup)?.requestSendAccessibilityEvent(this@NativeCanvasView, event)
        }
    }

    companion object {
        @Volatile
        private var cachedMaterialIconsTypeface: Typeface? = null

        private fun materialIconsTypeface(context: Context): Typeface {
            return cachedMaterialIconsTypeface ?: Typeface.createFromAsset(context.assets, "fonts/MaterialIcons-Regular.ttf").also {
                cachedMaterialIconsTypeface = it
            }
        }

        @Volatile
        private var cachedRobotoRegular: Typeface? = null

        @Volatile
        private var cachedRobotoBold: Typeface? = null

        /**
         * TextMetrics.php's per-character advance-width tables (what
         * Text/RichText's word-wrap and Center's
         * centering math are computed against) were measured against real
         * Roboto — but Typeface.DEFAULT/DEFAULT_BOLD is whatever the
         * OEM's Android skin ships as the system default, which on a
         * non-stock build (Transsion/XOS confirmed by hand, almost
         * certainly others too) is a DIFFERENT font with different glyph
         * widths. PHP's measured width and Kotlin's drawn width silently
         * disagreeing is exactly what makes centered text look
         * off-center: the button's own width was computed correctly, but
         * the label was centered using a wrong measurement of itself.
         * Bundling the actual Roboto (pulled from a real device's own
         * /system/fonts/, same Apache-2.0 AOSP font either way) and
         * loading it explicitly — the same fix MaterialIcons already
         * needed, and for the identical reason — makes drawn width match
         * measured width on every device, not just ones that happen to
         * default to Roboto already.
         */
        private fun robotoTypeface(context: Context, bold: Boolean): Typeface {
            if (bold) {
                return cachedRobotoBold ?: Typeface.create(robotoTypeface(context, bold = false), Typeface.BOLD).also {
                    cachedRobotoBold = it
                }
            }
            return cachedRobotoRegular ?: Typeface.createFromAsset(context.assets, "fonts/Roboto-Regular.ttf").also {
                cachedRobotoRegular = it
            }
        }
    }
}
