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

    // The size the CURRENT envelope was actually fetched for — a real
    // window resize means PHP laid out for the wrong size until a fresh
    // fetch catches up. Marked BEFORE the network result lands (see
    // fetch(action:)), not after, so RustScreenView.onResize firing again
    // mid-flight doesn't launch a second redundant fetch for what's
    // effectively the same resize.
    private var lastFetchedSize: (width: Double, height: Double)?

    public init(host: String, port: Int, initialScreen: String, renderer: RustRenderer, frame: NSRect) {
        self.host = host
        self.port = port
        self.stack = [initialScreen]
        self.view = RustScreenView(frame: frame, renderer: renderer)
        view.onAction = { [weak self] action, metaJSON, rect in self?.handle(action: action, metaJSON: metaJSON, rect: rect) }
        view.onResize = { [weak self] width, height in self?.handleResize(width: Double(width), height: Double(height)) }
        view.onFieldValueChanged = { [weak self] name, value in self?.fieldValues[name] = value }
    }

    public var contentView: NSView { view }

    public func start() {
        fetch(action: nil)
    }

    private var currentScreen: String { stack.last ?? "home" }

    private func handle(action: String, metaJSON: String?, rect: NSRect) {
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
            view.showTextInput(fieldName: fieldName, initialValue: fieldValues[fieldName] ?? "", rect: rect, multiline: multiline, secure: secure)
            return
        }

        // video:play:<url> (VideoPlayer.php) — same "entirely
        // client-side, no fetch at all" treatment as focus: above.
        if action.hasPrefix("video:play:") {
            let url = String(action.dropFirst("video:play:".count))
            view.showVideoOverlay(url: url, rect: rect)
            return
        }

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

    /// `RustScreenView.onResize` fires for EVERY frame-size change —
    /// this decides whether it's actually a NEW size worth refetching
    /// for (skips a spurious re-report of the size the view already has
    /// an envelope for).
    private func handleResize(width: Double, height: Double) {
        if let last = lastFetchedSize, last.width == width, last.height == height {
            return
        }
        guard width > 0, height > 0 else { return }
        fetch(action: nil)
    }

    private func fetch(action: String?) {
        lastFetchedSize = (Double(view.bounds.width), Double(view.bounds.height))
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
