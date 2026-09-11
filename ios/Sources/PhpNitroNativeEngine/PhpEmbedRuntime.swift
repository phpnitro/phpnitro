import CPhpEmbed
import Foundation

/// The Swift face of CPhpEmbed's phpx_embed_* C shim — the first real,
/// working piece of on-device PHP for iOS in this repo (see
/// ios/vendor/php/README.md for how libphp.xcframework itself was
/// built, and .github/workflows/ci.yml's ios-php-embed-step1..4 jobs
/// for the incremental, CI-observed path that got there before any of
/// this was wired into the actual app).
///
/// Mirrors Android's own "PHP as a real interpreter on-device" story in
/// effect, not mechanism — PhpServer.kt spawns a genuine `php -S`
/// subprocess and this app talks to it over local HTTP; iOS has no
/// Process/NSTask under the app sandbox, so there is no subprocess to
/// spawn at all. This runs PHP's embed SAPI directly in-process instead
/// — no socket, no HTTP round-trip, just a C function call in and a
/// string back.
///
/// One instance is meant to live for this app's entire process
/// lifetime, same "one embed SAPI instance" assumption CPhpEmbed's own
/// header already documents — start() must be called exactly once
/// before any eval(_:), and shutdown() at most once after.
public final class PhpEmbedRuntime {
    /// The app's own public/ + lib/ + packages/*/src + vendor/, staged by
    /// `phpx bundle:ios` (see Package.swift's own `.copy("Resources/www")`
    /// rule) — `Bundle.module` only resolves to the right bundle from
    /// code that actually lives IN the PhpNitroNativeEngine target itself
    /// (a test target or app target gets its OWN `Bundle.module`/
    /// `Bundle.main`), which is the whole reason this lives here instead
    /// of being computed at each call site.
    public static var wwwDirectoryURL: URL? {
        Bundle.module.url(forResource: "www", withExtension: nil)
    }

    /// `wwwDirectoryURL` sits inside the app bundle — **read-only** on a
    /// real device/Simulator, exactly like Android's own APK assets/
    /// (see `PhpServer.kt`'s own `copyAssets()`). `public/index.php`
    /// writes real files there at runtime — `Database::useSqlitePath()`
    /// (see `public/index.php`) opens `lib/backend/var/data.sqlite` for
    /// read/write, and PHP's own session handler writes session files —
    /// both fail with an uncaught `PDOException` ("unable to open
    /// database file") the instant a screen touches the database,
    /// confirmed on a real physical iPhone the first time this ran.
    ///
    /// Mirrors `copyAssets()`'s exact fix: copy the whole `www` tree out
    /// of the read-only bundle into a real writable, app-private
    /// directory (`Application Support`, iOS's counterpart to
    /// `context.filesDir`) before ever pointing PHP at it.
    ///
    /// Real bug found on a physical iPhone: a full app close + reopen
    /// reset the home screen's counter (`Engine\Preferences\Preferences`,
    /// backed by `lib/backend/var/data.sqlite`) back to 0, even though
    /// that class's own docblock explicitly promises it "survives across
    /// app restarts" like Flutter's shared_preferences — because this
    /// method unconditionally deleted and re-copied the ENTIRE `www` tree
    /// on every launch, `var/` (the only part actually written to at
    /// runtime — everything else is read-only app code) included. `var/`
    /// is now preserved across the wipe: moved aside first, restored
    /// after the fresh code copy lands, same fix applied to
    /// `copyAssets()`'s own identical unconditional wipe on Android.
    /// Everything else still refreshes on every launch (this app's own
    /// PHP code, picked up fresh each time) — only runtime-generated data
    /// persists, not the code itself.
    public static func stageWritableWwwDirectory() -> URL? {
        guard let bundleWwwURL = wwwDirectoryURL,
              let appSupport = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first
        else {
            return nil
        }

        let fm = FileManager.default
        let target = appSupport.appendingPathComponent("www")
        let varDir = target.appendingPathComponent("lib/backend/var")
        let preservedVarDir = appSupport.appendingPathComponent("www-var-preserved")

        do {
            try fm.createDirectory(at: appSupport, withIntermediateDirectories: true)

            if fm.fileExists(atPath: varDir.path) {
                try? fm.removeItem(at: preservedVarDir)
                try fm.moveItem(at: varDir, to: preservedVarDir)
            }

            if fm.fileExists(atPath: target.path) {
                try fm.removeItem(at: target)
            }
            try fm.copyItem(at: bundleWwwURL, to: target)

            if fm.fileExists(atPath: preservedVarDir.path) {
                try? fm.removeItem(at: varDir)
                try fm.createDirectory(at: varDir.deletingLastPathComponent(), withIntermediateDirectories: true)
                try fm.moveItem(at: preservedVarDir, to: varDir)
            }

            return target
        } catch {
            return nil
        }
    }

    public init() {}

    public func start() {
        phpx_embed_start()
    }

    /// Evaluates `phpCode` and returns everything it wrote via echo/print,
    /// or nil if start() hasn't run yet or phpCode raised an uncaught
    /// exception/parse error.
    public func eval(_ phpCode: String) -> String? {
        guard let cString = phpx_embed_eval(phpCode) else {
            return nil
        }
        defer { phpx_embed_free_string(cString) }
        return String(cString: cString)
    }

    /// Like eval(_:), but usable more than once per app launch — see
    /// CPhpEmbed's own phpx_embed_handle_request() docblock for why a
    /// plain eval(_:) can't just be called again for a second "screen
    /// fetch": PHP only resets declared classes/functions/included
    /// files at a request boundary, not for free between two
    /// zend_eval_string() calls in the same process.
    public func handleRequest(_ phpCode: String) -> String? {
        guard let cString = phpx_embed_handle_request(phpCode) else {
            return nil
        }
        defer { phpx_embed_free_string(cString) }
        return String(cString: cString)
    }

    public func shutdown() {
        phpx_embed_shutdown()
    }
}
