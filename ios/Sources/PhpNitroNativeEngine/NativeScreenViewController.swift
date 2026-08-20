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

    public init(host: String, port: Int, screen: String = "home") {
        self.client = ScreenClient(host: host, port: port)
        self.screenStack = [screen]
        super.init(nibName: nil, bundle: nil)
    }

    public required init?(coder: NSCoder) {
        fatalError("NativeScreenViewController is always created with a host/port, not from a storyboard.")
    }

    override public func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .white

        canvasView.translatesAutoresizingMaskIntoConstraints = false
        canvasView.onAction = { [weak self] action, rect in self?.handle(action: action, rect: rect) }
        canvasView.onFieldValueChanged = { [weak self] name, value in self?.setFieldValue(value, forName: name) }
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

        fetch(action: nil)
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
        let bounds = UIScreen.main.bounds
        let screen = screenStack.last ?? "home"
        client.fetchScreen(screen, action: action, width: bounds.width, height: bounds.height, fieldValues: fieldValues) { [weak self] result in
            DispatchQueue.main.async {
                switch result {
                case .success(let payload):
                    self?.errorView.isHidden = true
                    self?.canvasView.setPayload(payload)
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
