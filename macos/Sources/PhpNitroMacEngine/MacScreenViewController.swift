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

    /// Mirrors NativeScreenViewController.swift's own fieldValues — see
    /// that file's docblock for why this exists ahead of any widget
    /// that can actually call it (no TextField overlay exists on this
    /// platform yet either).
    private var fieldValues: [String: String] = [:]

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
        canvasView.onAction = { [weak self] action in self?.handle(action: action) }
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

    private func handle(action: String) {
        switch ScreenNavigation.reduce(action: action, stack: screenStack) {
        case .clientTabOnly(let key, let index):
            canvasView.setClientTab(key, index: index)
        case .fetch(let stack, let fetchAction):
            screenStack = stack
            fetch(action: fetchAction)
        }
    }

    private func fetch(action: String?) {
        let screen = screenStack.last ?? "home"
        let size = view.bounds.size
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
