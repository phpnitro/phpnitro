package com.phpnitro.engine

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.os.Bundle
import android.util.Log
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.widget.FrameLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.nio.ByteBuffer
import kotlin.concurrent.thread

/**
 * A second, deliberately separate proof of concept alongside
 * NativeRenderPocActivity — same fetch-JSON-from-/native/layout-demo idea,
 * but the pixels on screen come from RustRenderer.renderFrame() (the
 * shared Rust/tiny-skia core every desktop port already uses) instead of
 * NativeCanvasView's hand-written Kotlin/Canvas replay.
 *
 * This is a NEW file/Activity rather than a change to NativeCanvasView.kt
 * itself. That view's onDraw() has hero-flight tracking, whole-screen
 * crossfade, and pull-to-refresh/dismiss/reorder overlays all coupled to
 * Kotlin-side state, while Rust already replicates hero/crossfade
 * internally in its own, separate way (rust/phpnitro-render/src/
 * transition.rs) — reconciling the two inside the ONE view real screens
 * already render through, proven in production (Feexpay Mobile Money),
 * is a materially bigger and riskier change than adding an isolated,
 * adb-only POC next to it. Mirrors how windows/PhpNitroDesktop.App/
 * ScreenForm.cs and macos/Sources/PhpNitroMacApp/RustScreenView.swift are
 * their own separate, Rust-only apps rather than changes to those
 * platforms' existing GDI+/Core Graphics engines.
 *
 * adb-launched only, same as NativeRenderPocActivity started life:
 * `adb shell am start -n com.phpnitro.engine/.RustRenderPocActivity`
 *
 * Deliberately narrower than NativeRenderPocActivity: no TextField/
 * Checkbox field overlay, no hScroll/vScroll/slider drag, no hero/
 * pull-to-refresh/dismiss/reorder, no LazyList scroll-follow, no OAuth/
 * deep-link intent filters, no dev-tools panel. Just fetch ->
 * RustRenderer.renderFrame() -> draw the resulting Bitmap ->
 * RustRenderer.hitTest() on tap -> refetch. That narrower set is
 * deliberate, matching how simple ScreenForm.cs already gets away with
 * being on Windows — every one of those omitted features is exactly
 * where NativeCanvasView's own Kotlin-side state lives, so leaving them
 * out is what keeps this a genuinely separate, low-risk file instead of
 * a second copy of NativeCanvasView's own complexity.
 *
 * width/height/screen/action are the only /native/layout-demo query
 * params sent (see public/index.php's own `?? 360`/`?? 720`/`?? 'home'`/
 * `?? null` defaults) — dark/online/locale/scroll_y/lastHash/field
 * values are simply omitted rather than wired through, since the server
 * already degrades to a sane default for each of them.
 *
 * # Honesty
 * Bitmap.Config.ARGB_8888's in-memory byte buffer is R,G,B,A order
 * (despite the "ARGB" name, which only describes the packed-int shape
 * Color.argb()/getPixel() use) — matching RustRenderer's own documented
 * premultiplied RGBA8 output exactly, so copyPixelsFromBuffer() below
 * needs no channel swap. This specific claim has NOT been checked
 * against a real on-screen screenshot this session —
 * RustRendererDeviceTest.kt only inspected the raw byte array, it never
 * got as far as an actual Bitmap/View. If colors ever look swapped or
 * washed out on a real device, this is the first place to look.
 */
class RustRenderPocActivity : AppCompatActivity() {
    private lateinit var phpServer: PhpServer
    private lateinit var canvasView: RustRenderPocCanvasView
    private lateinit var statusView: TextView
    private var serverHost = "127.0.0.1"
    private var serverPort = 0
    private var accessToken: String? = null
    private var renderer: RustRenderer? = null
    private var lastEnvelopeJson: String? = null
    private val screenStack = mutableListOf("home")

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        CrashReporter.install(this)

        renderer = try {
            RustRenderer()
        } catch (e: RustRenderUnavailableException) {
            // libphpnitro_render.so missing for this ABI (never committed
            // to the repo — see bin/build-rust-android.sh) — fails soft
            // with a visible message instead of a startup crash, the same
            // "additive, never a hard dependency" spirit every other
            // optional native capability in this engine module already
            // follows.
            Log.w(TAG, "RustRenderer unavailable", e)
            null
        }

        val root = FrameLayout(this)
        canvasView = RustRenderPocCanvasView(this)
        canvasView.density = resources.displayMetrics.density
        canvasView.onTapDp = { x, y -> onTap(x, y) }
        root.addView(
            canvasView,
            FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT),
        )

        statusView = TextView(this).apply {
            setBackgroundColor(Color.parseColor("#CC000000"))
            setTextColor(Color.WHITE)
            val padDp = (6f * resources.displayMetrics.density).toInt()
            val padDpH = (12f * resources.displayMetrics.density).toInt()
            setPadding(padDpH, padDp, padDpH, padDp)
            textSize = 12f
        }
        root.addView(statusView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT))
        setContentView(root)

        if (renderer == null) {
            setStatus("RustRenderer indisponible (libphpnitro_render.so absent pour cet ABI) — voir bin/build-rust-android.sh")
            return
        }

        val remoteHost = intent.getStringExtra("serverHost")
        if (remoteHost != null) {
            // Same remote-mode convention as NativeRenderPocActivity
            // (PhpNitro Go / a dev server already running elsewhere on
            // the LAN) — no local php-cli process to start here either.
            serverHost = remoteHost
            serverPort = intent.getIntExtra("serverPort", 0)
            refetch(null)
        } else {
            phpServer = PhpServer(this)
            accessToken = phpServer.accessToken
            thread {
                serverPort = phpServer.start()
                Log.i(TAG, "PhpServer started on port $serverPort")
                refetch(null)
            }
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        renderer?.close()
        if (::phpServer.isInitialized) {
            phpServer.stop()
        }
    }

    private fun onTap(xDp: Float, yDp: Float) {
        val envelope = lastEnvelopeJson ?: return
        val hit = RustRenderer.hitTest(envelope, xDp, yDp) ?: return
        when {
            hit.action == "back" -> {
                if (screenStack.size > 1) {
                    screenStack.removeAt(screenStack.size - 1)
                    refetch(null)
                }
            }
            hit.action.startsWith("navigate:") -> {
                screenStack.add(hit.action.removePrefix("navigate:"))
                refetch(null)
            }
            else -> refetch(hit.action)
        }
    }

    private fun refetch(action: String?) {
        if (serverPort == 0) return
        thread { fetchAndRender(action) }
    }

    private fun fetchAndRender(action: String?) {
        val renderer = renderer ?: return
        val density = resources.displayMetrics.density
        val widthDp = (resources.displayMetrics.widthPixels / density).toInt()
        val heightDp = (resources.displayMetrics.heightPixels / density).toInt()
        val screen = screenStack.last()
        val actionParam = if (action != null) "&action=${URLEncoder.encode(action, "UTF-8")}" else ""
        try {
            val connection = URL(
                "http://$serverHost:$serverPort/native/layout-demo?width=$widthDp&height=$heightDp&screen=$screen$actionParam",
            ).openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            accessToken?.let { connection.setRequestProperty("X-PhpNitro-Token", it) }
            val responseCode = connection.responseCode
            // Same split HttpURLConnection forces on any non-2xx response
            // as fetchDrawCommands() already documents — .inputStream
            // throws FileNotFoundException on a 4xx/5xx, the actual
            // {"error": {...}} body only ever comes back through
            // .errorStream.
            val stream = if (responseCode in 200..299) connection.inputStream else connection.errorStream
            val body = stream.bufferedReader().readText()
            if (responseCode !in 200..299) {
                runOnUiThread { setStatus("Erreur serveur ($responseCode): $body") }
                return
            }
            lastEnvelopeJson = body
            val frame = renderer.renderFrame(body, widthDp, heightDp)
            if (frame == null) {
                runOnUiThread { setStatus("RustRenderer.renderFrame() a renvoyé null (JSON invalide ?)") }
                return
            }
            // stride is always width*4 for packed RGBA8 (4 already
            // divides evenly into any width, so there is no alignment
            // padding to add) — safe to hand the raw byte array straight
            // to copyPixelsFromBuffer() without a row-by-row copy.
            val bitmap = Bitmap.createBitmap(frame.width, frame.height, Bitmap.Config.ARGB_8888)
            bitmap.copyPixelsFromBuffer(ByteBuffer.wrap(frame.pixels))
            runOnUiThread {
                canvasView.showFrame(bitmap)
                setStatus("écran: $screen  ${frame.width}x${frame.height}dp")
            }
        } catch (e: Exception) {
            Log.e(TAG, "fetchAndRender failed", e)
            runOnUiThread { setStatus("Connexion impossible : ${e.message}") }
        }
    }

    private fun setStatus(text: String) {
        statusView.text = text
    }

    companion object {
        private const val TAG = "RustRenderPocActivity"
    }
}

/**
 * Draws whatever Bitmap RustRenderer last produced, scaled up from its
 * dp-sized raster (matching the width/height dp values sent to
 * /native/layout-demo, same convention windows/PhpNitroDesktop.App/
 * ScreenForm.cs's ClientSize already uses for RenderFrame) to the real
 * device pixel size via canvas.scale(density, density) — NOT drawn
 * unscaled, unlike ScreenForm.cs's DrawImageUnscaled(), since Android's
 * dp/px ratio is rarely 1:1 the way a typical desktop's is. Tap
 * coordinates are converted back to that same dp space (divide by
 * density) before being handed to RustRenderer.hitTest(), which expects
 * coordinates in the same space renderFrame() was called with.
 */
private class RustRenderPocCanvasView(context: Context) : View(context) {
    var density: Float = 1f
    var onTapDp: ((Float, Float) -> Unit)? = null
    private var bitmap: Bitmap? = null

    fun showFrame(frame: Bitmap) {
        bitmap = frame
        invalidate()
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)
        val b = bitmap ?: return
        canvas.save()
        canvas.scale(density, density)
        canvas.drawBitmap(b, 0f, 0f, null)
        canvas.restore()
    }

    override fun onTouchEvent(event: MotionEvent): Boolean {
        if (event.action == MotionEvent.ACTION_UP) {
            onTapDp?.invoke(event.x / density, event.y / density)
        }
        return true
    }
}
