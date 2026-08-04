package com.phpnitro.engine

import android.animation.ValueAnimator
import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RectF
import android.view.View
import kotlin.random.Random

/**
 * A one-shot celebratory particle burst — see Confetti.php's own
 * docblock (packages/ui/src/Native/Confetti.php) and
 * NativeRenderPocActivity's showConfettiOverlay(), which adds this as a
 * full-screen overlay View on top of rootLayout and removes it again once
 * the animation finishes. Owns its own ValueAnimator-driven clock (same
 * "client keeps ticking, no per-frame server round-trip" idiom
 * NativeCanvasView's spinner command already uses for the exact same
 * "continuous animation, one static JSON response can't express this"
 * problem) — nothing here talks back to PHP at all.
 */
class ConfettiView(context: Context) : View(context) {
    private data class Particle(
        var x: Float,
        var y: Float,
        val velocityX: Float,
        var velocityY: Float,
        var rotation: Float,
        val rotationSpeed: Float,
        val size: Float,
        val color: Int,
    )

    private val particles = mutableListOf<Particle>()
    private var animator: ValueAnimator? = null
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG)

    // Brand-ish palette, matching colors already hardcoded elsewhere in
    // this file's own dev-tools badge / error-card (see
    // NativeRenderPocActivity's showConnectionError()) — no config knob
    // for this yet, deliberately kept simple for a v1.
    private val colors = intArrayOf(
        Color.parseColor("#F97316"),
        Color.parseColor("#DC2626"),
        Color.parseColor("#111827"),
        Color.parseColor("#10B981"),
        Color.parseColor("#3B82F6"),
        Color.parseColor("#F59E0B"),
    )

    /** durationMs must match (or be slightly less than) whatever the caller schedules the view's removal for — see showConfettiOverlay(). */
    fun start(particleCount: Int = 120, durationMs: Long = 3000L) {
        val density = resources.displayMetrics.density

        // width is 0 until this view has actually been laid out — post()
        // defers particle creation to after that first layout pass so
        // particles spawn across the view's real width instead of all
        // piling up at x=0.
        post {
            particles.clear()
            val effectiveWidth = width.toFloat().coerceAtLeast(1f)
            repeat(particleCount) {
                particles.add(
                    Particle(
                        x = Random.nextFloat() * effectiveWidth,
                        y = -20f * density - Random.nextFloat() * 600f * density,
                        velocityX = (Random.nextFloat() - 0.5f) * 5f * density,
                        velocityY = (3f + Random.nextFloat() * 4f) * density,
                        rotation = Random.nextFloat() * 360f,
                        rotationSpeed = (Random.nextFloat() - 0.5f) * 14f,
                        size = (6f + Random.nextFloat() * 6f) * density,
                        color = colors[Random.nextInt(colors.size)],
                    ),
                )
            }

            animator?.cancel()
            animator = ValueAnimator.ofFloat(0f, 1f).apply {
                duration = durationMs
                addUpdateListener {
                    for (particle in particles) {
                        particle.x += particle.velocityX
                        particle.y += particle.velocityY
                        particle.rotation += particle.rotationSpeed
                    }
                    invalidate()
                }
                start()
            }
        }
    }

    fun stop() {
        animator?.cancel()
        animator = null
        particles.clear()
    }

    override fun onDraw(canvas: Canvas) {
        for (particle in particles) {
            if (particle.y - particle.size > height || particle.y + particle.size < 0) continue
            paint.color = particle.color
            canvas.save()
            canvas.translate(particle.x, particle.y)
            canvas.rotate(particle.rotation)
            canvas.drawRect(
                RectF(-particle.size / 2f, -particle.size / 4f, particle.size / 2f, particle.size / 4f),
                paint,
            )
            canvas.restore()
        }
    }
}
