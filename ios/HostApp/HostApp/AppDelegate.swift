import UIKit
import PhpNitroNativeEngine

@main
final class AppDelegate: UIResponder, UIApplicationDelegate {
    var window: UIWindow?

    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {
        let window = UIWindow(frame: UIScreen.main.bounds)
        let root = NativeScreenViewController(host: "127.0.0.1", port: 8090, screen: "home")
        window.rootViewController = UINavigationController(rootViewController: root)
        window.makeKeyAndVisible()
        self.window = window
        return true
    }
}
