import UIKit
import PhpNitroNativeEngine

@main
final class AppDelegate: UIResponder, UIApplicationDelegate {
    var window: UIWindow?

    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {
        // device:bgschedule — BGTaskScheduler requires this identifier's
        // handler registered before the app finishes launching, not
        // lazily the first time a screen actually schedules one.
        NativeDeviceBridge.registerBackgroundTask()

        // Lets a UI test point this harness at a specific PHP screen
        // (e.g. "device") without a second AppDelegate/target — set via
        // -screen <name> in the test's launch arguments; every real use
        // of this app (manual QA, screenshots) just gets "home".
        let args = ProcessInfo.processInfo.arguments
        func arg(_ name: String, default defaultValue: String) -> String {
            args.firstIndex(of: name).flatMap { args.count > $0 + 1 ? args[$0 + 1] : nil } ?? defaultValue
        }
        let screen = arg("-screen", default: "home")
        // -host still works for a UI test (or a manual run) that wants
        // to compare this app against a real `phpx serve` round-trip
        // instead — "127.0.0.1" only ever reaches the SIMULATOR's own
        // host Mac, a real device has no such shortcut (it's the
        // phone's own loopback there), which is why this ever needed to
        // be an explicit launch argument at all (2026-09-09, first
        // iPhone available for this project: the app launched fine,
        // every network fetch just failed to connect until this
        // existed). Omitted entirely (the normal case now): HostApp
        // serves its own bundled PHP in-process via PhpEmbedRuntime
        // (EmbeddedScreenDataSource) — no developer machine required at
        // all, see NativeScreenViewController's two initializers.
        let host = args.firstIndex(of: "-host").flatMap { args.count > $0 + 1 ? args[$0 + 1] : nil }

        let window = UIWindow(frame: UIScreen.main.bounds)
        let root: NativeScreenViewController = host.map {
            NativeScreenViewController(host: $0, port: 8090, screen: screen)
        } ?? NativeScreenViewController(embeddedScreen: screen)
        window.rootViewController = UINavigationController(rootViewController: root)
        window.makeKeyAndVisible()
        self.window = window

        // device:applink (Engine\Device\AppLinks) — a "phpnitro://" URL
        // that launched this process cold arrives here, not through
        // application(_:open:options:) below (that one only fires for a
        // link opened while already running).
        if let url = launchOptions?[.url] as? URL {
            NativeDeviceBridge.recordAppLink(url)
        }
        return true
    }

    func application(_ app: UIApplication, open url: URL, options: [UIApplication.OpenURLOptionsKey: Any] = [:]) -> Bool {
        NativeDeviceBridge.recordAppLink(url)
        return true
    }
}
