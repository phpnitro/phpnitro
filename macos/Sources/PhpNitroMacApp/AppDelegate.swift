import AppKit
import PhpNitroMacEngine
import RustMacRenderer

/// The macOS counterpart of `linux/phpnitro_desktop/app.py`'s `ScreenWindow`/
/// `run_local()` and `ios/App`'s own AppDelegate — owns exactly one
/// `MacPhpProcess` (started at launch, stopped at quit) and one window
/// showing a `RustScreenController`'s content view. Local mode only for
/// now: `args[1]` is the project directory to serve, no `--connect
/// HOST:PORT` remote mode yet (see `linux/phpnitro_desktop/__main__.py`'s
/// own docblock for that convention — real, separate follow-up work).
final class AppDelegate: NSObject, NSApplicationDelegate {
    private var window: NSWindow?
    private var phpProcess: MacPhpProcess?
    private var screenController: RustScreenController?

    func applicationDidFinishLaunching(_ notification: Notification) {
        let arguments = CommandLine.arguments
        guard arguments.count > 1 else {
            NSLog("usage: PhpNitroMacApp <project_directory> [screen]")
            NSApp.terminate(nil)
            return
        }
        let projectDirectory = URL(fileURLWithPath: arguments[1])
        let screen = arguments.count > 2 ? arguments[2] : "home"

        let process = MacPhpProcess(projectDirectory: projectDirectory)
        do {
            let port = try process.start()
            phpProcess = process

            let renderer = try RustRenderer()
            let frame = NSRect(x: 0, y: 0, width: 390, height: 844)
            let controller = RustScreenController(host: "127.0.0.1", port: port, initialScreen: screen, renderer: renderer, frame: frame)
            screenController = controller

            let window = NSWindow(
                contentRect: frame,
                styleMask: [.titled, .closable, .miniaturizable, .resizable],
                backing: .buffered,
                defer: false
            )
            window.title = "PhpNitro"
            window.contentView = controller.contentView
            window.center()
            window.makeKeyAndOrderFront(nil)
            self.window = window

            controller.start()
        } catch {
            NSLog("PhpNitroMacApp failed to start: \(error)")
            NSApp.terminate(nil)
        }
    }

    func applicationWillTerminate(_ notification: Notification) {
        phpProcess?.stop()
    }

    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool { true }
}
