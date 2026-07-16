import UIKit
import WebKit

/// Hosts the PHP-served UI in a native WKWebView — the iOS equivalent of
/// android/app/src/main/java/com/mobile/engine/MainActivity.kt.
///
/// Unlike Android, there is no PHP binary cross-compiled for iOS yet (that
/// requires Xcode's toolchain and a Mac, neither available in the
/// environment this framework was built in). For now this points at a PHP
/// server reachable over the network — same starting point Android had
/// before its NDK cross-compile. Swap `serverURL` for `http://127.0.0.1:PORT`
/// once an on-device PHP process exists (see PhpServer.kt for the Android
/// approach: cross-compile via the NDK, bundle as a resource, launch as a
/// subprocess with Process/NSTask on start).
final class ViewController: UIViewController, WKUIDelegate {

    private let serverURL = URL(string: "http://YOUR_COMPUTER_LAN_IP:8090/")!

    private lazy var webView: WKWebView = {
        let configuration = WKWebViewConfiguration()
        let webView = WKWebView(frame: .zero, configuration: configuration)
        webView.uiDelegate = self
        return webView
    }()

    override func viewDidLoad() {
        super.viewDidLoad()

        webView.frame = view.bounds
        webView.autoresizingMask = [.flexibleWidth, .flexibleHeight]
        view.addSubview(webView)

        webView.load(URLRequest(url: serverURL))
    }
}
