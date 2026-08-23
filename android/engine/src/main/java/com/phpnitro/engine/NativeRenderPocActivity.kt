package com.phpnitro.engine

import android.app.AlertDialog
import android.app.DatePickerDialog
import android.app.TimePickerDialog
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.graphics.RectF
import android.graphics.Typeface
import android.graphics.drawable.ColorDrawable
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.text.Editable
import android.text.InputType
import android.text.TextWatcher
import android.util.Log
import android.util.TypedValue
import android.view.Gravity
import android.view.ViewGroup
import android.view.inputmethod.InputMethodManager
import android.widget.EditText
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.common.InputImage
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.util.Calendar
import kotlin.concurrent.thread
import org.json.JSONObject

/**
 * Started life as a Phase 0 proof of concept, adb-launched only. As of
 * phase 7 it's also reachable from the real app UI — SettingsPage.php's
 * "Essayer le rendu natif" button, gated behind a Preferences flag —
 * via WebAppInterface.openNativeRenderPreview(). Still adb-launchable
 * directly too: `adb shell am start -n
 * com.mobile.engine/.NativeRenderPocActivity`.
 *
 * Starts its own PhpServer instance rather than reusing MainActivity's
 * (simpler, fully isolated — nothing about the existing WebView-based app
 * changes or risks regressing while this is built out in parallel),
 * fetches /native/layout-demo's draw commands over plain HTTP from that
 * embedded PHP process, and hands them to NativeCanvasView — the whole
 * point being to prove PHP can drive a real native Canvas paint with zero
 * WebView involved anywhere in this Activity.
 *
 * Navigation: a hit region's action starting with "navigate:" (e.g.
 * "navigate:otp", or "navigate:product?id=42" for one or more route
 * params, "navigate:product?id=42&tab=reviews" for several — mirrors
 * ProductPage.php's '/product/{id}') pushes that token onto a local back
 * stack and re-fetches — this Activity is what owns "which screen is
 * current", not PHP (each /native/layout-demo request is a stateless
 * render of whichever ?screen=&id=&tab=... it's given, exactly the plain
 * $_GET a screen's build() already reads). Plain "back" — or the
 * hardware back button, via the OnBackPressedCallback below — pops the
 * stack.
 *
 * Text input: "focus:name" (or "focus:secure:name" for a password field)
 * overlays a real android.widget.EditText at the tapped field's exact
 * rect — see showTextInput(). Typed values are tracked client-side in
 * fieldValues and only sent to PHP when a "submit:action" fires, which
 * collects every field and appends them as query params before doing the
 * normal round-trip with the action name stripped of its "submit:"
 * prefix. A "redirect" field in the JSON response (LoginPage.php's
 * onLogin() returning a path, translated to this architecture) swaps the
 * stack's current entry and re-fetches instead of rendering what came
 * back — see applyResponse().
 */
class NativeRenderPocActivity : AppCompatActivity() {

    private lateinit var phpServer: PhpServer
    private lateinit var canvasView: NativeCanvasView
    private lateinit var rootLayout: FrameLayout
    private var serverPort: Int = 0
    // "127.0.0.1" (embedded PhpServer, the default) unless a "serverHost"
    // intent extra says otherwise — see onCreate()'s remote-mode branch.
    // PhpNitro Go (android/go/) is the only caller that ever sets this: a
    // companion app with no bundled project code at all, that talks to a
    // `phpx serve` dev server over the LAN instead of an embedded php-cli
    // process, reusing this exact same rendering pipeline unmodified.
    private var serverHost: String = "127.0.0.1"
    // Null in remote mode (PhpNitro Go) — the LAN `phpx serve` this talks
    // to then has no idea what this token even is, see PhpServer.kt's own
    // accessToken docblock for why that's fine (a documented, accepted,
    // different threat model). Set right after the embedded PhpServer
    // starts, read by every fetchDrawCommands() call from then on.
    private var accessToken: String? = null
    private val screenStack = mutableListOf<String>()
    private val fieldValues = mutableMapOf<String, String>()
    private var activeEditText: EditText? = null
    private val deviceBridge by lazy { NativeDeviceBridge(this) }
    private var firstScreenRendered = false
    // Built lazily the first time a fetch fails outright (server
    // unreachable, wrong network, dev server not running) — before this,
    // that failure just left the splash screen up forever with zero
    // feedback (splash's keepOnScreenCondition never saw
    // firstScreenRendered flip true), which is what an all-black screen
    // actually was: not a rendering bug, an invisible failure.
    private var connectionErrorView: android.view.View? = null
    private var connectionErrorMessage: TextView? = null
    // Which OAuthProvider (see NativeDeviceBridge.signInWithGoogle()'s
    // sibling startOAuthFlow()) currently has a Custom Tab open — read by
    // handleOAuthCallback() once the redirect lands back in onNewIntent().
    private var pendingOAuthProvider: String? = null
    // ConfettiView overlay — see showConfettiOverlay(). Tracked so a
    // second burst (another render with confetti:true, or a manual
    // replay tap) before the first one finishes tears down the old view
    // instead of stacking a second one on top of it.
    private var activeConfettiView: ConfettiView? = null
    private var activeSnackbarView: android.view.View? = null
    // Canvas::stableHash() of the last response actually applied —
    // sent back as lastHash= on the next same-screen refetch so PHP can
    // reply {"unchanged":true} instead of the whole payload when nothing
    // visible would change. Reset to null whenever a real navigation
    // happens (see refetch()'s isNavigation branches) so a fresh screen
    // never risks matching a stale hash from wherever the user was before.
    var lastAppliedHash: String? = null
    // Splash's timed self-navigation — a single handler reused across
    // screens so a fresh scheduleAutoNavigate() call can always cancel
    // whatever the previous screen queued via the same instance.
    private val autoNavigateHandler = Handler(Looper.getMainLooper())

    // Same push-based (not poll-based) NFC model as MainActivity's —
    // nfcListening is the flag onNewIntent() checks before treating an
    // incoming intent as a tag scan, foreground dispatch registered in
    // onResume()/torn down in onPause() so a scan only ever reaches this
    // Activity while it's actually in front.
    private var nfcAdapter: android.nfc.NfcAdapter? = null
    private var nfcListening = false

    // Must be registered before onStart (ActivityResultRegistry's own
    // contract), same as MainActivity's identical launchers — can't be
    // lazily created inside NativeDeviceBridge on first tap, it would
    // already be too late.
    // Reports a short status, not the actual image data — a captured/
    // picked photo's base64 payload (tens of KB) would blow past the
    // query-string channel every other "$_GET['x_out'] carries a result"
    // capability uses to report back to PHP. Same "prove it works, don't
    // over-engineer a preview" pragmatism as contactsCount() reporting a
    // count instead of the actual contacts.
    private val takePicturePreview = registerForActivityResult(ActivityResultContracts.TakePicturePreview()) { bitmap ->
        fieldValues["photo_out"] = if (bitmap == null) "Annulé" else "Photo capturée (${bitmap.width}x${bitmap.height})"
        refetch(action = null, includeFields = true)
    }

    private val pickImage = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri == null) {
            fieldValues["picked_image_out"] = "Annulé"
        } else {
            val bytes = contentResolver.openInputStream(uri)?.use { it.readBytes() }
            fieldValues["picked_image_out"] = if (bytes == null) "Erreur" else "Image sélectionnée (${bytes.size} octets)"
        }
        refetch(action = null, includeFields = true)
    }

    // RECORD_AUDIO is a dangerous permission (Android 6+) — declaring it
    // in the manifest alone doesn't grant it, a real runtime prompt is
    // required. deviceBridge.recordAudioClip() already checked
    // checkSelfPermission() and correctly reported "permission_denied"
    // when missing, but nothing ever actually ASKED for it — the
    // manifest itself had no <uses-permission> line either, so this was
    // 100% broken on every real device before this. pendingMicToken
    // replays the original "mic:field:durationMs" action once the user
    // answers the prompt, so a screen never needs its own permission
    // dance — same "just call the action, the round-trip handles it"
    // promise every other capability here already makes.
    private var pendingMicToken: String? = null

    private val requestRecordAudioPermission = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        val token = pendingMicToken
        pendingMicToken = null
        if (token == null) return@registerForActivityResult
        if (granted) {
            startMicRecording(token)
        } else {
            val parts = token.split(":")
            fieldValues[parts.getOrElse(1) { "mic_out" }] = "permission_denied"
            refetch(action = null, includeFields = true)
        }
    }

    private fun startMicRecording(token: String) {
        val parts = token.split(":")
        val durationMs = parts.getOrNull(2)?.toLongOrNull() ?: 2000L
        deviceBridge.recordAudioClip(durationMs) { _, error ->
            fieldValues[parts.getOrElse(1) { "mic_out" }] = if (error != null) error else "Enregistré (${durationMs}ms)"
            refetch(action = null, includeFields = true)
        }
    }

    // A generic, standalone counterpart to the "mic" action's own
    // one-off permission dance above — "permission:<key>:<outputField>"
    // checks (and if needed, prompts for) any ONE of a small whitelisted
    // set of dangerous permissions this app already declares in its own
    // AndroidManifest.xml, and reports back "granted"/"denied"/
    // "unknown_permission" the same fieldValues+refetch way every other
    // capability here does. A screen that wants to gate some OTHER
    // feature on a permission (before wiring that feature's own action)
    // can check/ask up front with this instead of copying "mic"'s whole
    // pendingToken+launcher dance for itself — this is that dance, done
    // once, reusable. Deliberately a fixed whitelist, not "request
    // whatever string PHP sends": a typo'd or made-up permission name
    // from a screen would otherwise either silently no-op or (worse)
    // successfully request a permission this app's manifest never
    // declared, which throws at the OS level with a confusing message
    // far from where the typo actually is.
    private val permissionKeys = mapOf(
        "camera" to android.Manifest.permission.CAMERA,
        "microphone" to android.Manifest.permission.RECORD_AUDIO,
        "location" to android.Manifest.permission.ACCESS_FINE_LOCATION,
        "coarse_location" to android.Manifest.permission.ACCESS_COARSE_LOCATION,
        "contacts" to android.Manifest.permission.READ_CONTACTS,
        "calendar" to android.Manifest.permission.READ_CALENDAR,
        "notifications" to android.Manifest.permission.POST_NOTIFICATIONS,
        "bluetooth" to android.Manifest.permission.BLUETOOTH_CONNECT,
    )

    private var pendingPermissionToken: String? = null

    private val requestGenericPermission = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        val token = pendingPermissionToken
        pendingPermissionToken = null
        if (token == null) return@registerForActivityResult
        val parts = token.split(":")
        fieldValues[parts.getOrElse(2) { "permission_out" }] = if (granted) "granted" else "denied"
        refetch(action = null, includeFields = true)
    }

    // Decodes a QR/barcode from a single still, not a live-scanning
    // preview — see build.gradle.kts's own comment on the
    // barcode-scanning dependency for why that's the honest scope here
    // (no persistent camera preview surface on this native Canvas-only
    // path). A SEPARATE TakePicturePreview launcher from the plain
    // "camera" action's — that one's callback is hardcoded to
    // fieldValues["photo_out"], reusing it here would either clobber
    // that field or need every camera call site to somehow know which
    // purpose this particular tap was for.
    private var pendingQrOutputField: String? = null

    private val scanQrPicture = registerForActivityResult(ActivityResultContracts.TakePicturePreview()) { bitmap ->
        val outputField = pendingQrOutputField ?: "qr_out"
        pendingQrOutputField = null
        if (bitmap == null) {
            fieldValues[outputField] = "Annulé"
            refetch(action = null, includeFields = true)
            return@registerForActivityResult
        }
        BarcodeScanning.getClient().process(InputImage.fromBitmap(bitmap, 0))
            .addOnSuccessListener { barcodes ->
                fieldValues[outputField] = barcodes.firstOrNull()?.rawValue ?: "Aucun code détecté"
                refetch(action = null, includeFields = true)
            }
            .addOnFailureListener { e ->
                fieldValues[outputField] = e.message ?: "Erreur de décodage"
                refetch(action = null, includeFields = true)
            }
    }

    // Engine\Device\FileSelector — picks any file type (unlike pickImage's
    // "image/*"), reports back the display name only (see FileSelector's
    // own docblock for why not the bytes).
    private var pendingFileOutputField: String? = null

    private val pickFile = registerForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        val outputField = pendingFileOutputField ?: "file_out"
        pendingFileOutputField = null
        if (uri == null) {
            fieldValues[outputField] = "Annulé"
        } else {
            var name = uri.lastPathSegment ?: "fichier"
            contentResolver.query(uri, null, null, null, null)?.use { cursor ->
                val nameIndex = cursor.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME)
                if (nameIndex >= 0 && cursor.moveToFirst()) name = cursor.getString(nameIndex)
            }
            fieldValues[outputField] = name
        }
        refetch(action = null, includeFields = true)
    }

    // Engine\Device\ImageCropper — uri=null lets the cropper library
    // itself prompt for a source image (gallery/camera chooser) before
    // showing the crop UI, so a screen only needs to fire one action for
    // the whole "pick then crop" flow, not two.
    private var pendingCropOutputField: String? = null

    private val cropImage = registerForActivityResult(com.canhub.cropper.CropImageContract()) { result ->
        val outputField = pendingCropOutputField ?: "crop_out"
        pendingCropOutputField = null
        fieldValues[outputField] = when {
            result.isSuccessful && result.uriContent != null -> "Image recadrée"
            result.isSuccessful -> "Erreur"
            else -> "Annulé"
        }
        refetch(action = null, includeFields = true)
    }

    // Engine\Device\InAppUpdate — startUpdateFlowForResult's own launcher;
    // the flow's UI is entirely Play's, this callback just exists because
    // the KTX API requires a registered ActivityResultLauncher, there's
    // nothing of ours to do with its result.
    private val appUpdateLauncher = registerForActivityResult(ActivityResultContracts.StartIntentSenderForResult()) {}

    // Engine\Device\WebSocket — bound lazily on the FIRST "device:wsconnect"
    // (not in onCreate()) so an app that never uses WebSocket never starts
    // the foreground service or shows its persistent notification. Started
    // AND bound (see WebSocketService's own docblock for why both) — this
    // Activity being destroyed/recreated (rotation, process death) does
    // NOT stop the connection, only an explicit "device:wsdisconnect" does.
    private var webSocketService: WebSocketService? = null
    private var webSocketBound = false
    private var pendingWsConnect: Pair<String, String>? = null

    private val webSocketConnection = object : android.content.ServiceConnection {
        override fun onServiceConnected(name: android.content.ComponentName, binder: android.os.IBinder) {
            val service = (binder as WebSocketService.LocalBinder).service()
            webSocketService = service
            service.setListener { message ->
                fieldValues[service.currentOutputField()] = message
                refetch(action = null, includeFields = true)
            }
            pendingWsConnect?.let { (url, outputField) ->
                service.connect(url, outputField)
                pendingWsConnect = null
            }
            // Replays whatever arrived while THIS Activity instance didn't
            // exist yet (backgrounded and recreated, or a fresh instance
            // binding to an already-running service) — a plain refetch
            // (not from a tap) so the screen reflects it without the user
            // needing to do anything.
            service.lastMessage?.let { message ->
                fieldValues[service.currentOutputField()] = message
                refetch(action = null, includeFields = true)
            }
        }

        override fun onServiceDisconnected(name: android.content.ComponentName) {
            webSocketService = null
        }
    }

    private fun ensureWebSocketServiceBound() {
        if (webSocketBound) return
        webSocketBound = true
        val intent = Intent(this, WebSocketService::class.java)
        ContextCompat.startForegroundService(this, intent)
        bindService(intent, webSocketConnection, Context.BIND_AUTO_CREATE)
    }

    private fun handlePermissionAction(token: String) {
        val parts = token.split(":")
        val outputField = parts.getOrElse(2) { "permission_out" }
        val permission = permissionKeys[parts.getOrNull(1)]
        if (permission == null) {
            fieldValues[outputField] = "unknown_permission"
            refetch(action = null, includeFields = true)
            return
        }
        if (ContextCompat.checkSelfPermission(this, permission) == PackageManager.PERMISSION_GRANTED) {
            fieldValues[outputField] = "granted"
            refetch(action = null, includeFields = true)
        } else {
            pendingPermissionToken = token
            requestGenericPermission.launch(permission)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        // Same native SplashScreen MainActivity uses (Theme.App.Starting,
        // themes.xml) — stays up exactly until the PHP server is bound and
        // the first screen has actually rendered, now that this Activity
        // is the app's real launcher (see AndroidManifest.xml's
        // MainActivityDefault/Alt aliases) rather than an adb-only preview.
        val splashScreen = installSplashScreen()
        super.onCreate(savedInstanceState)
        splashScreen.setKeepOnScreenCondition { serverPort == 0 || !firstScreenRendered }

        // As early as possible — anything that throws further down in this
        // very method still gets caught and persisted, not just crashes
        // once the UI is up.
        CrashReporter.install(this)

        // Without this, every fetchDrawCommands() request gets a BRAND NEW
        // PHPSESSID — java.net.HttpURLConnection never sends/stores cookies
        // on its own, and nothing else in this app ever installed a
        // CookieHandler. $_SESSION (auth_user, the stepper's step/data,
        // Dismissible/Reorderable's demo state, anything a
        // screen persists server-side) silently never actually persisted
        // across taps — caught only by testing on a real device, since
        // every curl-based verification this project's history used its
        // own -c/-b cookie jar and never exercised this path. One
        // process-wide CookieManager fixes it for every HttpURLConnection
        // this Activity ever makes, no per-call plumbing needed.
        // PersistentCookieStore (not CookieManager's own in-memory default)
        // so PHPSESSID — and therefore every $_SESSION value above — also
        // survives Android killing the whole app process while
        // backgrounded, not just a tap-to-tap sequence within one process
        // lifetime. See its own docblock.
        if (java.net.CookieHandler.getDefault() == null) {
            java.net.CookieHandler.setDefault(java.net.CookieManager(PersistentCookieStore(applicationContext), java.net.CookiePolicy.ACCEPT_ALL))
        }

        // osmdroid's tile server ToS requires a real user agent — the
        // package name identifies which app is pulling tiles, same as
        // any other OSM client is expected to set.
        org.osmdroid.config.Configuration.getInstance().userAgentValue = packageName

        // savedInstanceState carries screenStack back across a process
        // death Android chose to recover from (see onSaveInstanceState) —
        // only fall back to the intent's own screen (a fresh launch, or a
        // process death Android didn't attempt to recover) when there's
        // nothing to restore.
        savedInstanceState?.getStringArrayList(STATE_SCREEN_STACK)?.let { screenStack.addAll(it) }
        if (screenStack.isEmpty()) {
            screenStack.add(deepLinkScreenToken(intent) ?: intent.getStringExtra("screen") ?: "home")
        }

        canvasView = NativeCanvasView(this)
        canvasView.density = resources.displayMetrics.density
        canvasView.onAction = { action, regionDp, meta -> onTap(action, regionDp, meta) }
        // LazyList screens: scrolled near the edge of the currently
        // loaded window, re-fetch with the new scrollY so PHP can build
        // the next one. No screen state changes (action stays null), same
        // idiom as a plain re-render with the field values already held.
        canvasView.onScrollFollow = { refetch(action = null, includeFields = true) }
        registerCustomCommandHandlers()

        rootLayout = FrameLayout(this)
        rootLayout.addView(canvasView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        setContentView(rootLayout)

        if (isDebuggable()) setupDevTools()

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (screenStack.size > 1) {
                    screenStack.removeAt(screenStack.size - 1)
                    clearTextInput()
                    refetch(action = null, isNavigation = true)
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        nfcAdapter = android.nfc.NfcAdapter.getDefaultAdapter(this)

        val remoteHost = intent.getStringExtra("serverHost")
        if (remoteHost != null) {
            // Remote mode (PhpNitro Go): the dev server already exists
            // somewhere else on the LAN — no local php-cli process to
            // start at all. serverPort is set directly (normally
            // PhpServer.start()'s return value does this once the
            // embedded server is actually listening) so the splash
            // screen's `serverPort == 0` gate still resolves correctly.
            serverHost = remoteHost
            serverPort = intent.getIntExtra("serverPort", 0)
            Log.i(TAG, "Remote mode: $serverHost:$serverPort")
            refetch(action = null, isNavigation = true)
        } else {
            phpServer = PhpServer(this)
            accessToken = phpServer.accessToken
            thread {
                val port = phpServer.start()
                serverPort = port
                Log.i(TAG, "PhpServer started on port $port")
                refetch(action = null, isNavigation = true)
            }
        }
    }

    // A hit region's action fired — same round-trip shape as nav.js's
    // phpxNav.submitAction() in the HTML pipeline (tell PHP what happened,
    // get back whatever should be on screen now), just fetching a fresh
    // draw-command list instead of swapping innerHTML. "navigate:X",
    // "back", "focus:X" and "submit:X" are all intercepted here rather
    // than sent to PHP verbatim — they're this Activity's concern (which
    // screen is current, whether the keyboard is showing), not a
    // server-side state change in their own right.
    private fun onTap(action: String, regionDp: RectF, meta: JSONObject?) {
        if (inspectMode) {
            inspectMode = false
            inspectBadgeView?.alpha = 0.5f
            android.app.AlertDialog.Builder(this)
                .setTitle("🔍 Widget inspecté")
                .setMessage(
                    "action: $action\n" +
                        "bounds (dp): x=${"%.1f".format(regionDp.left)} y=${"%.1f".format(regionDp.top)} " +
                        "w=${"%.1f".format(regionDp.width())} h=${"%.1f".format(regionDp.height())}\n" +
                        "meta: ${meta?.toString() ?: "—"}",
                )
                .setPositiveButton("OK", null)
                .show()
            return
        }

        when {
            action.startsWith("focus:") -> {
                var rest = action.removePrefix("focus:")
                val multiline = rest.startsWith("multiline:")
                if (multiline) rest = rest.removePrefix("multiline:")
                val secure = rest.startsWith("secure:")
                val fieldName = if (secure) rest.removePrefix("secure:") else rest
                showTextInput(fieldName, regionDp, secure, multiline)
            }
            action.startsWith("submit:") -> {
                clearTextInput()
                refetch(action.removePrefix("submit:"), includeFields = true)
            }
            action.startsWith("device:") -> handleDeviceAction(action.removePrefix("device:"))
            action.startsWith("webview:") -> deviceBridge.openWebView(action.removePrefix("webview:"))
            action.startsWith("media:play:") -> {
                deviceBridge.playAudio(action.removePrefix("media:play:"))
                fieldValues["audio_state"] = "playing"
                refetch(action = null, includeFields = true)
            }
            action == "media:pause" -> {
                deviceBridge.pauseAudio()
                fieldValues["audio_state"] = "paused"
                refetch(action = null, includeFields = true)
            }
            action.startsWith("video:play:") -> showVideoOverlay(action.removePrefix("video:play:"), regionDp)
            action.startsWith("youtube:play:") -> showYoutubeOverlay(action.removePrefix("youtube:play:"), regionDp)
            action.startsWith("map:open:") -> {
                val parts = action.removePrefix("map:open:").split(":")
                val lat = parts.getOrNull(0)?.toDoubleOrNull() ?: 48.8566
                val lon = parts.getOrNull(1)?.toDoubleOrNull() ?: 2.3522
                val zoom = parts.getOrNull(2)?.toIntOrNull() ?: 14
                showMapOverlay(lat, lon, zoom, regionDp)
            }
            action.startsWith("translate:") -> {
                val targetLanguage = action.removePrefix("translate:")
                val text = meta?.optString("text") ?: ""
                deviceBridge.translateText(text, "fr", targetLanguage) { translated ->
                    fieldValues["translate_out"] = translated
                    refetch(action = null, includeFields = true)
                }
            }
            // GitHub/Facebook/Microsoft/Apple — public/index.php already
            // built the full authorize URL (client_id, scope, state)
            // server-side via {Provider}SignIn::authorizeUrl(); this side
            // never sees a client secret. pendingOAuthProvider is what
            // lets handleOAuthCallback() (onNewIntent()) know which
            // "oauth_callback:" action to fire once the redirect lands —
            // only one OAuth flow can realistically be in flight at a
            // time (the Custom Tab has focus), so a single field is
            // enough, no need for a map.
            action.startsWith("oauth:") -> {
                val provider = action.removePrefix("oauth:")
                val authorizeUrl = meta?.optString("url")
                if (authorizeUrl.isNullOrEmpty()) {
                    // public/index.php only omits "url" from this
                    // button's meta when that provider's client_id/secret
                    // aren't configured in .env — same "fail
                    // informatively before opening anything" pre-flight
                    // check googlesignin's webClientId.isBlank() does,
                    // just decided server-side instead of via a string
                    // resource, since the client_id itself lives there.
                    fieldValues["oauth_error"] = "Connexion $provider non configurée (client_id manquant côté serveur)."
                    refetch(action = null, includeFields = true)
                } else {
                    pendingOAuthProvider = provider
                    deviceBridge.startOAuthFlow(authorizeUrl)
                }
            }
            action.startsWith("select:") -> showSelectDialog(action.removePrefix("select:"), meta)
            action.startsWith("datepicker:") -> showDatePickerDialog(action.removePrefix("datepicker:"), meta)
            action.startsWith("timepicker:") -> showTimePickerDialog(action.removePrefix("timepicker:"), meta)
            action.startsWith("toggle:") -> {
                fieldValues[action.removePrefix("toggle:")] = meta?.optString("next", "") ?: ""
                refetch(action = null, includeFields = true)
            }
            // ClientTabs — the tab selection lives entirely in
            // NativeCanvasView's own clientTabState, never PHP/session
            // state, so switching tabs never touches the network at all
            // (every panel's content already arrived in this same
            // response). See Canvas::clientTabPanel().
            action.startsWith("clientTab:") -> {
                val (key, index) = action.removePrefix("clientTab:").split(":", limit = 2)
                canvasView.setClientTab(key, index.toInt())
            }
            action == "dialog:alert" -> showAlertDialog(meta)
            action == "dialog:confirm" -> showConfirmDialog(meta)
            action.startsWith("navigate:") -> {
                clearTextInput()
                screenStack.add(action.removePrefix("navigate:"))
                refetch(action = null, isNavigation = true)
            }
            // A BottomNavigation tab switch — resets the whole stack
            // to that one screen instead of pushing, so hopping between
            // tabs repeatedly doesn't grow an ever-longer back stack the
            // way drilling into a real detail screen should.
            action.startsWith("tab:") -> {
                clearTextInput()
                screenStack.clear()
                screenStack.add(action.removePrefix("tab:"))
                refetch(action = null, isNavigation = true)
            }
            action == "back" -> {
                clearTextInput()
                if (screenStack.size > 1) screenStack.removeAt(screenStack.size - 1)
                refetch(action = null, isNavigation = true)
            }
            else -> refetch(action)
        }
    }

    // The options/message/title a select box or dialog needs travel in the
    // hit region's meta (see Canvas::hitRegion()'s $meta param) — no
    // second round-trip to PHP is needed just to know what to show. A pick
    // is tracked the same way TextField's typed value is: written
    // into fieldValues and only read by PHP on the next refetch.
    private fun showSelectDialog(name: String, meta: JSONObject?) {
        val options = meta?.optJSONObject("options") ?: return
        val values = mutableListOf<String>()
        val labels = mutableListOf<String>()
        options.keys().forEach { key ->
            values.add(key)
            labels.add(options.getString(key))
        }
        AlertDialog.Builder(this)
            .setItems(labels.toTypedArray()) { _, which ->
                fieldValues[name] = values[which]
                refetch(action = null, includeFields = true)
            }
            .show()
    }

    private fun showDatePickerDialog(name: String, meta: JSONObject?) {
        val calendar = Calendar.getInstance()
        val existing = meta?.optString("value", "") ?: ""
        if (existing.isNotEmpty()) {
            runCatching {
                val (year, month, day) = existing.split("-").map { it.toInt() }
                calendar.set(year, month - 1, day)
            }
        }
        DatePickerDialog(
            this,
            { _, year, month, day ->
                fieldValues[name] = "%04d-%02d-%02d".format(year, month + 1, day)
                refetch(action = null, includeFields = true)
            },
            calendar.get(Calendar.YEAR),
            calendar.get(Calendar.MONTH),
            calendar.get(Calendar.DAY_OF_MONTH),
        ).show()
    }

    private fun showTimePickerDialog(name: String, meta: JSONObject?) {
        val calendar = Calendar.getInstance()
        val existing = meta?.optString("value", "") ?: ""
        if (existing.isNotEmpty()) {
            runCatching {
                val (hour, minute) = existing.split(":").map { it.toInt() }
                calendar.set(Calendar.HOUR_OF_DAY, hour)
                calendar.set(Calendar.MINUTE, minute)
            }
        }
        TimePickerDialog(
            this,
            { _, hourOfDay, minute ->
                fieldValues[name] = "%02d:%02d".format(hourOfDay, minute)
                refetch(action = null, includeFields = true)
            },
            calendar.get(Calendar.HOUR_OF_DAY),
            calendar.get(Calendar.MINUTE),
            true,
        ).show()
    }

    // A real system dialog instead of a WebView hosting phpxDialogs.alert()'s
    // JS confirm() shim — what AlertButton exists to get for a native
    // app. No server round-trip needed, the message/title already travelled
    // in meta.
    private fun showAlertDialog(meta: JSONObject?) {
        showStyledDialog(
            title = meta?.optString("title", "")?.ifEmpty { null },
            message = meta?.optString("message", "") ?: "",
            negativeLabel = null,
            positiveLabel = "OK",
            positiveIsDanger = false,
            onPositive = {},
        )
    }

    // Same "don't call the server until confirmed" guarantee
    // Engine\Dialogs\ConfirmButton's JS callback gives — confirmAction only
    // reaches refetch() if the user actually taps the positive button.
    private fun showConfirmDialog(meta: JSONObject?) {
        val confirmAction = meta?.optString("confirmAction")
        if (confirmAction.isNullOrEmpty()) return

        showStyledDialog(
            title = meta.optString("title", "").ifEmpty { null },
            message = meta.optString("message", ""),
            negativeLabel = "Annuler",
            positiveLabel = meta.optString("label", "Confirmer"),
            positiveIsDanger = true,
            onPositive = { refetch(confirmAction, includeFields = true) },
        )
    }

    @Volatile
    private var cachedDialogTypefaceRegular: Typeface? = null

    @Volatile
    private var cachedDialogTypefaceBold: Typeface? = null

    private fun dialogTypeface(bold: Boolean): Typeface {
        val regular = cachedDialogTypefaceRegular
            ?: Typeface.createFromAsset(assets, "fonts/Roboto-Regular.ttf").also { cachedDialogTypefaceRegular = it }
        if (!bold) return regular
        return cachedDialogTypefaceBold ?: Typeface.create(regular, Typeface.BOLD).also { cachedDialogTypefaceBold = it }
    }

    /**
     * A rounded white card (Tokens::RADIUS_LG, Tokens::SPACE_XL padding)
     * with pill-shaped buttons matching Button's own shape/colors
     * (Tokens::ink() for a plain confirmation, Tokens::danger() for a
     * destructive one) — the stock AlertDialog chrome this replaced was
     * the one place in the app that still looked like generic Android UI
     * instead of PhpNitro's own Canvas-drawn design language. Still a
     * real android.app.AlertDialog underneath (back-button dismiss,
     * outside-tap dismiss, focus handling all still work) — only
     * `.setView()` + a transparent window background changed.
     */
    private fun showStyledDialog(
        title: String?,
        message: String,
        negativeLabel: String?,
        positiveLabel: String,
        positiveIsDanger: Boolean,
        onPositive: () -> Unit,
    ) {
        val density = resources.displayMetrics.density
        fun dp(value: Float) = (value * density).toInt()

        val dialog = AlertDialog.Builder(this).create()

        val card = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(20f), dp(20f), dp(20f), dp(20f))
            background = GradientDrawable().apply {
                setColor(Color.WHITE)
                cornerRadius = dp(18f).toFloat()
            }
        }

        if (title != null) {
            card.addView(TextView(this).apply {
                text = title
                setTextColor(Color.parseColor("#111827"))
                textSize = 19f
                typeface = dialogTypeface(bold = true)
            })
        }

        card.addView(TextView(this).apply {
            text = message
            setTextColor(Color.parseColor("#6B7280"))
            textSize = 15f
            typeface = dialogTypeface(bold = false)
            setLineSpacing(dp(2f).toFloat(), 1f)
        }, LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply {
            topMargin = if (title != null) dp(8f) else 0
        })

        val buttonRow = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.END
        }

        fun pillButton(label: String, filled: Boolean, backgroundColor: Int, textColor: Int, onClick: () -> Unit): TextView {
            return TextView(this).apply {
                text = label
                setTextColor(textColor)
                textSize = 15f
                typeface = dialogTypeface(bold = true)
                gravity = Gravity.CENTER
                setPadding(dp(20f), dp(10f), dp(20f), dp(10f))
                isClickable = true
                isFocusable = true
                val outValue = TypedValue()
                theme.resolveAttribute(android.R.attr.selectableItemBackgroundBorderless, outValue, true)
                foreground = androidx.core.content.ContextCompat.getDrawable(this@NativeRenderPocActivity, outValue.resourceId)
                if (filled) {
                    background = GradientDrawable().apply {
                        setColor(backgroundColor)
                        cornerRadius = dp(999f).toFloat()
                    }
                }
                setOnClickListener {
                    dialog.dismiss()
                    onClick()
                }
            }
        }

        if (negativeLabel != null) {
            buttonRow.addView(pillButton(negativeLabel, filled = false, backgroundColor = 0, textColor = Color.parseColor("#6B7280")) {})
        }
        buttonRow.addView(
            pillButton(
                positiveLabel,
                filled = true,
                backgroundColor = Color.parseColor(if (positiveIsDanger) "#DC2626" else "#111827"),
                textColor = Color.WHITE,
                onClick = onPositive,
            ),
            LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply {
                marginStart = if (negativeLabel != null) dp(12f) else 0
            },
        )

        card.addView(buttonRow, LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply {
            topMargin = dp(20f)
        })

        dialog.setView(card)
        dialog.window?.setBackgroundDrawable(ColorDrawable(Color.TRANSPARENT))
        dialog.show()
    }

    // "device:X" calls straight into NativeDeviceBridge — no PHP
    // round-trip for the call itself, this Activity has direct Android
    // API access same as WebAppInterface.kt does for the WebView path.
    // Capabilities that need to show a result (battery/deviceid) stash it
    // in fieldValues under the given output-field name — same mechanism
    // TextField uses, reusing "a value PHP reads via $_GET on the
    // next request" rather than inventing a second channel — and refetch
    // so the current screen re-renders with it. Fire-and-forget ones
    // (vibrate/torch) have no visible state in the screen, so no
    // round-trip at all.
    private fun handleDeviceAction(token: String) {
        val parts = token.split(":")
        when (parts.getOrNull(0)) {
            "vibrate" -> deviceBridge.vibrate(parts.getOrNull(1)?.toLongOrNull() ?: 200)
            "torch" -> {
                fieldValues[parts.getOrElse(1) { "torch_out" }] = if (deviceBridge.toggleTorch()) "on" else "off"
                refetch(action = null, includeFields = true)
            }
            // See CrashReporter — a real user's actual path to a
            // developer's inbox for a crash that already happened,
            // regardless of build type (no debug gate, unlike the dev
            // tools overlay).
            "report_crash" -> deviceBridge.share(CrashReporter.formatForSharing(this), "Rapport de bug PhpNitro")
            "battery" -> {
                fieldValues[parts.getOrElse(1) { "battery_out" }] = "${deviceBridge.batteryLevel()}%"
                refetch(action = null, includeFields = true)
            }
            "deviceid" -> {
                fieldValues[parts.getOrElse(1) { "device_id_out" }] = deviceBridge.deviceId()
                refetch(action = null, includeFields = true)
            }
            "bluetooth" -> {
                fieldValues[parts.getOrElse(1) { "bt_out" }] = deviceBridge.bluetoothState()
                refetch(action = null, includeFields = true)
            }
            "securestore" -> {
                val key = java.net.URLDecoder.decode(parts.getOrElse(1) { "demo_key" }, "UTF-8")
                val value = java.net.URLDecoder.decode(parts.getOrElse(2) { "" }, "UTF-8")
                deviceBridge.secureStore(key, value)
            }
            "secureretrieve" -> {
                val key = java.net.URLDecoder.decode(parts.getOrElse(1) { "demo_key" }, "UTF-8")
                fieldValues[parts.getOrElse(2) { "secure_out" }] = deviceBridge.secureRetrieve(key)
                refetch(action = null, includeFields = true)
            }
            "contacts" -> {
                val count = deviceBridge.contactsCount()
                fieldValues[parts.getOrElse(1) { "contacts_out" }] = if (count < 0) "Permission requise" else "$count contacts"
                refetch(action = null, includeFields = true)
            }
            "calendar" -> {
                val count = deviceBridge.upcomingEventsCount()
                fieldValues[parts.getOrElse(1) { "calendar_out" }] = if (count < 0) "Permission requise" else "$count événements"
                refetch(action = null, includeFields = true)
            }
            "sound" -> {
                val url = parts.getOrNull(1)?.let { java.net.URLDecoder.decode(it, "UTF-8") }
                    ?: "http://$serverHost:$serverPort/assets/audio/beep.wav"
                deviceBridge.playSound(url)
            }
            "notify" -> {
                val title = java.net.URLDecoder.decode(parts.getOrElse(1) { "PhpNitro" }, "UTF-8")
                val message = java.net.URLDecoder.decode(parts.getOrElse(2) { "Ceci est une notification native." }, "UTF-8")
                deviceBridge.showNotification(title, message)
            }
            "share" -> {
                val text = java.net.URLDecoder.decode(parts.getOrElse(1) { "Regarde cette app faite avec PhpNitro !" }, "UTF-8")
                val title = java.net.URLDecoder.decode(parts.getOrElse(2) { "PhpNitro" }, "UTF-8")
                deviceBridge.share(text, title)
            }
            "appicon" -> deviceBridge.setAppIcon(parts.getOrElse(1) { "default" })
            // Manual "🎉 encore" replay button — Confetti::triggerAction().
            // The automatic case (Canvas::triggerConfetti(), a widget
            // dropped in the tree) is handled in setCommands() instead,
            // since that one has to fire on every matching render, not
            // just a tap.
            "confetti" -> showConfettiOverlay()
            "brightness" -> deviceBridge.setBrightness(parts.getOrNull(1)?.toFloatOrNull() ?: 0.5f)
            "locate" -> {
                deviceBridge.getLocation { result ->
                    fieldValues[parts.getOrElse(1) { "location_out" }] = result
                    refetch(action = null, includeFields = true)
                }
            }
            "biometric" -> {
                deviceBridge.showBiometricPrompt { success, message ->
                    fieldValues[parts.getOrElse(1) { "biometric_out" }] = if (success) "Authentifié" else message
                    refetch(action = null, includeFields = true)
                }
            }
            "mic" -> {
                if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.RECORD_AUDIO) ==
                    PackageManager.PERMISSION_GRANTED
                ) {
                    startMicRecording(token)
                } else {
                    pendingMicToken = token
                    requestRecordAudioPermission.launch(android.Manifest.permission.RECORD_AUDIO)
                }
            }
            "camera" -> takePicturePreview.launch(null)
            "pickimage" -> pickImage.launch("image/*")
            "permission" -> handlePermissionAction(token)
            "scanqr" -> {
                pendingQrOutputField = parts.getOrElse(1) { "qr_out" }
                scanQrPicture.launch(null)
            }
            "googlesignin" -> {
                val webClientId = getString(R.string.google_web_client_id)
                deviceBridge.signInWithGoogle(webClientId) { idToken, error ->
                    if (idToken != null) {
                        fieldValues["google_id_token"] = idToken
                    } else {
                        fieldValues["google_signin_error"] = error ?: "Échec de connexion Google."
                    }
                    refetch("google_signin", includeFields = true)
                }
            }
            "sensor" -> {
                deviceBridge.readSensor(android.hardware.Sensor.TYPE_ACCELEROMETER) { result ->
                    fieldValues[parts.getOrElse(1) { "sensor_out" }] = result
                    refetch(action = null, includeFields = true)
                }
            }
            "nfcstart" -> {
                nfcListening = true
                enableNfcForegroundDispatch()
            }
            "nfcstop" -> {
                nfcListening = false
                nfcAdapter?.disableForegroundDispatch(this)
            }
            "iapquery" -> {
                val productId = java.net.URLDecoder.decode(parts.getOrElse(1) { "demo_product" }, "UTF-8")
                deviceBridge.queryProducts(listOf(productId)) { result ->
                    fieldValues[parts.getOrElse(2) { "iap_out" }] = result
                    refetch(action = null, includeFields = true)
                }
            }
            "iappurchase" -> {
                val productId = java.net.URLDecoder.decode(parts.getOrElse(1) { "demo_product" }, "UTF-8")
                deviceBridge.purchaseProduct(productId)
            }
            "geofenceadd" -> {
                val id = java.net.URLDecoder.decode(parts.getOrElse(1) { "paris_demo" }, "UTF-8")
                val lat = parts.getOrNull(2)?.toDoubleOrNull() ?: 48.8566
                val lng = parts.getOrNull(3)?.toDoubleOrNull() ?: 2.3522
                val radius = parts.getOrNull(4)?.toFloatOrNull() ?: 200f
                deviceBridge.addGeofence(id, lat, lng, radius)
            }
            "geofenceremove" -> {
                val id = java.net.URLDecoder.decode(parts.getOrElse(1) { "paris_demo" }, "UTF-8")
                deviceBridge.removeGeofence(id)
            }
            "bgschedule" -> {
                val endpoint = java.net.URLDecoder.decode(parts.getOrElse(1) { "/api/ping" }, "UTF-8")
                val interval = parts.getOrNull(2)?.toIntOrNull() ?: 15
                deviceBridge.scheduleBackgroundTask(endpoint, interval)
            }
            "bgcancel" -> deviceBridge.cancelBackgroundTask()
            "alarmschedule" -> {
                val requestCode = parts.getOrNull(1)?.toIntOrNull() ?: 1
                val delaySeconds = parts.getOrNull(2)?.toIntOrNull() ?: 3600
                val title = java.net.URLDecoder.decode(parts.getOrElse(3) { "Rappel" }, "UTF-8")
                val message = java.net.URLDecoder.decode(parts.getOrElse(4) { "" }, "UTF-8")
                deviceBridge.scheduleAlarm(requestCode, delaySeconds, title, message)
            }
            "printpdf" -> printCurrentScreen()
            // Engine\Device\UrlLauncher — the URL travels rawurlencode()'d
            // (see UrlLauncher's own docblock for why), decoded back here.
            "openurl" -> {
                val url = java.net.URLDecoder.decode(parts.getOrElse(1) { "" }, "UTF-8")
                if (url.isNotEmpty()) startActivity(Intent(Intent.ACTION_VIEW, android.net.Uri.parse(url)))
            }
            // Engine\Device\Connectivity — reuses the same isOnline() the
            // WebView path's Engine\Connectivity\ConnectivityBadge calls.
            "connectivity" -> {
                fieldValues[parts.getOrElse(1) { "connectivity_out" }] = if (deviceBridge.isOnline()) "online" else "offline"
                refetch(action = null, includeFields = true)
            }
            // Engine\Device\InAppReview — Play Core's own review sheet;
            // no result to report, see InAppReview's own docblock for why
            // Play never guarantees the prompt actually shows.
            "inappreview" -> {
                val manager = com.google.android.play.core.review.ReviewManagerFactory.create(this)
                manager.requestReviewFlow().addOnCompleteListener { task ->
                    if (task.isSuccessful) manager.launchReviewFlow(this, task.result)
                }
            }
            // Engine\Device\AppLinks — the current Intent's own data URI;
            // setIntent() in onNewIntent() keeps `intent` current even
            // after the launching Intent that started this instance.
            "applink" -> {
                fieldValues[parts.getOrElse(1) { "app_link_out" }] = intent?.data?.toString() ?: "Aucun lien"
                refetch(action = null, includeFields = true)
            }
            // Engine\Device\AppSettings — a fixed whitelist of Settings
            // screens, same "reject an unknown key up front" pattern
            // handlePermissionAction() uses; an unrecognised $screen falls
            // back to this app's own detail page.
            "appsettings" -> {
                val settingsIntent = when (parts.getOrElse(1) { "app" }) {
                    "wifi" -> Intent(android.provider.Settings.ACTION_WIFI_SETTINGS)
                    "location" -> Intent(android.provider.Settings.ACTION_LOCATION_SOURCE_SETTINGS)
                    "notifications" -> Intent(android.provider.Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                        .putExtra(android.provider.Settings.EXTRA_APP_PACKAGE, packageName)
                    "bluetooth" -> Intent(android.provider.Settings.ACTION_BLUETOOTH_SETTINGS)
                    else -> Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS, android.net.Uri.fromParts("package", packageName, null))
                }
                startActivity(settingsIntent)
            }
            // Engine\Device\OpenFile — writes the demo content to this
            // app's own files dir, then hands it off via the FileProvider
            // declared in this module's AndroidManifest.xml (see
            // res/xml/file_paths.xml) — a raw file:// Uri would be
            // rejected across app boundaries since API 24.
            "openfile" -> {
                // java.io.File(filesDir, fileName)'s two-arg constructor
                // does NOT confine the result to filesDir — a fileName of
                // "../../../etc/whatever" resolves right past it (a real
                // path-traversal write, not a hypothetical one). Taking
                // just File(fileName).name discards any directory
                // component before it ever reaches the real File(dir,
                // name) constructor below.
                val fileName = java.io.File(java.net.URLDecoder.decode(parts.getOrElse(1) { "document.txt" }, "UTF-8")).name
                val mimeType = java.net.URLDecoder.decode(parts.getOrElse(2) { "text/plain" }, "UTF-8")
                val content = java.net.URLDecoder.decode(parts.getOrElse(3) { "" }, "UTF-8")
                val file = java.io.File(filesDir, fileName)
                file.writeText(content)
                val uri = androidx.core.content.FileProvider.getUriForFile(this, "$packageName.fileprovider", file)
                startActivity(
                    Intent(Intent.ACTION_VIEW).apply {
                        setDataAndType(uri, mimeType)
                        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    },
                )
            }
            // Engine\Device\InAppUpdate — outside a real Play Store
            // install this always resolves to update_not_available (no
            // Play-side release exists to compare against), see
            // InAppUpdate's own docblock.
            "checkupdate" -> {
                val outputField = parts.getOrElse(1) { "update_out" }
                val appUpdateManager = com.google.android.play.core.appupdate.AppUpdateManagerFactory.create(this)
                appUpdateManager.appUpdateInfo
                    .addOnSuccessListener { info ->
                        if (info.updateAvailability() == com.google.android.play.core.install.model.UpdateAvailability.UPDATE_AVAILABLE &&
                            info.isUpdateTypeAllowed(com.google.android.play.core.install.model.AppUpdateType.FLEXIBLE)
                        ) {
                            fieldValues[outputField] = "update_available"
                            appUpdateManager.startUpdateFlowForResult(
                                info,
                                appUpdateLauncher,
                                com.google.android.play.core.appupdate.AppUpdateOptions.newBuilder(
                                    com.google.android.play.core.install.model.AppUpdateType.FLEXIBLE,
                                ).build(),
                            )
                        } else {
                            fieldValues[outputField] = "update_not_available"
                        }
                        refetch(action = null, includeFields = true)
                    }
                    .addOnFailureListener {
                        fieldValues[outputField] = "update_not_available"
                        refetch(action = null, includeFields = true)
                    }
            }
            "pickfile" -> {
                pendingFileOutputField = parts.getOrElse(1) { "file_out" }
                pickFile.launch(arrayOf("*/*"))
            }
            // Engine\Device\MapLauncher — a "geo:" Uri, resolved by
            // whichever maps app the OS has (or a chooser if several).
            "openmap" -> {
                val lat = parts.getOrNull(1) ?: "0"
                val lng = parts.getOrNull(2) ?: "0"
                val label = java.net.URLDecoder.decode(parts.getOrElse(3) { "" }, "UTF-8")
                val geoUri = if (label.isNotEmpty()) {
                    android.net.Uri.parse("geo:$lat,$lng?q=$lat,$lng(${android.net.Uri.encode(label)})")
                } else {
                    android.net.Uri.parse("geo:$lat,$lng?q=$lat,$lng")
                }
                startActivity(Intent(Intent.ACTION_VIEW, geoUri))
            }
            // Engine\Device\FileSaver — MediaStore.Downloads needs API 29+
            // (the scoped-storage way, no WRITE_EXTERNAL_STORAGE
            // permission); minSdk here is 24, hence the version gate.
            "savefile" -> {
                val fileName = java.net.URLDecoder.decode(parts.getOrElse(1) { "phpnitro.txt" }, "UTF-8")
                val content = java.net.URLDecoder.decode(parts.getOrElse(2) { "" }, "UTF-8")
                val outputField = parts.getOrElse(3) { "save_out" }
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.Q) {
                    try {
                        val values = android.content.ContentValues().apply {
                            put(android.provider.MediaStore.Downloads.DISPLAY_NAME, fileName)
                            put(android.provider.MediaStore.Downloads.MIME_TYPE, "text/plain")
                        }
                        val uri = contentResolver.insert(android.provider.MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
                        if (uri != null) {
                            contentResolver.openOutputStream(uri)?.use { it.write(content.toByteArray()) }
                            fieldValues[outputField] = "Enregistré"
                        } else {
                            fieldValues[outputField] = "Erreur d'enregistrement"
                        }
                    } catch (e: Exception) {
                        fieldValues[outputField] = e.message ?: "Erreur d'enregistrement"
                    }
                } else {
                    fieldValues[outputField] = "Non supporté (Android < 10)"
                }
                refetch(action = null, includeFields = true)
            }
            "clipboardcopy" -> {
                val text = java.net.URLDecoder.decode(parts.getOrElse(1) { "" }, "UTF-8")
                val clipboard = getSystemService(Context.CLIPBOARD_SERVICE) as android.content.ClipboardManager
                clipboard.setPrimaryClip(android.content.ClipData.newPlainText("PhpNitro", text))
            }
            "clipboardpaste" -> {
                val outputField = parts.getOrElse(1) { "clipboard_out" }
                val clipboard = getSystemService(Context.CLIPBOARD_SERVICE) as android.content.ClipboardManager
                val text = clipboard.primaryClip?.takeIf { it.itemCount > 0 }?.getItemAt(0)?.coerceToText(this)?.toString()
                fieldValues[outputField] = text?.takeIf { it.isNotEmpty() } ?: "Presse-papiers vide ou inaccessible"
                refetch(action = null, includeFields = true)
            }
            // Engine\Device\EmailSender — ACTION_SENDTO with a "mailto:"
            // Uri only ever matches real mail apps, unlike ACTION_SEND.
            "sendemail" -> {
                val to = java.net.URLDecoder.decode(parts.getOrElse(1) { "" }, "UTF-8")
                val subject = java.net.URLDecoder.decode(parts.getOrElse(2) { "" }, "UTF-8")
                val body = java.net.URLDecoder.decode(parts.getOrElse(3) { "" }, "UTF-8")
                startActivity(
                    Intent(Intent.ACTION_SENDTO).apply {
                        data = android.net.Uri.parse("mailto:")
                        putExtra(Intent.EXTRA_EMAIL, arrayOf(to))
                        putExtra(Intent.EXTRA_SUBJECT, subject)
                        putExtra(Intent.EXTRA_TEXT, body)
                    },
                )
            }
            // Engine\Device\RestartApp — relaunches the launcher Intent
            // with FLAG_ACTIVITY_CLEAR_TASK, then kills this process, so
            // the next launch is a genuinely fresh process.
            "restartapp" -> {
                val restartIntent = packageManager.getLaunchIntentForPackage(packageName)
                restartIntent?.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK or Intent.FLAG_ACTIVITY_NEW_TASK)
                if (restartIntent != null) startActivity(restartIntent)
                Runtime.getRuntime().exit(0)
            }
            // Engine\Device\WebSocket — a REAL persistent connection (see
            // WebSocketService), not polling. The URL travels
            // rawurlencode()'d, same reason UrlLauncher's own "openurl"
            // does.
            "wsconnect" -> {
                val url = java.net.URLDecoder.decode(parts.getOrElse(1) { "" }, "UTF-8")
                val outputField = parts.getOrElse(2) { "ws_out" }
                val service = webSocketService
                if (service != null) {
                    service.connect(url, outputField)
                } else {
                    pendingWsConnect = url to outputField
                    ensureWebSocketServiceBound()
                }
            }
            "wssend" -> {
                val message = java.net.URLDecoder.decode(parts.getOrElse(1) { "" }, "UTF-8")
                webSocketService?.send(message)
            }
            "wsdisconnect" -> {
                webSocketService?.disconnect()
                if (webSocketBound) {
                    unbindService(webSocketConnection)
                    webSocketBound = false
                }
                stopService(Intent(this, WebSocketService::class.java))
                webSocketService = null
            }
            "cropimage" -> {
                pendingCropOutputField = parts.getOrElse(1) { "crop_out" }
                cropImage.launch(com.canhub.cropper.CropImageContractOptions(uri = null, cropImageOptions = com.canhub.cropper.CropImageOptions()))
            }
        }
    }

    // Real android.print.PrintManager pipeline — NativePrintAdapter
    // replays this screen's own draw commands onto a PdfDocument.Page's
    // Canvas (see NativeCanvasView.drawForPrint()), same system print
    // dialog WebAppInterface.printPage() opens, but with no WebView
    // involved anywhere in the document's construction.
    private fun printCurrentScreen() {
        val printManager = getSystemService(Context.PRINT_SERVICE) as android.print.PrintManager
        val jobName = "PhpNitro-${screenStack.lastOrNull()?.substringBefore('/') ?: "screen"}"
        val adapter = NativePrintAdapter(this, canvasView, jobName)
        printManager.print(jobName, adapter, android.print.PrintAttributes.Builder().build())
    }

    // Overlays a real EditText at the tapped field's rect — there's no
    // DOM input for the OS keyboard to attach to on a Canvas, so this is
    // the actual text-entry surface; NativeCanvasView just draws the
    // field's *shape* underneath it. One at a time: switching fields
    // removes the previous overlay first.
    private fun showTextInput(fieldName: String, regionDp: RectF, secure: Boolean, multiline: Boolean = false) {
        activeEditText?.let { rootLayout.removeView(it) }

        val density = resources.displayMetrics.density
        val editText = EditText(this).apply {
            setText(fieldValues[fieldName] ?: "")
            setSelection(text.length)
            inputType = when {
                secure -> InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD
                multiline -> InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_MULTI_LINE
                else -> InputType.TYPE_CLASS_TEXT
            }
            gravity = if (multiline) {
                android.view.Gravity.TOP or android.view.Gravity.START
            } else {
                android.view.Gravity.CENTER_VERTICAL or android.view.Gravity.START
            }
            textSize = 15f
            setTextColor(android.graphics.Color.parseColor("#111827")) // Tokens::ink()
            // The default EditText style is just an underline over a
            // transparent background (Material's own colorAccent, which
            // is why a stock green line appeared on focus on this
            // device) — with no opaque fill of its own, this EditText
            // sat on top of the Canvas box TextField.php already
            // painted underneath (background + placeholder text baked
            // into that one static draw command), so the stale
            // placeholder kept showing through around/behind whatever
            // was actually typed. A solid white rounded rect matching
            // that same Container's own styling (Tokens::surface() +
            // RADIUS_MD + border(), values duplicated here the same way
            // this file already hardcodes brand colors elsewhere) fully
            // covers it instead of just hiding the underline.
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(android.graphics.Color.WHITE)
                cornerRadius = 14f * density
                setStroke((1 * density).toInt(), android.graphics.Color.parseColor("#E5E7EB"))
            }
            val paddingH = (12 * density).toInt()
            setPadding(paddingH, paddingTop, paddingH, paddingBottom)
            addTextChangedListener(object : TextWatcher {
                override fun afterTextChanged(s: Editable?) {
                    fieldValues[fieldName] = s?.toString() ?: ""
                }
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            })
        }

        val params = FrameLayout.LayoutParams(
            (regionDp.width() * density).toInt(),
            (regionDp.height() * density).toInt(),
        ).apply {
            leftMargin = (regionDp.left * density).toInt()
            topMargin = (regionDp.top * density).toInt()
        }
        rootLayout.addView(editText, params)
        editText.requestFocus()
        (getSystemService(INPUT_METHOD_SERVICE) as InputMethodManager)
            .showSoftInput(editText, InputMethodManager.SHOW_IMPLICIT)
        activeEditText = editText
    }

    // Also tears down any active video overlay — every navigate:/back/
    // tab:/submit: call site already calls this before moving to a
    // different screen, so a playing VideoPlayer doesn't keep
    // playing (or leak its overlay View) underneath whatever renders next.
    private fun clearTextInput() {
        activeEditText?.let { rootLayout.removeView(it) }
        activeEditText = null
        (getSystemService(INPUT_METHOD_SERVICE) as InputMethodManager)
            .hideSoftInputFromWindow(canvasView.windowToken, 0)
        clearVideoOverlay()
        clearMapOverlay()
        clearYoutubeOverlay()
    }

    // Lottie: unlike the video/map overlays below (shown only on
    // tap), a Lottie animation autoplays — this is reconciled after
    // EVERY setCommands(), not from onTap()'s dispatch.
    private val activeLottieViews = mutableMapOf<String, com.airbnb.lottie.LottieAnimationView>()

    private fun syncLottieOverlays(regions: List<NativeCanvasView.LottieRegion>) {
        val density = resources.displayMetrics.density
        val seenKeys = mutableSetOf<String>()
        for (region in regions) {
            seenKeys.add(region.key)
            val params = FrameLayout.LayoutParams(
                (region.rect.width() * density).toInt(),
                (region.rect.height() * density).toInt(),
            ).apply {
                leftMargin = (region.rect.left * density).toInt()
                topMargin = (region.rect.top * density).toInt()
            }
            val existing = activeLottieViews[region.key]
            if (existing != null) {
                existing.layoutParams = params
                continue
            }
            val view = com.airbnb.lottie.LottieAnimationView(this).apply {
                repeatCount = if (region.loop) com.airbnb.lottie.LottieDrawable.INFINITE else 0
                if (region.url.startsWith("http")) {
                    setAnimationFromUrl(region.url)
                } else {
                    setAnimation(region.url)
                }
                if (region.autoplay) playAnimation()
            }
            rootLayout.addView(view, params)
            activeLottieViews[region.key] = view
        }

        val staleKeys = activeLottieViews.keys - seenKeys
        for (key in staleKeys) {
            activeLottieViews.remove(key)?.let {
                it.cancelAnimation()
                rootLayout.removeView(it)
            }
        }
    }

    private var activeVideoView: android.widget.VideoView? = null

    private fun showVideoOverlay(url: String, regionDp: RectF) {
        clearVideoOverlay()

        val density = resources.displayMetrics.density
        val videoView = android.widget.VideoView(this)
        val mediaController = android.widget.MediaController(this)
        mediaController.setAnchorView(videoView)
        videoView.setMediaController(mediaController)
        videoView.setVideoURI(android.net.Uri.parse(url))
        videoView.setOnPreparedListener { it.start() }
        val params = FrameLayout.LayoutParams(
            (regionDp.width() * density).toInt(),
            (regionDp.height() * density).toInt(),
        ).apply {
            leftMargin = (regionDp.left * density).toInt()
            topMargin = (regionDp.top * density).toInt()
        }
        rootLayout.addView(videoView, params)
        activeVideoView = videoView
    }

    private fun clearVideoOverlay() {
        activeVideoView?.let {
            it.stopPlayback()
            rootLayout.removeView(it)
        }
        activeVideoView = null
    }

    // YoutubePlayer (Engine\Native\YoutubePlayer) — same tap-to-play
    // overlay idiom as showVideoOverlay() just above (one-shot, added on
    // tap, torn down by clearTextInput() on every navigate/back/submit),
    // just a WebView loaded with YouTube's IFrame embed instead of a
    // VideoView + raw media URL: YouTube requires their own embed player
    // (a raw .mp4/.m3u8 URL isn't available for a YouTube video), and the
    // IFrame Player API is the same technique every current
    // youtube_player_flutter/react-native-youtube-iframe package uses
    // under the hood — not a compromise specific to this framework.
    private var activeYoutubeView: android.webkit.WebView? = null

    private fun showYoutubeOverlay(videoId: String, regionDp: RectF) {
        clearYoutubeOverlay()

        val density = resources.displayMetrics.density
        val webView = android.webkit.WebView(this).apply {
            settings.javaScriptEnabled = true
            settings.mediaPlaybackRequiresUserGesture = false
            webChromeClient = android.webkit.WebChromeClient()
            loadDataWithBaseURL(
                "https://www.youtube.com",
                "<html><body style=\"margin:0;padding:0;background:#000;\">" +
                    "<iframe width=\"100%\" height=\"100%\" " +
                    "src=\"https://www.youtube.com/embed/$videoId?autoplay=1&playsinline=1\" " +
                    "frameborder=\"0\" allow=\"autoplay; encrypted-media\" allowfullscreen></iframe>" +
                    "</body></html>",
                "text/html",
                "utf-8",
                null,
            )
        }
        val params = FrameLayout.LayoutParams(
            (regionDp.width() * density).toInt(),
            (regionDp.height() * density).toInt(),
        ).apply {
            leftMargin = (regionDp.left * density).toInt()
            topMargin = (regionDp.top * density).toInt()
        }
        rootLayout.addView(webView, params)
        activeYoutubeView = webView
    }

    private fun clearYoutubeOverlay() {
        activeYoutubeView?.let {
            // Navigating away first stops audio/playback before the View
            // is actually removed — simply removeView()-ing a still-
            // playing embedded player leaves its audio track running.
            it.loadUrl("about:blank")
            rootLayout.removeView(it)
        }
        activeYoutubeView = null
    }

    // See ConfettiView's own docblock for the actual particle simulation
    // — this is just the overlay lifecycle (add, start, remove itself
    // after its own duration), the same shape every other full-screen/
    // full-rect overlay here (video, map) already follows, just with a
    // timed self-removal instead of clearVideoOverlay()/clearMapOverlay()
    // needing an explicit caller.
    private fun showConfettiOverlay() {
        activeConfettiView?.let { rootLayout.removeView(it) }

        val durationMs = 3000L
        val confettiView = ConfettiView(this)
        rootLayout.addView(confettiView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        confettiView.start(durationMs = durationMs)
        activeConfettiView = confettiView

        Handler(Looper.getMainLooper()).postDelayed({
            confettiView.stop()
            if (activeConfettiView === confettiView) {
                rootLayout.removeView(confettiView)
                activeConfettiView = null
            }
        }, durationMs)
    }

    // See Canvas::showSnackbar()/Snackbar's own docblocks. Fade in, hold,
    // fade out, self-remove — the identity check (activeSnackbarView ===
    // thisView) before every removal/fade-out guards against a SECOND
    // snackbar firing while the first one's still holding: the first
    // one's own delayed callback would otherwise fire later and rip out
    // the second one's view instead of its own.
    private fun showSnackbarOverlay(message: String, durationMs: Long) {
        activeSnackbarView?.let { rootLayout.removeView(it) }

        val density = resources.displayMetrics.density
        fun dp(value: Int) = (value * density).toInt()

        val snackbarView = TextView(this).apply {
            text = message
            setTextColor(android.graphics.Color.WHITE)
            textSize = 14f
            setPadding(dp(16), dp(14), dp(16), dp(14))
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(android.graphics.Color.parseColor("#EE111827"))
                cornerRadius = dp(10).toFloat()
            }
            alpha = 0f
        }
        val params = FrameLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            gravity = Gravity.BOTTOM or Gravity.CENTER_HORIZONTAL
            bottomMargin = dp(32)
            leftMargin = dp(24)
            rightMargin = dp(24)
        }
        rootLayout.addView(snackbarView, params)
        activeSnackbarView = snackbarView

        snackbarView.animate().alpha(1f).setDuration(200).start()

        val holdMs = (durationMs - 400L).coerceAtLeast(0L)
        Handler(Looper.getMainLooper()).postDelayed({
            if (activeSnackbarView !== snackbarView) return@postDelayed
            snackbarView.animate().alpha(0f).setDuration(200).withEndAction {
                if (activeSnackbarView === snackbarView) {
                    rootLayout.removeView(snackbarView)
                    activeSnackbarView = null
                }
            }.start()
        }, holdMs)
    }

    // A real, pannable/zoomable org.osmdroid.views.MapView (pinch-zoom is
    // built into MapView itself once setMultiTouchControls(true) is set,
    // no extra gesture wiring here) — same overlay-at-tapped-rect idiom as
    // showTextInput()/showVideoOverlay(). Needs no API key, unlike Mapbox/
    // Google Maps.
    private var activeMapView: org.osmdroid.views.MapView? = null

    private fun showMapOverlay(latitude: Double, longitude: Double, zoom: Int, regionDp: RectF) {
        clearMapOverlay()

        val density = resources.displayMetrics.density
        val mapView = org.osmdroid.views.MapView(this).apply {
            setTileSource(org.osmdroid.tileprovider.tilesource.TileSourceFactory.MAPNIK)
            setMultiTouchControls(true)
            controller.setZoom(zoom.toDouble())
            controller.setCenter(org.osmdroid.util.GeoPoint(latitude, longitude))
        }
        val params = FrameLayout.LayoutParams(
            (regionDp.width() * density).toInt(),
            (regionDp.height() * density).toInt(),
        ).apply {
            leftMargin = (regionDp.left * density).toInt()
            topMargin = (regionDp.top * density).toInt()
        }
        rootLayout.addView(mapView, params)
        mapView.onResume()
        activeMapView = mapView
    }

    private fun clearMapOverlay() {
        activeMapView?.let {
            it.onPause()
            rootLayout.removeView(it)
        }
        activeMapView = null
    }

    // --- DevTools -----------------------------------------------------
    // A minimal DevTools-equivalent: not a separate connected tool (no
    // protocol, no companion app), just a small on-device overlay
    // surfacing the numbers this session's work only ever exposed as
    // logcat lines (PERF roundTripMs/phpRenderTimeMs) plus the new
    // engine-internals no log line covered at all — whether a refetch's
    // output was skipped entirely (Canvas::stableHash()'s
    // "unchanged") and whether the redraw that followed was a partial
    // dirty-rect invalidate or a full one (NativeCanvasView's
    // computeDirtyRects()). Only ever constructed when isDebuggable() —
    // never present in a release build, no runtime cost either way.
    private var devToolsPanel: TextView? = null
    private var devToolsVisible = false
    private var lastRoundTripMs = 0.0
    private var lastPhpRenderTimeMs: Double? = null

    // Widget inspector — Flutter DevTools' "Select Widget Mode" but
    // scoped to what's actually available here: no widget tree survives
    // past paint() server-side to inspect, only the flat hit region list
    // NativeCanvasView already hit-tests against. Toggling this ON makes
    // onTap() (below) intercept the NEXT tap and show that region's
    // action string + dp bounds in a dialog instead of dispatching it —
    // enough to answer "why isn't this tappable"/"what action does this
    // actually send" without adding server round-trip protocol.
    private var inspectMode = false
    private var inspectBadgeView: TextView? = null

    private fun isDebuggable(): Boolean =
        (applicationInfo.flags and android.content.pm.ApplicationInfo.FLAG_DEBUGGABLE) != 0

    // The real, wired proof that Canvas::custom()/registerCustomCommandHandler()
    // works end to end: NativeCanvasView.kt has no built-in idea what a
    // "sparkline" is, only this app-layer registration does. A real
    // third-party package would call canvasView.registerCustomCommandHandler()
    // the exact same way, from wherever it hooks into a consuming app —
    // this method is that hook, not a special engine-internal case.
    private fun registerCustomCommandHandlers() {
        canvasView.registerCustomCommandHandler("sparkline") { canvas, command, alpha ->
            val x = command.getDouble("x").toFloat()
            val y = command.getDouble("y").toFloat()
            val w = command.getDouble("width").toFloat()
            val h = command.getDouble("height").toFloat()
            val values = command.getJSONArray("values")
            if (values.length() >= 2) {
                var min = Double.MAX_VALUE
                var max = -Double.MAX_VALUE
                for (i in 0 until values.length()) {
                    val v = values.getDouble(i)
                    if (v < min) min = v
                    if (v > max) max = v
                }
                val range = (max - min).let { if (it > 0.0) it else 1.0 }
                val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
                    style = android.graphics.Paint.Style.STROKE
                    strokeWidth = 2.5f
                    strokeCap = android.graphics.Paint.Cap.ROUND
                    strokeJoin = android.graphics.Paint.Join.ROUND
                    color = Color.parseColor(command.getString("color"))
                    this.alpha = (this.alpha * alpha).toInt()
                }
                val path = android.graphics.Path()
                for (i in 0 until values.length()) {
                    val px = x + w * i / (values.length() - 1)
                    val py = y + h - h * ((values.getDouble(i) - min) / range).toFloat()
                    if (i == 0) path.moveTo(px, py) else path.lineTo(px, py)
                }
                canvas.drawPath(path, paint)
            }
        }

        // BarChart (Engine\Native\BarChart) — same custom-command pattern
        // as sparkline right above, registered here rather than a new
        // drawXxxCommand() in NativeCanvasView.kt for the same reason.
        canvasView.registerCustomCommandHandler("barChart") { canvas, command, alpha ->
            val x = command.getDouble("x").toFloat()
            val y = command.getDouble("y").toFloat()
            val w = command.getDouble("width").toFloat()
            val h = command.getDouble("height").toFloat()
            val gap = command.getDouble("gap").toFloat()
            val values = command.getJSONArray("values")
            val count = values.length()
            if (count > 0) {
                var max = 0.0
                for (i in 0 until count) max = maxOf(max, values.getDouble(i))
                if (max <= 0.0) max = 1.0
                val barWidth = (w - gap * (count - 1)) / count
                val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
                    style = android.graphics.Paint.Style.FILL
                    color = Color.parseColor(command.getString("color"))
                    this.alpha = (this.alpha * alpha).toInt()
                }
                for (i in 0 until count) {
                    val barHeight = (h * (values.getDouble(i) / max)).toFloat().coerceAtLeast(0f)
                    val left = x + i * (barWidth + gap)
                    canvas.drawRect(left, y + h - barHeight, left + barWidth, y + h, paint)
                }
            }
        }

        // PieChart (Engine\Native\PieChart) — same pattern again, one arc
        // per slice via drawArc(useCenter = true), sweep angle
        // proportional to that slice's share of the total.
        canvasView.registerCustomCommandHandler("pieChart") { canvas, command, alpha ->
            val x = command.getDouble("x").toFloat()
            val y = command.getDouble("y").toFloat()
            val diameter = command.getDouble("diameter").toFloat()
            val values = command.getJSONArray("values")
            val colors = command.getJSONArray("colors")
            val count = values.length()
            var total = 0.0
            for (i in 0 until count) total += values.getDouble(i)
            if (count > 0 && total > 0.0) {
                val rect = android.graphics.RectF(x, y, x + diameter, y + diameter)
                val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG).apply {
                    style = android.graphics.Paint.Style.FILL
                }
                var startAngle = -90f
                for (i in 0 until count) {
                    val sweep = (values.getDouble(i) / total * 360.0).toFloat()
                    paint.color = Color.parseColor(colors.getString(i))
                    paint.alpha = (paint.alpha * alpha).toInt()
                    canvas.drawArc(rect, startAngle, sweep, true, paint)
                    startAngle += sweep
                }
            }
        }
    }

    private fun setupDevTools() {
        val density = resources.displayMetrics.density
        fun dp(value: Float) = (value * density).toInt()

        val badge = TextView(this).apply {
            text = "🛠"
            textSize = 18f
            setPadding(dp(10f), dp(6f), dp(10f), dp(6f))
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(android.graphics.Color.parseColor("#CC111827"))
                cornerRadius = dp(20f).toFloat()
            }
            setTextColor(android.graphics.Color.WHITE)
            isClickable = true
            setOnClickListener {
                devToolsVisible = !devToolsVisible
                devToolsPanel?.visibility = if (devToolsVisible) android.view.View.VISIBLE else android.view.View.GONE
            }
        }
        val badgeParams = FrameLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            gravity = Gravity.BOTTOM or Gravity.END
            marginEnd = dp(16f)
            bottomMargin = dp(24f)
        }
        rootLayout.addView(badge, badgeParams)

        val inspectBadge = TextView(this).apply {
            text = "🔍"
            textSize = 18f
            setPadding(dp(10f), dp(6f), dp(10f), dp(6f))
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(android.graphics.Color.parseColor("#CC111827"))
                cornerRadius = dp(20f).toFloat()
            }
            setTextColor(android.graphics.Color.WHITE)
            isClickable = true
            setOnClickListener {
                inspectMode = !inspectMode
                alpha = if (inspectMode) 1f else 0.5f
                Toast.makeText(
                    this@NativeRenderPocActivity,
                    if (inspectMode) "Inspecteur : ON — tapez un élément" else "Inspecteur : OFF",
                    Toast.LENGTH_SHORT,
                ).show()
            }
            alpha = 0.5f
        }
        val inspectBadgeParams = FrameLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            gravity = Gravity.BOTTOM or Gravity.END
            marginEnd = dp(68f)
            bottomMargin = dp(24f)
        }
        rootLayout.addView(inspectBadge, inspectBadgeParams)
        inspectBadgeView = inspectBadge

        val panel = TextView(this).apply {
            typeface = Typeface.MONOSPACE
            textSize = 11f
            setTextColor(android.graphics.Color.parseColor("#E5E7EB"))
            setPadding(dp(12f), dp(10f), dp(12f), dp(10f))
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(android.graphics.Color.parseColor("#DD111827"))
                cornerRadius = dp(10f).toFloat()
            }
            visibility = android.view.View.GONE
        }
        val panelParams = FrameLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            gravity = Gravity.BOTTOM or Gravity.END
            marginEnd = dp(16f)
            bottomMargin = dp(72f)
        }
        rootLayout.addView(panel, panelParams)
        devToolsPanel = panel
    }

    private fun updateDevToolsPanel(screen: String, wasUnchanged: Boolean) {
        val panel = devToolsPanel ?: return
        val phpMs = lastPhpRenderTimeMs?.let { "%.2f".format(it) } ?: "?"
        panel.text = """
            screen: $screen (stack depth ${screenStack.size})
            roundTrip: ${"%.1f".format(lastRoundTripMs)} ms  php: $phpMs ms
            commands: ${canvasView.lastCommandCount}  hitRegions: ${canvasView.lastHitRegionCount}
            last fetch: ${if (wasUnchanged) "skipped (unchanged)" else "applied"}
            last redraw: ${if (canvasView.lastInvalidateWasPartial) "partial (dirty rect)" else "full"}
        """.trimIndent()
    }

    // isNavigation gates NativeCanvasView's whole-screen crossfade
    // (startCrossfade()) — true for an actual scene change (navigate:/
    // tab:/back, or a server-side redirect), false for every other
    // refetch (a toggle, a counter increment, a field update). Without
    // this split, EVERY tap fades the entire screen out and back in even
    // when only one piece of text changed, which reads as "the screen
    // just reloaded" rather than "the counter went up" — Hero/
    // Animated's per-element transitions are unaffected either way,
    // since those are opt-in and driven by their own tag matching, not
    // this blanket fade.
    private fun refetch(action: String?, includeFields: Boolean = false, isNavigation: Boolean = false, isPoll: Boolean = false) {
        if (serverPort == 0) return
        thread { fetchDrawCommands(serverPort, action, includeFields, isNavigation, isPoll) }
    }

    private fun fetchDrawCommands(port: Int, action: String?, includeFields: Boolean = false, isNavigation: Boolean = false, isPoll: Boolean = false) {
        // dp-space width/height, not raw device pixels — every size the
        // PHP side hands back (font sizes, radii, button heights, Tokens'
        // whole scale) is authored as a dp-like number, and
        // NativeCanvasView scales its Canvas by the real density before
        // replaying, so the layout math needs to run against the same dp
        // dimensions or a phone with more physical pixels per dp would
        // just get a narrower/shorter logical screen instead of
        // correctly-sized content. Height matters for screens (like
        // NativeOtpScreen) that use a Flexible spacer to pin content to
        // the true bottom of the screen.
        val density = resources.displayMetrics.density
        val screenWidthDp = resources.displayMetrics.widthPixels / density
        val screenHeightDp = resources.displayMetrics.heightPixels / density
        // "product?id=42&tab=reviews" -> screen=product, a real query
        // string carrying however many named params a screen needs — a
        // route-param screen token is just "name?query", split once at
        // fetch time rather than teaching screenStack about a richer
        // shape. Each pair is re-encoded individually (not passed through
        // as-is) so a param VALUE containing "&"/"=" or non-ASCII text
        // can't corrupt the URL or collide with the other query params
        // appended below (action/online/dark/...).
        val screenToken = screenStack.last()
        val screen = screenToken.substringBefore('?')
        val rawQuery = screenToken.substringAfter('?', missingDelimiterValue = "")
        val routeParams = if (rawQuery.isEmpty()) {
            ""
        } else {
            "&" + rawQuery.split("&").joinToString("&") { pair ->
                val parts = pair.split("=", limit = 2)
                val key = URLEncoder.encode(parts[0], "UTF-8")
                val value = URLEncoder.encode(parts.getOrElse(1) { "" }, "UTF-8")
                "$key=$value"
            }
        }
        val actionParam = if (action != null) "&action=${URLEncoder.encode(action, "UTF-8")}" else ""
        val onlineParam = "&online=${if (deviceBridge.isOnline()) 1 else 0}"
        // Tokens::init()'s own param — Configuration.UI_MODE_NIGHT_YES is
        // the system's real current setting (dark-mode toggle in Android
        // Settings, or the phone's day/night schedule), not anything
        // this app tracks or lets the user override itself yet. Read
        // fresh on every fetch rather than cached at onCreate() so
        // toggling system dark mode while the app is already open (a
        // real, common thing to do — the OS supports it live) takes
        // effect on the very next tap/refetch instead of needing a
        // restart.
        val nightModeFlags = resources.configuration.uiMode and android.content.res.Configuration.UI_MODE_NIGHT_MASK
        val darkParam = "&dark=${if (nightModeFlags == android.content.res.Configuration.UI_MODE_NIGHT_YES) 1 else 0}"
        // Translator::init()'s own param — the device's real system
        // language (Settings -> System -> Languages), same "system
        // default, no separate in-app setting to remember to change"
        // story as darkParam above. Only the bare language subtag
        // ("fr", "en") travels, not a full BCP 47 tag (fr-FR, en-US) —
        // lib/lang/*.php is keyed by language only; a project that
        // genuinely needs region-specific variants would need its own
        // richer locale files, not something this v1 tries to solve.
        val localeParam = "&locale=${URLEncoder.encode(resources.configuration.locales[0].language, "UTF-8")}"
        // LazyList's windowed prefetch needs to know where the user
        // actually is in the virtual list to build the right window —
        // harmless for every other screen, which simply never reads it.
        val scrollYParam = "&scroll_y=${canvasView.currentScrollYDp}"
        val fieldsParam = if (includeFields) {
            fieldValues.entries.joinToString("") { (name, value) -> "&${URLEncoder.encode(name, "UTF-8")}=${URLEncoder.encode(value, "UTF-8")}" }
        } else {
            ""
        }
        // Only sent for a same-screen refetch — a real navigation always
        // wants the fresh screen's full content regardless of what hash
        // happened to be lying around from wherever the user was before.
        // Never sent for a poll (Async/Canvas::pollAgain()):
        // the entire point of a poll is noticing that AsyncTask moved
        // from pending to done, so short-circuiting it to "unchanged"
        // the one time it might actually differ would silently stop the
        // polling loop — see scheduleTimedRefetch()'s pollAgain branch.
        // See Canvas::stableHash().
        val lastHashParam = if (!isNavigation && !isPoll && lastAppliedHash != null) "&lastHash=$lastAppliedHash" else ""
        // Point 3 of the "grow the framework" pass: a real performance
        // number, not an intuition. roundTripMs is tap-to-parsed-frame —
        // HTTP + PHP compute + JSON parse — everything except the actual
        // Canvas draw (that's onDraw's own concern, already logged
        // separately). PHP's own renderTimeMs rides in the response body,
        // so a slow frame here can be split into "PHP was slow" vs
        // "network/parse overhead" instead of one opaque total.
        val startNanos = System.nanoTime()
        try {
            val connection = URL("http://$serverHost:$port/native/layout-demo?width=$screenWidthDp&height=$screenHeightDp&screen=$screen$routeParams$actionParam$onlineParam$darkParam$localeParam$scrollYParam$fieldsParam$lastHashParam").openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            // Absent in remote mode (accessToken stays null, see its own
            // field docblock) — public/index.php only enforces this
            // header when PHPNITRO_ACCESS_TOKEN is actually set in its
            // environment, which phpx serve never sets either, so this
            // is a no-op there regardless.
            accessToken?.let { connection.setRequestProperty("X-PhpNitro-Token", it) }
            val responseCode = connection.responseCode
            Log.i(TAG, "Fetching /native/layout-demo (screen=$screen, action=$action), response code $responseCode")
            // HttpURLConnection throws FileNotFoundException from
            // .inputStream on any non-2xx response — the body (here,
            // public/index.php's {"error": {...}} payload, see its own
            // set_exception_handler() for this route) only comes back
            // through .errorStream. Reading the wrong one for a 500 isn't
            // a network failure at all, but was landing in this method's
            // own catch block below regardless, which shows
            // showConnectionError()'s "can't reach the server" card for
            // what's actually "reached the server fine, it threw" — the
            // two need different messages, see showScreenErrorOverlay().
            val stream = if (responseCode >= 400) connection.errorStream else connection.inputStream
            val json = stream.bufferedReader().use { it.readText() }
            connection.disconnect()

            if (responseCode >= 400) {
                Handler(Looper.getMainLooper()).post { showScreenErrorOverlay(json) }
                return
            }

            val roundTripMs = (System.nanoTime() - startNanos) / 1_000_000.0
            val renderTimeMs = Regex("\"renderTimeMs\":([0-9.]+)").find(json)?.groupValues?.get(1)?.toDoubleOrNull()
            Log.i(TAG, "PERF screen=$screen roundTripMs=${"%.1f".format(roundTripMs)} phpRenderTimeMs=${renderTimeMs?.let { "%.2f".format(it) } ?: "?"}")
            lastRoundTripMs = roundTripMs
            lastPhpRenderTimeMs = renderTimeMs

            Handler(Looper.getMainLooper()).post { applyResponse(json, screenWidthDp, isNavigation) }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to fetch draw commands", e)
            Handler(Looper.getMainLooper()).post { showConnectionError() }
        }
    }

    // Only ever reached after a fetch failure — see fetchDrawCommands()'s
    // catch block. Reuses ConnectActivity's plain-Views style (this class
    // has no XML layouts at all) rather than adding a layout file for one
    // screen. Idempotent: repeated failures (e.g. an auto-retry poll)
    // just update the existing view's text instead of stacking duplicates.
    private fun showConnectionError() {
        firstScreenRendered = true // dismiss the splash — see its keepOnScreenCondition above

        val target = "$serverHost:$serverPort"
        val existing = connectionErrorView
        if (existing != null) {
            connectionErrorMessage?.text = target
            existing.visibility = android.view.View.VISIBLE
            return
        }

        val density = resources.displayMetrics.density
        fun dp(value: Int) = (value * density).toInt()
        val accent = android.graphics.Color.parseColor("#F97316") // same accent NativeCanvasView's own dev-tools badge/panel already use

        val icon = TextView(this).apply {
            text = "📡"
            textSize = 30f
            gravity = Gravity.CENTER
            background = android.graphics.drawable.GradientDrawable().apply {
                shape = android.graphics.drawable.GradientDrawable.OVAL
                setColor(android.graphics.Color.parseColor("#332D3748"))
            }
        }
        val title = TextView(this).apply {
            text = "Connexion impossible"
            textSize = 18f
            setTypeface(typeface, Typeface.BOLD)
            setTextColor(android.graphics.Color.WHITE)
            gravity = Gravity.CENTER
            setPadding(0, dp(18), 0, dp(6))
        }
        val targetLabel = TextView(this).apply {
            text = target
            typeface = Typeface.MONOSPACE
            textSize = 13f
            setTextColor(accent)
            gravity = Gravity.CENTER
            setPadding(0, 0, 0, dp(14))
        }
        connectionErrorMessage = targetLabel
        val hint = TextView(this).apply {
            text = "Vérifie que cet appareil est sur le même réseau Wi-Fi que la machine de dev, et que `phpx serve` tourne toujours."
            textSize = 14f
            setTextColor(android.graphics.Color.parseColor("#9CA3AF"))
            gravity = Gravity.CENTER
        }
        val retryButton = TextView(this).apply {
            text = "Réessayer"
            textSize = 15f
            setTypeface(typeface, Typeface.BOLD)
            setTextColor(android.graphics.Color.WHITE)
            gravity = Gravity.CENTER
            setPadding(dp(24), dp(12), dp(24), dp(12))
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(accent)
                cornerRadius = dp(24).toFloat()
            }
            isClickable = true
            setOnClickListener {
                connectionErrorView?.visibility = android.view.View.GONE
                refetch(action = null, isNavigation = true)
            }
        }
        val card = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            gravity = Gravity.CENTER_HORIZONTAL
            setPadding(dp(28), dp(28), dp(28), dp(28))
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(android.graphics.Color.parseColor("#EE111827"))
                cornerRadius = dp(18).toFloat()
            }
            addView(icon, android.widget.LinearLayout.LayoutParams(dp(56), dp(56)))
            addView(title)
            addView(targetLabel)
            addView(hint)
            addView(retryButton, android.widget.LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
                topMargin = dp(22)
            })
        }
        val params = FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            gravity = Gravity.CENTER
            leftMargin = dp(32)
            rightMargin = dp(32)
        }
        rootLayout.addView(card, params)
        connectionErrorView = card
    }

    private var screenErrorView: android.view.View? = null

    /**
     * public/index.php's /native/layout-demo route replaces its usual
     * HTML exception handler with a JSON one for exactly this case — see
     * that route's own set_exception_handler() call for why (an HTML
     * body under a "application/json" Content-Type header used to fail
     * to parse silently, leaving whatever was already on screen with no
     * indication anything broke). Full-screen and scrollable, not a
     * small centered card like showConnectionError() — a real PHP stack
     * trace needs room a card can't give it. file/line/trace are only
     * present when the server's APP_DEBUG is on (same gating the old
     * HTML error page used) — each row only renders if its field is
     * actually non-empty, so a production build's generic
     * class+message-only payload doesn't leave blank rows behind.
     */
    private fun showScreenErrorOverlay(json: String) {
        firstScreenRendered = true // dismiss the splash — see its keepOnScreenCondition above

        val error = try {
            JSONObject(json).optJSONObject("error")
        } catch (e: org.json.JSONException) {
            null
        }
        if (error == null) {
            // Not our own {"error": {...}} shape (a raw 500 from
            // something outside this route's own control, or a
            // genuinely malformed response) — nothing structured to
            // show, fall back to the connection-error card rather than
            // a blank overlay.
            showConnectionError()
            return
        }

        CrashReporter.logPhpError(this, error)

        screenErrorView?.let { rootLayout.removeView(it) }

        val density = resources.displayMetrics.density
        fun dp(value: Int) = (value * density).toInt()
        val danger = android.graphics.Color.parseColor("#DC2626")

        val content = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            setPadding(dp(24), dp(48), dp(24), dp(48))
        }

        content.addView(
            TextView(this).apply {
                text = "⚠️ Erreur PHP"
                textSize = 20f
                setTypeface(typeface, Typeface.BOLD)
                setTextColor(android.graphics.Color.WHITE)
            },
        )
        content.addView(
            TextView(this).apply {
                text = error.optString("class", "Exception")
                textSize = 15f
                setTypeface(typeface, Typeface.BOLD)
                setTextColor(danger)
                setPadding(0, dp(16), 0, dp(4))
            },
        )
        content.addView(
            TextView(this).apply {
                text = error.optString("message", "")
                textSize = 14f
                setTextColor(android.graphics.Color.WHITE)
            },
        )

        val file = error.optString("file", "")
        if (file.isNotEmpty()) {
            val line = error.optInt("line", -1)
            content.addView(
                TextView(this).apply {
                    text = if (line >= 0) "$file:$line" else file
                    typeface = Typeface.MONOSPACE
                    textSize = 12f
                    setTextColor(android.graphics.Color.parseColor("#9CA3AF"))
                    setPadding(0, dp(12), 0, 0)
                },
            )
        }

        val trace = error.optString("trace", "")
        if (trace.isNotEmpty()) {
            content.addView(
                TextView(this).apply {
                    text = trace
                    typeface = Typeface.MONOSPACE
                    textSize = 11f
                    setTextColor(android.graphics.Color.parseColor("#D1D5DB"))
                    setPadding(dp(12), dp(12), dp(12), dp(12))
                    background = android.graphics.drawable.GradientDrawable().apply {
                        setColor(android.graphics.Color.parseColor("#1F2937"))
                        cornerRadius = dp(8).toFloat()
                    }
                },
                android.widget.LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
                    topMargin = dp(16)
                },
            )
        }

        val retryButton = TextView(this).apply {
            text = "Réessayer"
            textSize = 15f
            setTypeface(typeface, Typeface.BOLD)
            setTextColor(android.graphics.Color.WHITE)
            gravity = Gravity.CENTER
            setPadding(0, dp(14), 0, dp(14))
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(danger)
                cornerRadius = dp(24).toFloat()
            }
            isClickable = true
            setOnClickListener {
                screenErrorView?.let { rootLayout.removeView(it) }
                screenErrorView = null
                refetch(action = null, isNavigation = true)
            }
        }
        content.addView(
            retryButton,
            android.widget.LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
                topMargin = dp(24)
            },
        )

        val scrollView = android.widget.ScrollView(this).apply {
            setBackgroundColor(android.graphics.Color.parseColor("#111827"))
            addView(content)
        }

        rootLayout.addView(scrollView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        screenErrorView = scrollView
    }

    // A "redirect" field means PHP wants the client on a different screen
    // than the one it just rendered (LoginPage.php's onLogin() returning
    // a path, translated to this architecture — see public/index.php's
    // handling). Swap the stack's top entry and re-fetch instead of
    // drawing the stale response.
    private fun applyResponse(json: String, screenWidthDp: Float, isNavigation: Boolean) {
        connectionErrorView?.visibility = android.view.View.GONE
        screenErrorView?.let { rootLayout.removeView(it) }
        screenErrorView = null
        if (isNavigation) lastAppliedHash = null

        // {"unchanged":true} — PHP determined its output would be byte-
        // identical to the lastHash= this same request sent, so it skipped
        // building the full payload entirely. Nothing to parse, nothing
        // to redraw: the screen already shows this exact content.
        if (json.contains("\"unchanged\":true")) {
            firstScreenRendered = true
            if (devToolsPanel != null) updateDevToolsPanel(screenStack.lastOrNull() ?: "?", wasUnchanged = true)
            return
        }

        val redirect = Regex("\"redirect\":\"([a-zA-Z0-9_/]+)\"").find(json)?.groupValues?.get(1)
        if (redirect != null && screenStack.isNotEmpty()) {
            screenStack[screenStack.size - 1] = redirect
            refetch(action = null, isNavigation = true)
            return
        }
        canvasView.setCommands(json, screenWidthDp, isNavigation)
        syncLottieOverlays(canvasView.lottieRegions)
        firstScreenRendered = true
        scheduleTimedRefetch(json)
        // Canvas::triggerConfetti() (Confetti, dropped anywhere in the
        // tree) — fires automatically on whatever render included it, no
        // tap needed. Same raw-string regex check scheduleTimedRefetch()
        // just did above for autoNavigate/pollAgain, not a JSONObject
        // parse — this method already has the raw response string, no
        // need to parse it twice.
        if (json.contains("\"confetti\":true")) {
            showConfettiOverlay()
        }
        // Canvas::showSnackbar() — unlike confetti's plain boolean flag,
        // the message is arbitrary text (could contain quotes, emoji,
        // anything), which a regex has no business trying to parse
        // correctly — a real JSONObject parse is the only reliable way
        // to pull it back out.
        val snackbar = try {
            JSONObject(json).optJSONObject("snackbar")
        } catch (e: org.json.JSONException) {
            null
        }
        if (snackbar != null) {
            showSnackbarOverlay(snackbar.optString("message"), snackbar.optLong("durationMs", 3000L))
        }
        lastAppliedHash = Regex("\"hash\":\"([0-9a-f]+)\"").find(json)?.groupValues?.get(1)
        if (devToolsPanel != null) updateDevToolsPanel(screenStack.lastOrNull() ?: "?", wasUnchanged = false)
    }

    // Splash emits an "autoNavigate":{"screen":"...","afterMs":N}
    // field so a splash screen can push itself to its target screen once
    // its animation has had time to play, without the user tapping
    // anything. Any previously queued jump is cancelled first — if this
    // same screen re-renders without the field (a real navigation already
    // happened, or a splash re-render came from something else), the stale
    // jump must not fire on top of wherever the user is now. Same handler
    // (and same cancel-first discipline) covers Async's
    // "pollAgain":N field — a poll never mutates screenStack, it just
    // refetches the SAME screen so AsyncTask::poll() gets asked again.
    // Only one of the two fields can ever be present in a given response
    // (autoNavigate wins if somehow both were), matching "a screen only
    // ever wants to schedule one timed thing" from autoNavigate()'s own
    // docblock.
    private fun scheduleTimedRefetch(json: String) {
        autoNavigateHandler.removeCallbacksAndMessages(null)

        val autoNav = Regex("\"autoNavigate\":\\{\"screen\":\"([a-zA-Z0-9_/]+)\",\"afterMs\":([0-9]+)\\}").find(json)
        if (autoNav != null) {
            val (screen, afterMs) = autoNav.destructured
            autoNavigateHandler.postDelayed({
                clearTextInput()
                if (screenStack.isNotEmpty()) screenStack[screenStack.size - 1] = screen else screenStack.add(screen)
                refetch(action = null, isNavigation = true)
            }, afterMs.toLong())
            return
        }

        val pollAgain = Regex("\"pollAgain\":([0-9]+)").find(json) ?: return
        val afterMs = pollAgain.groupValues[1].toLong()
        autoNavigateHandler.postDelayed({
            refetch(action = null, isNavigation = false, isPoll = true)
        }, afterMs)
    }

    companion object {
        private const val TAG = "NativeRenderPoc"
        private const val STATE_SCREEN_STACK = "screenStack"

        // `phpx dev:push` -> HotReloadReceiver's only way to reach a live
        // Activity instance (a manifest-registered BroadcastReceiver is
        // instantiated fresh per broadcast, with no reference of its own to
        // whatever Activity is on screen). WeakReference so a killed/
        // recreated Activity can't be kept alive by this static field.
        var hotReloadInstance: java.lang.ref.WeakReference<NativeRenderPocActivity>? = null
    }

    // Re-fetches the current screen with isNavigation = false — same
    // instant, no-flash path a counter increment already takes. Edited PHP
    // was just pushed straight into filesDir/www (see PhpServer.kt), and
    // `php -S` recompiles straight off disk with no persistent opcache, so
    // this refetch is already hitting the new code. No Activity restart:
    // screenStack and the PHP session are both untouched.
    fun hotReload() {
        Log.i(TAG, "Hot reload: refetching current screen")
        refetch(action = null, isNavigation = false)
    }

    // android:launchMode="singleTask" (AndroidManifest.xml, needed so
    // repeated launcher-icon taps resume the existing instance instead of
    // stacking a new one) means a fresh "screen" extra — e.g.
    // WebAppInterface.openNativeRenderPreviewAt() jumping back from a
    // WebView-only screen — arrives here instead of a new onCreate() when
    // this Activity is already running. Push it the same way "navigate:"
    // does rather than silently resuming wherever the stack already was.
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)

        if (handleOAuthCallback(intent)) {
            return
        }

        if (handleNfcIntent(intent)) {
            return
        }

        val screen = deepLinkScreenToken(intent) ?: intent.getStringExtra("screen") ?: return
        clearTextInput()
        screenStack.add(screen)
        refetch(action = null, isNavigation = true)
    }

    // phpnitro://product?id=42 -> "product?id=42", the exact screenStack
    // token shape fetchDrawCommands() already knows how to split
    // ("name?query" — see its own comment above screenToken). A deep link
    // is just another way to arrive at an ordinary screen, not a separate
    // code path on the PHP side, same principle as MainActivity's own
    // deepLinkPath() for the legacy WebView shell. host, not path, holds
    // the first segment (standard scheme://authority/path parsing —
    // confirmed against MainActivity's identical case), so the screen
    // name is read from uri.host / uri.path, and route params from
    // uri.query — a real query string, not positional path segments (a
    // deep link's own params use exactly the same "?id=42&tab=reviews"
    // shape navigate: does, decoded once here, re-encoded once in
    // fetchDrawCommands() same as any other route param). Returns null
    // for a non-phpnitro URI, a bare "phpnitro://" with nothing after it,
    // and deliberately for host="oauth-callback" (handleOAuthCallback()
    // owns that one).
    private fun deepLinkScreenToken(intent: Intent?): String? {
        val uri = intent?.data ?: return null
        if (uri.scheme != "phpnitro") return null
        if (uri.host == "oauth-callback") return null

        val screen = "${uri.host.orEmpty()}${uri.path.orEmpty()}".trim('/')
        if (screen.isEmpty()) return null

        return if (uri.query.isNullOrEmpty()) screen else "$screen?${uri.query}"
    }

    // phpnitro://oauth-callback?code=...&state=... (or &error=...) — see
    // AndroidManifest.xml's matching intent-filter and NativeDeviceBridge's
    // startOAuthFlow(). state travels back to PHP verbatim; PHP is the one
    // that actually verifies it against what it stored in $_SESSION when
    // it built the authorize URL (see public/index.php's "oauth_callback:"
    // handling) — this method doesn't validate anything itself, it's pure
    // transport, same "PHP decides, Kotlin just reports" split as every
    // other capability here.
    private fun handleOAuthCallback(intent: Intent): Boolean {
        val uri = intent.data ?: return false
        if (uri.scheme != "phpnitro" || uri.host != "oauth-callback") return false

        val provider = pendingOAuthProvider
        pendingOAuthProvider = null
        if (provider == null) {
            Log.e(TAG, "oauth-callback received with no pending provider")
            return true
        }

        val code = uri.getQueryParameter("code")
        if (code != null) {
            fieldValues["oauth_code"] = code
            fieldValues["oauth_state"] = uri.getQueryParameter("state") ?: ""
            refetch("oauth_callback:$provider", includeFields = true)
        } else {
            fieldValues["oauth_error"] = uri.getQueryParameter("error") ?: "Connexion annulée."
            refetch(action = null, includeFields = true)
        }
        return true
    }

    // Same tag-reading logic as MainActivity.handleNfcIntent() — an NDEF
    // text record's payload starts with a status byte + language-code
    // length header this strips for the common case (UTF-8, short
    // language code), not a full NDEF text-record parser.
    private fun handleNfcIntent(intent: Intent): Boolean {
        if (!nfcListening) return false
        if (intent.action !in setOf(
                android.nfc.NfcAdapter.ACTION_NDEF_DISCOVERED,
                android.nfc.NfcAdapter.ACTION_TECH_DISCOVERED,
                android.nfc.NfcAdapter.ACTION_TAG_DISCOVERED,
            )
        ) {
            return false
        }

        @Suppress("DEPRECATION")
        val tag: android.nfc.Tag? = intent.getParcelableExtra(android.nfc.NfcAdapter.EXTRA_TAG)
        val tagId = tag?.id?.joinToString("") { "%02X".format(it) } ?: ""
        val text = try {
            android.nfc.tech.Ndef.get(tag)?.let { ndef ->
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

        fieldValues["nfc_out"] = if (tagId.isEmpty()) "Tag lu" else "$tagId${if (text.isNotEmpty()) " — $text" else ""}"
        refetch(action = null, includeFields = true)
        return true
    }

    override fun onResume() {
        super.onResume()
        hotReloadInstance = java.lang.ref.WeakReference(this)
        if (nfcListening) enableNfcForegroundDispatch()
        activeMapView?.onResume()
    }

    private fun enableNfcForegroundDispatch() {
        nfcAdapter?.let { adapter ->
            val intent = Intent(this, javaClass).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
            val pendingIntent = android.app.PendingIntent.getActivity(this, 0, intent, android.app.PendingIntent.FLAG_MUTABLE)
            adapter.enableForegroundDispatch(this, pendingIntent, null, null)
        }
    }

    override fun onPause() {
        super.onPause()
        if (hotReloadInstance?.get() === this) hotReloadInstance = null
        nfcAdapter?.disableForegroundDispatch(this)
        activeMapView?.onPause()
    }

    // Android can recover a killed background process's Activity later
    // with this same Bundle handed back to onCreate() — the PHP session
    // itself already survives that (see PersistentCookieStore), but
    // "which screen was on top" only ever lived in this in-memory list
    // until now, so a real process kill used to always land back on the
    // launch screen even when the server-side session picked up right
    // where it left off underneath it.
    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        outState.putStringArrayList(STATE_SCREEN_STACK, ArrayList(screenStack))
    }

    override fun onDestroy() {
        autoNavigateHandler.removeCallbacksAndMessages(null)
        // Remote mode never constructs phpServer at all (see onCreate()) —
        // ::phpServer.isInitialized guards against UninitializedPropertyAccessException.
        if (::phpServer.isInitialized) phpServer.stop()
        // Deliberately unbindService() only, never stopService() — the
        // WebSocket connection was independently STARTED (see
        // ensureWebSocketServiceBound()), so it keeps running across this
        // Activity being destroyed (rotation, process death, swiping the
        // app from recents). Only an explicit "device:wsdisconnect" ever
        // calls stopService() on it.
        if (webSocketBound) {
            unbindService(webSocketConnection)
            webSocketBound = false
        }
        super.onDestroy()
    }
}
