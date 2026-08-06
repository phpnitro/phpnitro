import Foundation
import WebKit

/// ARCHITECTURE SKETCH — NOT WORKING CODE. NOT COMPILED, NOT TESTED.
///
/// This file exists to correct a real mistake in this project's earlier
/// thinking (previously written in ios/README.md): the assumption that iOS
/// could run PHP the same way Android does — cross-compile a `php -S`
/// binary, then launch it as a subprocess via `Process`/`NSTask` from
/// PhpServer.kt's Swift equivalent.
///
/// That approach is impossible on iOS. `Process` (formerly `NSTask`) is
/// part of Foundation but is explicitly UNAVAILABLE on iOS/tvOS/watchOS —
/// only macOS apps can spawn subprocess executables. Apple's app sandbox on
/// iOS does not permit it, full stop. Android's NDK-cross-compiled
/// `libphp.so` executed via `ProcessBuilder` has no iOS equivalent, no
/// matter how PHP itself is built.
///
/// ## The actual correct approach: PHP's embed SAPI, in-process
///
/// PHP ships a SAPI (Server API) specifically for this: `sapi/embed`,
/// built via php-src's `--enable-embed=static` configure flag. It compiles
/// PHP into a static library exposing a small C API
/// (`php_embed_init`/`php_embed_shutdown`, plus the normal Zend Engine
/// execution entry points) that a HOST PROGRAM links directly and calls
/// in-process — no subprocess, no socket server required to exist as a
/// separate OS process. This is the same idea projects like a2php/Peachpie
/// embedding scenarios and various "PHP in an iOS app" proofs of concept
/// use; it's the standard way to run PHP inside a process that isn't a
/// traditional CLI/CGI/FPM host.
///
/// Concretely, for this framework:
/// 1. Cross-compile php-src for `arm64-apple-ios` (device) and
///    `arm64-apple-ios-simulator`/`x86_64-apple-ios-simulator` (simulator)
///    using Xcode's clang + iOS SDK sysroot, with `--enable-embed=static`
///    and the extensions this project actually needs (pdo_sqlite at
///    minimum, matching Android's bundled libsqlite3.so). This produces a
///    `libphp.a` (or `.xcframework` bundling both device/simulator slices).
/// 2. A thin C/Objective-C shim (a bridging header — Swift cannot call
///    some of php-src's C macros/varargs directly) exposes something like:
///    `phpx_embed_start()`, `phpx_embed_execute_request(method, path,
///    headers, body) -> (status, headers, body)`, `phpx_embed_shutdown()`.
/// 3. Swift calls that shim once at app launch (`phpx_embed_start()`,
///    replacing PhpServer.kt's subprocess spawn), then on every WebView
///    request.
///
/// ## Serving requests: WKURLSchemeHandler, not a socket server
///
/// Rather than reimplementing an HTTP server loop against 127.0.0.1 inside
/// the embed SAPI (extra complexity, extra risk of App Transport
/// Security/background-execution edge cases), the natural iOS-native way
/// to serve the WKWebView from in-process PHP is a custom URL scheme
/// handler: WKWebView lets an app intercept ANY scheme (not just
/// http/https) via `WKURLSchemeHandler`, registered on the
/// `WKWebViewConfiguration` this framework's `ViewController.swift` already
/// builds. Every request the WebView makes — including the initial page
/// load, `<script src>`, `fetch()` calls nav.js makes for partial
/// navigation — comes through `webView(_:start:)` below, translated into a
/// PHP request via the embed SAPI, with the response fed back through the
/// same `WKURLSchemeTask`.
///
/// This sketch below shows the SHAPE this needs to take. The actual
/// php-src build flags, the bridging header's exact C signatures, and the
/// request/response translation details have NOT been verified — there is
/// no Mac/Xcode/php-src build toolchain available in this environment to
/// iterate against. Treat every function body below as a documented `TODO`,
/// not working code.
final class PhpEmbedBridge: NSObject, WKURLSchemeHandler {

    /// The scheme ViewController.swift's WKWebViewConfiguration must
    /// register this handler for, and the scheme `serverURL` must use
    /// instead of `http://127.0.0.1:PORT/` once this is real — e.g.
    /// `phpx://app/` resolving to `public/index.php`.
    static let scheme = "phpx"

    /// Must be called once at app launch (AppDelegate.application(_:didFinishLaunchingWithOptions:)),
    /// mirroring PhpServer.kt's `start()` — except here there's no port to
    /// bind or wait for, since nothing is listening on a socket.
    ///
    /// TODO: call into the embed-SAPI bridging header's init function,
    /// pointing it at the same `public/`, `lib/`, `packages/` tree Android
    /// copies into assets/www (see bin/phpx's cmdBundleAndroid) — bundled
    /// here as an Xcode "Copy Bundle Resources" phase instead of Android's
    /// AssetManager copy-to-filesDir step.
    func start() {
        // TODO: phpx_embed_start(bundleRootPath)
    }

    /// TODO: call the embed-SAPI shutdown function. Mirrors PhpServer.kt's
    /// `stop()`, called from a scene/app lifecycle teardown hook.
    func shutdown() {
        // TODO: phpx_embed_shutdown()
    }

    // MARK: - WKURLSchemeHandler

    func webView(_ webView: WKWebView, start urlSchemeTask: WKURLSchemeTask) {
        guard let request = urlSchemeTask.request as URLRequest?, let url = request.url else {
            urlSchemeTask.didFailWithError(URLError(.badURL))
            return
        }

        // TODO: translate `request` (method, path, headers, HTTP body) into
        // whatever shape the embed-SAPI shim's execute function expects —
        // the Swift-side equivalent of PHP's own $_SERVER/$_GET/$_POST
        // superglobal population that a normal SAPI (CLI server, FPM)
        // handles for you automatically. The embed SAPI does NOT do this
        // itself; the host program (this file) is responsible for it.
        //
        // TODO: execute the request against public/index.php (or
        // public/router.php, matching bin/phpx serve's behavior) via the
        // embed SAPI, capturing stdout instead of it going to a real
        // stdout fd, and capturing headers PHP would normally have sent
        // via header() calls Symfony's HttpFoundation Response emits.
        //
        // TODO: feed the captured status/headers/body back via
        // urlSchemeTask.didReceive(response:), .didReceive(data:), and
        // .didFinish() — WKURLSchemeTask's contract, not an HTTP response
        // in the socket sense.

        urlSchemeTask.didFailWithError(URLError(.unsupportedURL))
    }

    func webView(_ webView: WKWebView, stop urlSchemeTask: WKURLSchemeTask) {
        // TODO: cancel any in-flight embed-SAPI execution for this task,
        // if the request translation above ends up asynchronous.
    }
}
