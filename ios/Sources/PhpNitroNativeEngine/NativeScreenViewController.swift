import PhpNitroProtocol
import UIKit

/// The iOS counterpart of NativeRenderPocActivity.kt — deliberately the
/// minimal slice: hosts one NativeCanvasView, fetches one screen via
/// ScreenClient on load, refetches on every tap via NativeCanvasView's
/// own onAction, and runs ScreenNavigation.reduce(_:_:) against
/// `screenStack` to decide what that refetch should actually be (a plain
/// same-screen action, a navigate:/tab:/back stack change, or a fully
/// local clientTab switch with no fetch at all). No polling, no
/// intercepting the OS-level swipe-back gesture (see ScreenNavigation's
/// own docblock for the full list of what's deferred) — see
/// ScreenClient's own docblock and ios/README.md for what's real,
/// separate follow-up work.
public final class NativeScreenViewController: UIViewController {
    private let client: ScreenDataSource
    private var screenStack: [String]
    private let canvasView = NativeCanvasView()

    /// Mirrors NativeRenderPocActivity.kt's own `fieldValues` — a
    /// TextField's current text, or any other widget's own output slot,
    /// sent on the next fetch. Written on every keystroke by
    /// `NativeCanvasView`'s own text-input overlay (see `handle(action:
    /// rect:)`'s `focus:` branch below) via `setFieldValue(_:forName:)`.
    private var fieldValues: [String: String] = [:]

    private let errorView = ScreenErrorView()

    /// Guards the first fetch, now fired from viewDidLayoutSubviews()
    /// instead of viewDidLoad() — see that method's own doc comment for
    /// why this moved.
    private var hasFetchedOnce = false

    #if DEBUG
    /// See DevToolsOverlay's own docblock for why this whole feature is
    /// compiled out of a release build entirely, not just hidden.
    private let devTools = DevToolsOverlay()
    #endif

    /// Kept alongside `client` (which keeps its own private copy) only
    /// for building `device:sound:`'s default URL and `bgschedule`'s
    /// real background HTTP endpoint — nil when `client` is an
    /// `EmbeddedScreenDataSource`, since there is no "this app's own
    /// server" to point at (see `defaultSoundURL`/`bgschedule` below for
    /// how each of those two call sites degrades instead of crashing).
    private let host: String?
    private let port: Int?

    /// The real, per-project app's own path: talk to `PhpEmbedRuntime`
    /// in-process (see `EmbeddedScreenDataSource`'s own docblock for why
    /// `.shared`) — no developer machine running `phpx serve` required
    /// at all. This is what `HostApp` (not `GoHostApp`) is built to use.
    public init(embeddedScreen screen: String = "home") {
        self.client = EmbeddedScreenDataSource.shared
        self.host = nil
        self.port = nil
        self.screenStack = [screen]
        super.init(nibName: nil, bundle: nil)
    }

    /// The companion-client path: a real HTTP round-trip to a `phpx
    /// serve` running somewhere on the LAN — no PHP bundled into this
    /// app at all. This is what `PhpNitroGo`/`GoHostApp` uses (see
    /// `ConnectViewController`) — a project-agnostic "point me at any
    /// running phpx serve" tool, deliberately never switched to the
    /// embedded path the way `HostApp` is above.
    public init(host: String, port: Int, screen: String = "home") {
        self.client = ScreenClient(host: host, port: port)
        self.host = host
        self.port = port
        self.screenStack = [screen]
        super.init(nibName: nil, bundle: nil)
    }

    public required init?(coder: NSCoder) {
        fatalError("NativeScreenViewController is always created with init(embeddedScreen:) or init(host:port:screen:), not from a storyboard.")
    }

    /// "device:sound:" with no explicit URL — a real `http://host:port/…`
    /// URL when `client` talks to a real server, or the exact same
    /// bundled asset `phpx bundle:ios` already stages into
    /// `Resources/www/public/assets/audio/beep.wav` (see
    /// `PhpEmbedRuntime.wwwDirectoryURL`) played straight from disk via
    /// `file://` when there's no server to fetch it from at all —
    /// `AVPlayer` (`NativeDeviceBridge.playSound`) plays a local file URL
    /// exactly the same way it plays a remote one.
    private var defaultSoundURLString: String {
        if let host, let port {
            return "http://\(host):\(port)/assets/audio/beep.wav"
        }
        guard let assetURL = PhpEmbedRuntime.wwwDirectoryURL?
            .appendingPathComponent("public/assets/audio/beep.wav") else {
            return ""
        }
        return assetURL.absoluteString
    }

    override public func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        // Real layout bug found the first time this was compared
        // side-by-side against a booted Android emulator (2026-09-09):
        // every Android activity here runs under
        // `Theme.AppCompat.DayNight.NoActionBar` (fully edge-to-edge,
        // see android/engine/src/main/res/values/themes.xml) — this
        // engine draws its own in-canvas app bars and back buttons
        // (Canvas::appBar(), the "back" hitRegion) and never needs a
        // second, system-drawn one. A UINavigationController's nav bar
        // was left at its default visible state, pushing every screen's
        // content down by its own height for nothing — a real gap
        // between the status bar and the canvas' own drawn content that
        // Android's equivalent screenshot never had.
        navigationController?.setNavigationBarHidden(true, animated: animated)
    }

    override public func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .white

        canvasView.translatesAutoresizingMaskIntoConstraints = false
        canvasView.onAction = { [weak self] action, rect in self?.handle(action: action, rect: rect) }
        canvasView.onFieldValueChanged = { [weak self] name, value in self?.setFieldValue(value, forName: name) }
        #if DEBUG
        canvasView.onInspect = { [weak self] action, rect in self?.showInspectResult(action: action, rect: rect) }
        #endif
        view.addSubview(canvasView)
        NSLayoutConstraint.activate([
            canvasView.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            canvasView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            canvasView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            canvasView.bottomAnchor.constraint(equalTo: view.bottomAnchor),
        ])

        errorView.translatesAutoresizingMaskIntoConstraints = false
        errorView.isHidden = true
        errorView.onRetry = { [weak self] in self?.fetch(action: nil) }
        view.addSubview(errorView)
        NSLayoutConstraint.activate([
            errorView.topAnchor.constraint(equalTo: view.topAnchor),
            errorView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            errorView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            errorView.bottomAnchor.constraint(equalTo: view.bottomAnchor),
        ])

        #if DEBUG
        devTools.translatesAutoresizingMaskIntoConstraints = false
        devTools.onToggleInspect = { [weak self] in self?.toggleInspectMode() }
        view.addSubview(devTools)
        NSLayoutConstraint.activate([
            devTools.topAnchor.constraint(equalTo: view.topAnchor),
            devTools.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            devTools.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            devTools.bottomAnchor.constraint(equalTo: view.bottomAnchor),
        ])
        #endif
    }

    #if DEBUG
    private func toggleInspectMode() {
        canvasView.inspectMode.toggle()
        devTools.setInspecting(canvasView.inspectMode)
    }

    private func showInspectResult(action: String, rect: CGRect) {
        devTools.setInspecting(false)
        let message = String(
            format: "action: %@\nbounds: x=%.1f y=%.1f w=%.1f h=%.1f",
            action, rect.origin.x, rect.origin.y, rect.width, rect.height
        )
        let alert = UIAlertController(title: "🔍 Widget inspecté", message: message, preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "OK", style: .default))
        present(alert, animated: true)
    }
    #endif

    override public func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        // The very first fetch used to fire from viewDidLoad(), before
        // Auto Layout had ever run — canvasView.bounds was still .zero
        // then, so fetch(action:) fell back to UIScreen.main.bounds
        // (the FULL device height, status bar included). That mismatch
        // is exactly what broke the bottom tab bar (see fetch(action:)'s
        // own doc comment): PHP's "fixed to bottom" elements ended up
        // positioned a status-bar's-height too low for canvasView's own,
        // smaller real bounds, pushing them out of its drawable area
        // entirely. Firing the first fetch here instead — after layout
        // has actually run — means canvasView.bounds is correct by the
        // time PHP hears about it.
        if !hasFetchedOnce {
            hasFetchedOnce = true
            fetch(action: nil)
        }
    }

    /// For a future TextField overlay (or any other widget with its own
    /// output slot) to call — see `fieldValues`'s own docblock.
    public func setFieldValue(_ value: String, forName name: String) {
        fieldValues[name] = value
    }

    private func handle(action: String, rect: CGRect) {
        // focus: never reaches ScreenNavigation.reduce (no fetch at all,
        // entirely client-side — same "not funneled through the generic
        // reducer" treatment clientTab: gets) — matches
        // NativeRenderPocActivity.kt's own onTap(), which branches on
        // "focus:" before any of the actions that DO end in a refetch.
        if action.hasPrefix("focus:") {
            var rest = action.dropFirst("focus:".count)
            let multiline = rest.hasPrefix("multiline:")
            if multiline { rest = rest.dropFirst("multiline:".count) }
            let secure = rest.hasPrefix("secure:")
            if secure { rest = rest.dropFirst("secure:".count) }
            let fieldName = String(rest)
            canvasView.showTextInput(fieldName: fieldName, initialValue: fieldValues[fieldName] ?? "", rect: rect, multiline: multiline, secure: secure)
            return
        }

        // video:play:<url> (VideoPlayer.php) — same "entirely
        // client-side, no fetch at all" treatment as focus: above.
        if action.hasPrefix("video:play:") {
            let url = String(action.dropFirst("video:play:".count))
            canvasView.showVideoOverlay(url: url, rect: rect)
            return
        }

        // device:* (Engine\Device\* action-string builders, e.g.
        // Vibrate::vibrateAction()) — matches
        // NativeRenderPocActivity.kt's own handleDeviceAction(), which
        // branches on "device:" before anything else. Two shapes exist
        // there: "vibrate" (entirely client-side, no fetch at all — same
        // treatment focus:/video:play: get above) and the rest (torch/
        // battery/deviceid...), which write their result into
        // `fieldValues[outputFieldName]` and trigger a normal refetch so
        // PHP can render it — `fetch(action: nil)` already sends every
        // non-empty fieldValues entry (see ScreenClient's own docblock),
        // so that's the whole "includeFields" equivalent here, no
        // separate flag needed. Only these forty-eight exist so far
        // (2026-09-10) of Android's ~40 — see NativeDeviceBridge.swift's
        // own docblock on why this is starting small.
        if action.hasPrefix("device:") {
            let parts = action.dropFirst("device:".count).components(separatedBy: ":")
            switch parts.first {
            case "vibrate":
                NativeDeviceBridge.vibrate(milliseconds: parts.count > 1 ? Int(parts[1]) ?? 200 : 200)
            case "torch":
                let outField = parts.count > 1 ? parts[1] : "torch_out"
                fieldValues[outField] = NativeDeviceBridge.toggleTorch() ? "on" : "off"
                fetch(action: nil)
            case "battery":
                let outField = parts.count > 1 ? parts[1] : "battery_out"
                fieldValues[outField] = "\(NativeDeviceBridge.batteryLevel())%"
                fetch(action: nil)
            case "deviceid":
                let outField = parts.count > 1 ? parts[1] : "device_id_out"
                fieldValues[outField] = NativeDeviceBridge.deviceId()
                fetch(action: nil)
            case "bluetooth":
                let outField = parts.count > 1 ? parts[1] : "bt_out"
                NativeDeviceBridge.bluetoothState { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "securestore":
                // "device:securestore:<key>:<value>" — both rawurlencode()d
                // PHP-side (Engine\Device\SecureStorage::storeAction()).
                // Fire-and-forget, no output field, no refetch — matches
                // handleDeviceAction()'s own "securestore" branch exactly.
                let key = (parts.count > 1 ? parts[1] : "demo_key").removingPercentEncoding ?? ""
                let value = (parts.count > 2 ? parts[2] : "").removingPercentEncoding ?? ""
                NativeDeviceBridge.secureStore(key: key, value: value)
            case "secureretrieve":
                let key = (parts.count > 1 ? parts[1] : "demo_key").removingPercentEncoding ?? ""
                let outField = parts.count > 2 ? parts[2] : "secure_out"
                fieldValues[outField] = NativeDeviceBridge.secureRetrieve(key: key)
                fetch(action: nil)
            case "contacts":
                let outField = parts.count > 1 ? parts[1] : "contacts_out"
                let count = NativeDeviceBridge.contactsCount()
                fieldValues[outField] = count < 0 ? "Permission requise" : "\(count) contacts"
                fetch(action: nil)
            case "calendar":
                let outField = parts.count > 1 ? parts[1] : "calendar_out"
                let count = NativeDeviceBridge.upcomingEventsCount()
                fieldValues[outField] = count < 0 ? "Permission requise" : "\(count) événements"
                fetch(action: nil)
            case "sound":
                // "device:sound:<url>" — Engine\Device\Sound::playAction()
                // rawurlencode()s the URL; falls back to this app's own
                // demo asset when omitted, matching handleDeviceAction()'s
                // own "sound" branch default exactly.
                let urlString = (parts.count > 1 ? parts[1].removingPercentEncoding : nil)
                    ?? defaultSoundURLString
                NativeDeviceBridge.playSound(urlString)
            case "notify":
                let title = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "PhpNitro"
                let message = (parts.count > 2 ? parts[2].removingPercentEncoding : nil) ?? "Ceci est une notification native."
                NativeDeviceBridge.showNotification(title: title, message: message)
            case "share":
                let text = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "Regarde cette app faite avec PhpNitro !"
                let title = (parts.count > 2 ? parts[2].removingPercentEncoding : nil) ?? "PhpNitro"
                NativeDeviceBridge.share(text: text, title: title, from: self)
            case "brightness":
                NativeDeviceBridge.setBrightness(parts.count > 1 ? Float(parts[1]) ?? 0.5 : 0.5)
            case "connectivity":
                let outField = parts.count > 1 ? parts[1] : "connectivity_out"
                NativeDeviceBridge.isOnline { [weak self] online in
                    self?.fieldValues[outField] = online ? "online" : "offline"
                    self?.fetch(action: nil)
                }
            case "openurl":
                let url = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                if !url.isEmpty {
                    NativeDeviceBridge.openURL(url)
                }
            case "appicon":
                NativeDeviceBridge.setAppIcon(parts.count > 1 ? parts[1] : "default")
            case "clipboardcopy":
                let text = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                NativeDeviceBridge.clipboardCopy(text)
            case "clipboardpaste":
                let outField = parts.count > 1 ? parts[1] : "clipboard_out"
                fieldValues[outField] = NativeDeviceBridge.clipboardPaste()
                fetch(action: nil)
            case "sendemail":
                let to = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                let subject = (parts.count > 2 ? parts[2].removingPercentEncoding : nil) ?? ""
                let body = (parts.count > 3 ? parts[3].removingPercentEncoding : nil) ?? ""
                NativeDeviceBridge.sendEmail(to: to, subject: subject, body: body)
            case "appsettings":
                NativeDeviceBridge.openAppSettings()
            case "permission":
                let key = parts.count > 1 ? parts[1] : ""
                let outField = parts.count > 2 ? parts[2] : "permission_out"
                NativeDeviceBridge.requestPermission(key) { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "sensor":
                let outField = parts.count > 1 ? parts[1] : "sensor_out"
                NativeDeviceBridge.readAccelerometer { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "alarmschedule":
                let requestCode = parts.count > 1 ? Int(parts[1]) ?? 1 : 1
                let delaySeconds = parts.count > 2 ? Int(parts[2]) ?? 3600 : 3600
                let title = (parts.count > 3 ? parts[3].removingPercentEncoding : nil) ?? "Rappel"
                let message = (parts.count > 4 ? parts[4].removingPercentEncoding : nil) ?? ""
                NativeDeviceBridge.scheduleAlarm(requestCode: requestCode, delaySeconds: delaySeconds, title: title, message: message)
            case "inappreview":
                NativeDeviceBridge.requestInAppReview(from: self)
            case "biometric":
                let outField = parts.count > 1 ? parts[1] : "biometric_out"
                NativeDeviceBridge.authenticateBiometric { [weak self] success, message in
                    self?.fieldValues[outField] = success ? "Authentifié" : message
                    self?.fetch(action: nil)
                }
            case "pickimage":
                NativeDeviceBridge.pickImage(from: self) { [weak self] result in
                    self?.fieldValues["picked_image_out"] = result
                    self?.fetch(action: nil)
                }
            case "openmap":
                let lat = parts.count > 1 ? Double(parts[1]) ?? 0 : 0
                let lng = parts.count > 2 ? Double(parts[2]) ?? 0 : 0
                let label = (parts.count > 3 ? parts[3].removingPercentEncoding : nil) ?? ""
                NativeDeviceBridge.openMap(latitude: lat, longitude: lng, label: label)
            case "checkupdate":
                let outField = parts.count > 1 ? parts[1] : "update_out"
                fieldValues[outField] = NativeDeviceBridge.checkForUpdate()
                fetch(action: nil)
            case "locate":
                let outField = parts.count > 1 ? parts[1] : "location_out"
                NativeDeviceBridge.getLocation { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "pickfile":
                let outField = parts.count > 1 ? parts[1] : "file_out"
                NativeDeviceBridge.pickFile(from: self) { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "savefile":
                let fileName = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "phpnitro.txt"
                let content = (parts.count > 2 ? parts[2].removingPercentEncoding : nil) ?? ""
                let outField = parts.count > 3 ? parts[3] : "save_out"
                fieldValues[outField] = NativeDeviceBridge.saveFile(fileName: fileName, content: content)
                fetch(action: nil)
            case "restartapp":
                // RestartApp.php's own docblock: Android relaunches its
                // launcher Intent then kills the process outright — no
                // public iOS API does either (Apple rejects apps that
                // call exit()/abort() deliberately; there's no
                // "relaunch yourself" API at all). The closest safe
                // equivalent: drop back to a fresh screen stack and
                // fieldValues, the same "no prior state survives" effect
                // from the user's own point of view, without actually
                // killing the process.
                screenStack = ["home"]
                fieldValues = [:]
                fetch(action: nil)
            case "printpdf":
                printCurrentScreen()
            case "applink":
                let outField = parts.count > 1 ? parts[1] : "app_link_out"
                fieldValues[outField] = NativeDeviceBridge.lastAppLink
                fetch(action: nil)
            case "camera":
                NativeDeviceBridge.capturePhoto(from: self) { [weak self] result in
                    self?.fieldValues["photo_out"] = result
                    self?.fetch(action: nil)
                }
            case "mic":
                let outField = parts.count > 1 ? parts[1] : "mic_out"
                let durationMs = parts.count > 2 ? Int(parts[2]) ?? 2000 : 2000
                NativeDeviceBridge.recordAudioClip(durationMs: durationMs) { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "geofenceadd":
                let id = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "paris_demo"
                let lat = parts.count > 2 ? Double(parts[2]) ?? 0 : 0
                let lng = parts.count > 3 ? Double(parts[3]) ?? 0 : 0
                let radius = parts.count > 4 ? Double(parts[4]) ?? 200 : 200
                NativeDeviceBridge.addGeofence(id: id, latitude: lat, longitude: lng, radiusMeters: radius)
            case "geofenceremove":
                let id = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "paris_demo"
                NativeDeviceBridge.removeGeofence(id: id)
            case "wsconnect":
                let url = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                let outField = parts.count > 2 ? parts[2] : "ws_out"
                NativeDeviceBridge.connectWebSocket(urlString: url) { [weak self] message in
                    self?.fieldValues[outField] = message
                    self?.fetch(action: nil)
                }
            case "wssend":
                let message = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                NativeDeviceBridge.sendWebSocket(message)
            case "wsjoinroom":
                let room = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                NativeDeviceBridge.joinWebSocketRoom(room)
            case "wsleaveroom":
                let room = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                NativeDeviceBridge.leaveWebSocketRoom(room)
            case "wsdisconnect":
                NativeDeviceBridge.disconnectWebSocket()
            case "scanqr":
                let outField = parts.count > 1 ? parts[1] : "qr_out"
                NativeDeviceBridge.scanQrCode(from: self) { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "bgschedule":
                // BGTaskScheduler wakes THIS app in the background to run
                // a real HTTP fetch against `host:port` — meaningless
                // without one (see `host`/`port`'s own docblock): there
                // is no separate server to poll when `client` is an
                // EmbeddedScreenDataSource, and waking the embedded
                // runtime itself in the background is real, separate
                // follow-up work (it CAN run in-process while suspended-
                // then-woken, unlike a remote fetch, but wiring that up
                // is untested territory this change doesn't attempt) —
                // so this degrades to a no-op rather than crash on the
                // force-unwrap a `host`/`port`-taking call would need.
                if let host, let port {
                    let endpoint = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "/api/ping"
                    let intervalMinutes = parts.count > 2 ? Int(parts[2]) ?? 15 : 15
                    NativeDeviceBridge.scheduleBackgroundTask(endpoint: endpoint, intervalMinutes: intervalMinutes, host: host, port: port)
                }
            case "bgcancel":
                NativeDeviceBridge.cancelBackgroundTask()
            case "nfcstart":
                NativeDeviceBridge.startNfcListening()
            case "nfcstop":
                fieldValues["nfc_out"] = NativeDeviceBridge.lastNfcResult
                NativeDeviceBridge.stopNfcListening()
                fetch(action: nil)
            case "iapquery":
                let productId = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "demo_product"
                let outField = parts.count > 2 ? parts[2] : "iap_out"
                NativeDeviceBridge.queryProducts(productIds: [productId]) { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "iappurchase":
                let productId = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? "demo_product"
                NativeDeviceBridge.purchaseProduct(productId: productId)
            case "cropimage":
                let outField = parts.count > 1 ? parts[1] : "crop_out"
                NativeDeviceBridge.cropImage(from: self) { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "airplanemode":
                let outField = parts.count > 1 ? parts[1] : "airplane_out"
                fieldValues[outField] = NativeDeviceBridge.airplaneModeState()
                fetch(action: nil)
            case "wifi":
                let outField = parts.count > 1 ? parts[1] : "wifi_out"
                NativeDeviceBridge.wifiState { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "hotspot":
                let outField = parts.count > 1 ? parts[1] : "hotspot_out"
                fieldValues[outField] = NativeDeviceBridge.hotspotState()
                fetch(action: nil)
            case "wallpaper":
                let url = (parts.count > 1 ? parts[1].removingPercentEncoding : nil) ?? ""
                let outField = parts.count > 2 ? parts[2] : "wallpaper_out"
                NativeDeviceBridge.setWallpaper(imageUrl: url) { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "filesapp":
                NativeDeviceBridge.openFilesApp()
            case "health":
                let outField = parts.count > 1 ? parts[1] : "health_out"
                NativeDeviceBridge.healthStepCount { [weak self] result in
                    self?.fieldValues[outField] = result
                    self?.fetch(action: nil)
                }
            case "reminders":
                let outField = parts.count > 1 ? parts[1] : "reminders_out"
                NativeDeviceBridge.remindersCount { [weak self] count in
                    self?.fieldValues[outField] = count < 0 ? "Permission requise" : "\(count) rappels"
                    self?.fetch(action: nil)
                }
            case "apn":
                let outField = parts.count > 1 ? parts[1] : "apn_out"
                fieldValues[outField] = NativeDeviceBridge.apnName()
                fetch(action: nil)
            default:
                break
            }
            return
        }

        // map:open:<lat>:<lon>:<zoom> (MapView.php) — same "entirely
        // client-side, no fetch at all" treatment as focus: above.
        // Fallback values mirror NativeRenderPocActivity.kt's own
        // showMapOverlay dispatch exactly (Paris, zoom 14).
        if action.hasPrefix("map:open:") {
            let parts = action.dropFirst("map:open:".count).components(separatedBy: ":")
            let latitude = Double(parts.count > 0 ? parts[0] : "") ?? 48.8566
            let longitude = Double(parts.count > 1 ? parts[1] : "") ?? 2.3522
            let zoom = Int(parts.count > 2 ? parts[2] : "") ?? 14
            canvasView.showMapOverlay(latitude: latitude, longitude: longitude, zoom: zoom, rect: rect)
            return
        }

        switch ScreenNavigation.reduce(action: action, stack: screenStack) {
        case .clientTabOnly(let key, let index):
            canvasView.setClientTab(key, index: index)
        case .fieldUpdate:
            // Never produced here — this call site never passes
            // `metaJson` to `reduce()` (NativeCanvasView.onAction carries
            // no meta at all), so `toggle:` always falls through to the
            // `.fetch` case below instead, unchanged from before this
            // case existed. See ScreenNavigation.swift's own doc comment.
            break
        case .fetch(let stack, let fetchAction):
            screenStack = stack
            fetch(action: fetchAction)
        }
    }

    private func fetch(action: String?) {
        // canvasView.bounds, NOT UIScreen.main.bounds — PHP positions
        // "fixed" elements (the bottom tab bar, a FAB) assuming the
        // height it's told IS the real drawable height. UIScreen's own
        // bounds include the status bar that canvasView's own top
        // constraint (view.safeAreaLayoutGuide.topAnchor) sits below, so
        // it used to tell PHP the canvas had ~59pt more room at the
        // bottom than it actually does — see viewDidLayoutSubviews()'s
        // own doc comment for how this was found and why the first
        // fetch had to move there to get a real, laid-out bounds at all.
        let bounds = canvasView.bounds
        let screen = screenStack.last ?? "home"
        #if DEBUG
        let fetchStart = Date()
        #endif
        client.fetchScreen(screen, action: action, width: bounds.width, height: bounds.height, fieldValues: fieldValues) { [weak self] result in
            DispatchQueue.main.async {
                switch result {
                case .success(let payload):
                    self?.errorView.isHidden = true
                    self?.canvasView.setPayload(payload)
                    #if DEBUG
                    guard let self else { return }
                    self.devTools.update(
                        screen: screen,
                        stackDepth: self.screenStack.count,
                        roundTripMs: Date().timeIntervalSince(fetchStart) * 1000,
                        phpRenderTimeMs: payload.renderTimeMs,
                        commandCount: payload.commands.count,
                        hitRegionCount: payload.hitRegions.count,
                        wasUnchanged: false
                    )
                    #endif
                case .failure(let error):
                    self?.errorView.show(error)
                }
            }
        }
    }

    /// Mirrors NativeRenderPocActivity.kt's own printCurrentScreen() in
    /// effect, not mechanism — Android's NativePrintAdapter replays this
    /// screen's own draw commands directly onto a PdfDocument.Page's
    /// Canvas (real vector output, same fidelity as the screen itself).
    /// UIPrintInteractionController has no equivalent "hand me a
    /// CGContext to draw into" entry point for a plain UIView the way
    /// PdfDocument.Page does — only a UIPrintFormatter (text/HTML/PDF-
    /// data-backed, none of which fit a Core Graphics-drawn canvas) or a
    /// rasterized image. A snapshot image is the pragmatic equivalent
    /// here: same visible content, lower fidelity if scaled up (a real
    /// platform tradeoff, not a bug) — reusing the system print dialog
    /// is what actually matters for this demo, not pixel-perfect vector
    /// output.
    private func printCurrentScreen() {
        let renderer = UIGraphicsImageRenderer(bounds: canvasView.bounds)
        let image = renderer.image { context in
            canvasView.layer.render(in: context.cgContext)
        }
        let controller = UIPrintInteractionController.shared
        let info = UIPrintInfo(dictionary: nil)
        info.outputType = .general
        info.jobName = "PhpNitro-\(screenStack.last ?? "screen")"
        controller.printInfo = info
        controller.printingItem = image
        controller.present(animated: true, completionHandler: nil)
    }
}

/// The iOS counterpart of NativeRenderPocActivity.kt's
/// showConnectionError()/showScreenErrorOverlay() — a single view
/// covering both cases (a network failure never reaching the server, or
/// the server reaching back with a real `{"error":{...}}`), unlike the
/// two separate Android views, since neither needs a materially
/// different treatment on iOS yet (no distinct icon/copy per case there
/// either — see message(for:) below).
private final class ScreenErrorView: UIView {
    var onRetry: (() -> Void)?

    private let messageLabel = UILabel()

    override init(frame: CGRect) {
        super.init(frame: frame)
        backgroundColor = .systemBackground
        configureLayout()
    }

    required init?(coder: NSCoder) {
        super.init(coder: coder)
        backgroundColor = .systemBackground
        configureLayout()
    }

    private func configureLayout() {
        let icon = UILabel()
        icon.text = "📡"
        icon.font = .systemFont(ofSize: 32)
        icon.textAlignment = .center

        let title = UILabel()
        title.text = "Connexion impossible"
        title.font = .boldSystemFont(ofSize: 18)
        title.textAlignment = .center

        messageLabel.font = .systemFont(ofSize: 14)
        messageLabel.textColor = .secondaryLabel
        messageLabel.textAlignment = .center
        messageLabel.numberOfLines = 0

        let retryButton = UIButton(type: .system)
        retryButton.setTitle("Réessayer", for: .normal)
        retryButton.titleLabel?.font = .boldSystemFont(ofSize: 15)
        retryButton.addTarget(self, action: #selector(retryTapped), for: .touchUpInside)

        let stack = UIStackView(arrangedSubviews: [icon, title, messageLabel, retryButton])
        stack.axis = .vertical
        stack.spacing = 10
        stack.setCustomSpacing(4, after: icon)
        stack.translatesAutoresizingMaskIntoConstraints = false

        addSubview(stack)
        NSLayoutConstraint.activate([
            stack.centerYAnchor.constraint(equalTo: centerYAnchor),
            stack.leadingAnchor.constraint(equalTo: safeAreaLayoutGuide.leadingAnchor, constant: 32),
            stack.trailingAnchor.constraint(equalTo: safeAreaLayoutGuide.trailingAnchor, constant: -32),
        ])
    }

    func show(_ error: ScreenFetchError) {
        messageLabel.text = message(for: error)
        isHidden = false
    }

    private func message(for error: ScreenFetchError) -> String {
        switch error {
        case .network(let description): return description
        case .server(_, let message): return message
        case .decoding(let description): return description
        }
    }

    @objc private func retryTapped() {
        onRetry?()
    }
}
