package com.mobile.engine

import android.app.Activity
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.media.MediaPlayer
import android.media.MediaRecorder
import android.net.Uri
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.print.PrintAttributes
import android.print.PrintManager
import android.util.Base64
import android.webkit.JavascriptInterface
import android.webkit.WebView
import androidx.appcompat.app.AlertDialog
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import org.json.JSONObject
import java.io.ByteArrayOutputStream
import java.io.File

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

    /**
     * Real native microphone capture (MediaRecorder), NOT
     * navigator.mediaDevices.getUserMedia({audio: true}) — confirmed live
     * on a real device (Infinix X6532) that WebView's getUserMedia fails
     * with "Could not start audio source" even with RECORD_AUDIO already
     * granted, a known Chromium/WebView audio-capture limitation on some
     * OEM builds, not something fixable from the JS side. Every other
     * capability in device.js already prefers this native bridge first —
     * the microphone was the one exception, going straight to the (broken,
     * here) Web API; this closes that gap the same way Camera's native
     * still-capture already works around the same class of WebView limits.
     * Records to a temp file, base64-encodes it, and hands it back via
     * window.onNativeAudioRecorded so the caller can play it back —
     * proof the mic genuinely works, not just a permission check.
     */
    @JavascriptInterface
    fun recordAudioClip(durationMs: Int) {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.RECORD_AUDIO)
            != PackageManager.PERMISSION_GRANTED
        ) {
            webView.post {
                webView.evaluateJavascript(
                    "window.onNativeAudioRecorded && window.onNativeAudioRecorded(null, 'permission_denied')",
                    null,
                )
            }
            return
        }

        val outputFile = File(context.cacheDir, "phpx_mic_clip.m4a")
        val recorder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            MediaRecorder(context)
        } else {
            @Suppress("DEPRECATION")
            MediaRecorder()
        }

        try {
            recorder.setAudioSource(MediaRecorder.AudioSource.MIC)
            recorder.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4)
            recorder.setAudioEncoder(MediaRecorder.AudioEncoder.AAC)
            recorder.setOutputFile(outputFile.absolutePath)
            recorder.prepare()
            recorder.start()
        } catch (e: Exception) {
            webView.post {
                webView.evaluateJavascript(
                    "window.onNativeAudioRecorded && window.onNativeAudioRecorded(null, " +
                        JSONObject.quote(e.message ?: "erreur inconnue") + ")",
                    null,
                )
            }
            return
        }

        Handler(Looper.getMainLooper()).postDelayed({
            try {
                recorder.stop()
            } catch (_: Exception) {
                // A recorder that never actually received audio (stopped
                // too fast, hardware busy) throws here instead of on
                // start() — treated as silence below, not a crash.
            }
            recorder.release()

            if (!outputFile.exists() || outputFile.length() == 0L) {
                webView.post {
                    webView.evaluateJavascript(
                        "window.onNativeAudioRecorded && window.onNativeAudioRecorded(null, 'empty_recording')",
                        null,
                    )
                }
                return@postDelayed
            }

            val base64 = Base64.encodeToString(outputFile.readBytes(), Base64.NO_WRAP)
            webView.post {
                webView.evaluateJavascript(
                    "window.onNativeAudioRecorded && window.onNativeAudioRecorded('data:audio/mp4;base64,$base64', null)",
                    null,
                )
            }
        }, durationMs.toLong())
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

    /**
     * The real Android share sheet (Intent.ACTION_SEND + createChooser) —
     * lets the user send text/a link to any app that registers as a share
     * target (Messages, WhatsApp, Gmail...), not something a WebView page
     * can invoke on its own the way it can navigator.share() in a real
     * Chrome tab (Web Share API is unavailable inside a plain WebView).
     */
    @JavascriptInterface
    fun share(text: String, title: String) {
        val activity = context as? Activity ?: return

        activity.runOnUiThread {
            val sendIntent = Intent(Intent.ACTION_SEND).apply {
                type = "text/plain"
                putExtra(Intent.EXTRA_TEXT, text)
            }
            val chooserTitle = title.ifEmpty { null }
            context.startActivity(Intent.createChooser(sendIntent, chooserTitle))
        }
    }

    /**
     * Switches the home-screen launcher icon at runtime — enables one of
     * the two mutually-exclusive activity-alias entries declared in
     * AndroidManifest.xml (both targeting MainActivity, see the manifest's
     * comment) and disables the other. DONT_KILL_APP keeps this process
     * alive; most launchers still re-read the icon/label immediately, some
     * only after the next home-screen refresh — expected OS-level behavior
     * for this feature, not a bug here.
     */
    @JavascriptInterface
    fun setAppIcon(iconKey: String) {
        val packageManager = context.packageManager
        val defaultAlias = ComponentName(context, "com.mobile.engine.MainActivityDefault")
        val altAlias = ComponentName(context, "com.mobile.engine.MainActivityAlt")
        val (enable, disable) = if (iconKey == "alt") altAlias to defaultAlias else defaultAlias to altAlias

        packageManager.setComponentEnabledSetting(
            enable,
            PackageManager.COMPONENT_ENABLED_STATE_ENABLED,
            PackageManager.DONT_KILL_APP,
        )
        packageManager.setComponentEnabledSetting(
            disable,
            PackageManager.COMPONENT_ENABLED_STATE_DISABLED,
            PackageManager.DONT_KILL_APP,
        )
    }

    /**
     * Real connection type via ConnectivityManager — what
     * assets/js/connectivity.js's connectionType() prefers over the
     * browser's own (limited/inconsistent) navigator.connection.
     */
    @JavascriptInterface
    fun getConnectionType(): String {
        val manager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val network = manager.activeNetwork ?: return "none"
        val capabilities = manager.getNetworkCapabilities(network) ?: return "none"

        return when {
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "wifi"
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "cellular"
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> "ethernet"
            else -> "other"
        }
    }

    /**
     * Opens any URI (https://, tel:, mailto:, sms:...) via the system's own
     * handler app — url_launcher's core job. See MainActivity.kt's
     * shouldOverrideUrlLoading() for the equivalent behavior on a plain
     * <a href> a developer didn't route through this JS trigger.
     */
    @JavascriptInterface
    fun launchUrl(url: String) {
        val activity = context as? Activity ?: return

        activity.runOnUiThread {
            try {
                context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
            } catch (_: Exception) {
                // No app installed that handles this URI — nothing sensible
                // to fall back to natively, same as a dead link.
            }
        }
    }

    /**
     * android_alarm_manager_plus equivalent — schedules AlarmReceiver to
     * fire (and show a notification) after $delaySeconds, even if this
     * app's process has since been killed. setExactAndAllowWhileIdle so it
     * still fires under Doze, at the cost of needing the (normal, no
     * runtime prompt) SCHEDULE_EXACT_ALARM permission on API 31+.
     */
    @JavascriptInterface
    fun scheduleAlarm(requestCode: Int, delaySeconds: Int, title: String, message: String) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as android.app.AlarmManager
        val intent = Intent(context, AlarmReceiver::class.java).apply {
            putExtra("title", title)
            putExtra("message", message)
            putExtra("requestCode", requestCode)
        }
        val pendingIntent = android.app.PendingIntent.getBroadcast(
            context,
            requestCode,
            intent,
            android.app.PendingIntent.FLAG_UPDATE_CURRENT or android.app.PendingIntent.FLAG_IMMUTABLE,
        )
        val triggerAt = System.currentTimeMillis() + delaySeconds * 1000L

        alarmManager.setExactAndAllowWhileIdle(android.app.AlarmManager.RTC_WAKEUP, triggerAt, pendingIntent)
    }

    @JavascriptInterface
    fun showAlertDialog(title: String, message: String) {
        val activity = context as? Activity ?: return

        activity.runOnUiThread {
            AlertDialog.Builder(activity)
                .setTitle(title.ifEmpty { null })
                .setMessage(message)
                .setPositiveButton(android.R.string.ok, null)
                .show()
        }
    }

    @JavascriptInterface
    fun showConfirmDialog(title: String, message: String) {
        val activity = context as? Activity ?: run {
            reportConfirmDialogResult(false)
            return
        }

        activity.runOnUiThread {
            AlertDialog.Builder(activity)
                .setTitle(title.ifEmpty { null })
                .setMessage(message)
                .setPositiveButton(android.R.string.ok) { _, _ -> reportConfirmDialogResult(true) }
                .setNegativeButton(android.R.string.cancel) { _, _ -> reportConfirmDialogResult(false) }
                .setOnCancelListener { reportConfirmDialogResult(false) }
                .show()
        }
    }

    private fun reportConfirmDialogResult(confirmed: Boolean) {
        webView.post {
            webView.evaluateJavascript(
                "window.onNativeConfirmResult && window.onNativeConfirmResult(${confirmed})",
                null,
            )
        }
    }
}
