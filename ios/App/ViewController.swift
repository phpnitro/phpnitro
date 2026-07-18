import UIKit
import WebKit

/// Hosts the PHP-served UI in a native WKWebView — the iOS equivalent of
/// android/app/src/main/java/com/mobile/engine/MainActivity.kt.
///
/// Wires up the same native bridge (see WebAppInterface.swift) so every
/// Engine\Device\/Engine\Dialogs\ service works from iOS exactly like it
/// does from Android — `window.iOSNative`, injected here as a WKUserScript,
/// with the same method names/callback shape as `window.AndroidNative`.
///
/// What's still missing, unlike Android (see PhpEmbedBridge.swift): there is
/// no PHP binary running on the device yet. `serverURL` below points at a
/// PHP process reachable over the network — replace
/// `YOUR_COMPUTER_LAN_IP` with the LAN IP of the machine running
/// `php bin/phpx serve` to test any of this once compiled on a Mac.
///
/// NOT COMPILED, NOT TESTED — no Xcode/Mac available in this environment.
final class ViewController: UIViewController, WKUIDelegate, WKNavigationDelegate {

    private let serverURL = URL(string: "http://YOUR_COMPUTER_LAN_IP:8090/")!

    private var webView: WKWebView!
    private var webAppInterface: WebAppInterface!

    override func viewDidLoad() {
        super.viewDidLoad()
        configureWebView()

        view.addSubview(webView)
        webView.frame = view.bounds
        webView.autoresizingMask = [.flexibleWidth, .flexibleHeight]

        webView.load(URLRequest(url: serverURL))
    }

    private func configureWebView() {
        let userContentController = WKUserContentController()

        // window.iOSNative — same method names as window.AndroidNative, each
        // one just posts {action, ...params} to the "phpxNative" message
        // handler WebAppInterface registers below. Injected at document
        // start so it exists before any PHP-rendered page script runs
        // (mirrors nav.js's own script-ordering concerns after a partial
        // swap — see WebAppInterface.swift's header comment).
        let bridgeScriptSource = """
            window.iOSNative = {
                vibrate: function (ms) {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'vibrate', ms: ms });
                },
                takeNativePhoto: function () {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'takeNativePhoto' });
                },
                pickImage: function () {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'pickImage' });
                },
                showBiometricPrompt: function () {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'showBiometricPrompt' });
                },
                playSound: function (url) {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'playSound', url: url });
                },
                showNotification: function (title, message) {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'showNotification', title: title, message: message });
                },
                printPage: function () {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'printPage' });
                },
                showAlertDialog: function (title, message) {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'showAlertDialog', title: title, message: message });
                },
                showConfirmDialog: function (title, message) {
                    window.webkit.messageHandlers.phpxNative.postMessage({ action: 'showConfirmDialog', title: title, message: message });
                },
            };
            """
        let bridgeScript = WKUserScript(
            source: bridgeScriptSource,
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true
        )
        userContentController.addUserScript(bridgeScript)

        let configuration = WKWebViewConfiguration()
        configuration.userContentController = userContentController
        // Needed for <video>/<audio> inline playback (CameraPreview's own
        // <video> element, AudioPlayer/VideoPlayer widgets) instead of
        // forcing fullscreen playback the way WKWebView defaults to.
        configuration.allowsInlineMediaPlayback = true

        webView = WKWebView(frame: .zero, configuration: configuration)
        webView.uiDelegate = self
        webView.navigationDelegate = self
        // iOS's equivalent of wiring the hardware back button on Android
        // (MainActivity.kt's OnBackPressedCallback): nav.js pushes
        // history.pushState entries for every partial navigation, so a
        // swipe-from-edge gesture can pop back through them via the
        // WKWebView's own back/forward list.
        webView.allowsBackForwardNavigationGestures = true
        // A visible bouncing/fading scrollbar reads as "browser", not
        // "app" — same reasoning as MainActivity.kt disabling Android's
        // WebView scrollbars.
        webView.scrollView.showsVerticalScrollIndicator = false
        webView.scrollView.showsHorizontalScrollIndicator = false

        webAppInterface = WebAppInterface(webView: webView, presentingViewController: self)
        userContentController.add(webAppInterface, name: WebAppInterface.messageHandlerName)
    }

    // MARK: - WKUIDelegate

    /// Grants camera/microphone access requested via getUserMedia (the
    /// CameraPreview/MicrophoneButton widgets' fallback path when no native
    /// bridge call is used) — iOS 15+ only; on this WKWebView API doesn't
    /// exist before that, unlike Android's `onPermissionRequest` which has
    /// worked since WebView's early permission APIs. Equivalent Info.plist
    /// keys (NSCameraUsageDescription/NSMicrophoneUsageDescription) are
    /// still required regardless — already present.
    @available(iOS 15.0, *)
    func webView(
        _ webView: WKWebView,
        requestMediaCapturePermissionFor origin: WKSecurityOrigin,
        initiatedByFrame frame: WKFrameInfo,
        type: WKMediaCaptureType,
        decisionHandler: @escaping (WKPermissionDecision) -> Void
    ) {
        decisionHandler(.grant)
    }

    // MARK: - WKNavigationDelegate

    /// Defensive: if the very first load races the PHP server still
    /// starting up (once PhpEmbedBridge.swift is real), retry instead of
    /// leaving a permanently blank WebView — same idiom as
    /// MainActivity.kt's WebViewClient.onReceivedError.
    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        DispatchQueue.main.asyncAfter(deadline: .now() + 1) { [weak self] in
            guard let self = self else { return }
            self.webView.load(URLRequest(url: self.serverURL))
        }
    }
}
