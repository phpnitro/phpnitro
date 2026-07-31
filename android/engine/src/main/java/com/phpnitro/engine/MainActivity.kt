package com.phpnitro.engine

import android.Manifest
import android.annotation.SuppressLint
import android.app.PendingIntent
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.nfc.NfcAdapter
import android.nfc.Tag
import android.nfc.tech.Ndef
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.webkit.GeolocationPermissions
import android.webkit.PermissionRequest
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import org.json.JSONObject

/**
 * Hosts the PHP-served UI in a native WebView, backed by PhpServer — a
 * cross-compiled PHP binary running as a real on-device process
 * (armeabi-v7a and arm64-v8a both bundled, so this works on 32-bit and
 * 64-bit devices alike).
 *
 * Grants the runtime permissions and WebView callbacks needed for the
 * device-capability widgets (camera, microphone, geolocation) to work from
 * the pages served by ui/, and exposes WebAppInterface as
 * window.AndroidNative for the genuinely-native vibrate/camera/biometric/
 * notification/print path.
 *
 * A native SplashScreen (see themes.xml, Theme.App.Starting) stays on
 * screen until PhpServer has actually bound its port AND the WebView has
 * finished loading — no fixed timeout, no blank-white flash while PHP boots.
 */
class MainActivity : AppCompatActivity() {

    private val requestedPermissions = buildList {
        add(Manifest.permission.CAMERA)
        add(Manifest.permission.RECORD_AUDIO)
        add(Manifest.permission.ACCESS_FINE_LOCATION)
        add(Manifest.permission.POST_NOTIFICATIONS)
        add(Manifest.permission.READ_CONTACTS)
        add(Manifest.permission.READ_CALENDAR)
        // BLUETOOTH_CONNECT/SCAN are new (API 31+) runtime permissions —
        // requesting them pre-31 would just be an unknown-permission no-op
        // on older platforms, but requestPermissions() rejects an array
        // containing a permission string the running OS doesn't define.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            add(Manifest.permission.BLUETOOTH_CONNECT)
            add(Manifest.permission.BLUETOOTH_SCAN)
        }
    }.toTypedArray()

    private lateinit var webAppInterface: WebAppInterface
    private lateinit var phpServer: PhpServer
    private lateinit var webView: WebView
    private var nfcAdapter: NfcAdapter? = null

    @Volatile
    private var serverReady = false

    @Volatile
    private var pageLoaded = false

    @Volatile
    private var port = 0

    private val takePicturePreview = registerForActivityResult(
        ActivityResultContracts.TakePicturePreview(),
    ) { bitmap -> webAppInterface.deliverPhoto(bitmap) }

    private val pickImage = registerForActivityResult(
        ActivityResultContracts.GetContent(),
    ) { uri -> webAppInterface.deliverPickedImage(uri) }

    /**
     * A WebView PermissionRequest (mic/camera via getUserMedia) held here
     * while its matching Android runtime permission is requested — see
     * onPermissionRequest()'s comment for why this exists at all.
     */
    private var pendingWebPermissionRequest: PermissionRequest? = null

    private val requestWebMediaPermissions = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions(),
    ) { results ->
        val request = pendingWebPermissionRequest
        pendingWebPermissionRequest = null
        if (request == null) return@registerForActivityResult

        if (results.values.all { it }) {
            request.grant(request.resources)
        } else {
            request.deny()
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        val splashScreen = installSplashScreen()
        super.onCreate(savedInstanceState)
        splashScreen.setKeepOnScreenCondition { !(serverReady && pageLoaded) }

        ActivityCompat.requestPermissions(this, requestedPermissions, 0)

        nfcAdapter = NfcAdapter.getDefaultAdapter(this)

        webView = WebView(this)
        webAppInterface = WebAppInterface(this, webView) { takePicturePreview.launch(null) }
            .also { it.onImagePickRequested = { pickImage.launch("image/*") } }
        webView.addJavascriptInterface(webAppInterface, "AndroidNative")

        webView.settings.javaScriptEnabled = true
        webView.settings.setGeolocationEnabled(true)
        // A visible fading scrollbar reads as "browser", not "app" — every
        // native app hides or fully customizes it. The CSS scrollbar-hiding
        // rules in ui/src/input.css only cover in-page browser testing;
        // this is what actually matters inside the WebView.
        webView.isVerticalScrollBarEnabled = false
        webView.isHorizontalScrollBarEnabled = false
        // Defensive: if the very first load races the PHP server still
        // starting up, retry instead of leaving a permanently blank WebView.
        webView.webViewClient = object : WebViewClient() {
            // Without this, a tel:/mailto:/sms: link (or a plain http(s)
            // link to a real external site) just fails silently — WebView
            // has no dialer/mail app of its own to hand those off to.
            // Engine\Launcher\ triggers the same Intent.ACTION_VIEW path
            // via WebAppInterface.launchUrl() for JS-initiated opens;
            // this covers a developer's own plain <a href="tel:...">.
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val uri = request.url
                val isOwnServer = uri.scheme in listOf("http", "https") && uri.host == "127.0.0.1" && uri.port == port

                if (isOwnServer) {
                    return false
                }

                return try {
                    this@MainActivity.startActivity(Intent(Intent.ACTION_VIEW, uri))
                    true
                } catch (_: ActivityNotFoundException) {
                    true
                }
            }

            override fun onReceivedError(
                view: WebView,
                request: WebResourceRequest,
                error: WebResourceError,
            ) {
                if (request.isForMainFrame && port != 0) {
                    Handler(Looper.getMainLooper()).postDelayed({
                        view.loadUrl("http://127.0.0.1:$port/")
                    }, 1000)
                }
            }

            override fun onPageFinished(view: WebView, url: String) {
                pageLoaded = true
            }
        }
        webView.webChromeClient = object : WebChromeClient() {
            /**
             * request.grant() alone is NOT enough for microphone/camera:
             * WebChromeClient can only grant a resource whose matching
             * Android runtime permission is ALREADY granted — it never
             * triggers the system permission dialog itself. The upfront
             * ActivityCompat.requestPermissions() call in onCreate() covers
             * the common case (user responds before ever tapping a mic/
             * camera button), but if they dismissed/denied it, or simply
             * hadn't answered yet, every later getUserMedia() call would
             * silently fail here with no recovery — confirmed as the real
             * cause of the microphone demo not working. Now requests the
             * exact permission on demand, right when the WebView actually
             * needs it, same as a real browser tab would.
             */
            override fun onPermissionRequest(request: PermissionRequest) {
                val androidPermissions = request.resources.mapNotNull {
                    when (it) {
                        PermissionRequest.RESOURCE_AUDIO_CAPTURE -> Manifest.permission.RECORD_AUDIO
                        PermissionRequest.RESOURCE_VIDEO_CAPTURE -> Manifest.permission.CAMERA
                        else -> null
                    }
                }

                val alreadyGranted = androidPermissions.isNotEmpty() && androidPermissions.all {
                    ActivityCompat.checkSelfPermission(this@MainActivity, it) == PackageManager.PERMISSION_GRANTED
                }

                if (alreadyGranted) {
                    request.grant(request.resources)
                    return
                }

                if (androidPermissions.isEmpty()) {
                    request.deny()
                    return
                }

                pendingWebPermissionRequest = request
                requestWebMediaPermissions.launch(androidPermissions.toTypedArray())
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

        setContentView(webView)

        // nav.js swaps screens via history.pushState instead of real page
        // loads, so the WebView's own back/forward stack carries those
        // entries too — without this, the hardware back button has nothing
        // to pop and falls straight through to finishing the Activity.
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        phpServer = PhpServer(this)
        Thread {
            val boundPort = phpServer.start()
            port = boundPort
            serverReady = true
            val path = deepLinkPath(intent) ?: "/"
            runOnUiThread { webView.loadUrl("http://127.0.0.1:$boundPort$path") }
        }.start()
    }

    /**
     * Fires when a deep link (or a re-tap of the launcher icon) arrives
     * while the Activity is already running — android:launchMode="singleTask"
     * (AndroidManifest.xml) routes it here instead of spawning a second
     * MainActivity instance on top of the WebView that's already showing.
     */
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)

        if (handleNfcIntent(intent)) {
            return
        }

        val path = deepLinkPath(intent) ?: return
        if (serverReady) {
            webView.loadUrl("http://127.0.0.1:$port$path")
        }
    }

    /**
     * Foreground dispatch (enabled only while the Activity is in front,
     * see onResume/onPause) routes any tag scan through onNewIntent instead
     * of launching a separate Activity — this only forwards it to JS when
     * Engine\Device\Nfc's start/stop-listening flag is on, so an app that
     * never asked for NFC never sees these intents do anything.
     */
    private fun handleNfcIntent(intent: Intent): Boolean {
        if (!webAppInterface.isNfcListening()) {
            return false
        }

        if (intent.action !in setOf(
                NfcAdapter.ACTION_NDEF_DISCOVERED,
                NfcAdapter.ACTION_TECH_DISCOVERED,
                NfcAdapter.ACTION_TAG_DISCOVERED,
            )
        ) {
            return false
        }

        @Suppress("DEPRECATION")
        val tag: Tag? = intent.getParcelableExtra(NfcAdapter.EXTRA_TAG)
        val tagId = tag?.id?.joinToString("") { String.format("%02X", it) } ?: ""

        // NDEF text records start with a status byte + language-code length
        // header before the actual text — dropping the first
        // (statusByte and 0x3F) + 1 bytes strips that header for the common
        // case (UTF-8, short language code) without pulling in a full NDEF
        // text-record parser for what is meant to be a basic read.
        val text = try {
            Ndef.get(tag)?.let { ndef ->
                ndef.connect()
                val payload = ndef.cachedNdefMessage?.records?.firstOrNull()?.payload
                ndef.close()
                payload?.let { bytes ->
                    val languageCodeLength = bytes[0].toInt() and 0x3F
                    String(bytes, 1 + languageCodeLength, bytes.size - 1 - languageCodeLength, Charsets.UTF_8)
                } ?: ""
            } ?: ""
        } catch (e: Exception) {
            ""
        }

        val json = JSONObject().apply {
            put("id", tagId)
            put("text", text)
        }.toString()

        webView.evaluateJavascript(
            "window.onNativeNfcTag && window.onNativeNfcTag(${JSONObject.quote(json)})",
            null,
        )
        return true
    }

    override fun onResume() {
        super.onResume()
        nfcAdapter?.let { adapter ->
            val intent = Intent(this, javaClass).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
            val pendingIntent = PendingIntent.getActivity(
                this,
                0,
                intent,
                PendingIntent.FLAG_MUTABLE,
            )
            adapter.enableForegroundDispatch(this, pendingIntent, null, null)
        }
    }

    override fun onPause() {
        super.onPause()
        nfcAdapter?.disableForegroundDispatch(this)
    }

    /**
     * phpnitro://<path> (e.g. phpnitro://product/42) -> "/product/42",
     * resolved by the exact same Engine\Router every normal in-app
     * navigation goes through — a deep link is just a different way to
     * arrive at an ordinary route, not a separate code path on the PHP
     * side. Only this app's own scheme is handled; anything else (a stray
     * VIEW intent with unexpected data) is ignored rather than guessed at.
     *
     * uri.host, not uri.path, holds the first path segment: standard
     * scheme://authority/path URI parsing treats whatever comes right after
     * "://" up to the next "/" as the authority (host), not part of the
     * path — confirmed live (phpnitro://settings landed on host="settings",
     * path="", which a naive uri.path-only read silently treated as "/" and
     * opened the home screen instead of Réglages). Host and path are
     * concatenated back into one route instead.
     */
    private fun deepLinkPath(intent: Intent?): String? {
        val uri: Uri = intent?.data ?: return null
        if (uri.scheme != "phpnitro") return null

        val path = "/${uri.host.orEmpty()}${uri.path.orEmpty()}"
        return if (path == "/") "/" else path
    }

    override fun onDestroy() {
        super.onDestroy()
        phpServer.stop()
    }
}
