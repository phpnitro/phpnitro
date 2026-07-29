package com.mobile.engine

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.activity.OnBackPressedCallback
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
 *
 * Navigation: a hit region's action starting with "navigate:" (e.g.
 * "navigate:otp") pushes that screen name onto a local back stack and
 * re-fetches — this Activity is what owns "which screen is current", not
 * PHP (each /native/layout-demo request is a stateless render of
 * whichever ?screen= it's given). Plain "back" — or the hardware back
 * button, via the OnBackPressedCallback below — pops the stack. Anything
 * else is a normal server round-trip (increment, etc.), same as before.
 */
class NativeRenderPocActivity : AppCompatActivity() {

    private lateinit var phpServer: PhpServer
    private lateinit var canvasView: NativeCanvasView
    private var serverPort: Int = 0
    private val screenStack = mutableListOf<String>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        screenStack.add(intent.getStringExtra("screen") ?: "home")

        canvasView = NativeCanvasView(this)
        canvasView.density = resources.displayMetrics.density
        canvasView.onAction = { action -> onTap(action) }
        setContentView(canvasView)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (screenStack.size > 1) {
                    screenStack.removeAt(screenStack.size - 1)
                    refetch(action = null)
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        phpServer = PhpServer(this)
        thread {
            val port = phpServer.start()
            serverPort = port
            Log.i(TAG, "PhpServer started on port $port")
            refetch(action = null)
        }
    }

    // A hit region's action fired — same round-trip shape as nav.js's
    // phpxNav.submitAction() in the HTML pipeline (tell PHP what happened,
    // get back whatever should be on screen now), just fetching a fresh
    // draw-command list instead of swapping innerHTML. "navigate:X" and
    // "back" are intercepted here rather than sent to PHP as an action —
    // they're this Activity's concern (which screen is current), not a
    // server-side state change.
    private fun onTap(action: String) {
        when {
            action.startsWith("navigate:") -> {
                screenStack.add(action.removePrefix("navigate:"))
                refetch(action = null)
            }
            action == "back" -> {
                if (screenStack.size > 1) screenStack.removeAt(screenStack.size - 1)
                refetch(action = null)
            }
            else -> refetch(action)
        }
    }

    private fun refetch(action: String?) {
        if (serverPort == 0) return
        thread { fetchDrawCommands(serverPort, action) }
    }

    private fun fetchDrawCommands(port: Int, action: String?) {
        // dp-space width/height, not raw device pixels — every size the
        // PHP side hands back (font sizes, radii, button heights, Tokens'
        // whole scale) is authored as a dp-like number, and
        // NativeCanvasView scales its Canvas by the real density before
        // replaying, so the layout math needs to run against the same dp
        // dimensions or a phone with more physical pixels per dp would
        // just get a narrower/shorter logical screen instead of
        // correctly-sized content. Height matters for screens (like
        // NativeOtpScreen) that use a Flexible spacer to pin content to
        // the true bottom of the screen.
        val density = resources.displayMetrics.density
        val screenWidthDp = resources.displayMetrics.widthPixels / density
        val screenHeightDp = resources.displayMetrics.heightPixels / density
        val screen = screenStack.last()
        val actionParam = if (action != null) "&action=${java.net.URLEncoder.encode(action, "UTF-8")}" else ""
        // Point 3 of the "grow the framework" pass: a real performance
        // number, not an intuition. roundTripMs is tap-to-parsed-frame —
        // HTTP + PHP compute + JSON parse — everything except the actual
        // Canvas draw (that's onDraw's own concern, already logged
        // separately). PHP's own renderTimeMs rides in the response body,
        // so a slow frame here can be split into "PHP was slow" vs
        // "network/parse overhead" instead of one opaque total.
        val startNanos = System.nanoTime()
        try {
            val connection = URL("http://127.0.0.1:$port/native/layout-demo?width=$screenWidthDp&height=$screenHeightDp&screen=$screen$actionParam").openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            Log.i(TAG, "Fetching /native/layout-demo (screen=$screen, action=$action), response code ${connection.responseCode}")
            val json = connection.inputStream.bufferedReader().use { it.readText() }
            connection.disconnect()
            val roundTripMs = (System.nanoTime() - startNanos) / 1_000_000.0
            val renderTimeMs = Regex("\"renderTimeMs\":([0-9.]+)").find(json)?.groupValues?.get(1)?.toDoubleOrNull()
            Log.i(TAG, "PERF screen=$screen roundTripMs=${"%.1f".format(roundTripMs)} phpRenderTimeMs=${renderTimeMs?.let { "%.2f".format(it) } ?: "?"}")

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
