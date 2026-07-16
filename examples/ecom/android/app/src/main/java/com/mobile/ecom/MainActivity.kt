package com.mobile.ecom

import android.Manifest
import android.annotation.SuppressLint
import android.content.pm.PackageManager
import android.os.Bundle
import android.webkit.GeolocationPermissions
import android.webkit.PermissionRequest
import android.webkit.WebChromeClient
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat

/**
 * Hosts the PHP-served UI in a native WebView, backed by PhpServer — a
 * cross-compiled PHP binary running as a real on-device process
 * (armeabi-v7a and arm64-v8a both bundled, so this works on 32-bit and
 * 64-bit devices alike).
 *
 * Grants the runtime permissions and WebView callbacks needed for the
 * device-capability widgets (camera, microphone, geolocation) to work from
 * the pages served by engine/, and exposes WebAppInterface as
 * window.AndroidNative for the genuinely-native vibrate/camera path.
 */
class MainActivity : AppCompatActivity() {

    private val requestedPermissions = arrayOf(
        Manifest.permission.CAMERA,
        Manifest.permission.RECORD_AUDIO,
        Manifest.permission.ACCESS_FINE_LOCATION,
    )

    private lateinit var webAppInterface: WebAppInterface

    private val takePicturePreview = registerForActivityResult(
        ActivityResultContracts.TakePicturePreview(),
    ) { bitmap -> webAppInterface.deliverPhoto(bitmap) }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        ActivityCompat.requestPermissions(this, requestedPermissions, 0)

        Thread { PhpServer(this).start() }.also { it.start(); it.join(8000) }

        val webView = WebView(this)
        webAppInterface = WebAppInterface(this, webView) { takePicturePreview.launch(null) }
        webView.addJavascriptInterface(webAppInterface, "AndroidNative")

        webView.settings.javaScriptEnabled = true
        webView.settings.setGeolocationEnabled(true)
        webView.webViewClient = WebViewClient()
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
        webView.loadUrl("http://127.0.0.1:${PhpServer.PORT}/")

        setContentView(webView)
    }
}
