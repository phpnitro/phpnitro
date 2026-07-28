package com.mobile.engine

import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RectF

/**
 * A small icon set drawn from raw Canvas primitives (lines, arcs, paths)
 * rather than SVG path data or a bundled icon font — no vector asset
 * pipeline to build, and no risk of shipping subtly-wrong hand-copied
 * path coordinates. Each icon is deliberately simple geometry (a
 * checkmark is two line segments, a chevron is two line segments, a
 * document is a rounded rect + folded corner + text lines) that still
 * reads clearly as the intended icon at typical UI sizes (20-32dp).
 *
 * Every icon is drawn inside a size x size box with (x, y) as its
 * top-left corner, matching NativeCanvas.icon()'s convention.
 */
object IconPainter {

    fun draw(canvas: Canvas, name: String, x: Float, y: Float, size: Float, color: Int, strokeWidth: Float) {
        val cx = x + size / 2
        val cy = y + size / 2
        val half = size / 2

        val stroke = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            this.color = color
            style = Paint.Style.STROKE
            this.strokeWidth = strokeWidth
            strokeCap = Paint.Cap.ROUND
            strokeJoin = Paint.Join.ROUND
        }
        val fill = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            this.color = color
            style = Paint.Style.FILL
        }

        when (name) {
            "arrow_back" -> {
                canvas.drawLine(cx - half * 0.55f, cy, cx + half * 0.6f, cy, stroke)
                canvas.drawLine(cx - half * 0.55f, cy, cx - half * 0.1f, cy - half * 0.5f, stroke)
                canvas.drawLine(cx - half * 0.55f, cy, cx - half * 0.1f, cy + half * 0.5f, stroke)
            }

            "close" -> {
                canvas.drawLine(cx - half * 0.5f, cy - half * 0.5f, cx + half * 0.5f, cy + half * 0.5f, stroke)
                canvas.drawLine(cx + half * 0.5f, cy - half * 0.5f, cx - half * 0.5f, cy + half * 0.5f, stroke)
            }

            "plus" -> {
                canvas.drawLine(cx, cy - half * 0.55f, cx, cy + half * 0.55f, stroke)
                canvas.drawLine(cx - half * 0.55f, cy, cx + half * 0.55f, cy, stroke)
            }

            "check" -> {
                val path = Path().apply {
                    moveTo(cx - half * 0.5f, cy)
                    lineTo(cx - half * 0.1f, cy + half * 0.4f)
                    lineTo(cx + half * 0.55f, cy - half * 0.4f)
                }
                canvas.drawPath(path, stroke)
            }

            "check_circle" -> {
                canvas.drawCircle(cx, cy, half * 0.85f, stroke)
                val path = Path().apply {
                    moveTo(cx - half * 0.42f, cy)
                    lineTo(cx - half * 0.08f, cy + half * 0.32f)
                    lineTo(cx + half * 0.45f, cy - half * 0.32f)
                }
                canvas.drawPath(path, stroke)
            }

            "chevron_down" -> {
                val path = Path().apply {
                    moveTo(cx - half * 0.45f, cy - half * 0.2f)
                    lineTo(cx, cy + half * 0.3f)
                    lineTo(cx + half * 0.45f, cy - half * 0.2f)
                }
                canvas.drawPath(path, stroke)
            }

            "chevron_up" -> {
                val path = Path().apply {
                    moveTo(cx - half * 0.45f, cy + half * 0.2f)
                    lineTo(cx, cy - half * 0.3f)
                    lineTo(cx + half * 0.45f, cy + half * 0.2f)
                }
                canvas.drawPath(path, stroke)
            }

            "edit" -> {
                // Pencil shaft (round cap reads as the pencil body) + a
                // small filled tip triangle, same silhouette logic as
                // Material's "edit" glyph without copying its path data.
                canvas.drawLine(cx - half * 0.5f, cy + half * 0.55f, cx + half * 0.3f, cy - half * 0.35f, stroke)
                val tip = Path().apply {
                    moveTo(cx + half * 0.3f, cy - half * 0.35f)
                    lineTo(cx + half * 0.55f, cy - half * 0.55f)
                    lineTo(cx + half * 0.5f, cy - half * 0.25f)
                    close()
                }
                canvas.drawPath(tip, fill)
            }

            "document" -> {
                val rect = RectF(cx - half * 0.5f, cy - half * 0.7f, cx + half * 0.5f, cy + half * 0.7f)
                canvas.drawRoundRect(rect, size * 0.08f, size * 0.08f, stroke)
                val lineY = floatArrayOf(-0.2f, 0.15f, 0.5f)
                for (fraction in lineY) {
                    canvas.drawLine(cx - half * 0.28f, cy + half * fraction, cx + half * 0.28f, cy + half * fraction, stroke)
                }
            }

            "hourglass" -> {
                val path = Path().apply {
                    moveTo(cx - half * 0.45f, cy - half * 0.6f)
                    lineTo(cx + half * 0.45f, cy - half * 0.6f)
                    lineTo(cx + half * 0.08f, cy)
                    lineTo(cx + half * 0.45f, cy + half * 0.6f)
                    lineTo(cx - half * 0.45f, cy + half * 0.6f)
                    lineTo(cx - half * 0.08f, cy)
                    close()
                }
                canvas.drawPath(path, stroke)
            }

            "shield" -> {
                val path = Path().apply {
                    moveTo(cx, cy - half * 0.7f)
                    lineTo(cx + half * 0.5f, cy - half * 0.4f)
                    lineTo(cx + half * 0.5f, cy + half * 0.1f)
                    lineTo(cx, cy + half * 0.7f)
                    lineTo(cx - half * 0.5f, cy + half * 0.1f)
                    lineTo(cx - half * 0.5f, cy - half * 0.4f)
                    close()
                }
                canvas.drawPath(path, stroke)
            }

            "warning" -> {
                val path = Path().apply {
                    moveTo(cx, cy - half * 0.65f)
                    lineTo(cx + half * 0.6f, cy + half * 0.5f)
                    lineTo(cx - half * 0.6f, cy + half * 0.5f)
                    close()
                }
                canvas.drawPath(path, stroke)
                canvas.drawLine(cx, cy - half * 0.15f, cx, cy + half * 0.12f, stroke)
                canvas.drawCircle(cx, cy + half * 0.32f, strokeWidth * 0.6f, fill)
            }

            "info" -> {
                canvas.drawCircle(cx, cy, half * 0.75f, stroke)
                canvas.drawCircle(cx, cy - half * 0.3f, strokeWidth * 0.55f, fill)
                canvas.drawLine(cx, cy - half * 0.05f, cx, cy + half * 0.4f, stroke)
            }
        }
    }
}
