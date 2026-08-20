import AppKit
import PhpNitroProtocol

/// The macOS counterpart of NativeScreenViewController.swift (iOS) —
/// same responsibilities, same ScreenNavigation.reduce(_:_:) dispatch,
/// hosted in an NSViewController instead of a UIViewController so it can
/// be dropped into any NSWindow (see MacApp's own AppDelegate, which
/// plays the same role ios/App/AppDelegate.swift does for the iOS
/// target — outside this package, a consumer's own app project).
public final class MacScreenViewController: NSViewController {
    private let client: ScreenClient
    private var screenStack: [String]
    private let canvasView = MacCanvasView()

    /// Mirrors NativeScreenViewController.swift's own fieldValues —
    /// written on every keystroke by MacCanvasView's own text-input
    /// overlay (see handle(action:rect:)'s focus: branch below).
    private var fieldValues: [String: String] = [:]

    /// The size the CURRENT payload was actually fetched for — see
    /// MacCanvasView.onResize's own doc comment. Marked BEFORE the
    /// network result lands (see fetch(action:)), not after, so a second
    /// onResize firing mid-flight doesn't launch a redundant fetch for
    /// what's effectively the same resize.
    private var lastFetchedSize: (width: CGFloat, height: CGFloat)?

    private let errorView = MacScreenErrorView()

    public init(host: String, port: Int, screen: String = "home") {
        self.client = ScreenClient(host: host, port: port)
        self.screenStack = [screen]
        super.init(nibName: nil, bundle: nil)
    }

    public required init?(coder: NSCoder) {
        fatalError("MacScreenViewController is always created with a host/port, not from a storyboard.")
    }

    public override func loadView() {
        view = NSView(frame: NSRect(x: 0, y: 0, width: 900, height: 700))
    }

    public override func viewDidLoad() {
        super.viewDidLoad()

        canvasView.translatesAutoresizingMaskIntoConstraints = false
        canvasView.onAction = { [weak self] action, rect in self?.handle(action: action, rect: rect) }
        canvasView.onResize = { [weak self] width, height in self?.handleResize(width: width, height: height) }
        canvasView.onFieldValueChanged = { [weak self] name, value in self?.setFieldValue(value, forName: name) }
        view.addSubview(canvasView)

        errorView.translatesAutoresizingMaskIntoConstraints = false
        errorView.isHidden = true
        errorView.onRetry = { [weak self] in self?.fetch(action: nil) }
        view.addSubview(errorView)

        NSLayoutConstraint.activate([
            canvasView.topAnchor.constraint(equalTo: view.topAnchor),
            canvasView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            canvasView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            canvasView.bottomAnchor.constraint(equalTo: view.bottomAnchor),

            errorView.topAnchor.constraint(equalTo: view.topAnchor),
            errorView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            errorView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            errorView.bottomAnchor.constraint(equalTo: view.bottomAnchor),
        ])

        fetch(action: nil)
    }

    public func setFieldValue(_ value: String, forName name: String) {
        fieldValues[name] = value
    }

    private func handle(action: String, rect: NSRect) {
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

        switch ScreenNavigation.reduce(action: action, stack: screenStack) {
        case .clientTabOnly(let key, let index):
            canvasView.setClientTab(key, index: index)
        case .fieldUpdate:
            // Never produced here — this call site never passes
            // `metaJson` to `reduce()` (MacCanvasView.onAction carries no
            // meta at all), so `toggle:` always falls through to the
            // `.fetch` case below instead, unchanged from before this
            // case existed. See ScreenNavigation.swift's own doc comment.
            break
        case .fetch(let stack, let fetchAction):
            screenStack = stack
            fetch(action: fetchAction)
        }
    }

    /// `MacCanvasView.onResize` fires for EVERY frame-size change — this
    /// decides whether it's actually a NEW size worth refetching for.
    private func handleResize(width: CGFloat, height: CGFloat) {
        if let last = lastFetchedSize, last.width == width, last.height == height {
            return
        }
        guard width > 0, height > 0 else { return }
        fetch(action: nil)
    }

    private func fetch(action: String?) {
        let screen = screenStack.last ?? "home"
        let size = view.bounds.size
        lastFetchedSize = (size.width, size.height)
        client.fetchScreen(screen, action: action, width: size.width, height: size.height, fieldValues: fieldValues) { [weak self] result in
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

/// The macOS counterpart of NativeScreenViewController.swift's own
/// ScreenErrorView — same role, NSTextField/NSButton standing in for
/// UILabel/UIButton.
private final class MacScreenErrorView: NSView {
    var onRetry: (() -> Void)?

    private let messageLabel = NSTextField(labelWithString: "")

    public override init(frame frameRect: NSRect) {
        super.init(frame: frameRect)
        configureLayout()
    }

    public required init?(coder: NSCoder) {
        super.init(coder: coder)
        configureLayout()
    }

    private func configureLayout() {
        wantsLayer = true
        layer?.backgroundColor = NSColor.windowBackgroundColor.cgColor

        let icon = NSTextField(labelWithString: "📡")
        icon.font = .systemFont(ofSize: 32)
        icon.alignment = .center

        let title = NSTextField(labelWithString: "Connexion impossible")
        title.font = .boldSystemFont(ofSize: 18)
        title.alignment = .center

        messageLabel.font = .systemFont(ofSize: 14)
        messageLabel.textColor = .secondaryLabelColor
        messageLabel.alignment = .center
        messageLabel.maximumNumberOfLines = 0

        let retryButton = NSButton(title: "Réessayer", target: self, action: #selector(retryTapped))
        retryButton.bezelStyle = .rounded

        let stack = NSStackView(views: [icon, title, messageLabel, retryButton])
        stack.orientation = .vertical
        stack.spacing = 10
        stack.translatesAutoresizingMaskIntoConstraints = false

        addSubview(stack)
        NSLayoutConstraint.activate([
            stack.centerYAnchor.constraint(equalTo: centerYAnchor),
            stack.leadingAnchor.constraint(greaterThanOrEqualTo: leadingAnchor, constant: 32),
            stack.trailingAnchor.constraint(lessThanOrEqualTo: trailingAnchor, constant: -32),
            stack.centerXAnchor.constraint(equalTo: centerXAnchor),
        ])
    }

    func show(_ error: ScreenFetchError) {
        messageLabel.stringValue = message(for: error)
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
