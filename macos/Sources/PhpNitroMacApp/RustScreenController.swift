import AppKit
import Foundation
import PhpNitroProtocol
import RustMacRenderer

/// Coordinates one `RustScreenView` against a real `php -S` — the Rust-only
/// counterpart of `PhpNitroMacEngine`'s own `MacScreenViewController`.
/// Deliberately does its OWN minimal URL-building/fetch (rather than
/// reusing `PhpNitroProtocol`'s `ScreenClient`) since that shared client
/// only ever hands back a DECODED `DrawCommandPayload` — this controller
/// needs the raw envelope JSON string instead (`RustRenderer`/
/// `rustHitTest` both take that directly), and `DrawCommandPayload` has no
/// `Encodable` conformance to re-serialize it back into one. `ScreenNavigation.reduce`
/// IS reused as-is — pure stack/action logic, no payload decoding involved.
public final class RustScreenController {
    private let host: String
    private let port: Int
    private let view: RustScreenView
    private var stack: [String]
    private let session = URLSession(configuration: .ephemeral)

    // Checkbox/Toggle/Slider's shared "toggle:" commit destination —
    // mirrors NativeRenderPocActivity.kt's own fieldValues: sent as extra
    // query params on every fetch, never cleared, same "server
    // round-trips it back via the next render" contract every platform's
    // fieldValues already has.
    private var fieldValues: [String: String] = [:]

    public init(host: String, port: Int, initialScreen: String, renderer: RustRenderer, frame: NSRect) {
        self.host = host
        self.port = port
        self.stack = [initialScreen]
        self.view = RustScreenView(frame: frame, renderer: renderer)
        view.onAction = { [weak self] action, metaJSON in self?.handle(action: action, metaJSON: metaJSON) }
    }

    public var contentView: NSView { view }

    public func start() {
        fetch(action: nil)
    }

    private var currentScreen: String { stack.last ?? "home" }

    private func handle(action: String, metaJSON: String?) {
        switch ScreenNavigation.reduce(action: action, stack: stack, metaJson: metaJSON) {
        case .clientTabOnly(let key, let index):
            // Entirely local, no fetch at all — the view owns this state
            // itself (see RustScreenView.setClientTab(_:index:)'s own
            // doc comment), same division of responsibility Android has
            // between NativeCanvasView and NativeRenderPocActivity.
            view.setClientTab(key, index: index)
        case .fieldUpdate(let key, let value):
            fieldValues[key] = value
            fetch(action: nil)
        case .fetch(let newStack, let fetchAction):
            stack = newStack
            fetch(action: fetchAction)
        }
    }

    private func fetch(action: String?) {
        var components = URLComponents()
        components.scheme = "http"
        components.host = host
        components.port = port
        components.path = "/native/layout-demo"
        var items = [
            URLQueryItem(name: "screen", value: currentScreen),
            URLQueryItem(name: "width", value: String(Double(view.bounds.width))),
            URLQueryItem(name: "height", value: String(Double(view.bounds.height))),
        ]
        if let action {
            items.append(URLQueryItem(name: "action", value: action))
        }
        // Sorted by name — an arbitrary but STABLE order, matching
        // linux/phpnitro_desktop/screen_client.py's own build_url()
        // convention, so the same fieldValues always produce the same URL.
        for name in fieldValues.keys.sorted() {
            items.append(URLQueryItem(name: name, value: fieldValues[name]))
        }
        components.queryItems = items
        guard let url = components.url else { return }

        session.dataTask(with: url) { [weak self] data, response, error in
            guard let self, let data, error == nil,
                  let http = response as? HTTPURLResponse, (200..<300).contains(http.statusCode),
                  let rawJSON = String(data: data, encoding: .utf8) else {
                return
            }
            DispatchQueue.main.async {
                self.view.setEnvelope(rawJSON: rawJSON)
            }
        }.resume()
    }
}
