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
        let screen = args.firstIndex(of: "-screen").flatMap { args.count > $0 + 1 ? args[$0 + 1] : nil } ?? "home"

        let window = UIWindow(frame: UIScreen.main.bounds)
        let root = NativeScreenViewController(host: "127.0.0.1", port: 8090, screen: screen)
        window.rootViewController = UINavigationController(rootViewController: root)
        window.makeKeyAndVisible()
        self.window = window
        return true
    }
}
