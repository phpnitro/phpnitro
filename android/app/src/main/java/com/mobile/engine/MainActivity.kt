package com.mobile.engine

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.webkit.GeolocationPermissions
import android.webkit.PermissionRequest
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen

/**
 * Hosts the PHP-served UI in a native WebView, backed by PhpServer — a
 * cross-compiled PHP binary running as a real on-device process
 * (armeabi-v7a and arm64-v8a both bundled, so this works on 32-bit and
 * 64-bit devices alike).
 *
 * Grants the runtime permissions and WebView callbacks needed for the
 * device-capability widgets (camera, microphone, geolocation) to work from
 * the pages served by ui/, and exposes WebAppInterface as
 * window.AndroidNative for the genuinely-native vibrate/camera/biometric/
 * notification/print path.
 *
 * A native SplashScreen (see themes.xml, Theme.App.Starting) stays on
 * screen until PhpServer has actually bound its port AND the WebView has
 * finished loading — no fixed timeout, no blank-white flash while PHP boots.
 */
class MainActivity : AppCompatActivity() {

    private val requestedPermissions = arrayOf(
        Manifest.permission.CAMERA,
        Manifest.permission.RECORD_AUDIO,
        Manifest.permission.ACCESS_FINE_LOCATION,
        Manifest.permission.POST_NOTIFICATIONS,
    )

    private lateinit var webAppInterface: WebAppInterface
    private lateinit var phpServer: PhpServer
    private lateinit var webView: WebView

    @Volatile
    private var serverReady = false

    @Volatile
    private var pageLoaded = false

    @Volatile
    private var port = 0

    private val takePicturePreview = registerForActivityResult(
        ActivityResultContracts.TakePicturePreview(),
    ) { bitmap -> webAppInterface.deliverPhoto(bitmap) }

    private val pickImage = registerForActivityResult(
        ActivityResultContracts.GetContent(),
    ) { uri -> webAppInterface.deliverPickedImage(uri) }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        val splashScreen = installSplashScreen()
        super.onCreate(savedInstanceState)
        splashScreen.setKeepOnScreenCondition { !(serverReady && pageLoaded) }

        ActivityCompat.requestPermissions(this, requestedPermissions, 0)

        webView = WebView(this)
        webAppInterface = WebAppInterface(this, webView) { takePicturePreview.launch(null) }
            .also { it.onImagePickRequested = { pickImage.launch("image/*") } }
        webView.addJavascriptInterface(webAppInterface, "AndroidNative")

        webView.settings.javaScriptEnabled = true
        webView.settings.setGeolocationEnabled(true)
        // A visible fading scrollbar reads as "browser", not "app" — every
        // native app hides or fully customizes it. The CSS scrollbar-hiding
        // rules in ui/src/input.css only cover in-page browser testing;
        // this is what actually matters inside the WebView.
        webView.isVerticalScrollBarEnabled = false
        webView.isHorizontalScrollBarEnabled = false
        // Defensive: if the very first load races the PHP server still
        // starting up, retry instead of leaving a permanently blank WebView.
        webView.webViewClient = object : WebViewClient() {
            override fun onReceivedError(
                view: WebView,
                request: WebResourceRequest,
                error: WebResourceError,
            ) {
                if (request.isForMainFrame && port != 0) {
                    Handler(Looper.getMainLooper()).postDelayed({
                        view.loadUrl("http://127.0.0.1:$port/")
                    }, 1000)
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                pageLoaded = true
            }
        }
        webView.webChromeClient = object : WebChromeClient() {
            override fun onPermissionRequest(request: PermissionRequest) {
                request.grant(request.resources)
            }

            override fun onGeolocationPermissionsShowPrompt(
                origin: String,
                callback: GeolocationPermissions.Callback,
            ) {
                val granted = ActivityCompat.checkSelfPermission(
                    this@MainActivity,
                    Manifest.permission.ACCESS_FINE_LOCATION,
                ) == PackageManager.PERMISSION_GRANTED
                callback.invoke(origin, granted, false)
            }
        }

        setContentView(webView)

        // nav.js swaps screens via history.pushState instead of real page
        // loads, so the WebView's own back/forward stack carries those
        // entries too — without this, the hardware back button has nothing
        // to pop and falls straight through to finishing the Activity.
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        phpServer = PhpServer(this)
        Thread {
            val boundPort = phpServer.start()
            port = boundPort
            serverReady = true
            val path = deepLinkPath(intent) ?: "/"
            runOnUiThread { webView.loadUrl("http://127.0.0.1:$boundPort$path") }
        }.start()
    }

    /**
     * Fires when a deep link (or a re-tap of the launcher icon) arrives
     * while the Activity is already running — android:launchMode="singleTask"
     * (AndroidManifest.xml) routes it here instead of spawning a second
     * MainActivity instance on top of the WebView that's already showing.
     */
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)

        val path = deepLinkPath(intent) ?: return
        if (serverReady) {
            webView.loadUrl("http://127.0.0.1:$port$path")
        }
    }

    /**
     * phpnitro://<path> (e.g. phpnitro://product/42) -> "/product/42",
     * resolved by the exact same Engine\Router every normal in-app
     * navigation goes through — a deep link is just a different way to
     * arrive at an ordinary route, not a separate code path on the PHP
     * side. Only this app's own scheme is handled; anything else (a stray
     * VIEW intent with unexpected data) is ignored rather than guessed at.
     *
     * uri.host, not uri.path, holds the first path segment: standard
     * scheme://authority/path URI parsing treats whatever comes right after
     * "://" up to the next "/" as the authority (host), not part of the
     * path — confirmed live (phpnitro://settings landed on host="settings",
     * path="", which a naive uri.path-only read silently treated as "/" and
     * opened the home screen instead of Réglages). Host and path are
     * concatenated back into one route instead.
     */
    private fun deepLinkPath(intent: Intent?): String? {
        val uri: Uri = intent?.data ?: return null
        if (uri.scheme != "phpnitro") return null

        val path = "/${uri.host.orEmpty()}${uri.path.orEmpty()}"
        return if (path == "/") "/" else path
    }

    override fun onDestroy() {
        super.onDestroy()
        phpServer.stop()
    }
}
