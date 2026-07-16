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
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import org.json.JSONObject
import java.io.ByteArrayOutputStream

/**
 * Bridge exposed to the JS running inside the WebView as `window.AndroidNative`.
 * Unlike the getUserMedia/navigator.vibrate Web APIs (which work, but are
 * mediated by the WebView's browser engine), these calls go straight to
 * Android's native APIs — the same ones a fully native app would use.
 *
 * Biometric auth specifically REQUIRES this native path: Android's WebView
 * does not implement WebAuthn/FIDO2 platform authenticators the way the
 * Chrome browser app does, so `navigator.credentials` inside a WebView is
 * unreliable-to-absent even when the device has a fingerprint enrolled.
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

    @JavascriptInterface
    fun showBiometricPrompt() {
        val activity = context as? FragmentActivity ?: run {
            reportBiometricResult(false, "Contexte non compatible.")
            return
        }

        val availability = BiometricManager.from(context)
            .canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_STRONG)

        if (availability != BiometricManager.BIOMETRIC_SUCCESS) {
            reportBiometricResult(false, biometricUnavailableReason(availability))
            return
        }

        activity.runOnUiThread {
            val prompt = BiometricPrompt(
                activity,
                ContextCompat.getMainExecutor(context),
                object : BiometricPrompt.AuthenticationCallback() {
                    override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                        reportBiometricResult(true, "")
                    }

                    override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                        reportBiometricResult(false, errString.toString())
                    }
                },
            )

            val promptInfo = BiometricPrompt.PromptInfo.Builder()
                .setTitle("Authentification")
                .setSubtitle("Confirme ton identité")
                .setNegativeButtonText("Annuler")
                .build()

            prompt.authenticate(promptInfo)
        }
    }

    private fun biometricUnavailableReason(availability: Int): String = when (availability) {
        BiometricManager.BIOMETRIC_ERROR_NONE_ENROLLED -> "Aucune empreinte/visage enregistré sur ce téléphone."
        BiometricManager.BIOMETRIC_ERROR_NO_HARDWARE -> "Ce device n'a pas de capteur biométrique."
        BiometricManager.BIOMETRIC_ERROR_HW_UNAVAILABLE -> "Capteur biométrique momentanément indisponible."
        else -> "Authentification biométrique indisponible."
    }

    private fun reportBiometricResult(success: Boolean, message: String) {
        webView.post {
            webView.evaluateJavascript(
                "window.onNativeBiometricResult && window.onNativeBiometricResult(" +
                    "${success}, ${JSONObject.quote(message)})",
                null,
            )
        }
    }
}
