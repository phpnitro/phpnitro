package com.mobile.engine

import android.annotation.SuppressLint
import android.os.Bundle
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.appcompat.app.AppCompatActivity

/**
 * Hosts the PHP-served UI in a native WebView. For now this points at a PHP
 * server reachable over the network (10.0.2.2 is the Android emulator's
 * alias for the host machine's localhost) — there is no on-device PHP
 * runtime yet, that is a separate, much larger piece of work.
 */
class MainActivity : AppCompatActivity() {

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val webView = WebView(this)
        webView.settings.javaScriptEnabled = true
        webView.webViewClient = WebViewClient()
        webView.loadUrl("http://10.0.2.2:8090/")

        setContentView(webView)
    }
}
