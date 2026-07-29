package com.mobile.engine

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
import android.graphics.Typeface
import android.util.Log
import android.view.HapticFeedbackConstants
import android.view.MotionEvent
import android.view.VelocityTracker
import android.view.View
import android.view.ViewConfiguration
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
 * — NativeCanvas.php's paint pass, not the frozen Phase 0 protocol. A tap
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
    }

    private var commands: JSONArray = JSONArray()
    private var hitRegions: JSONArray = JSONArray()
    private var previousCommands: JSONArray? = null
    private var fadeAnimator: ValueAnimator? = null
    private var fadeProgress: Float = 1f
    // regionDp is the tapped hit region's own rect, in the same dp space
    // as every draw command — NativeRenderPocActivity needs it for
    // "focus:" actions, to position a real EditText overlay exactly over
    // the tapped field (see its showTextInput()).
    var onAction: ((action: String, regionDp: RectF) -> Unit)? = null

    // Scrolling: page-level only (the whole screen scrolls together, not
    // independent nested lists — see docs/proposals/moteur-rendu-natif.md's
    // phased plan for what a real per-widget ListView would need beyond
    // this). PHP reports how tall the laid-out content actually is
    // (contentHeight); scrollY is clamped to [0, contentHeight - viewport].
    private var contentHeight: Float = 0f
    private var scrollY: Float = 0f
    private var scrollAnimator: ValueAnimator? = null
    private var velocityTracker: VelocityTracker? = null
    private var touchDownX = 0f
    private var touchDownY = 0f
    private var lastTouchY = 0f
    private var isDragging = false
    private val touchSlop = ViewConfiguration.get(context).scaledTouchSlop

    fun setCommands(json: String) {
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
            scrollY = scrollY.coerceIn(0f, maxScrollY())

            previousCommands = if (commands.length() > 0) commands else null
            commands = newCommands
            Log.i("NativeCanvasView", "setCommands: ${commands.length()} commands, ${hitRegions.length()} hit regions, contentHeight=$contentHeight, view size ${width}x${height}")
            startCrossfade()
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

    override fun onTouchEvent(event: MotionEvent): Boolean {
        val maxScroll = maxScrollY()

        when (event.action) {
            MotionEvent.ACTION_DOWN -> {
                scrollAnimator?.cancel()
                touchDownX = event.x
                touchDownY = event.y
                lastTouchY = event.y
                isDragging = false
                velocityTracker?.recycle()
                velocityTracker = VelocityTracker.obtain().also { it.addMovement(event) }
            }

            MotionEvent.ACTION_MOVE -> {
                velocityTracker?.addMovement(event)
                val totalDelta = event.y - touchDownY
                if (!isDragging && maxScroll > 0f && abs(totalDelta) > touchSlop && abs(totalDelta) > abs(event.x - touchDownX)) {
                    isDragging = true
                }
                if (isDragging) {
                    val deltaDp = (lastTouchY - event.y) / density
                    scrollY = (scrollY + deltaDp).coerceIn(0f, maxScroll)
                    lastTouchY = event.y
                    invalidate()
                }
            }

            MotionEvent.ACTION_UP -> {
                if (isDragging) {
                    velocityTracker?.let {
                        it.addMovement(event)
                        it.computeCurrentVelocity(1000)
                        flingScroll(-it.yVelocity / density, maxScroll)
                    }
                } else {
                    handleTap(event)
                }
                velocityTracker?.recycle()
                velocityTracker = null
            }

            MotionEvent.ACTION_CANCEL -> {
                velocityTracker?.recycle()
                velocityTracker = null
            }
        }

        return true
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
                invalidate()
            }
            start()
        }
    }

    private fun handleTap(event: MotionEvent) {
        // Touch coordinates arrive in real device pixels; hitRegions are in
        // the same dp space the draw commands use, so this has to undo the
        // same scale (and scroll offset) onDraw applies before comparing.
        val touchX = event.x / density
        val touchY = event.y / density + scrollY

        for (index in 0 until hitRegions.length()) {
            val region = hitRegions.getJSONObject(index)
            val left = region.getDouble("x")
            val top = region.getDouble("y")
            val right = left + region.getDouble("width")
            val bottom = top + region.getDouble("height")

            if (touchX >= left && touchX <= right && touchY >= top && touchY <= bottom) {
                Log.i("NativeCanvasView", "tap hit region: ${region.getString("action")}")
                performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onAction?.invoke(region.getString("action"), RectF(left.toFloat(), top.toFloat(), right.toFloat(), bottom.toFloat()))
                return
            }
        }
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)

        val savedState = canvas.save()
        canvas.scale(density, density)
        canvas.translate(0f, -scrollY)

        val previous = previousCommands
        if (previous != null && fadeProgress < 1f) {
            drawCommands(canvas, previous, 1f - fadeProgress)
        }
        drawCommands(canvas, commands, fadeProgress)

        canvas.restoreToCount(savedState)
    }

    private fun drawCommands(canvas: Canvas, list: JSONArray, alpha: Float) {
        for (index in 0 until list.length()) {
            val command = list.getJSONObject(index)
            when (command.getString("type")) {
                "rect" -> drawRectCommand(canvas, command, alpha)
                "text" -> drawTextCommand(canvas, command, alpha)
                "icon" -> drawIconCommand(canvas, command, alpha)
                "image" -> drawImageCommand(canvas, command, alpha)
            }
        }
    }

    private fun drawRectCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val rect = RectF(
            command.getDouble("x").toFloat(),
            command.getDouble("y").toFloat(),
            (command.getDouble("x") + command.getDouble("width")).toFloat(),
            (command.getDouble("y") + command.getDouble("height")).toFloat(),
        )
        val radius = command.optDouble("radius", 0.0).toFloat()

        // NativeCanvas.php (the layout-engine paint target) omits "color"
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

    private fun drawTextCommand(canvas: Canvas, command: JSONObject, alpha: Float) {
        val bold = command.optBoolean("bold", false)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = Color.parseColor(command.optString("color", "#000000"))
            textSize = command.optDouble("size", 16.0).toFloat()
            typeface = if (bold) Typeface.DEFAULT_BOLD else Typeface.DEFAULT
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

    companion object {
        @Volatile
        private var cachedMaterialIconsTypeface: Typeface? = null

        private fun materialIconsTypeface(context: Context): Typeface {
            return cachedMaterialIconsTypeface ?: Typeface.createFromAsset(context.assets, "fonts/MaterialIcons-Regular.ttf").also {
                cachedMaterialIconsTypeface = it
            }
        }
    }
}
