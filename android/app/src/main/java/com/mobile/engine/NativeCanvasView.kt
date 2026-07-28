package com.mobile.engine

import android.animation.ValueAnimator
import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.Shader
import android.graphics.Typeface
import android.util.Log
import android.view.HapticFeedbackConstants
import android.view.MotionEvent
import android.view.View
import android.view.animation.DecelerateInterpolator
import org.json.JSONArray
import org.json.JSONObject

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
 */
class NativeCanvasView(context: Context) : View(context) {

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
    var onAction: ((String) -> Unit)? = null

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

            previousCommands = if (commands.length() > 0) commands else null
            commands = newCommands
            Log.i("NativeCanvasView", "setCommands: ${commands.length()} commands, ${hitRegions.length()} hit regions, view size ${width}x${height}")
            startCrossfade()
        } catch (e: org.json.JSONException) {
            Log.e("NativeCanvasView", "setCommands: response wasn't valid JSON: $json", e)
        }
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
        if (event.action != MotionEvent.ACTION_UP) {
            return true
        }

        for (index in 0 until hitRegions.length()) {
            val region = hitRegions.getJSONObject(index)
            val left = region.getDouble("x")
            val top = region.getDouble("y")
            val right = left + region.getDouble("width")
            val bottom = top + region.getDouble("height")

            if (event.x >= left && event.x <= right && event.y >= top && event.y <= bottom) {
                Log.i("NativeCanvasView", "tap hit region: ${region.getString("action")}")
                performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY)
                onAction?.invoke(region.getString("action"))
                return true
            }
        }

        return true
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)

        val previous = previousCommands
        if (previous != null && fadeProgress < 1f) {
            drawCommands(canvas, previous, 1f - fadeProgress)
        }
        drawCommands(canvas, commands, fadeProgress)
    }

    private fun drawCommands(canvas: Canvas, list: JSONArray, alpha: Float) {
        for (index in 0 until list.length()) {
            val command = list.getJSONObject(index)
            when (command.getString("type")) {
                "rect" -> drawRectCommand(canvas, command, alpha)
                "text" -> drawTextCommand(canvas, command, alpha)
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
}
