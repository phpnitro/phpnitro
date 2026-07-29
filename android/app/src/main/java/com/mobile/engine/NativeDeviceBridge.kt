package com.mobile.engine

import android.bluetooth.BluetoothManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CameraManager
import android.media.MediaPlayer
import android.net.Uri
import android.os.BatteryManager
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.provider.CalendarContract
import android.provider.ContactsContract
import android.provider.Settings
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * The "beaucoup plus petit que WebAppInterface.kt" native bridge
 * docs/proposals/moteur-rendu-natif.md's phase 7 describes — a handful
 * of real device capabilities NativeDeviceScreen needs, duplicated in
 * miniature rather than reusing WebAppInterface directly (that class's
 * constructor wants a WebView, which this Activity deliberately doesn't
 * have; instantiating one just to stub it out would fight the whole
 * point of this being a WebView-free path).
 *
 * Deliberately a small first slice, not all ~30 of DevicePage.php's
 * capabilities — camera/microphone/image-picker need real UI overlays
 * (a preview surface, a picker result callback) beyond what a single
 * synchronous bridge call can do; those stay on the WebView path for now.
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
     * Toggles between the "default" and "alt" launcher-icon aliases, same
     * PackageManager.setComponentEnabledSetting dance as
     * WebAppInterface.setAppIcon() — the two aliases already exist in the
     * manifest for the WebView path, and an icon isn't path-specific
     * either.
     */
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
}
