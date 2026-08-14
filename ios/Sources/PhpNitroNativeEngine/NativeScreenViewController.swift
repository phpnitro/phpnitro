import UIKit

/// The iOS counterpart of NativeRenderPocActivity.kt — deliberately the
/// minimal slice: hosts one NativeCanvasView, fetches one screen via
/// ScreenClient on load, and refetches (same screen, with the tapped
/// action) whenever NativeCanvasView.onAction fires. No screen stack, no
/// back-navigation, no polling, no forms — see ScreenClient's own
/// docblock and ios/README.md for what's real, separate follow-up work.
public final class NativeScreenViewController: UIViewController {
    private let client: ScreenClient
    private let screen: String
    private let canvasView = NativeCanvasView()

    public init(host: String, port: Int, screen: String = "home") {
        self.client = ScreenClient(host: host, port: port)
        self.screen = screen
        super.init(nibName: nil, bundle: nil)
    }

    public required init?(coder: NSCoder) {
        fatalError("NativeScreenViewController is always created with a host/port, not from a storyboard.")
    }

    override public func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .white

        canvasView.translatesAutoresizingMaskIntoConstraints = false
        canvasView.onAction = { [weak self] action in self?.fetch(action: action) }
        view.addSubview(canvasView)
        NSLayoutConstraint.activate([
            canvasView.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            canvasView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            canvasView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            canvasView.bottomAnchor.constraint(equalTo: view.bottomAnchor),
        ])

        fetch(action: nil)
    }

    private func fetch(action: String?) {
        let bounds = UIScreen.main.bounds
        client.fetchScreen(screen, action: action, width: bounds.width, height: bounds.height) { [weak self] result in
            DispatchQueue.main.async {
                switch result {
                case .success(let payload):
                    self?.canvasView.setPayload(payload)
                case .failure:
                    // A real error card (see NativeRenderPocActivity.kt's
                    // showConnectionError()/showScreenErrorOverlay()) is
                    // real, separate follow-up work — a failed fetch is a
                    // silent no-op here for now, same "don't crash on a
                    // bad response" contract the rest of this pipeline
                    // already follows.
                    break
                }
            }
        }
    }
}
