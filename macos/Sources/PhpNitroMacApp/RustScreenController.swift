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

    // clientPanel.key -> currently active index — a .clientTabOnly action
    // is "entirely local, no fetch at all" (ScreenNavigation.swift's own
    // reasoning), so this is the only state that tracks it: updated on
    // tap, pushed to `view.interactionStateJSON` (same shape
    // rust/phpnitro-render/src/hittest.rs's InteractionState decodes),
    // never cleared — a key absent here just falls back to whichever
    // panel PHP marked initiallyActive, exactly like Android's own
    // seedClientTabState() never clearing clientTabState either.
    private var activePanel: [String: Int] = [:]

    public init(host: String, port: Int, initialScreen: String, renderer: RustRenderer, frame: NSRect) {
        self.host = host
        self.port = port
        self.stack = [initialScreen]
        self.view = RustScreenView(frame: frame, renderer: renderer)
        view.onAction = { [weak self] action in self?.handle(action: action) }
    }

    public var contentView: NSView { view }

    public func start() {
        fetch(action: nil)
    }

    private var currentScreen: String { stack.last ?? "home" }

    private func handle(action: String) {
        switch ScreenNavigation.reduce(action: action, stack: stack) {
        case .clientTabOnly(let key, let index):
            activePanel[key] = index
            view.interactionStateJSON = interactionStateJSON()
            view.needsDisplay = true
        case .fetch(let newStack, let fetchAction):
            stack = newStack
            fetch(action: fetchAction)
        }
    }

    /// `{"activePanel":{"key1":0,...}}` — the same shape
    /// `rust/phpnitro-render/src/hittest.rs`'s `InteractionState` decodes
    /// (`#[serde(rename_all = "camelCase")]`). Built via `JSONSerialization`
    /// rather than a `Codable` wrapper type — a one-field passthrough
    /// dictionary doesn't need one.
    private func interactionStateJSON() -> String? {
        guard let data = try? JSONSerialization.data(withJSONObject: ["activePanel": activePanel]) else { return nil }
        return String(data: data, encoding: .utf8)
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
