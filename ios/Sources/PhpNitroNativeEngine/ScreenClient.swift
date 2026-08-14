import Foundation

public enum ScreenFetchError: Error, Equatable {
    case network(String)
    case server(status: Int, message: String)
    case decoding(String)
}

/// Body shape of public/index.php's own `{"error":{"class":...,
/// "message":...}}` — set_exception_handler()'s payload for the
/// `/native/layout-demo` route, see that file's own docblock.
struct ServerErrorEnvelope: Decodable {
    struct ServerError: Decodable {
        let message: String
    }

    let error: ServerError
}

/// The iOS counterpart of NativeRenderPocActivity.kt's
/// fetchDrawCommands() — deliberately the MINIMAL slice of that method's
/// contract: fetch one screen, optionally with a tap action, get back a
/// DrawCommandPayload. Everything else fetchDrawCommands() also does
/// (screen-stack push/pop, lastHash short-circuiting so an unchanged
/// screen skips re-parsing, dark/locale/online params, scroll-position
/// prefetch hints for LazyList, form field values, polling for
/// Async/Canvas::pollAgain(), confetti/snackbar/redirect side-channels)
/// is real, separate follow-up work — see ios/README.md.
public final class ScreenClient {
    private let host: String
    private let port: Int
    private let session: URLSession

    public init(host: String, port: Int, session: URLSession = .shared) {
        self.host = host
        self.port = port
        self.session = session
    }

    /// Mirrors fetchDrawCommands()'s own
    /// "/native/layout-demo?width=...&height=...&screen=...&action=..."
    /// URL, minus the params this minimal client doesn't send yet (see
    /// this type's own docblock) — every one of those has a server-side
    /// default (see public/index.php's own `$_GET[...] ?? ...` fallbacks),
    /// so omitting them is a real degradation (no dark mode, no i18n,
    /// always the full 'fr'/light-mode render) rather than a broken
    /// request.
    static func url(host: String, port: Int, screen: String, action: String?, width: Double, height: Double) -> URL? {
        var components = URLComponents()
        components.scheme = "http"
        components.host = host
        components.port = port
        components.path = "/native/layout-demo"

        var items = [
            URLQueryItem(name: "screen", value: screen),
            URLQueryItem(name: "width", value: String(width)),
            URLQueryItem(name: "height", value: String(height)),
        ]
        if let action {
            items.append(URLQueryItem(name: "action", value: action))
        }
        components.queryItems = items

        return components.url
    }

    /// `completion` is called on an arbitrary background queue (whatever
    /// URLSession's delegate queue happens to be) — same contract
    /// URLSession itself has; a caller touching UIKit from it must hop to
    /// the main queue itself (see NativeScreenViewController's own
    /// fetch()).
    public func fetchScreen(
        _ screen: String,
        action: String? = nil,
        width: Double,
        height: Double,
        completion: @escaping (Result<DrawCommandPayload, ScreenFetchError>) -> Void
    ) {
        guard let url = Self.url(host: host, port: port, screen: screen, action: action, width: width, height: height) else {
            completion(.failure(.decoding("invalid URL for host \(host):\(port)")))
            return
        }

        var request = URLRequest(url: url)
        request.timeoutInterval = 8

        session.dataTask(with: request) { data, response, error in
            if let error {
                completion(.failure(.network(error.localizedDescription)))
                return
            }
            guard let http = response as? HTTPURLResponse, let data else {
                completion(.failure(.network("no response body")))
                return
            }
            // HttpURLConnection on the Android side has separate
            // .inputStream/.errorStream accessors for this same split
            // (see fetchDrawCommands()'s own comment on that) — URLSession
            // only ever gives one `data`, so the status-code check itself
            // is what routes a non-2xx body to `.server` instead of
            // trying (and failing) to decode it as a DrawCommandPayload.
            guard (200..<300).contains(http.statusCode) else {
                let message = (try? JSONDecoder().decode(ServerErrorEnvelope.self, from: data))?.error.message
                    ?? "HTTP \(http.statusCode)"
                completion(.failure(.server(status: http.statusCode, message: message)))
                return
            }
            do {
                completion(.success(try JSONDecoder().decode(DrawCommandPayload.self, from: data)))
            } catch {
                completion(.failure(.decoding(error.localizedDescription)))
            }
        }.resume()
    }
}
