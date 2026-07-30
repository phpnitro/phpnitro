package com.mobile.engine

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
    private val screenStack = mutableListOf<String>()
    private val fieldValues = mutableMapOf<String, String>()
    private var activeEditText: EditText? = null
    private val deviceBridge by lazy { NativeDeviceBridge(this) }
    private var firstScreenRendered = false

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
        // RenderDismissible/RenderReorderable's demo state, anything a
        // screen persists server-side) silently never actually persisted
        // across taps — caught only by testing on a real device, since
        // every curl-based verification this project's history used its
        // own -c/-b cookie jar and never exercised this path. One
        // process-wide CookieManager fixes it for every HttpURLConnection
        // this Activity ever makes, no per-call plumbing needed.
        if (java.net.CookieHandler.getDefault() == null) {
            java.net.CookieHandler.setDefault(java.net.CookieManager(null, java.net.CookiePolicy.ACCEPT_ALL))
        }

        // osmdroid's tile server ToS requires a real user agent — the
        // package name identifies which app is pulling tiles, same as
        // any other OSM client is expected to set.
        org.osmdroid.config.Configuration.getInstance().userAgentValue = packageName

        screenStack.add(intent.getStringExtra("screen") ?: "home")

        canvasView = NativeCanvasView(this)
        canvasView.density = resources.displayMetrics.density
        canvasView.onAction = { action, regionDp, meta -> onTap(action, regionDp, meta) }
        // RenderLazyList screens: scrolled near the edge of the currently
        // loaded window, re-fetch with the new scrollY so PHP can build
        // the next one. No screen state changes (action stays null), same
        // idiom as a plain re-render with the field values already held.
        canvasView.onScrollFollow = { refetch(action = null, includeFields = true) }

        rootLayout = FrameLayout(this)
        rootLayout.addView(canvasView, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT))
        setContentView(rootLayout)

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

        phpServer = PhpServer(this)
        thread {
            val port = phpServer.start()
            serverPort = port
            Log.i(TAG, "PhpServer started on port $port")
            refetch(action = null, isNavigation = true)
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
            action.startsWith("select:") -> showSelectDialog(action.removePrefix("select:"), meta)
            action.startsWith("datepicker:") -> showDatePickerDialog(action.removePrefix("datepicker:"), meta)
            action.startsWith("timepicker:") -> showTimePickerDialog(action.removePrefix("timepicker:"), meta)
            action.startsWith("toggle:") -> {
                fieldValues[action.removePrefix("toggle:")] = meta?.optString("next", "") ?: ""
                refetch(action = null, includeFields = true)
            }
            action == "dialog:alert" -> showAlertDialog(meta)
            action == "dialog:confirm" -> showConfirmDialog(meta)
            action.startsWith("navigate:") -> {
                clearTextInput()
                screenStack.add(action.removePrefix("navigate:"))
                refetch(action = null, isNavigation = true)
            }
            // A NativeBottomNavigation tab switch — resets the whole stack
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
    // hit region's meta (see NativeCanvas::hitRegion()'s $meta param) — no
    // second round-trip to PHP is needed just to know what to show. A pick
    // is tracked the same way NativeTextField's typed value is: written
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
    // JS confirm() shim — what NativeAlertButton exists to get for a native
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
     * with pill-shaped buttons matching NativeButton's own shape/colors
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
    // NativeTextField uses, reusing "a value PHP reads via $_GET on the
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
            "sound" -> deviceBridge.playSound("http://127.0.0.1:$serverPort/assets/audio/beep.wav")
            "notify" -> deviceBridge.showNotification("PhpNitro", "Ceci est une notification native.")
            "share" -> deviceBridge.share("Regarde cette app faite avec PhpNitro !", "PhpNitro Demo")
            "appicon" -> deviceBridge.setAppIcon(parts.getOrElse(1) { "default" })
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
            if (multiline) {
                gravity = android.view.Gravity.TOP or android.view.Gravity.START
            }
            textSize = 15f
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
    // different screen, so a playing NativeVideoPlayer doesn't keep
    // playing (or leak its overlay View) underneath whatever renders next.
    private fun clearTextInput() {
        activeEditText?.let { rootLayout.removeView(it) }
        activeEditText = null
        (getSystemService(INPUT_METHOD_SERVICE) as InputMethodManager)
            .hideSoftInputFromWindow(canvasView.windowToken, 0)
        clearVideoOverlay()
        clearMapOverlay()
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

    // isNavigation gates NativeCanvasView's whole-screen crossfade
    // (startCrossfade()) — true for an actual scene change (navigate:/
    // tab:/back, or a server-side redirect), false for every other
    // refetch (a toggle, a counter increment, a field update). Without
    // this split, EVERY tap fades the entire screen out and back in even
    // when only one piece of text changed, which reads as "the screen
    // just reloaded" rather than "the counter went up" — RenderHero/
    // RenderAnimated's per-element transitions are unaffected either way,
    // since those are opt-in and driven by their own tag matching, not
    // this blanket fade.
    private fun refetch(action: String?, includeFields: Boolean = false, isNavigation: Boolean = false) {
        if (serverPort == 0) return
        thread { fetchDrawCommands(serverPort, action, includeFields, isNavigation) }
    }

    private fun fetchDrawCommands(port: Int, action: String?, includeFields: Boolean = false, isNavigation: Boolean = false) {
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
        // RenderLazyList's windowed prefetch needs to know where the user
        // actually is in the virtual list to build the right window —
        // harmless for every other screen, which simply never reads it.
        val scrollYParam = "&scroll_y=${canvasView.currentScrollYDp}"
        val fieldsParam = if (includeFields) {
            fieldValues.entries.joinToString("") { (name, value) -> "&${URLEncoder.encode(name, "UTF-8")}=${URLEncoder.encode(value, "UTF-8")}" }
        } else {
            ""
        }
        // Point 3 of the "grow the framework" pass: a real performance
        // number, not an intuition. roundTripMs is tap-to-parsed-frame —
        // HTTP + PHP compute + JSON parse — everything except the actual
        // Canvas draw (that's onDraw's own concern, already logged
        // separately). PHP's own renderTimeMs rides in the response body,
        // so a slow frame here can be split into "PHP was slow" vs
        // "network/parse overhead" instead of one opaque total.
        val startNanos = System.nanoTime()
        try {
            val connection = URL("http://127.0.0.1:$port/native/layout-demo?width=$screenWidthDp&height=$screenHeightDp&screen=$screen$idParam$actionParam$onlineParam$scrollYParam$fieldsParam").openConnection() as HttpURLConnection
            connection.connectTimeout = 5000
            Log.i(TAG, "Fetching /native/layout-demo (screen=$screen, action=$action), response code ${connection.responseCode}")
            val json = connection.inputStream.bufferedReader().use { it.readText() }
            connection.disconnect()
            val roundTripMs = (System.nanoTime() - startNanos) / 1_000_000.0
            val renderTimeMs = Regex("\"renderTimeMs\":([0-9.]+)").find(json)?.groupValues?.get(1)?.toDoubleOrNull()
            Log.i(TAG, "PERF screen=$screen roundTripMs=${"%.1f".format(roundTripMs)} phpRenderTimeMs=${renderTimeMs?.let { "%.2f".format(it) } ?: "?"}")

            Handler(Looper.getMainLooper()).post { applyResponse(json, screenWidthDp, isNavigation) }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to fetch draw commands", e)
        }
    }

    // A "redirect" field means PHP wants the client on a different screen
    // than the one it just rendered (LoginPage.php's onLogin() returning
    // a path, translated to this architecture — see public/index.php's
    // handling). Swap the stack's top entry and re-fetch instead of
    // drawing the stale response.
    private fun applyResponse(json: String, screenWidthDp: Float, isNavigation: Boolean) {
        val redirect = Regex("\"redirect\":\"([a-zA-Z0-9_/]+)\"").find(json)?.groupValues?.get(1)
        if (redirect != null && screenStack.isNotEmpty()) {
            screenStack[screenStack.size - 1] = redirect
            refetch(action = null, isNavigation = true)
            return
        }
        canvasView.setCommands(json, screenWidthDp, isNavigation)
        firstScreenRendered = true
    }

    companion object {
        private const val TAG = "NativeRenderPoc"
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

        if (handleNfcIntent(intent)) {
            return
        }

        val screen = intent.getStringExtra("screen") ?: return
        clearTextInput()
        screenStack.add(screen)
        refetch(action = null, isNavigation = true)
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
        nfcAdapter?.disableForegroundDispatch(this)
        activeMapView?.onPause()
    }

    override fun onDestroy() {
        phpServer.stop()
        super.onDestroy()
    }
}
