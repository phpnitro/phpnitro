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

    /// Mirrors NativeRenderPocActivity.kt's own `lastAppliedHash` —
    /// `Canvas::stableHash()` of the last payload actually applied to
    /// this screen, round-tripped as `lastHash` on the NEXT same-screen
    /// fetch so PHP can reply `{"unchanged":true}` instead of a full
    /// payload when nothing changed. Reset to nil on a real navigation
    /// (a different screen has nothing in common to compare against).
    private var lastAppliedHash: String?

    /// Mirrors NativeRenderPocActivity.kt's own `autoNavigateHandler` —
    /// a single pending timed refetch, never more than one at a time.
    /// Invalidated unconditionally at the top of every `fetch(...)` call
    /// (any real navigation, tap, or field update always wins over a
    /// stale poll), then re-armed from `payload.pollAgain` if the fresh
    /// response still wants one. `Timer`, not `DispatchWorkItem` — needs
    /// no explicit queue-hopping, always fires on the main run loop this
    /// view controller already lives on.
    private var pollTimer: Timer?

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
        // Real bug found testing VideoPlayer(showControls: true) on a
        // physical device: an AVPlayerViewController added as a plain
        // subview (no containing view controller of its own to parent
        // it to — see showVideoOverlay's own docblock) never ran its
        // own view lifecycle, so its internal video layer/controls
        // chrome never actually got built — a solid black box, nothing
        // playing, no transport bar. hostViewController lets
        // showVideoOverlay() do real addChild(_:)/didMove(toParent:)
        // containment against the one UIViewController that actually
        // owns this canvas.
        canvasView.hostViewController = self
        canvasView.onAction = { [weak self] action, rect, meta, vScrollKey in self?.handle(action: action, rect: rect, meta: meta, vScrollKey: vScrollKey) }
        canvasView.onFieldValueChanged = { [weak self] name, value in self?.setFieldValue(value, forName: name) }
        // Real bug found testing the social example app's Fil (LazyList)
        // page on a physical device: this port never had
        // NativeRenderPocActivity.kt's own `onScrollFollow` wiring at
        // all, so scrolling past the first fetch's built window hit
        // permanent blank sections — see checkScrollFollow()'s own
        // docblock in NativeCanvasView. `preserveScroll: true` here
        // matters just as much as the refetch itself: without it,
        // setPayload's normal "reset to the top" behavior would snap
        // the user back to y=0 on every single one of these refetches.
        canvasView.onScrollFollow = { [weak self] _ in self?.fetch(action: nil, preserveScroll: true) }
        registerCustomCommandHandlers()
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

    /// The real, wired proof that `Canvas::custom()`/
    /// `registerCustomCommandHandler()` works end to end on iOS, same
    /// role NativeRenderPocActivity.kt's own `registerCustomCommandHandlers()`
    /// plays on Android — `canvasView` itself has no built-in idea what
    /// a "sparkline" is, only this app-layer registration does. Real
    /// bug found testing the health example app on a physical device:
    /// BarChart/Sparkline/PieChart all painted blank white space, since
    /// this whole extension point never existed on iOS at all (see
    /// NativeCanvasView.swift's own `registerCustomCommandHandler`
    /// docblock). Math ported field-for-field from
    /// NativeRenderPocActivity.kt's own registerCustomCommandHandlers()
    /// — same min/max normalization, same bar-width/gap formula, same
    /// -90°-start clockwise pie slices (see draw(_:ArcCommand,in:)'s own
    /// docblock for why Core Graphics angles get negated here too).
    private func registerCustomCommandHandlers() {
        canvasView.registerCustomCommandHandler("sparkline") { context, payload in
            guard
                let x = payload["x"]?.doubleValue,
                let y = payload["y"]?.doubleValue,
                let w = payload["width"]?.doubleValue,
                let h = payload["height"]?.doubleValue,
                let color = payload["color"]?.stringValue.flatMap(UIColor.init(hex:)),
                let values = payload["values"]?.arrayValue?.compactMap({ $0.doubleValue }),
                values.count >= 2
            else { return }

            let minValue = values.min() ?? 0
            let maxValue = values.max() ?? 0
            let range = (maxValue - minValue) > 0 ? (maxValue - minValue) : 1.0

            let path = CGMutablePath()
            for (index, value) in values.enumerated() {
                let px = x + w * Double(index) / Double(values.count - 1)
                let py = y + h - h * ((value - minValue) / range)
                if index == 0 {
                    path.move(to: CGPoint(x: px, y: py))
                } else {
                    path.addLine(to: CGPoint(x: px, y: py))
                }
            }

            context.saveGState()
            context.setStrokeColor(color.cgColor)
            context.setLineWidth(2.5)
            context.setLineCap(.round)
            context.setLineJoin(.round)
            context.addPath(path)
            context.strokePath()
            context.restoreGState()
        }

        canvasView.registerCustomCommandHandler("barChart") { context, payload in
            guard
                let x = payload["x"]?.doubleValue,
                let y = payload["y"]?.doubleValue,
                let w = payload["width"]?.doubleValue,
                let h = payload["height"]?.doubleValue,
                let gap = payload["gap"]?.doubleValue,
                let color = payload["color"]?.stringValue.flatMap(UIColor.init(hex:)),
                let values = payload["values"]?.arrayValue?.compactMap({ $0.doubleValue }),
                !values.isEmpty
            else { return }

            let maxValue = (values.max() ?? 0) > 0 ? (values.max() ?? 0) : 1.0
            let count = values.count
            let barWidth = (w - gap * Double(count - 1)) / Double(count)

            context.saveGState()
            context.setFillColor(color.cgColor)
            for (index, value) in values.enumerated() {
                let barHeight = max(0, h * (value / maxValue))
                let left = x + Double(index) * (barWidth + gap)
                context.fill(CGRect(x: left, y: y + h - barHeight, width: barWidth, height: barHeight))
            }
            context.restoreGState()
        }

        canvasView.registerCustomCommandHandler("pieChart") { context, payload in
            guard
                let x = payload["x"]?.doubleValue,
                let y = payload["y"]?.doubleValue,
                let diameter = payload["diameter"]?.doubleValue,
                let values = payload["values"]?.arrayValue?.compactMap({ $0.doubleValue }),
                let colors = payload["colors"]?.arrayValue?.compactMap({ $0.stringValue }),
                !values.isEmpty,
                colors.count >= values.count
            else { return }

            let total = values.reduce(0, +)
            guard total > 0 else { return }

            let center = CGPoint(x: x + diameter / 2, y: y + diameter / 2)
            let radius = diameter / 2

            context.saveGState()
            var startDegrees = -90.0
            for (index, value) in values.enumerated() {
                let sweepDegrees = value / total * 360.0
                guard let color = UIColor(hex: colors[index]) else { continue }

                let startRadians = -startDegrees * .pi / 180
                let endRadians = -(startDegrees + sweepDegrees) * .pi / 180

                let slice = CGMutablePath()
                slice.move(to: center)
                slice.addArc(center: center, radius: radius, startAngle: startRadians, endAngle: endRadians, clockwise: true)
                slice.closeSubpath()

                context.setFillColor(color.cgColor)
                context.addPath(slice)
                context.fillPath()

                startDegrees += sweepDegrees
            }
            context.restoreGState()
        }
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

    /// Originally a minimal clone of Android's own OS-level "Copied"
    /// toast (see "clipboardcopy" below for the bug that led to it,
    /// still this method's only OTHER call site) — now doing double
    /// duty as `Canvas::showSnackbar()`'s own consumer too (see
    /// `fetch()`'s own success handler), since the two are visually and
    /// behaviorally identical (bottom-anchored, self-dismissing,
    /// fade in/out). `durationMs` defaults to the clipboard toast's
    /// own original fixed timing, unchanged for that call site.
    private func showToast(_ message: String, durationMs: Int = 1400) {
        let label = UILabel()
        label.text = message
        label.textColor = .white
        label.font = .systemFont(ofSize: 14, weight: .medium)
        label.textAlignment = .center
        label.numberOfLines = 0
        label.backgroundColor = UIColor.black.withAlphaComponent(0.8)
        label.layer.cornerRadius = 8
        label.layer.masksToBounds = true
        label.alpha = 0

        view.addSubview(label)
        label.translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([
            label.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            label.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -32),
            label.leadingAnchor.constraint(greaterThanOrEqualTo: view.leadingAnchor, constant: 24),
            label.trailingAnchor.constraint(lessThanOrEqualTo: view.trailingAnchor, constant: -24),
        ])
        label.setContentHuggingPriority(.required, for: .horizontal)

        // Fake padding: UILabel has no insets of its own, and a stray
        // extra constant here is cheaper than a UIEdgeInsets-aware
        // subclass for a toast this small.
        label.text = "  \(message)  "

        UIView.animate(withDuration: 0.2, animations: {
            label.alpha = 1
        }, completion: { _ in
            UIView.animate(withDuration: 0.2, delay: Double(durationMs) / 1000, options: [], animations: {
                label.alpha = 0
            }, completion: { _ in
                label.removeFromSuperview()
            })
        })
    }

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

    override public func traitCollectionDidChange(_ previousTraitCollection: UITraitCollection?) {
        super.traitCollectionDidChange(previousTraitCollection)
        // Android has no equivalent of this at all — Configuration
        // changes recreate the whole Activity there, which re-reads
        // nightModeFlags from scratch on the resulting fresh fetch.
        // iOS keeps this view controller alive across a Control
        // Center dark-mode toggle, so without this the `dark` value
        // `fetch()` just started sending (see its own doc comment)
        // would silently go stale until the next UNRELATED refetch —
        // a real light/dark mismatch, not merely a missed optimization.
        guard hasFetchedOnce else { return }
        if traitCollection.hasDifferentColorAppearance(comparedTo: previousTraitCollection) {
            fetch(action: nil)
        }
    }

    /// For a future TextField overlay (or any other widget with its own
    /// output slot) to call — see `fieldValues`'s own docblock.
    public func setFieldValue(_ value: String, forName name: String) {
        fieldValues[name] = value
    }

    private func handle(action: String, rect: CGRect, meta: [String: JSONValue]? = nil, vScrollKey: String? = nil) {
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
            // "keyboard:<type>:" — Engine\Native\TextField::$keyboardType,
            // same optional-prefix-chain shape multiline:/secure: already
            // use. Mirrors NativeRenderPocActivity.kt's own parsing.
            var keyboardType = "text"
            if rest.hasPrefix("keyboard:") {
                let afterPrefix = rest.dropFirst("keyboard:".count)
                if let colonIndex = afterPrefix.firstIndex(of: ":") {
                    keyboardType = String(afterPrefix[afterPrefix.startIndex..<colonIndex])
                    rest = afterPrefix[afterPrefix.index(after: colonIndex)...]
                }
            }
            let fieldName = String(rest)
            canvasView.showTextInput(fieldName: fieldName, initialValue: fieldValues[fieldName] ?? "", rect: rect, multiline: multiline, secure: secure, keyboardType: keyboardType, vScrollKey: vScrollKey)
            return
        }

        // video:play:<url> (VideoPlayer.php) — same "entirely
        // client-side, no fetch at all" treatment as focus: above.
        // loop/muted/showControls/playInBackground travel as `meta`
        // flags (Tappable's own escape hatch) rather than more prefix
        // segments on the action string — focus:'s own multiline:/
        // secure:/keyboard: chain already shows how quickly that gets
        // hard to read past a couple of optional flags, and every one
        // of these is a plain bool with no value of its own to carry.
        if action.hasPrefix("video:play:") {
            let url = String(action.dropFirst("video:play:".count))
            canvasView.showVideoOverlay(
                url: url,
                rect: rect,
                loop: meta?["loop"]?.stringValue == "true",
                muted: meta?["muted"]?.stringValue == "true",
                showControls: meta?["controls"]?.stringValue == "true",
                playInBackground: meta?["background"]?.stringValue == "true"
            )
            return
        }

        // select:<name> (SelectBox.php) — same "entirely client-side
        // until a choice is made" shape focus:/video:play: already use,
        // but the choice itself needs a real system picker: mirrors
        // NativeRenderPocActivity.kt's own showSelectDialog(), an
        // AlertDialog listing every option, writing the picked KEY (not
        // its label) into fieldValues and refetching. Real bug found
        // testing the ecommerce example app's checkout page ("Choisir
        // un opérateur mobile money") on a physical device: this whole
        // action was never wired up on iOS at all — tapping the
        // SelectBox did nothing, silently (see HitRegion.meta's own
        // docblock for why `options` couldn't even be READ before that
        // type was widened to JSONValue).
        if action.hasPrefix("select:") {
            let name = String(action.dropFirst("select:".count))
            guard let options = meta?["options"]?.dictionaryValue else { return }
            let alert = UIAlertController(title: nil, message: nil, preferredStyle: .actionSheet)
            for (value, label) in options {
                alert.addAction(UIAlertAction(title: label.stringValue ?? value, style: .default) { [weak self] _ in
                    self?.fieldValues[name] = value
                    self?.fetch(action: nil)
                })
            }
            alert.addAction(UIAlertAction(title: "Annuler", style: .cancel))
            // Real regression found testing this on a physical iPhone
            // 11 Pro Max: "harmless on iPhone, which ignores
            // popoverPresentationController entirely" was wrong — just
            // setting sourceView/sourceRect (even unused) was enough to
            // make this whole actionSheet render as a tiny anchored
            // popover bubble instead of the expected full-width sheet
            // sliding from the bottom, right on top of the tapped
            // SelectBox. iPad genuinely DOES need a real anchor (a
            // plain .actionSheet crashes there with no
            // popoverPresentationController target at all) — scoped to
            // .pad only now, since that's the one idiom this anchor is
            // actually for.
            if UIDevice.current.userInterfaceIdiom == .pad {
                alert.popoverPresentationController?.sourceView = view
                alert.popoverPresentationController?.sourceRect = rect
            }
            present(alert, animated: true)
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
                // Real bug found testing Clipboard::copyAction() on a
                // physical device: nothing ever confirmed the copy
                // happened. NativeRenderPocActivity.kt's own
                // "clipboardcopy" branch has the same gap — it never
                // shows a Toast either, relying entirely on Android's
                // OWN system-level "Copied" toast (added in Android 12,
                // not guaranteed on older OS versions either); iOS has
                // no equivalent automatic confirmation for
                // UIPasteboard writes at all, so a tap on "Copier" gave
                // no feedback whatsoever. showToast() below is a small,
                // self-contained clone of that same OS-level Toast, not
                // the full snackbar/toast SYSTEM this framework still
                // doesn't have on iOS (see ios/README.md's own tracked
                // gap) — scoped to just this one real, reported symptom.
                showToast("Copié dans le presse-papiers")
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
                    // Real bug found testing the social example app's
                    // Chat page on a physical device: typing in the
                    // message TextField, then an unrelated WebSocket
                    // echo landing (no tap involved — see this case's
                    // own docblock) fired this same fetch(action: nil),
                    // which used to tear down the live text-input
                    // overlay via setPayload's unconditional
                    // clearTextInput() — wiping whatever the user was
                    // mid-typing. preserveTextInput keeps the overlay
                    // alive across exactly this one "nobody tapped
                    // anything" fetch path.
                    self?.fetch(action: nil, preserveTextInput: true)
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

        let metaJson = meta.flatMap { try? JSONEncoder().encode($0) }
            .flatMap { String(data: $0, encoding: .utf8) }

        switch ScreenNavigation.reduce(action: action, stack: screenStack, metaJson: metaJson) {
        case .clientTabOnly(let key, let index):
            canvasView.setClientTab(key, index: index)
        case .fieldUpdate(let key, let value):
            // toggle:<key> (Checkbox/NumberPicker/Drawer's own hamburger
            // icon, etc) — mirrors NativeRenderPocActivity.kt's own
            // `toggle:` branch exactly: write the new value, then refetch
            // the same screen with it included, same as any other field
            // (see fieldValues' own docblock). preserveScroll: true —
            // see ScreenNavigationResult.fetch's own docblock for the
            // real bug (a tappable Rating star yanking the whole page
            // back to the top) this default-inversion fix addresses.
            fieldValues[key] = value
            fetch(action: nil, preserveScroll: true)
        case .fetch(let stack, let fetchAction, let isNavigation):
            screenStack = stack
            fetch(action: fetchAction, preserveScroll: !isNavigation)
        }
    }

    // Real bug found testing the ecommerce example app's Rating widget
    // on a physical device: EVERY call site below that just does
    // fetch(action: nil) after writing a device result into
    // fieldValues (camera, mic, scanqr, nfcstop, reminders, apn, …) —
    // every one a same-screen refetch, never a navigation — used to
    // default to resetting scroll to the top, same root cause
    // ScreenNavigationResult.fetch's own docblock documents for
    // toggle:. preserveScroll now defaults to true (Android's own
    // setCommands() never resets scroll on ANY refetch, full stop) —
    // only the one real navigation path (see the `.fetch` case in
    // handle(action:rect:meta:)) explicitly passes false.
    private func fetch(action: String?, preserveTextInput: Bool = false, preserveScroll: Bool = true, isPollFetch: Bool = false) {
        // Mirrors scheduleTimedRefetch()'s own unconditional
        // `autoNavigateHandler.removeCallbacksAndMessages(null)` at its
        // very top — every fetch, whatever triggered it (a real
        // navigation, a tap, a field update, or the poll timer itself),
        // invalidates any previously-armed poll first. This is what
        // keeps a stale poll from ever firing after the user has since
        // navigated away or triggered an unrelated refetch.
        pollTimer?.invalidate()
        pollTimer = nil
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
        // preserveScroll's own false value means exactly what
        // NativeRenderPocActivity.kt's own `isNavigation` means (see
        // ScreenNavigationResult.fetch's docblock) — reused here rather
        // than threading a second, redundant flag through. A real
        // navigation has nothing in common with the screen
        // `lastAppliedHash` belongs to, so it's cleared before this
        // fetch — and, since `isNavigationFetch` also gates sending
        // `lastHash` below, PHP always gets a full response for it
        // anyway, not an `{"unchanged":true}` short-circuit.
        let isNavigationFetch = !preserveScroll
        if isNavigationFetch {
            lastAppliedHash = nil
        }
        // Real bug found testing the ecommerce example app on a
        // physical device: tapping a product (Tappable's own
        // "navigate:product?id=42") visibly did nothing — ScreenNavigation
        // .reduce(_:_:) pushes the WHOLE "product?id=42" token onto
        // screenStack (matches NativeRenderPocActivity.kt's own
        // `screenStack.add(action.removePrefix("navigate:"))` exactly),
        // but unlike Android's own fetchDrawCommands() — which splits
        // that token into a bare `screen` and re-encodes its own query
        // params separately (see its own `substringBefore('?')`/
        // `substringAfter('?')` split) — this fetch() used to pass the
        // ENTIRE "product?id=42" string as the `screen` query param
        // itself. AutoRouter::discover()'s route map only ever has a
        // bare "product" key, so the lookup silently missed and fell
        // back to 'home' — same screen re-rendered, looking like the
        // tap did nothing at all.
        let screenToken = screenStack.last ?? "home"
        let screen = String(screenToken.split(separator: "?", maxSplits: 1)[0])
        let rawQuery = screenToken.contains("?") ? String(screenToken.split(separator: "?", maxSplits: 1)[1]) : ""

        // LazyList's windowed prefetch needs to know where the user
        // actually is in the virtual list to build the right window —
        // harmless for every other screen, which simply never reads it
        // (mirrors NativeRenderPocActivity.kt's own scrollYParam, sent
        // unconditionally the same way). Never overwrites an explicit
        // "scroll_y" already in fieldValues (there isn't one — nothing
        // else in this codebase sets that key), just the simplest way
        // to fold it into the existing query-string construction
        // without widening ScreenDataSource's own protocol signature.
        var requestFieldValues = fieldValues
        requestFieldValues["scroll_y"] = String(Double(canvasView.currentScrollYDp))
        // Mirrors fetchDrawCommands()'s own dark/locale/online params —
        // folded into fieldValues rather than widening ScreenDataSource's
        // own protocol (same reasoning as scroll_y just above: on the
        // wire these are indistinguishable $_GET keys either way, and
        // public/index.php already defaults every one of them when
        // absent, see Tokens::init()/Translator::init()'s own `?? '0'`/
        // `?? 'fr'` fallbacks and NativeSettingsScreen.php's own
        // `?? '1'` for online — so this was a real, silent degradation
        // (always light mode, always 'fr', always assumed online)
        // rather than a broken request, same as the gap this closes.
        requestFieldValues["dark"] = view.traitCollection.userInterfaceStyle == .dark ? "1" : "0"
        requestFieldValues["locale"] = Locale.current.languageCode ?? "fr"
        requestFieldValues["online"] = NativeDeviceBridge.isOnlineCached ? "1" : "0"
        if !rawQuery.isEmpty {
            for pair in rawQuery.components(separatedBy: "&") {
                let parts = pair.split(separator: "=", maxSplits: 1).map(String.init)
                guard let key = parts.first, !key.isEmpty else { continue }
                requestFieldValues[key] = parts.count > 1 ? parts[1] : ""
            }
        }
        #if DEBUG
        let fetchStart = Date()
        #endif
        // A poll-triggered refetch never sends `lastHash` — same
        // `isPoll`-gated omission as fetchDrawCommands()'s own call site
        // (see that method's own doc comment): the whole point of
        // polling is to notice `AsyncTask::poll()` moving from pending
        // to done, which an `{"unchanged":true}` short-circuit would
        // hide from this view controller entirely.
        let lastHashForThisFetch = (isNavigationFetch || isPollFetch) ? nil : lastAppliedHash
        client.fetchScreen(screen, action: action, width: bounds.width, height: bounds.height, fieldValues: requestFieldValues, lastHash: lastHashForThisFetch) { [weak self] result in
            DispatchQueue.main.async {
                switch result {
                case .success(let payload):
                    self?.errorView.isHidden = true
                    guard let payload else {
                        // {"unchanged":true} — the current frame already
                        // IS this screen's real state, nothing to apply.
                        // See DrawCommandPayload.decodeApplied's own
                        // docblock for the real bug this branch fixes.
                        #if DEBUG
                        guard let self else { return }
                        self.devTools.update(
                            screen: screen,
                            stackDepth: self.screenStack.count,
                            roundTripMs: Date().timeIntervalSince(fetchStart) * 1000,
                            phpRenderTimeMs: nil,
                            commandCount: 0,
                            hitRegionCount: 0,
                            wasUnchanged: true
                        )
                        #endif
                        return
                    }
                    // Canvas::setRedirect() — must short-circuit BEFORE
                    // this payload is ever applied to canvasView, same
                    // as applyResponse()'s own redirect check: mutate
                    // screenStack in place and refetch as a real
                    // navigation, never painting this response's own
                    // commands at all.
                    if let redirect = payload.redirect, let self, !self.screenStack.isEmpty {
                        self.screenStack[self.screenStack.count - 1] = redirect
                        self.fetch(action: nil, preserveScroll: false)
                        return
                    }
                    self?.lastAppliedHash = payload.hash
                    self?.canvasView.setPayload(payload, preserveTextInput: preserveTextInput, preserveScroll: preserveScroll)
                    if let snackbar = payload.snackbar {
                        self?.showToast(snackbar.message, durationMs: snackbar.durationMs)
                    }
                    if let afterMs = payload.pollAgain, let self {
                        // Scheduled fresh off THIS payload, not the one
                        // `isPollFetch` arrived from — Async re-arms
                        // pollAgain on every pending paint, so a poll
                        // chain simply keeps re-scheduling itself here
                        // until a render finally omits it (task done).
                        self.pollTimer = Timer.scheduledTimer(withTimeInterval: Double(afterMs) / 1000, repeats: false) { [weak self] _ in
                            self?.fetch(action: nil, preserveScroll: true, isPollFetch: true)
                        }
                    }
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
