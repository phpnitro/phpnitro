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
    /// sent on the next fetch. No widget writes to this yet (no TextField
    /// overlay exists on iOS — see ios/README.md), so this is currently
    /// only reachable via setFieldValue(_:forName:) for a future caller
    /// (a TextField overlay, once one exists) to use — the wire protocol
    /// is ready ahead of the widget that will drive it, same order icons
    /// (IconFont.swift) and hit-testing (DrawCommandPayload.action(at:))
    /// were built in.
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
        canvasView.onAction = { [weak self] action in self?.handle(action: action) }
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
