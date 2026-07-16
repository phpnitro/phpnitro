package com.mobile.engine

import android.app.Activity
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.media.MediaPlayer
import android.net.Uri
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.print.PrintAttributes
import android.print.PrintManager
import android.util.Base64
import android.webkit.JavascriptInterface
import android.webkit.WebView
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
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
 * Biometric auth, local notifications and PDF printing specifically REQUIRE
 * this native path: WebView doesn't implement WebAuthn/FIDO2 platform
 * authenticators, the Web Notifications API, or window.print() the way a
 * real browser tab does.
 */
class WebAppInterface(
    private val context: Context,
    private val webView: WebView,
    private val onPhotoRequested: () -> Unit,
) {
    companion object {
        private const val NOTIFICATION_CHANNEL_ID = "phpx_default"
    }

    /** Set by MainActivity to wire up the image-picker activity launcher. */
    var onImagePickRequested: () -> Unit = {}

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
    fun pickImage() {
        onImagePickRequested()
    }

    /** Called from MainActivity once the native image-picker result comes back. */
    fun deliverPickedImage(uri: Uri?) {
        if (uri == null) {
            webView.post {
                webView.evaluateJavascript("window.onNativeImagePicked && window.onNativeImagePicked(null)", null)
            }
            return
        }

        val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
        if (bytes == null) {
            webView.post {
                webView.evaluateJavascript("window.onNativeImagePicked && window.onNativeImagePicked(null)", null)
            }
            return
        }

        val mimeType = context.contentResolver.getType(uri) ?: "image/jpeg"
        val base64 = Base64.encodeToString(bytes, Base64.NO_WRAP)

        webView.post {
            webView.evaluateJavascript(
                "window.onNativeImagePicked && window.onNativeImagePicked('data:$mimeType;base64,$base64')",
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

    /**
     * Plays a short sound through the device speaker (not the WebView's own
     * <audio> tag — MediaPlayer, so it keeps playing correctly across screen
     * lock / audio focus changes the way a native app's sound would).
     */
    @JavascriptInterface
    fun playSound(url: String) {
        try {
            val player = MediaPlayer()
            player.setDataSource(url)
            player.setOnPreparedListener { it.start() }
            player.setOnCompletionListener { it.release() }
            player.setOnErrorListener { mp, _, _ -> mp.release(); true }
            player.prepareAsync()
        } catch (_: Exception) {
            // Swallow: a failed notification sound shouldn't crash the app.
        }
    }

    /**
     * Shows a real system notification (status bar + heads-up), independent
     * of any push service — works fully offline, no Firebase/internet
     * needed. This is "local" notifications; remote push still needs FCM
     * (see FcmService.kt.example) to wake the app when it isn't running.
     */
    @JavascriptInterface
    fun showNotification(title: String, message: String) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ActivityCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                NOTIFICATION_CHANNEL_ID,
                "Notifications",
                NotificationManager.IMPORTANCE_DEFAULT,
            )
            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }

        val notification = NotificationCompat.Builder(context, NOTIFICATION_CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(message)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setAutoCancel(true)
            .build()

        NotificationManagerCompat.from(context).notify(System.currentTimeMillis().toInt(), notification)
    }

    /**
     * Turns the current page into a PDF via Android's native print pipeline
     * (WebView.createPrintDocumentAdapter + PrintManager) — the same "Save
     * as PDF" flow any native app gets from the system print dialog, no PHP
     * PDF library needed.
     */
    @JavascriptInterface
    fun printPage() {
        val activity = context as? Activity ?: return

        activity.runOnUiThread {
            val printManager = context.getSystemService(Context.PRINT_SERVICE) as PrintManager
            val jobName = "Document"
            val adapter = webView.createPrintDocumentAdapter(jobName)
            printManager.print(jobName, adapter, PrintAttributes.Builder().build())
        }
    }
}
