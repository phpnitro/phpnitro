package com.phpnitro.engine

import android.bluetooth.BluetoothManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CameraManager
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
import android.provider.CalendarContract
import android.provider.ContactsContract
import android.provider.Settings
import android.util.Base64
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.google.android.gms.location.LocationServices
import java.io.File

/**
 * The "beaucoup plus petit que WebAppInterface.kt" native bridge
 * docs/proposals/moteur-rendu-natif.md's phase 7 describes — a handful
 * of real device capabilities NativeDeviceScreen needs, duplicated in
 * miniature rather than reusing WebAppInterface directly (that class's
 * constructor wants a WebView, which this Activity deliberately doesn't
 * have; instantiating one just to stub it out would fight the whole
 * point of this being a WebView-free path).
 *
 * Camera/image-picker capture ARE covered (see NativeRenderPocActivity's
 * takePicturePreview/pickImage launchers, which call back through here) —
 * what's genuinely still missing is a LIVE camera/mic preview surface
 * (getUserMedia's WebView-only equivalent) and printing (needs a WebView
 * document source); those stay on the WebView path, see
 * NativeWidgetsMediaScreen.php's docblock for the same reasoning applied
 * to VideoPlayer.
 */
class NativeDeviceBridge(private val context: Context) {

    fun vibrate(milliseconds: Long) {
        val vibrator = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            (context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as VibratorManager).defaultVibrator
        } else {
            @Suppress("DEPRECATION")
            context.getSystemService(Context.VIBRATOR_SERVICE) as Vibrator
        }
        vibrator.vibrate(VibrationEffect.createOneShot(milliseconds, VibrationEffect.DEFAULT_AMPLITUDE))
    }

    private var torchOn = false

    fun toggleTorch(): Boolean {
        val cameraManager = context.getSystemService(Context.CAMERA_SERVICE) as CameraManager
        val cameraId = cameraManager.cameraIdList.firstOrNull {
            cameraManager.getCameraCharacteristics(it).get(CameraCharacteristics.FLASH_INFO_AVAILABLE) == true
        } ?: return false

        torchOn = !torchOn
        cameraManager.setTorchMode(cameraId, torchOn)
        return torchOn
    }

    fun batteryLevel(): Int {
        val batteryManager = context.getSystemService(Context.BATTERY_SERVICE) as BatteryManager
        return batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
    }

    /** Settings.Secure.ANDROID_ID — same choice/rationale as WebAppInterface.getDeviceId(). */
    fun deviceId(): String {
        return Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID) ?: ""
    }

    /** "unsupported" | "off" | "on" — never triggers pairing/scanning UI, same as WebAppInterface.getBluetoothState(). */
    fun bluetoothState(): String {
        val adapter = (context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter
            ?: return "unsupported"
        return if (adapter.isEnabled) "on" else "off"
    }

    /** Same real ConnectivityManager check WebAppInterface.getConnectionType() uses — the native replacement for Engine\Connectivity\ConnectivityBadge's JS-side navigator.onLine. */
    fun isOnline(): Boolean {
        val manager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as android.net.ConnectivityManager
        val network = manager.activeNetwork ?: return false
        val capabilities = manager.getNetworkCapabilities(network) ?: return false
        return capabilities.hasCapability(android.net.NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    // Same Keystore-backed file WebAppInterface's secureStore/secureRetrieve
    // use ("phpx_secure_storage") — a secret stored via one rendering path
    // is readable from the other, which is the correct behavior for an
    // app-level capability that isn't really "a WebView thing" or "a
    // native-Canvas thing" at all.
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

    fun secureStore(key: String, value: String) {
        encryptedPrefs.edit().putString(key, value).apply()
    }

    fun secureRetrieve(key: String): String {
        return encryptedPrefs.getString(key, "") ?: ""
    }

    /**
     * -1 means "permission not granted" (distinct from a real 0), same
     * fail-quiet-not-fail-loud convention WebAppInterface.getContacts()
     * uses — this only READS the permission state, it never prompts;
     * there's no runtime permission-request flow wired into this
     * WebView-free Activity yet, so this only returns real data on a
     * device where READ_CONTACTS was already granted through some other
     * path (e.g. the WebView app's own contacts feature).
     */
    fun contactsCount(): Int {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.READ_CONTACTS)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return -1
        }
        val cursor = context.contentResolver.query(
            ContactsContract.CommonDataKinds.Phone.CONTENT_URI,
            arrayOf(ContactsContract.CommonDataKinds.Phone._ID),
            null,
            null,
            null,
        )
        return cursor?.use { it.count } ?: 0
    }

    /** Same -1-means-no-permission convention as contactsCount(); events in the next 30 days. */
    fun upcomingEventsCount(): Int {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.READ_CALENDAR)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return -1
        }
        val now = System.currentTimeMillis()
        val cursor = context.contentResolver.query(
            CalendarContract.Events.CONTENT_URI,
            arrayOf(CalendarContract.Events._ID),
            "${CalendarContract.Events.DTSTART} BETWEEN ? AND ?",
            arrayOf(now.toString(), (now + 30L * 24 * 60 * 60 * 1000).toString()),
            null,
        )
        return cursor?.use { it.count } ?: 0
    }

    // A single persistent player (unlike playSound()'s fire-and-forget,
    // release-on-completion one) — what NativeWidgetsMediaScreen's
    // AudioPlayer needs: play/pause state that survives across taps, the
    // way a real <audio controls> element's playback state does.
    private var audioPlayer: MediaPlayer? = null

    fun playAudio(url: String) {
        val player = audioPlayer
        if (player != null) {
            player.start()
            return
        }
        audioPlayer = MediaPlayer().apply {
            setDataSource(url)
            setOnPreparedListener { it.start() }
            setOnErrorListener { mp, _, _ -> mp.release(); audioPlayer = null; true }
            prepareAsync()
        }
    }

    fun pauseAudio() {
        audioPlayer?.let { if (it.isPlaying) it.pause() }
    }

    /** Fire-and-forget, same as WebAppInterface.playSound() — releases itself on completion. */
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

    // Same channel WebAppInterface's showNotification() posts to — a
    // notification isn't really "a WebView thing" any more than secure
    // storage is, so both rendering paths share it rather than each
    // creating their own.
    private val notificationChannelId = "phpx_default"

    /**
     * No-op if POST_NOTIFICATIONS (API 33+) isn't granted — same
     * permission-safe-read convention as contactsCount(), just applied to
     * a write instead of a read: this never prompts, it only checks.
     */
    fun showNotification(title: String, message: String) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ActivityCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS)
            != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = android.app.NotificationChannel(
                notificationChannelId,
                "Notifications",
                android.app.NotificationManager.IMPORTANCE_DEFAULT,
            )
            (context.getSystemService(Context.NOTIFICATION_SERVICE) as android.app.NotificationManager)
                .createNotificationChannel(channel)
        }
        val notification = NotificationCompat.Builder(context, notificationChannelId)
            .setContentTitle(title)
            .setContentText(message)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setAutoCancel(true)
            .build()
        NotificationManagerCompat.from(context).notify(System.currentTimeMillis().toInt(), notification)
    }

    /** Opens the system share sheet — same Intent.ACTION_SEND chooser as WebAppInterface.share(). */
    fun share(text: String, title: String) {
        val sendIntent = Intent(Intent.ACTION_SEND).apply {
            type = "text/plain"
            putExtra(Intent.EXTRA_TEXT, text)
        }
        context.startActivity(Intent.createChooser(sendIntent, title.ifEmpty { null }))
    }

    /**
     * Enables the activity-alias matching $iconKey and disables every
     * OTHER activity-alias that follows the same ".DynamicIcon<Key>"
     * naming convention (Engine\Device\DynamicIcon, see its own
     * docblock) — discovered via PackageManager
     * (GET_ACTIVITIES|MATCH_DISABLED_COMPONENTS also returns
     * activity-aliases, not just real activities), not a hardcoded
     * two-alias list. A project isn't capped at exactly two icons: add
     * another ".DynamicIcon<Key>" alias to AndroidManifest.xml (its own
     * icon/roundIcon, same shape as the two already there) and this
     * function picks it up with no code change here — the one thing
     * that genuinely can't be dynamic is the icon FILES themselves
     * (real OS constraint: every launcher-icon variant an app can ever
     * switch to must be declared, and its APK size paid for, at build
     * time — no framework can materialize a brand new icon file at
     * runtime).
     *
     * $iconKey is snake_case/kebab-case ("holiday_2026") converted to
     * the alias's PascalCase suffix (".DynamicIconHoliday2026") —
     * matches DynamicIcon::setAction()'s own PHP-side key convention.
     */
    fun setAppIcon(iconKey: String) {
        val packageManager = context.packageManager
        val aliasSuffix = iconKey.split("_", "-")
            .filter { it.isNotEmpty() }
            .joinToString("") { it.replaceFirstChar(Char::uppercaseChar) }
        val targetAlias = "${context.packageName}.DynamicIcon$aliasSuffix"

        val packageInfo = packageManager.getPackageInfo(
            context.packageName,
            PackageManager.GET_ACTIVITIES or PackageManager.MATCH_DISABLED_COMPONENTS,
        )
        val knownAliases = packageInfo.activities
            ?.map { it.name }
            ?.filter { it.contains(".DynamicIcon") }
            ?: emptyList()

        for (aliasName in knownAliases) {
            val state = if (aliasName == targetAlias) {
                PackageManager.COMPONENT_ENABLED_STATE_ENABLED
            } else {
                PackageManager.COMPONENT_ENABLED_STATE_DISABLED
            }
            packageManager.setComponentEnabledSetting(ComponentName(context, aliasName), state, PackageManager.DONT_KILL_APP)
        }
    }

    /**
     * The reverse of WebAppInterface.openNativeRenderPreviewAt(): for the
     * handful of capabilities that still genuinely need a WebView (camera
     * preview, NFC foreground dispatch, VideoPlayer, an interactive map,
     * FadeIn/PageView's animation — see each Native*Screen's docblock for
     * which), opens MainActivity at a specific path via the same
     * phpnitro:// deep-link scheme MainActivity.deepLinkPath() already
     * parses for real deep links — not a second routing mechanism.
     */
    fun openWebView(path: String) {
        val activity = context as? android.app.Activity ?: return
        activity.startActivity(
            Intent(context, MainActivity::class.java).apply {
                data = Uri.parse("phpnitro://" + path.removePrefix("/"))
            },
        )
    }

    fun setBrightness(level: Float) {
        val activity = context as? android.app.Activity ?: return
        activity.runOnUiThread {
            val params = activity.window.attributes
            params.screenBrightness = level.coerceIn(0.01f, 1.0f)
            activity.window.attributes = params
        }
    }

    /**
     * FusedLocationProviderClient (play-services-location, already a
     * dependency) instead of Engine\LocationButton's browser
     * navigator.geolocation — the real native equivalent, not a shim.
     * Never prompts for the runtime permission, only reads it (same
     * fail-quiet convention as contactsCount()); getLastLocation() can
     * itself return null (no fix cached yet), reported as such rather
     * than left hanging.
     */
    fun getLocation(onResult: (String) -> Unit) {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.ACCESS_FINE_LOCATION)
            != PackageManager.PERMISSION_GRANTED
        ) {
            onResult("Permission requise")
            return
        }
        LocationServices.getFusedLocationProviderClient(context).lastLocation
            .addOnSuccessListener { location ->
                if (location == null) {
                    onResult("Position inconnue")
                } else {
                    onResult("%.5f, %.5f".format(location.latitude, location.longitude))
                }
            }
            .addOnFailureListener { onResult("Erreur de localisation") }
    }

    /**
     * GitHub/Facebook/Microsoft/Apple sign-in — opens $authorizeUrl (a
     * URL public/index.php already built server-side via
     * Engine\SocialAuth\{Provider}SignIn::authorizeUrl(), client_id/
     * secret never touching this Kotlin code at all) in a Custom Tab.
     * The provider redirects to phpnitro://oauth-callback?code=...&
     * state=... on success — AndroidManifest's matching intent-filter on
     * NativeRenderPocActivity (android:launchMode="singleTask") routes
     * that back into onNewIntent() on the SAME Activity instance, not a
     * new one. This method's only job is opening the tab; the actual
     * token exchange happens server-side once the code comes back (see
     * handleOAuthCallback() in NativeRenderPocActivity.kt).
     */
    fun startOAuthFlow(authorizeUrl: String) {
        androidx.browser.customtabs.CustomTabsIntent.Builder()
            .build()
            .launchUrl(context, android.net.Uri.parse(authorizeUrl))
    }

    /**
     * Real Google Sign-In via Credential Manager (not the deprecated
     * GoogleSignInClient) — returns a Google-issued ID token (a signed
     * JWT), NOT a Firebase session by itself. See
     * FirebaseAuth::signInWithGoogleIdToken() for what happens to that
     * token server-side (public/index.php's "google_signin" action
     * handler exchanges it for one).
     *
     * webClientId MUST be a Google Cloud OAuth 2.0 "Web application"
     * client ID (Firebase Console -> Authentication -> Sign-in method ->
     * Google -> Web SDK configuration -> Web client ID) — NOT an Android
     * client ID, even though this runs on Android; that's how
     * GetGoogleIdOption identifies which app/project is asking. This
     * project has no such ID configured by default (it's per-Firebase-
     * project, same as FIREBASE_WEB_API_KEY) — an empty string here
     * always fails informatively rather than crashing, so the button
     * exists and explains itself before a developer has wired up a real
     * project.
     *
     * getCredentialAsync (callback-based), not the suspend getCredential
     * — this class has no coroutine scope anywhere else, and every other
     * async capability here (location, biometric, mic) already uses a
     * plain callback, so this matches rather than introducing the only
     * coroutine in the file for one method.
     */
    fun signInWithGoogle(webClientId: String, onResult: (String?, String?) -> Unit) {
        val activity = context as? FragmentActivity ?: run {
            onResult(null, "Contexte non compatible.")
            return
        }
        if (webClientId.isBlank()) {
            onResult(null, "Google Sign-In non configuré (Web Client ID manquant côté Android).")
            return
        }

        val googleIdOption = com.google.android.libraries.identity.googleid.GetGoogleIdOption.Builder()
            .setFilterByAuthorizedAccounts(false)
            .setServerClientId(webClientId)
            .build()
        val request = androidx.credentials.GetCredentialRequest.Builder()
            .addCredentialOption(googleIdOption)
            .build()

        androidx.credentials.CredentialManager.create(context).getCredentialAsync(
            activity,
            request,
            android.os.CancellationSignal(),
            ContextCompat.getMainExecutor(context),
            object : androidx.credentials.CredentialManagerCallback<androidx.credentials.GetCredentialResponse, androidx.credentials.exceptions.GetCredentialException> {
                override fun onResult(result: androidx.credentials.GetCredentialResponse) {
                    val credential = result.credential
                    if (credential is androidx.credentials.CustomCredential &&
                        credential.type == com.google.android.libraries.identity.googleid.GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
                    ) {
                        val idTokenCredential = com.google.android.libraries.identity.googleid.GoogleIdTokenCredential.createFrom(credential.data)
                        onResult(idTokenCredential.idToken, null)
                    } else {
                        onResult(null, "Type d'identifiant inattendu.")
                    }
                }

                override fun onError(e: androidx.credentials.exceptions.GetCredentialException) {
                    onResult(null, e.message ?: "Connexion Google annulée ou indisponible.")
                }
            },
        )
    }

    /**
     * A real android.hardware.biometrics prompt (fingerprint/face unlock)
     * — needs a FragmentActivity, which NativeRenderPocActivity (an
     * AppCompatActivity) already is.
     */
    fun showBiometricPrompt(onResult: (Boolean, String) -> Unit) {
        val activity = context as? FragmentActivity ?: run {
            onResult(false, "Contexte non compatible.")
            return
        }

        val availability = BiometricManager.from(context)
            .canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_STRONG)
        if (availability != BiometricManager.BIOMETRIC_SUCCESS) {
            onResult(false, biometricUnavailableReason(availability))
            return
        }

        activity.runOnUiThread {
            val prompt = BiometricPrompt(
                activity,
                ContextCompat.getMainExecutor(context),
                object : BiometricPrompt.AuthenticationCallback() {
                    override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                        onResult(true, "")
                    }

                    override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                        onResult(false, errString.toString())
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

    /**
     * Same MediaRecorder approach WebAppInterface.recordAudioClip() uses
     * (a real getUserMedia({audio:true}) equivalent is broken on some
     * WebView/OEM builds, which is why that one exists at all) — records
     * to a cache file, hands back a base64 data: URI ImageLoader.kt can
     * decode directly if ever previewed, though this bridge just reports
     * success/failure text.
     */
    fun recordAudioClip(durationMs: Long, onResult: (String?, String?) -> Unit) {
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.RECORD_AUDIO)
            != PackageManager.PERMISSION_GRANTED
        ) {
            onResult(null, "permission_denied")
            return
        }

        val outputFile = File(context.cacheDir, "phpx_native_mic_clip.m4a")
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
            onResult(null, e.message ?: "erreur inconnue")
            return
        }

        Handler(Looper.getMainLooper()).postDelayed({
            try {
                recorder.stop()
            } catch (_: Exception) {
                // A recorder that never actually received audio throws
                // here instead of on start() — treated as silence below.
            }
            recorder.release()

            if (!outputFile.exists() || outputFile.length() == 0L) {
                onResult(null, "empty_recording")
                return@postDelayed
            }

            val base64 = Base64.encodeToString(outputFile.readBytes(), Base64.NO_WRAP)
            onResult("data:audio/mp4;base64,$base64", null)
        }, durationMs)
    }

    /**
     * A single snapshot reading, not the continuous stream
     * WebAppInterface.startSensor()/stopSensor() push to JS — this
     * pipeline's paint model is one-shot per request, so "keep listening
     * forever" has no screen to keep updating anyway. Registers, waits for
     * the first reading, unregisters immediately.
     */
    fun readSensor(sensorType: Int, onResult: (String) -> Unit) {
        val sensorManager = context.getSystemService(Context.SENSOR_SERVICE) as android.hardware.SensorManager
        val sensor = sensorManager.getDefaultSensor(sensorType) ?: run {
            onResult("Capteur indisponible")
            return
        }

        val listener = object : android.hardware.SensorEventListener {
            override fun onSensorChanged(event: android.hardware.SensorEvent) {
                sensorManager.unregisterListener(this)
                onResult("%.2f, %.2f, %.2f".format(event.values[0], event.values[1], event.values[2]))
            }

            override fun onAccuracyChanged(sensor: android.hardware.Sensor, accuracy: Int) {}
        }
        sensorManager.registerListener(listener, sensor, android.hardware.SensorManager.SENSOR_DELAY_UI)
    }

    /**
     * Google Play Billing (billing-ktx, already a dependency) — same
     * documented caveat as WebAppInterface.queryProducts()/
     * purchaseProduct(): written to follow the real Billing Library v7
     * flow, never exercised against a real Play Console product (no
     * sandbox reachable outside a real account).
     */
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

    fun queryProducts(productIds: List<String>, onResult: (String) -> Unit) {
        val productList = productIds.map { id ->
            com.android.billingclient.api.QueryProductDetailsParams.Product.newBuilder()
                .setProductId(id)
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
                    onResult(summary)
                }
            }

            override fun onBillingServiceDisconnected() {}
        })
    }

    fun purchaseProduct(productId: String) {
        val activity = context as? android.app.Activity ?: return
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
                    billingClient.launchBillingFlow(activity, flowParams)
                }
            }

            override fun onBillingServiceDisconnected() {}
        })
    }

    /** Same GeofenceReceiver (AndroidManifest.xml) both pipelines share — a real zone is a real zone either way. */
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

    fun removeGeofence(id: String) {
        geofencingClient.removeGeofences(listOf(id))
    }

    /** Same BackgroundPingWorker (WorkManager, min 15min floor) both pipelines share. */
    fun scheduleBackgroundTask(endpoint: String, intervalMinutes: Int) {
        val data = androidx.work.Data.Builder().putString("endpoint", endpoint).build()
        val request = androidx.work.PeriodicWorkRequestBuilder<BackgroundPingWorker>(
            intervalMinutes.coerceAtLeast(15).toLong(),
            java.util.concurrent.TimeUnit.MINUTES,
        ).setInputData(data).build()

        androidx.work.WorkManager.getInstance(context)
            .enqueueUniquePeriodicWork("phpx_background_ping", androidx.work.ExistingPeriodicWorkPolicy.KEEP, request)
    }

    fun cancelBackgroundTask() {
        androidx.work.WorkManager.getInstance(context).cancelUniqueWork("phpx_background_ping")
    }

    /**
     * Same AlarmReceiver WebAppInterface.scheduleAlarm() already uses —
     * genuinely shared (fires a notification even if this app's process
     * has since been killed, whichever rendering path scheduled it).
     * setExactAndAllowWhileIdle so it still fires under Doze, at the cost
     * of needing the (normal, no runtime prompt) SCHEDULE_EXACT_ALARM +
     * USE_EXACT_ALARM permissions on API 31+ — already declared in
     * android/app/src/main/AndroidManifest.xml.
     */
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

    /**
     * ML Kit Translate — on-device, no API key, downloads the language
     * model on first use over whatever network is available (kept
     * simple: no Wi-Fi-only restriction for this demo). The real native
     * equivalent of Engine\GoogleTranslate's web-based widget, not a
     * workaround for it.
     */
    fun translateText(text: String, sourceLanguage: String, targetLanguage: String, onResult: (String) -> Unit) {
        val options = com.google.mlkit.nl.translate.TranslatorOptions.Builder()
            .setSourceLanguage(sourceLanguage)
            .setTargetLanguage(targetLanguage)
            .build()
        val translator = com.google.mlkit.nl.translate.Translation.getClient(options)

        val conditions = com.google.mlkit.common.model.DownloadConditions.Builder().build()
        translator.downloadModelIfNeeded(conditions)
            .addOnSuccessListener {
                translator.translate(text)
                    .addOnSuccessListener { translated -> onResult(translated) }
                    .addOnFailureListener { onResult("Erreur de traduction") }
            }
            .addOnFailureListener { onResult("Téléchargement du modèle échoué") }
    }
}
