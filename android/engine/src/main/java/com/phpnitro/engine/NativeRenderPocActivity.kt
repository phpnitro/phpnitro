package com.phpnitro.engine

import android.app.AlertDialog
import android.app.DatePickerDialog
import android.app.TimePickerDialog
import android.content.Context
import android.content.Intent
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
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
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
 * "navigate:otp", or "navigate:product/42" for a route param — mirrors
 * ProductPage.php's '/product/{id}') pushes that token onto a local back
 * stack and re-fetches — this Activity is what owns "which screen is
 * current", not PHP (each /native/layout-demo request is a stateless
 * render of whichever ?screen=&id= it's given). Plain "back" — or the
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

    override fun onCreate(savedInstanceState: Bundle?) {
        // Same native SplashScreen MainActivity uses (Theme.App.Starting,
        // themes.xml) — stays up exactly until the PHP server is bound and
        // the first screen has actually rendered, now that this Activity
        // is the app's real launcher (see AndroidManifest.xml's
        // MainActivityDefault/Alt aliases) rather than an adb-only preview.
        val splashScreen = installSplashScreen()
        super.onCreate(savedInstanceState)
        splashScreen.setKeepOnScreenCondition { serverPort == 0 || !firstScreenRendered }

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
            screenStack.add(intent.getStringExtra("screen") ?: "home")
        }

        canvasView = NativeCanvasView(this)
        canvasView.density = resources.displayMetrics.density
        canvasView.onAction = { action, regionDp, meta -> onTap(action, regionDp, meta) }
        // LazyList screens: scrolled near the edge of the currently
        // loaded window, re-fetch with the new scrollY so PHP can build
        // the next one. No screen state changes (action stays null), same
        // idiom as a plain re-render with the field values already held.
        canvasView.onScrollFollow = { refetch(action = null, includeFields = true) }

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
            "vibrate" -> deviceBridge.vibrate(200)
            "torch" -> deviceBridge.toggleTorch()
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
            "securestore" -> deviceBridge.secureStore(parts.getOrElse(1) { "demo_key" }, "valeur secrète")
            "secureretrieve" -> {
                val key = parts.getOrElse(1) { "demo_key" }
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
            "sound" -> deviceBridge.playSound("http://$serverHost:$serverPort/assets/audio/beep.wav")
            "notify" -> deviceBridge.showNotification("PhpNitro", "Ceci est une notification native.")
            "share" -> deviceBridge.share("Regarde cette app faite avec PhpNitro !", "PhpNitro Demo")
            "appicon" -> deviceBridge.setAppIcon(parts.getOrElse(1) { "default" })
            // Manual "🎉 encore" replay button — Confetti::triggerAction().
            // The automatic case (Canvas::triggerConfetti(), a widget
            // dropped in the tree) is handled in setCommands() instead,
            // since that one has to fire on every matching render, not
            // just a tap.
            "confetti" -> showConfettiOverlay()
            "brightness" -> deviceBridge.setBrightness(0.5f)
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
                deviceBridge.recordAudioClip(2000L) { _, error ->
                    fieldValues[parts.getOrElse(1) { "mic_out" }] = if (error != null) error else "Enregistré (2s)"
                    refetch(action = null, includeFields = true)
                }
            }
            "camera" -> takePicturePreview.launch(null)
            "pickimage" -> pickImage.launch("image/*")
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
                deviceBridge.queryProducts(listOf("demo_product")) { result ->
                    fieldValues[parts.getOrElse(1) { "iap_out" }] = result
                    refetch(action = null, includeFields = true)
                }
            }
            "iappurchase" -> deviceBridge.purchaseProduct("demo_product")
            "geofenceadd" -> deviceBridge.addGeofence("paris_demo", 48.8566, 2.3522, 200f)
            "geofenceremove" -> deviceBridge.removeGeofence("paris_demo")
            "bgschedule" -> deviceBridge.scheduleBackgroundTask("/api/ping", 15)
            "bgcancel" -> deviceBridge.cancelBackgroundTask()
            "printpdf" -> printCurrentScreen()
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

    private fun isDebuggable(): Boolean =
        (applicationInfo.flags and android.content.pm.ApplicationInfo.FLAG_DEBUGGABLE) != 0

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
        // "product/42" -> screen=product, id=42 — a route-param screen
        // token is just "name/param", split once at fetch time rather
        // than teaching screenStack about a richer shape.
        val screenToken = screenStack.last()
        val screen = screenToken.substringBefore('/')
        val screenParam = screenToken.substringAfter('/', missingDelimiterValue = "").ifEmpty { null }
        val idParam = if (screenParam != null) "&id=${URLEncoder.encode(screenParam, "UTF-8")}" else ""
        val actionParam = if (action != null) "&action=${URLEncoder.encode(action, "UTF-8")}" else ""
        val onlineParam = "&online=${if (deviceBridge.isOnline()) 1 else 0}"
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
            val connection = URL("http://$serverHost:$port/native/layout-demo?width=$screenWidthDp&height=$screenHeightDp&screen=$screen$idParam$actionParam$onlineParam$scrollYParam$fieldsParam$lastHashParam").openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            Log.i(TAG, "Fetching /native/layout-demo (screen=$screen, action=$action), response code ${connection.responseCode}")
            val json = connection.inputStream.bufferedReader().use { it.readText() }
            connection.disconnect()
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

    // A "redirect" field means PHP wants the client on a different screen
    // than the one it just rendered (LoginPage.php's onLogin() returning
    // a path, translated to this architecture — see public/index.php's
    // handling). Swap the stack's top entry and re-fetch instead of
    // drawing the stale response.
    private fun applyResponse(json: String, screenWidthDp: Float, isNavigation: Boolean) {
        connectionErrorView?.visibility = android.view.View.GONE
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

        val screen = intent.getStringExtra("screen") ?: return
        clearTextInput()
        screenStack.add(screen)
        refetch(action = null, isNavigation = true)
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
        super.onDestroy()
    }
}
