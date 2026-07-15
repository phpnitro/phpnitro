package com.mobile.engine

import android.Manifest
import android.annotation.SuppressLint
import android.content.pm.PackageManager
import android.os.Bundle
import android.webkit.GeolocationPermissions
import android.webkit.PermissionRequest
import android.webkit.WebChromeClient
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat

/**
 * Hosts the PHP-served UI in a native WebView. For now this points at a PHP
 * server reachable over the network (10.0.2.2 is the Android emulator's
 * alias for the host machine's localhost) — there is no on-device PHP
 * runtime yet, that is a separate, much larger piece of work.
 *
 * Grants the runtime permissions and WebView callbacks needed for the
 * device-capability widgets (camera, microphone, geolocation) to work from
 * the pages served by engine/.
 */
class MainActivity : AppCompatActivity() {

    private val requestedPermissions = arrayOf(
        Manifest.permission.CAMERA,
        Manifest.permission.RECORD_AUDIO,
        Manifest.permission.ACCESS_FINE_LOCATION,
    )

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        ActivityCompat.requestPermissions(this, requestedPermissions, 0)

        val webView = WebView(this)
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
        webView.loadUrl("http://10.0.2.2:8090/")

        setContentView(webView)
    }
}
