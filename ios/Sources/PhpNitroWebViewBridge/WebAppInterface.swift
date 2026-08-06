import AVFoundation
import LocalAuthentication
import PhotosUI
import UIKit
import UserNotifications
import WebKit

/// Bridge exposed to the JS running inside the WKWebView as
/// `window.iOSNative` — the iOS counterpart of Android's
/// WebAppInterface.kt/`window.AndroidNative`. device.js/dialogs.js already
/// check `window.AndroidNative || window.iOSNative` for every capability,
/// so no JS changes are needed beyond that: this file only has to answer
/// the same messages with the same callback shape.
///
/// Unlike Android's `@JavascriptInterface` (a synchronous Java object JS
/// calls directly), WKWebView only offers `WKScriptMessageHandler` — an
/// async postMessage bridge. That's not a limitation here: every call this
/// project makes into the native bridge is already fire-and-forget or
/// answered via a global JS callback (`window.onNativePhotoTaken`,
/// `onNativeBiometricResult`, `onNativeImagePicked`, `onNativeConfirmResult`)
/// rather than a return value — see ViewController.swift for the JS shim
/// (`window.iOSNative = {...}`) that turns each method call into a
/// `postMessage`.
///
/// NOT COMPILED, NOT TESTED — written without Xcode/a Mac available in this
/// environment. Every API used here (LocalAuthentication, PHPickerViewController,
/// UIPrintInteractionController, UNUserNotificationCenter) is long-stable,
/// well-documented iOS SDK surface, but this file has never been built.
/// Verify against a real Xcode toolchain before shipping.
final class WebAppInterface: NSObject, WKScriptMessageHandler,
    UIImagePickerControllerDelegate, UINavigationControllerDelegate,
    PHPickerViewControllerDelegate {

    /// The single message handler name the injected JS shim posts to (see
    /// ViewController.swift's WKUserScript) — every action is dispatched by
    /// a "action" field in the message body instead of registering one
    /// handler per capability.
    static let messageHandlerName = "phpxNative"

    private weak var webView: WKWebView?
    private weak var presentingViewController: UIViewController?
    private var audioPlayer: AVPlayer?

    init(webView: WKWebView, presentingViewController: UIViewController) {
        self.webView = webView
        self.presentingViewController = presentingViewController
    }

    // MARK: - WKScriptMessageHandler

    /// Apple guarantees this fires on the main thread, so everything
    /// dispatched from here (presenting a UIAlertController/UIImagePickerController)
    /// is already main-thread-safe without an extra `DispatchQueue.main.async`.
    /// The async completion handlers further down (LAContext, PHPicker) do
    /// still need one, since THEY run on an arbitrary queue.
    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        guard let body = message.body as? [String: Any], let action = body["action"] as? String else {
            return
        }

        switch action {
        case "vibrate":
            vibrate()
        case "takeNativePhoto":
            takeNativePhoto()
        case "pickImage":
            pickImage()
        case "showBiometricPrompt":
            showBiometricPrompt()
        case "playSound":
            if let url = body["url"] as? String {
                playSound(url)
            }
        case "showNotification":
            showNotification(title: body["title"] as? String ?? "", message: body["message"] as? String ?? "")
        case "printPage":
            printPage()
        case "share":
            if let text = body["text"] as? String {
                share(text: text)
            }
        case "showAlertDialog":
            showAlertDialog(title: body["title"] as? String ?? "", message: body["message"] as? String ?? "")
        case "showConfirmDialog":
            showConfirmDialog(title: body["title"] as? String ?? "", message: body["message"] as? String ?? "")
        default:
            break
        }
    }

    // MARK: - Vibrate

    /// iOS has no public API for an arbitrary-duration vibration the way
    /// Android's `VibrationEffect.createOneShot(milliseconds, ...)` does —
    /// third-party apps only get the Taptic Engine's short, fixed-length
    /// feedback generators. `milliseconds` is accepted (for call-shape
    /// parity with Android — the JS shim still passes it) but genuinely
    /// ignored here: every call produces the same short haptic pulse,
    /// unlike Android where the duration is honored exactly.
    private func vibrate() {
        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
    }

    // MARK: - Camera (native photo)

    private func takeNativePhoto() {
        guard let presenter = presentingViewController, UIImagePickerController.isSourceTypeAvailable(.camera) else {
            deliverPhoto(dataURL: nil)
            return
        }

        let picker = UIImagePickerController()
        picker.sourceType = .camera
        picker.delegate = self
        presenter.present(picker, animated: true)
    }

    func imagePickerController(
        _ picker: UIImagePickerController,
        didFinishPickingMediaWithInfo info: [UIImagePickerController.InfoKey: Any]
    ) {
        picker.dismiss(animated: true)

        guard let image = info[.originalImage] as? UIImage, let data = image.jpegData(compressionQuality: 0.8) else {
            deliverPhoto(dataURL: nil)
            return
        }

        deliverPhoto(dataURL: "data:image/jpeg;base64,\(data.base64EncodedString())")
    }

    func imagePickerControllerDidCancel(_ picker: UIImagePickerController) {
        picker.dismiss(animated: true)
        deliverPhoto(dataURL: nil)
    }

    private func deliverPhoto(dataURL: String?) {
        callback("window.onNativePhotoTaken", argument: dataURL)
    }

    // MARK: - Image picker (gallery)

    /// PHPickerViewController (iOS 14+) runs the picker UI in a separate
    /// process and only hands the app the specific item the user selected —
    /// unlike the legacy UIImagePickerController photo-library mode, this
    /// does NOT require NSPhotoLibraryUsageDescription in Info.plist at all.
    private func pickImage() {
        guard let presenter = presentingViewController else {
            deliverPickedImage(dataURL: nil)
            return
        }

        var config = PHPickerConfiguration()
        config.filter = .images
        config.selectionLimit = 1

        let picker = PHPickerViewController(configuration: config)
        picker.delegate = self
        presenter.present(picker, animated: true)
    }

    func picker(_ picker: PHPickerViewController, didFinishPicking results: [PHPickerResult]) {
        picker.dismiss(animated: true)

        guard let provider = results.first?.itemProvider, provider.canLoadObject(ofClass: UIImage.self) else {
            deliverPickedImage(dataURL: nil)
            return
        }

        provider.loadObject(ofClass: UIImage.self) { [weak self] object, _ in
            DispatchQueue.main.async {
                guard let image = object as? UIImage, let data = image.jpegData(compressionQuality: 0.8) else {
                    self?.deliverPickedImage(dataURL: nil)
                    return
                }
                self?.deliverPickedImage(dataURL: "data:image/jpeg;base64,\(data.base64EncodedString())")
            }
        }
    }

    private func deliverPickedImage(dataURL: String?) {
        callback("window.onNativeImagePicked", argument: dataURL)
    }

    // MARK: - Biometric (Face ID / Touch ID)

    /// LAContext.evaluatePolicy is iOS's equivalent of Android's
    /// BiometricPrompt — same "does NOT need WebAuthn" rationale documented
    /// in device.js: WKWebView doesn't implement platform authenticators
    /// the way a full browser does, so this native path is the only
    /// reliable one, not an optimization.
    private func showBiometricPrompt() {
        let context = LAContext()
        var error: NSError?

        guard context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &error) else {
            reportBiometricResult(success: false, message: biometricUnavailableReason(error))
            return
        }

        context.evaluatePolicy(
            .deviceOwnerAuthenticationWithBiometrics,
            localizedReason: "Confirme ton identité"
        ) { [weak self] success, evaluationError in
            DispatchQueue.main.async {
                self?.reportBiometricResult(
                    success: success,
                    message: success ? "" : (evaluationError?.localizedDescription ?? "Authentification échouée.")
                )
            }
        }
    }

    private func biometricUnavailableReason(_ error: NSError?) -> String {
        switch error?.code {
        case LAError.biometryNotEnrolled.rawValue:
            return "Aucune empreinte/visage enregistré sur ce téléphone."
        case LAError.biometryNotAvailable.rawValue:
            return "Ce device n'a pas de capteur biométrique."
        default:
            return "Authentification biométrique indisponible."
        }
    }

    private func reportBiometricResult(success: Bool, message: String) {
        let successLiteral = success ? "true" : "false"
        webView?.evaluateJavaScript(
            "window.onNativeBiometricResult && window.onNativeBiometricResult(\(successLiteral), \(jsStringLiteral(message)))"
        )
    }

    // MARK: - Sound

    /// AVPlayer (not AVAudioPlayer, which needs local Data/file up front)
    /// streams the URL directly — closer to Android's
    /// `MediaPlayer.setDataSource(url)` + `prepareAsync()` than downloading
    /// the whole file first would be. `url` may be relative (e.g.
    /// "/assets/audio/beep.wav", as PHP widgets emit it) — resolved against
    /// the WKWebView's current page URL, i.e. the embedded PHP server's
    /// own origin.
    private func playSound(_ urlString: String) {
        guard let resolved = URL(string: urlString, relativeTo: webView?.url)?.absoluteURL else {
            return
        }

        audioPlayer = AVPlayer(url: resolved)
        audioPlayer?.play()
    }

    // MARK: - Notification

    /// UNUserNotificationCenter's local notifications — like Android's
    /// NotificationCompat path, independent of any push service, works
    /// fully offline. Requesting authorization on every call is redundant
    /// once the user has answered once (iOS caches the decision and never
    /// re-prompts), so this stays simple rather than tracking state
    /// separately.
    private func showNotification(title: String, message: String) {
        let center = UNUserNotificationCenter.current()
        center.requestAuthorization(options: [.alert, .sound]) { granted, _ in
            guard granted else { return }

            let content = UNMutableNotificationContent()
            content.title = title
            content.body = message

            let request = UNNotificationRequest(identifier: UUID().uuidString, content: content, trigger: nil)
            center.add(request)
        }
    }

    // MARK: - Print

    /// `WKWebView.viewPrintFormatter()` (inherited from UIView) is iOS's
    /// equivalent of Android's `WebView.createPrintDocumentAdapter()` — the
    /// same system print/"Save as PDF"/AirPrint sheet, no PHP PDF library
    /// needed, same as the Android path.
    private func printPage() {
        guard let webView = webView else { return }

        let printInfo = UIPrintInfo(dictionary: nil)
        printInfo.outputType = .general
        printInfo.jobName = "Document"

        let controller = UIPrintInteractionController.shared
        controller.printInfo = printInfo
        controller.printFormatter = webView.viewPrintFormatter()
        controller.present(animated: true, completionHandler: nil)
    }

    // MARK: - Share

    /// UIActivityViewController — the real iOS share sheet, same idea as
    /// Android's Intent.ACTION_SEND chooser (WebAppInterface.kt's share()).
    /// title is accepted for call-shape parity with Android (which passes it
    /// as the chooser dialog's own title) but unused here: UIActivityViewController
    /// has no equivalent "chooser title" parameter, only the shared items.
    private func share(text: String) {
        guard let presenter = presentingViewController else { return }

        let activity = UIActivityViewController(activityItems: [text], applicationActivities: nil)
        presenter.present(activity, animated: true)
    }

    // MARK: - Dialogs (Engine\Dialogs\)

    private func showAlertDialog(title: String, message: String) {
        guard let presenter = presentingViewController else { return }

        let alert = UIAlertController(title: title.isEmpty ? nil : title, message: message, preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "OK", style: .default))
        presenter.present(alert, animated: true)
    }

    private func showConfirmDialog(title: String, message: String) {
        guard let presenter = presentingViewController else {
            reportConfirmDialogResult(false)
            return
        }

        let alert = UIAlertController(title: title.isEmpty ? nil : title, message: message, preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "OK", style: .default) { [weak self] _ in
            self?.reportConfirmDialogResult(true)
        })
        alert.addAction(UIAlertAction(title: "Annuler", style: .cancel) { [weak self] _ in
            self?.reportConfirmDialogResult(false)
        })
        presenter.present(alert, animated: true)
    }

    private func reportConfirmDialogResult(_ confirmed: Bool) {
        webView?.evaluateJavaScript("window.onNativeConfirmResult && window.onNativeConfirmResult(\(confirmed ? "true" : "false"))")
    }

    // MARK: - JS callback helpers

    /// Same role as Android's `JSONObject.quote(message)`: safely encodes a
    /// Swift string as a JS string literal (quotes + escaping handled by
    /// JSONEncoder, not a hand-rolled `replacingOccurrences`) so a title or
    /// message containing quotes/newlines can never break the injected script.
    private func jsStringLiteral(_ value: String) -> String {
        guard let data = try? JSONEncoder().encode(value), let json = String(data: data, encoding: .utf8) else {
            return "\"\""
        }
        return json
    }

    private func callback(_ functionName: String, argument: String?) {
        let literal = argument.map(jsStringLiteral) ?? "null"
        webView?.evaluateJavaScript("\(functionName) && \(functionName)(\(literal))")
    }
}
