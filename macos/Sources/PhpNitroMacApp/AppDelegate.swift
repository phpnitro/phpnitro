import AppKit
import PhpNitroMacEngine
import RustMacRenderer

/// The macOS counterpart of `linux/phpnitro_desktop/app.py`'s `ScreenWindow`/
/// `run_local()`/`run_remote()` and `ios/App`'s own AppDelegate — owns at
/// most one `MacPhpProcess` (started at launch, stopped at quit; none at
/// all in `--connect` mode) and one window showing a
/// `RustScreenController`'s content view.
///
/// Two launch modes, mirroring `linux/phpnitro_desktop/__main__.py`'s own
/// convention:
///   PhpNitroMacApp <project_directory> [screen]   — spawns `php -S` itself
///   PhpNitroMacApp --connect HOST:PORT [screen]   — PhpNitro-Go-style
///                                                    remote client, no
///                                                    local process at all
final class AppDelegate: NSObject, NSApplicationDelegate {
    private var window: NSWindow?
    private var phpProcess: MacPhpProcess?
    private var screenController: RustScreenController?

    func applicationDidFinishLaunching(_ notification: Notification) {
        let arguments = CommandLine.arguments
        guard arguments.count > 1 else {
            NSLog("usage: PhpNitroMacApp <project_directory> [screen]")
            NSLog("   or: PhpNitroMacApp --connect HOST:PORT [screen]")
            NSApp.terminate(nil)
            return
        }

        if arguments[1] == "--connect" {
            guard arguments.count > 2, let parsed = HostPort.parse(arguments[2]) else {
                NSLog("--connect expects HOST:PORT, e.g. 192.168.1.23:8090")
                NSApp.terminate(nil)
                return
            }
            let screen = arguments.count > 3 ? arguments[3] : "home"
            startScreen(host: parsed.host, port: parsed.port, screen: screen)
            return
        }

        let projectDirectory = URL(fileURLWithPath: arguments[1])
        let screen = arguments.count > 2 ? arguments[2] : "home"

        let process = MacPhpProcess(projectDirectory: projectDirectory)
        do {
            let port = try process.start()
            phpProcess = process
            startScreen(host: "127.0.0.1", port: port, screen: screen)
        } catch {
            NSLog("PhpNitroMacApp failed to start: \(error)")
            NSApp.terminate(nil)
        }
    }

    private func startScreen(host: String, port: Int, screen: String) {
        do {
            let renderer = try RustRenderer()
            let frame = NSRect(x: 0, y: 0, width: 390, height: 844)
            let controller = RustScreenController(host: host, port: port, initialScreen: screen, renderer: renderer, frame: frame)
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
