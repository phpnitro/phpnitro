import UIKit
import PhpNitroNativeEngine

@main
final class AppDelegate: UIResponder, UIApplicationDelegate {
    var window: UIWindow?

    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {
        // Lets a UI test point this harness at a specific PHP screen
        // (e.g. "device") without a second AppDelegate/target — set via
        // -screen <name> in the test's launch arguments; every real use
        // of this app (manual QA, screenshots) just gets "home".
        let args = ProcessInfo.processInfo.arguments
        func arg(_ name: String, default defaultValue: String) -> String {
            args.firstIndex(of: name).flatMap { args.count > $0 + 1 ? args[$0 + 1] : nil } ?? defaultValue
        }
        let screen = arg("-screen", default: "home")
        // "127.0.0.1" only ever reaches the SIMULATOR's own host Mac — a
        // real device has no such shortcut (it's the phone's own
        // loopback there), so a real-device run needs the Mac's actual
        // LAN IP passed via -host (2026-09-09, first iPhone available
        // for this project, confirmed this exact gap: the app launched
        // fine, every fetch just failed to connect until this existed).
        let host = arg("-host", default: "127.0.0.1")

        let window = UIWindow(frame: UIScreen.main.bounds)
        let root = NativeScreenViewController(host: host, port: 8090, screen: screen)
        window.rootViewController = UINavigationController(rootViewController: root)
        window.makeKeyAndVisible()
        self.window = window
        return true
    }
}
