import UIKit

@main
final class AppDelegate: UIResponder, UIApplicationDelegate {

    var window: UIWindow?

    /// Currently a documented no-op stub (see PhpEmbedBridge.swift) — the
    /// call site is wired up now so the embed-SAPI work has nowhere else
    /// to be forgotten once it's real. Mirrors PhpServer.start()/stop()
    /// being called from MainActivity.kt's onCreate()/onDestroy().
    private let phpEmbedBridge = PhpEmbedBridge()

    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {
        phpEmbedBridge.start()

        window = UIWindow(frame: UIScreen.main.bounds)
        window?.rootViewController = ViewController()
        window?.makeKeyAndVisible()
        return true
    }

    func applicationWillTerminate(_ application: UIApplication) {
        phpEmbedBridge.shutdown()
    }
}
