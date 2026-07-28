package com.mobile.engine

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.appcompat.app.AppCompatActivity
import java.net.HttpURLConnection
import java.net.URL
import kotlin.concurrent.thread

/**
 * Phase 0 of docs/proposals/moteur-rendu-natif.md — launched directly via
 * adb during development (`adb shell am start -n
 * com.mobile.engine/.NativeRenderPocActivity`), not reachable from the
 * normal app UI. Starts its own PhpServer instance rather than reusing
 * MainActivity's (simpler, fully isolated — this is a throwaway proof of
 * concept, not something a real user path depends on), fetches
 * /native/demo's draw commands over plain HTTP from that embedded PHP
 * process, and hands them to NativeCanvasView — the whole point being to
 * prove PHP can drive a real native Canvas paint with zero WebView
 * involved anywhere in this Activity.
 */
class NativeRenderPocActivity : AppCompatActivity() {

    private lateinit var phpServer: PhpServer
    private lateinit var canvasView: NativeCanvasView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        canvasView = NativeCanvasView(this)
        setContentView(canvasView)

        phpServer = PhpServer(this)
        thread {
            val port = phpServer.start()
            Log.i(TAG, "PhpServer started on port $port")
            fetchDrawCommands(port)
        }
    }

    private fun fetchDrawCommands(port: Int) {
        // Real device pixel width — the layout engine's Constraints are in
        // the same absolute-pixel space Canvas draws in, so this has to
        // match resources.displayMetrics, not a dp value.
        val screenWidthPx = resources.displayMetrics.widthPixels
        try {
            val connection = URL("http://127.0.0.1:$port/native/layout-demo?width=$screenWidthPx").openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            Log.i(TAG, "Fetching /native/demo, response code ${connection.responseCode}")
            val json = connection.inputStream.bufferedReader().use { it.readText() }
            connection.disconnect()
            Log.i(TAG, "Draw commands: $json")

            Handler(Looper.getMainLooper()).post {
                canvasView.setCommands(json)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to fetch draw commands", e)
        }
    }

    companion object {
        private const val TAG = "NativeRenderPoc"
    }

    override fun onDestroy() {
        phpServer.stop()
        super.onDestroy()
    }
}
