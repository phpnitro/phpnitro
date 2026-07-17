package com.mobile.engine

import android.Manifest
import android.annotation.SuppressLint
import android.content.pm.PackageManager
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

        val webView = WebView(this)
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
            runOnUiThread { webView.loadUrl("http://127.0.0.1:$boundPort/") }
        }.start()
    }

    override fun onDestroy() {
        super.onDestroy()
        phpServer.stop()
    }
}
