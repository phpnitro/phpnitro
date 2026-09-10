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
    private let client: ScreenClient
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
    /// for building `device:sound:`'s default URL — the one action that
    /// needs to know where "this app's own server" is.
    private let host: String
    private let port: Int

    public init(host: String, port: Int, screen: String = "home") {
        self.client = ScreenClient(host: host, port: port)
        self.host = host
        self.port = port
        self.screenStack = [screen]
        super.init(nibName: nil, bundle: nil)
    }

    public required init?(coder: NSCoder) {
        fatalError("NativeScreenViewController is always created with a host/port, not from a storyboard.")
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
        // separate flag needed. Only these eighteen exist so far
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
                    ?? "http://\(host):\(port)/assets/audio/beep.wav"
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
