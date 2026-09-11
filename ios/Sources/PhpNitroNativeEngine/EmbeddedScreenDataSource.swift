import Foundation
import PhpNitroProtocol

/// The embedded counterpart of `ScreenClient` — same `ScreenDataSource`
/// contract, but every "fetch" is a real in-process PHP request through
/// `PhpEmbedRuntime.handleRequest(_:)` instead of an HTTP round-trip to
/// a `phpx serve` the developer's machine happens to be running. This is
/// the whole point of vendoring `public/index.php` into
/// `Resources/www` (see `PhpEmbedRuntime.wwwDirectoryURL`, `phpx
/// bundle:ios`) — `HostApp` (the real per-project app) can now serve its
/// own screens standalone; `GoHostApp`/`PhpNitroGo` (a pure companion
/// client for someone else's `phpx serve`) keeps using `ScreenClient`
/// instead, on purpose — see `NativeScreenViewController`'s two
/// initializers.
///
/// `.shared`, not one instance per `NativeScreenViewController`:
/// `PhpEmbedRuntime.start()` must run exactly once for the process'
/// entire lifetime (see that type's own docblock) — a fresh instance
/// per view controller would double-`start()` the moment a user
/// navigates back and a new screen controller gets pushed. Every
/// `fetchScreen` call — regardless of which `NativeScreenViewController`
/// issued it — is serialized onto one private queue for the same
/// reason: the embed SAPI's request/response cycle
/// (`phpx_embed_handle_request`) is a single global C buffer, not
/// reentrant, and PHP's own request lifecycle assumes one request
/// finishes before the next starts, same as a real single-worker PHP
/// server would.
public final class EmbeddedScreenDataSource: ScreenDataSource {
    /// The real app's own instance — its `PhpEmbedRuntime` is never
    /// `shutdown()`, by design (matches `PhpEmbedRuntime`'s own "one
    /// instance for the process' entire lifetime" invariant: HostApp
    /// itself never calls `shutdown()` either). `init(runtime:)` below
    /// exists specifically so tests DON'T have to share this permanent,
    /// never-torn-down instance — see that initializer's own docblock.
    public static let shared = EmbeddedScreenDataSource()

    /// `{"error":{"message":...}}` — the same envelope shape
    /// `public/index.php`'s `set_exception_handler()` writes for the
    /// `/native/layout-demo` route (see `ScreenClient.swift`'s own
    /// `ServerErrorEnvelope`, kept `internal` to that module — small
    /// enough to redeclare here rather than widen that type's access
    /// just for this).
    private struct ServerErrorEnvelope: Decodable {
        struct ServerError: Decodable { let message: String }
        let error: ServerError
    }

    private let runtime: PhpEmbedRuntime
    private let queue = DispatchQueue(label: "com.phpnitro.embedded-screen-source")
    private var started = false
    private var indexPath: String?

    /// `runtime` is injectable specifically so a test can own a
    /// throwaway `PhpEmbedRuntime` it `start()`s/`shutdown()`s itself
    /// (exactly like `PhpEmbedRuntimeTests`' own tests already do),
    /// instead of reaching into `.shared`'s permanent, process-lifetime
    /// instance — only ONE embed SAPI instance may ever be `start()`ed
    /// per process (see `PhpEmbedRuntime`'s own docblock), so a test
    /// that shares `.shared` alongside sibling tests creating their own
    /// fresh instances would crash on whichever tries to `start()`
    /// second, depending purely on XCTest's test-ordering — confirmed
    /// the hard way the first time `EmbeddedScreenDataSourceTests` and
    /// `PhpEmbedRuntimeTests` ran in the same process.
    public init(runtime: PhpEmbedRuntime = PhpEmbedRuntime()) {
        self.runtime = runtime
    }

    public func fetchScreen(
        _ screen: String,
        action: String?,
        width: Double,
        height: Double,
        fieldValues: [String: String],
        completion: @escaping (Result<DrawCommandPayload, ScreenFetchError>) -> Void
    ) {
        // A throwaway host:port pair purely to reuse ScreenClient.url()'s
        // own query-string construction/encoding — never actually
        // dialed, no network involved below this line.
        guard let url = ScreenClient.url(
            host: "embedded", port: 0, screen: screen, action: action,
            width: width, height: height, fieldValues: fieldValues
        ), let path = url.path.isEmpty ? nil : url.path else {
            completion(.failure(.decoding("invalid embedded request for screen \(screen)")))
            return
        }
        let requestUri = url.query.map { "\(path)?\($0)" } ?? path

        queue.async { [self] in
            if !started {
                runtime.start()
                started = true
            }
            if indexPath == nil {
                // The read-only app-bundle copy of www/ can't be used
                // directly — public/index.php writes real files at
                // runtime (SQLite database, PHP sessions), which fails
                // there (see stageWritableWwwDirectory()'s own docblock
                // for the real PDOException this fixes).
                guard let writableWwwURL = PhpEmbedRuntime.stageWritableWwwDirectory() else {
                    DispatchQueue.main.async {
                        completion(.failure(.network("Resources/www not staged, or couldn't copy it to a writable directory — run `phpx bundle:ios` first")))
                    }
                    return
                }
                indexPath = writableWwwURL.appendingPathComponent("public/index.php").path
            }

            let requestPhp = """
            $_SERVER['REQUEST_URI'] = '\(phpSingleQuoted(requestUri))';
            parse_str('\(phpSingleQuoted(url.query ?? ""))', $_GET);
            require '\(phpSingleQuoted(indexPath!))';
            """

            guard let output = runtime.handleRequest(requestPhp) else {
                completion(.failure(.server(status: 500, message: "embedded PHP raised an uncaught exception for screen \(screen)")))
                return
            }

            guard let data = output.data(using: .utf8) else {
                completion(.failure(.decoding("non-UTF8 output for screen \(screen)")))
                return
            }
            if let payload = try? JSONDecoder().decode(DrawCommandPayload.self, from: data) {
                completion(.success(payload))
                return
            }
            let message = (try? JSONDecoder().decode(ServerErrorEnvelope.self, from: data))?.error.message
                ?? String(output.prefix(500))
            completion(.failure(.server(status: 500, message: message)))
        }
    }
}

/// Escapes `'` and `\` for embedding inside a PHP single-quoted string
/// literal — the only two characters PHP itself treats specially there
/// (unlike a double-quoted literal, no variable interpolation or `\n`-
/// style escapes to worry about).
private func phpSingleQuoted(_ value: String) -> String {
    value.replacingOccurrences(of: "\\", with: "\\\\")
        .replacingOccurrences(of: "'", with: "\\'")
}
