package com.phpnitro.engine

import android.app.Activity
import android.app.NotificationChannel
import android.app.NotificationManager
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.database.Cursor
import android.graphics.Bitmap
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.media.MediaPlayer
import android.media.MediaRecorder
import android.net.Uri
import android.os.BatteryManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.print.PrintAttributes
import android.print.PrintManager
import android.provider.CalendarContract
import android.provider.ContactsContract
import android.provider.Settings
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
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import org.json.JSONArray
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
     * Phase 7 of docs/proposals/moteur-rendu-natif.md: the native render
     * engine stops being an adb-only proof of concept the moment a real
     * button inside the actual app UI can reach it — this is that button's
     * bridge call. Settings gates it behind a Preferences-backed flag
     * (SettingsPage.php) so it can be turned on/off without a rebuild,
     * same "flag-gated, opt-in" rollout shape the roadmap describes for
     * gradually migrating widgets off the WebView pipeline.
     */
    @JavascriptInterface
    fun openNativeRenderPreview() {
        openNativeRenderPreviewAt("home")
    }

    /**
     * Same as openNativeRenderPreview() but jumps straight to a given
     * screen — for call sites whose WebView page was removed because its
     * native conversion is complete (see LoginPage.php's removal, for
     * instance): the WebView "Se connecter" link couldn't render '/login'
     * itself anymore, so it opens the native screen that replaced it
     * instead of 404ing.
     */
    @JavascriptInterface
    fun openNativeRenderPreviewAt(screen: String) {
        val activity = context as? Activity ?: return

        activity.runOnUiThread {
            context.startActivity(Intent(context, NativeRenderPocActivity::class.java).putExtra("screen", screen))
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
        val defaultAlias = ComponentName(context, "${context.packageName}.MainActivityDefault")
        val altAlias = ComponentName(context, "${context.packageName}.MainActivityAlt")
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

    // --- Sensors (accelerometer/gyroscope/compass) ---

    private val sensorManager by lazy { context.getSystemService(Context.SENSOR_SERVICE) as SensorManager }
    private val activeSensorListeners = mutableMapOf<Int, SensorEventListener>()

    /**
     * Streams live readings via window.onNativeSensorReading(type, x, y, z)
     * until stopSensor() is called — one JS callback for all three sensor
     * types (accelerometer/gyroscope/magnetic field for compass heading),
     * $sensorType is the Android Sensor.TYPE_* constant so JS doesn't need
     * its own copy of that mapping.
     */
    @JavascriptInterface
    fun startSensor(sensorType: Int) {
        val sensor = sensorManager.getDefaultSensor(sensorType) ?: return
        stopSensor(sensorType)

        val listener = object : SensorEventListener {
            override fun onSensorChanged(event: SensorEvent) {
                webView.post {
                    webView.evaluateJavascript(
                        "window.onNativeSensorReading && window.onNativeSensorReading(" +
                            "$sensorType, ${event.values[0]}, ${event.values[1]}, ${event.values[2]})",
                        null,
                    )
                }
            }

            override fun onAccuracyChanged(sensor: Sensor, accuracy: Int) {}
        }
        activeSensorListeners[sensorType] = listener
        sensorManager.registerListener(listener, sensor, SensorManager.SENSOR_DELAY_UI)
    }

    @JavascriptInterface
    fun stopSensor(sensorType: Int) {
        activeSensorListeners.remove(sensorType)?.let { sensorManager.unregisterListener(it) }
    }

    // --- Torch, brightness, battery, device ID ---

    private var torchOn = false

    @JavascriptInterface
    fun toggleTorch(): Boolean {
        val cameraManager = context.getSystemService(Context.CAMERA_SERVICE) as android.hardware.camera2.CameraManager
        val cameraId = cameraManager.cameraIdList.firstOrNull {
            cameraManager.getCameraCharacteristics(it)
                .get(android.hardware.camera2.CameraCharacteristics.FLASH_INFO_AVAILABLE) == true
        } ?: return false

        torchOn = !torchOn
        cameraManager.setTorchMode(cameraId, torchOn)
        return torchOn
    }

    /** 0.0–1.0, only affects THIS Activity's window, not the system-wide setting. */
    @JavascriptInterface
    fun setScreenBrightness(level: Float) {
        val activity = context as? Activity ?: return
        activity.runOnUiThread {
            val params = activity.window.attributes
            params.screenBrightness = level.coerceIn(0.01f, 1.0f)
            activity.window.attributes = params
        }
    }

    @JavascriptInterface
    fun getBatteryLevel(): Int {
        val batteryManager = context.getSystemService(Context.BATTERY_SERVICE) as BatteryManager
        return batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
    }

    /**
     * Settings.Secure.ANDROID_ID, not the IMEI — resettable on factory
     * reset, different per app signing key since Android 8, but doesn't
     * require any dangerous permission or user-facing privacy prompt the
     * way a hardware serial/IMEI would.
     */
    @JavascriptInterface
    fun getDeviceId(): String {
        return Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID) ?: ""
    }

    // --- Bluetooth ---

    /** "unsupported" | "off" | "on" — never triggers pairing/scanning UI on its own. */
    @JavascriptInterface
    fun getBluetoothState(): String {
        val adapter = (context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter
            ?: return "unsupported"
        return if (adapter.isEnabled) "on" else "off"
    }

    /**
     * Bonded (already-paired) devices only — a full BLE discovery scan
     * needs a foreground service + location context beyond this bridge's
     * scope for now.
     */
    @JavascriptInterface
    fun getBondedBluetoothDevices(): String {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.BLUETOOTH_CONNECT)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return "[]"
        }
        val adapter = (context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter
            ?: return "[]"

        val devices = JSONArray()
        adapter.bondedDevices?.forEach { device ->
            devices.put(JSONObject().apply {
                put("name", device.name ?: "")
                put("address", device.address)
            })
        }
        return devices.toString()
    }

    // --- NFC (read-only NDEF tag scanning) ---
    //
    // Push-based, not poll-based: there's no "read now" call, only a
    // listening flag MainActivity checks in onNewIntent() when a foreground
    // NFC dispatch delivers a scanned tag. isNfcListening() is exposed to
    // MainActivity (same class package, no @JavascriptInterface needed
    // there) rather than duplicating the flag.
    private var nfcListening = false

    @JavascriptInterface
    fun startNfc() {
        nfcListening = true
    }

    @JavascriptInterface
    fun stopNfc() {
        nfcListening = false
    }

    fun isNfcListening(): Boolean = nfcListening

    // --- In-app purchase (Google Play Billing, one-time products only) ---
    //
    // Never exercised against a real Play Console product — there's no
    // sandbox reachable outside a real Play Console account. Written to
    // compile and follow the documented Billing Library v7 flow, not
    // verified end-to-end.
    private val billingClient by lazy {
        com.android.billingclient.api.BillingClient.newBuilder(context)
            .setListener { _, _ -> }
            .enablePendingPurchases(
                com.android.billingclient.api.PendingPurchasesParams.newBuilder()
                    .enableOneTimeProducts()
                    .build(),
            )
            .build()
    }

    @JavascriptInterface
    fun queryProducts(productIdsJson: String, outputElementId: String) {
        val ids = JSONArray(productIdsJson)
        val productList = (0 until ids.length()).map { i ->
            com.android.billingclient.api.QueryProductDetailsParams.Product.newBuilder()
                .setProductId(ids.getString(i))
                .setProductType(com.android.billingclient.api.BillingClient.ProductType.INAPP)
                .build()
        }
        val params = com.android.billingclient.api.QueryProductDetailsParams.newBuilder()
            .setProductList(productList)
            .build()

        billingClient.startConnection(object : com.android.billingclient.api.BillingClientStateListener {
            override fun onBillingSetupFinished(result: com.android.billingclient.api.BillingResult) {
                billingClient.queryProductDetailsAsync(params) { _, productDetailsList ->
                    val summary = productDetailsList.joinToString(", ") { details ->
                        "${details.title}: ${details.oneTimePurchaseOfferDetails?.formattedPrice ?: "?"}"
                    }.ifEmpty { "Aucun produit trouvé." }

                    webView.post {
                        webView.evaluateJavascript(
                            "(function(el){ if (el) el.textContent = ${JSONObject.quote(summary)}; })" +
                                "(document.getElementById(${JSONObject.quote(outputElementId)}))",
                            null,
                        )
                    }
                }
            }

            override fun onBillingServiceDisconnected() {}
        })
    }

    @JavascriptInterface
    fun purchaseProduct(productId: String) {
        val product = com.android.billingclient.api.QueryProductDetailsParams.Product.newBuilder()
            .setProductId(productId)
            .setProductType(com.android.billingclient.api.BillingClient.ProductType.INAPP)
            .build()
        val params = com.android.billingclient.api.QueryProductDetailsParams.newBuilder()
            .setProductList(listOf(product))
            .build()

        billingClient.startConnection(object : com.android.billingclient.api.BillingClientStateListener {
            override fun onBillingSetupFinished(result: com.android.billingclient.api.BillingResult) {
                billingClient.queryProductDetailsAsync(params) { _, productDetailsList ->
                    val details = productDetailsList.firstOrNull() ?: return@queryProductDetailsAsync
                    val offerParams = com.android.billingclient.api.BillingFlowParams.ProductDetailsParams
                        .newBuilder()
                        .setProductDetails(details)
                        .build()
                    val flowParams = com.android.billingclient.api.BillingFlowParams.newBuilder()
                        .setProductDetailsParamsList(listOf(offerParams))
                        .build()
                    (context as? Activity)?.let { activity ->
                        billingClient.launchBillingFlow(activity, flowParams)
                    }
                }
            }

            override fun onBillingServiceDisconnected() {}
        })
    }

    // --- Geofencing (real zone + enter/exit, Play Services GeofencingClient) ---
    private val geofencingClient by lazy {
        com.google.android.gms.location.LocationServices.getGeofencingClient(context)
    }

    private fun geofencePendingIntent(): android.app.PendingIntent {
        val intent = Intent(context, GeofenceReceiver::class.java)
        return android.app.PendingIntent.getBroadcast(
            context,
            0,
            intent,
            android.app.PendingIntent.FLAG_UPDATE_CURRENT or android.app.PendingIntent.FLAG_MUTABLE,
        )
    }

    @JavascriptInterface
    fun addGeofence(id: String, latitude: Double, longitude: Double, radiusMeters: Float) {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.ACCESS_FINE_LOCATION)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        val geofence = com.google.android.gms.location.Geofence.Builder()
            .setRequestId(id)
            .setCircularRegion(latitude, longitude, radiusMeters)
            .setExpirationDuration(com.google.android.gms.location.Geofence.NEVER_EXPIRE)
            .setTransitionTypes(
                com.google.android.gms.location.Geofence.GEOFENCE_TRANSITION_ENTER or
                    com.google.android.gms.location.Geofence.GEOFENCE_TRANSITION_EXIT,
            )
            .build()

        val request = com.google.android.gms.location.GeofencingRequest.Builder()
            .setInitialTrigger(com.google.android.gms.location.GeofencingRequest.INITIAL_TRIGGER_ENTER)
            .addGeofence(geofence)
            .build()

        geofencingClient.addGeofences(request, geofencePendingIntent())
    }

    @JavascriptInterface
    fun removeGeofence(id: String) {
        geofencingClient.removeGeofences(listOf(id))
    }

    // --- Secure storage (Android Keystore-backed, for tokens that shouldn't sit in plain SQLite) ---

    private val encryptedPrefs by lazy {
        val masterKey = MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build()
        EncryptedSharedPreferences.create(
            context,
            "phpx_secure_storage",
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    @JavascriptInterface
    fun secureStore(key: String, value: String) {
        encryptedPrefs.edit().putString(key, value).apply()
    }

    @JavascriptInterface
    fun secureRetrieve(key: String): String {
        return encryptedPrefs.getString(key, "") ?: ""
    }

    @JavascriptInterface
    fun secureRemove(key: String) {
        encryptedPrefs.edit().remove(key).apply()
    }

    // --- Contacts / calendar (read-only) ---

    /** [{name, phone}], empty if permission not granted — no exception thrown into JS. */
    @JavascriptInterface
    fun getContacts(): String {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.READ_CONTACTS)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return "[]"
        }

        val contacts = JSONArray()
        val cursor: Cursor? = context.contentResolver.query(
            ContactsContract.CommonDataKinds.Phone.CONTENT_URI,
            arrayOf(ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME, ContactsContract.CommonDataKinds.Phone.NUMBER),
            null,
            null,
            "${ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME} ASC LIMIT 200",
        )
        cursor?.use {
            val nameIdx = it.getColumnIndex(ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME)
            val numberIdx = it.getColumnIndex(ContactsContract.CommonDataKinds.Phone.NUMBER)
            while (it.moveToNext()) {
                contacts.put(JSONObject().apply {
                    put("name", it.getString(nameIdx) ?: "")
                    put("phone", it.getString(numberIdx) ?: "")
                })
            }
        }
        return contacts.toString()
    }

    /** [{title, start, end}] for events in the next 30 days, empty if permission not granted. */
    @JavascriptInterface
    fun getUpcomingEvents(): String {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.READ_CALENDAR)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return "[]"
        }

        val events = JSONArray()
        val now = System.currentTimeMillis()
        val projection = arrayOf(
            CalendarContract.Events.TITLE,
            CalendarContract.Events.DTSTART,
            CalendarContract.Events.DTEND,
        )
        val cursor: Cursor? = context.contentResolver.query(
            CalendarContract.Events.CONTENT_URI,
            projection,
            "${CalendarContract.Events.DTSTART} BETWEEN ? AND ?",
            arrayOf(now.toString(), (now + 30L * 24 * 60 * 60 * 1000).toString()),
            "${CalendarContract.Events.DTSTART} ASC LIMIT 100",
        )
        cursor?.use {
            while (it.moveToNext()) {
                events.put(JSONObject().apply {
                    put("title", it.getString(0) ?: "")
                    put("start", it.getLong(1))
                    put("end", it.getLong(2))
                })
            }
        }
        return events.toString()
    }

    // --- Background work ---

    /**
     * Periodic background task (WorkManager, min 15 minutes — an Android
     * platform floor, not a choice made here) that POSTs to $endpoint every
     * time it fires, even if the app isn't in the foreground. Not
     * geofencing (that needs Play Services' FusedLocationProvider
     * geofencing APIs, a separate dependency not pulled in here) — a
     * periodic ping, the other common "background execution" need.
     */
    @JavascriptInterface
    fun scheduleBackgroundTask(endpoint: String, intervalMinutes: Int) {
        val data = androidx.work.Data.Builder().putString("endpoint", endpoint).build()
        val request = androidx.work.PeriodicWorkRequestBuilder<BackgroundPingWorker>(
            intervalMinutes.coerceAtLeast(15).toLong(),
            java.util.concurrent.TimeUnit.MINUTES,
        ).setInputData(data).build()

        androidx.work.WorkManager.getInstance(context)
            .enqueueUniquePeriodicWork("phpx_background_ping", androidx.work.ExistingPeriodicWorkPolicy.KEEP, request)
    }

    @JavascriptInterface
    fun cancelBackgroundTask() {
        androidx.work.WorkManager.getInstance(context).cancelUniqueWork("phpx_background_ping")
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
