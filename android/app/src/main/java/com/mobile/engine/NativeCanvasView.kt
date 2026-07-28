package com.mobile.engine

import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RectF
import android.view.View
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
 */
class NativeCanvasView(context: Context) : View(context) {

    private var commands: JSONArray = JSONArray()

    fun setCommands(json: String) {
        commands = JSONArray(json)
        invalidate()
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)

        for (index in 0 until commands.length()) {
            val command = commands.getJSONObject(index)
            when (command.getString("type")) {
                "rect" -> drawRectCommand(canvas, command)
                "text" -> drawTextCommand(canvas, command)
            }
        }
    }

    private fun drawRectCommand(canvas: Canvas, command: JSONObject) {
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = Color.parseColor(command.getString("color"))
            style = Paint.Style.FILL
        }
        val rect = RectF(
            command.getDouble("x").toFloat(),
            command.getDouble("y").toFloat(),
            (command.getDouble("x") + command.getDouble("width")).toFloat(),
            (command.getDouble("y") + command.getDouble("height")).toFloat(),
        )
        val radius = command.optDouble("radius", 0.0).toFloat()

        if (radius > 0) {
            canvas.drawRoundRect(rect, radius, radius, paint)
        } else {
            canvas.drawRect(rect, paint)
        }
    }

    private fun drawTextCommand(canvas: Canvas, command: JSONObject) {
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = Color.parseColor(command.optString("color", "#000000"))
            textSize = command.optDouble("size", 16.0).toFloat()
        }
        canvas.drawText(
            command.getString("text"),
            command.getDouble("x").toFloat(),
            command.getDouble("y").toFloat(),
            paint,
        )
    }
}
