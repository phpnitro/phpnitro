package com.mobile.engine

import android.graphics.RectF
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.text.Editable
import android.text.InputType
import android.text.TextWatcher
import android.util.Log
import android.view.ViewGroup
import android.view.inputmethod.InputMethodManager
import android.widget.EditText
import android.widget.FrameLayout
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
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
 * "navigate:otp", or "navigate:product/42" for a route param — mirrors
 * ProductPage.php's '/product/{id}') pushes that token onto a local back
 * stack and re-fetches — this Activity is what owns "which screen is
 * current", not PHP (each /native/layout-demo request is a stateless
 * render of whichever ?screen=&id= it's given). Plain "back" — or the
 * hardware back button, via the OnBackPressedCallback below — pops the
 * stack.
 *
 * Text input: "focus:name" (or "focus:secure:name" for a password field)
 * overlays a real android.widget.EditText at the tapped field's exact
 * rect — see showTextInput(). Typed values are tracked client-side in
 * fieldValues and only sent to PHP when a "submit:action" fires, which
 * collects every field and appends them as query params before doing the
 * normal round-trip with the action name stripped of its "submit:"
 * prefix. A "redirect" field in the JSON response (LoginPage.php's
 * onLogin() returning a path, translated to this architecture) swaps the
 * stack's current entry and re-fetches instead of rendering what came
 * back — see applyResponse().
 */
class NativeRenderPocActivity : AppCompatActivity() {

    private lateinit var phpServer: PhpServer
    private lateinit var canvasView: NativeCanvasView
    private lateinit var rootLayout: FrameLayout
    private var serverPort: Int = 0
    private val screenStack = mutableListOf<String>()
    private val fieldValues = mutableMapOf<String, String>()
    private var activeEditText: EditText? = null
    private val deviceBridge by lazy { NativeDeviceBridge(this) }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        screenStack.add(intent.getStringExtra("screen") ?: "home")

        canvasView = NativeCanvasView(this)
        canvasView.density = resources.displayMetrics.density
        canvasView.onAction = { action, regionDp -> onTap(action, regionDp) }

        rootLayout = FrameLayout(this)
        rootLayout.addView(canvasView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        setContentView(rootLayout)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (screenStack.size > 1) {
                    screenStack.removeAt(screenStack.size - 1)
                    clearTextInput()
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
    // draw-command list instead of swapping innerHTML. "navigate:X",
    // "back", "focus:X" and "submit:X" are all intercepted here rather
    // than sent to PHP verbatim — they're this Activity's concern (which
    // screen is current, whether the keyboard is showing), not a
    // server-side state change in their own right.
    private fun onTap(action: String, regionDp: RectF) {
        when {
            action.startsWith("focus:") -> {
                val rest = action.removePrefix("focus:")
                val secure = rest.startsWith("secure:")
                val fieldName = if (secure) rest.removePrefix("secure:") else rest
                showTextInput(fieldName, regionDp, secure)
            }
            action.startsWith("submit:") -> {
                clearTextInput()
                refetch(action.removePrefix("submit:"), includeFields = true)
            }
            action.startsWith("device:") -> handleDeviceAction(action.removePrefix("device:"))
            action.startsWith("navigate:") -> {
                clearTextInput()
                screenStack.add(action.removePrefix("navigate:"))
                refetch(action = null)
            }
            action == "back" -> {
                clearTextInput()
                if (screenStack.size > 1) screenStack.removeAt(screenStack.size - 1)
                refetch(action = null)
            }
            else -> refetch(action)
        }
    }

    // "device:X" calls straight into NativeDeviceBridge — no PHP
    // round-trip for the call itself, this Activity has direct Android
    // API access same as WebAppInterface.kt does for the WebView path.
    // Capabilities that need to show a result (battery/deviceid) stash it
    // in fieldValues under the given output-field name — same mechanism
    // NativeTextField uses, reusing "a value PHP reads via $_GET on the
    // next request" rather than inventing a second channel — and refetch
    // so the current screen re-renders with it. Fire-and-forget ones
    // (vibrate/torch) have no visible state in the screen, so no
    // round-trip at all.
    private fun handleDeviceAction(token: String) {
        val parts = token.split(":")
        when (parts.getOrNull(0)) {
            "vibrate" -> deviceBridge.vibrate(200)
            "torch" -> deviceBridge.toggleTorch()
            "battery" -> {
                fieldValues[parts.getOrElse(1) { "battery_out" }] = "${deviceBridge.batteryLevel()}%"
                refetch(action = null, includeFields = true)
            }
            "deviceid" -> {
                fieldValues[parts.getOrElse(1) { "device_id_out" }] = deviceBridge.deviceId()
                refetch(action = null, includeFields = true)
            }
            "bluetooth" -> {
                fieldValues[parts.getOrElse(1) { "bt_out" }] = deviceBridge.bluetoothState()
                refetch(action = null, includeFields = true)
            }
            "securestore" -> deviceBridge.secureStore(parts.getOrElse(1) { "demo_key" }, "valeur secrète")
            "secureretrieve" -> {
                val key = parts.getOrElse(1) { "demo_key" }
                fieldValues[parts.getOrElse(2) { "secure_out" }] = deviceBridge.secureRetrieve(key)
                refetch(action = null, includeFields = true)
            }
        }
    }

    // Overlays a real EditText at the tapped field's rect — there's no
    // DOM input for the OS keyboard to attach to on a Canvas, so this is
    // the actual text-entry surface; NativeCanvasView just draws the
    // field's *shape* underneath it. One at a time: switching fields
    // removes the previous overlay first.
    private fun showTextInput(fieldName: String, regionDp: RectF, secure: Boolean) {
        activeEditText?.let { rootLayout.removeView(it) }

        val density = resources.displayMetrics.density
        val editText = EditText(this).apply {
            setText(fieldValues[fieldName] ?: "")
            setSelection(text.length)
            inputType = if (secure) {
                InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD
            } else {
                InputType.TYPE_CLASS_TEXT
            }
            textSize = 15f
            addTextChangedListener(object : TextWatcher {
                override fun afterTextChanged(s: Editable?) {
                    fieldValues[fieldName] = s?.toString() ?: ""
                }
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            })
        }

        val params = FrameLayout.LayoutParams(
            (regionDp.width() * density).toInt(),
            (regionDp.height() * density).toInt(),
        ).apply {
            leftMargin = (regionDp.left * density).toInt()
            topMargin = (regionDp.top * density).toInt()
        }
        rootLayout.addView(editText, params)
        editText.requestFocus()
        (getSystemService(INPUT_METHOD_SERVICE) as InputMethodManager)
            .showSoftInput(editText, InputMethodManager.SHOW_IMPLICIT)
        activeEditText = editText
    }

    private fun clearTextInput() {
        activeEditText?.let { rootLayout.removeView(it) }
        activeEditText = null
        (getSystemService(INPUT_METHOD_SERVICE) as InputMethodManager)
            .hideSoftInputFromWindow(canvasView.windowToken, 0)
    }

    private fun refetch(action: String?, includeFields: Boolean = false) {
        if (serverPort == 0) return
        thread { fetchDrawCommands(serverPort, action, includeFields) }
    }

    private fun fetchDrawCommands(port: Int, action: String?, includeFields: Boolean = false) {
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
        // "product/42" -> screen=product, id=42 — a route-param screen
        // token is just "name/param", split once at fetch time rather
        // than teaching screenStack about a richer shape.
        val screenToken = screenStack.last()
        val screen = screenToken.substringBefore('/')
        val screenParam = screenToken.substringAfter('/', missingDelimiterValue = "").ifEmpty { null }
        val idParam = if (screenParam != null) "&id=${URLEncoder.encode(screenParam, "UTF-8")}" else ""
        val actionParam = if (action != null) "&action=${URLEncoder.encode(action, "UTF-8")}" else ""
        val fieldsParam = if (includeFields) {
            fieldValues.entries.joinToString("") { (name, value) -> "&${URLEncoder.encode(name, "UTF-8")}=${URLEncoder.encode(value, "UTF-8")}" }
        } else {
            ""
        }
        // Point 3 of the "grow the framework" pass: a real performance
        // number, not an intuition. roundTripMs is tap-to-parsed-frame —
        // HTTP + PHP compute + JSON parse — everything except the actual
        // Canvas draw (that's onDraw's own concern, already logged
        // separately). PHP's own renderTimeMs rides in the response body,
        // so a slow frame here can be split into "PHP was slow" vs
        // "network/parse overhead" instead of one opaque total.
        val startNanos = System.nanoTime()
        try {
            val connection = URL("http://127.0.0.1:$port/native/layout-demo?width=$screenWidthDp&height=$screenHeightDp&screen=$screen$idParam$actionParam$fieldsParam").openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            Log.i(TAG, "Fetching /native/layout-demo (screen=$screen, action=$action), response code ${connection.responseCode}")
            val json = connection.inputStream.bufferedReader().use { it.readText() }
            connection.disconnect()
            val roundTripMs = (System.nanoTime() - startNanos) / 1_000_000.0
            val renderTimeMs = Regex("\"renderTimeMs\":([0-9.]+)").find(json)?.groupValues?.get(1)?.toDoubleOrNull()
            Log.i(TAG, "PERF screen=$screen roundTripMs=${"%.1f".format(roundTripMs)} phpRenderTimeMs=${renderTimeMs?.let { "%.2f".format(it) } ?: "?"}")

            Handler(Looper.getMainLooper()).post { applyResponse(json) }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to fetch draw commands", e)
        }
    }

    // A "redirect" field means PHP wants the client on a different screen
    // than the one it just rendered (LoginPage.php's onLogin() returning
    // a path, translated to this architecture — see public/index.php's
    // handling). Swap the stack's top entry and re-fetch instead of
    // drawing the stale response.
    private fun applyResponse(json: String) {
        val redirect = Regex("\"redirect\":\"([a-zA-Z0-9_/]+)\"").find(json)?.groupValues?.get(1)
        if (redirect != null && screenStack.isNotEmpty()) {
            screenStack[screenStack.size - 1] = redirect
            refetch(action = null)
            return
        }
        canvasView.setCommands(json)
    }

    companion object {
        private const val TAG = "NativeRenderPoc"
    }

    override fun onDestroy() {
        phpServer.stop()
        super.onDestroy()
    }
}
