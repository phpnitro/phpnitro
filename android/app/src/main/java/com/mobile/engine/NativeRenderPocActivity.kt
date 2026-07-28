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
 * Started life as a Phase 0 proof of concept, adb-launched only. As of
 * phase 7 it's also reachable from the real app UI — SettingsPage.php's
 * "Essayer le rendu natif" button, gated behind a Preferences flag —
 * via WebAppInterface.openNativeRenderPreview(). Still adb-launchable
 * directly too: `adb shell am start -n
 * com.mobile.engine/.NativeRenderPocActivity`.
 *
 * Starts its own PhpServer instance rather than reusing MainActivity's
 * (simpler, fully isolated — nothing about the existing WebView-based app
 * changes or risks regressing while this is built out in parallel),
 * fetches /native/layout-demo's draw commands over plain HTTP from that
 * embedded PHP process, and hands them to NativeCanvasView — the whole
 * point being to prove PHP can drive a real native Canvas paint with zero
 * WebView involved anywhere in this Activity.
 */
class NativeRenderPocActivity : AppCompatActivity() {

    private lateinit var phpServer: PhpServer
    private lateinit var canvasView: NativeCanvasView
    private var serverPort: Int = 0

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        canvasView = NativeCanvasView(this)
        canvasView.onAction = { action -> onTap(action) }
        setContentView(canvasView)

        phpServer = PhpServer(this)
        thread {
            val port = phpServer.start()
            serverPort = port
            Log.i(TAG, "PhpServer started on port $port")
            fetchDrawCommands(port, action = null)
        }
    }

    // A hit region's action fired — same round-trip shape as nav.js's
    // phpxNav.submitAction() in the HTML pipeline (tell PHP what happened,
    // get back whatever should be on screen now), just fetching a fresh
    // draw-command list instead of swapping innerHTML.
    private fun onTap(action: String) {
        if (serverPort == 0) {
            return
        }
        thread { fetchDrawCommands(serverPort, action) }
    }

    private fun fetchDrawCommands(port: Int, action: String?) {
        // Real device pixel width — the layout engine's Constraints are in
        // the same absolute-pixel space Canvas draws in, so this has to
        // match resources.displayMetrics, not a dp value.
        val screenWidthPx = resources.displayMetrics.widthPixels
        val actionParam = if (action != null) "&action=${java.net.URLEncoder.encode(action, "UTF-8")}" else ""
        try {
            val connection = URL("http://127.0.0.1:$port/native/layout-demo?width=$screenWidthPx$actionParam").openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            Log.i(TAG, "Fetching /native/layout-demo (action=$action), response code ${connection.responseCode}")
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
