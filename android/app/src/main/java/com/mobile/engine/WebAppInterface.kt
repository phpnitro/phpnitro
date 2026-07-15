package com.mobile.engine

import android.content.Context
import android.graphics.Bitmap
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.util.Base64
import android.webkit.JavascriptInterface
import android.webkit.WebView
import java.io.ByteArrayOutputStream

/**
 * Bridge exposed to the JS running inside the WebView as `window.AndroidNative`.
 * Unlike the getUserMedia/navigator.vibrate Web APIs (which work, but are
 * mediated by the WebView's browser engine), these calls go straight to
 * Android's native APIs — the same ones a fully native app would use.
 */
class WebAppInterface(
    private val context: Context,
    private val webView: WebView,
    private val onPhotoRequested: () -> Unit,
) {
    @JavascriptInterface
    fun vibrate(milliseconds: Long) {
        val vibrator = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            val manager = context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as VibratorManager
            manager.defaultVibrator
        } else {
            @Suppress("DEPRECATION")
            context.getSystemService(Context.VIBRATOR_SERVICE) as Vibrator
        }

        vibrator.vibrate(VibrationEffect.createOneShot(milliseconds, VibrationEffect.DEFAULT_AMPLITUDE))
    }

    @JavascriptInterface
    fun takeNativePhoto() {
        onPhotoRequested()
    }

    /** Called from MainActivity once the native camera activity result comes back. */
    fun deliverPhoto(bitmap: Bitmap?) {
        if (bitmap == null) {
            webView.post {
                webView.evaluateJavascript("window.onNativePhotoTaken && window.onNativePhotoTaken(null)", null)
            }
            return
        }

        val output = ByteArrayOutputStream()
        bitmap.compress(Bitmap.CompressFormat.JPEG, 80, output)
        val base64 = Base64.encodeToString(output.toByteArray(), Base64.NO_WRAP)

        webView.post {
            webView.evaluateJavascript(
                "window.onNativePhotoTaken && window.onNativePhotoTaken('data:image/jpeg;base64,$base64')",
                null,
            )
        }
    }
}
