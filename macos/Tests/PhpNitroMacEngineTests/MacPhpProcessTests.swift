import XCTest
@testable import PhpNitroMacEngine

/// `#filePath` is a compile-time literal capturing THIS source file's
/// real path on disk — walking up from it (MacPhpProcessTests.swift ->
/// PhpNitroMacEngineTests -> Tests -> macos -> repo root) reaches the
/// monorepo root the same way Linux's own test_php_process.py does via
/// `Path(__file__).resolve().parent.parent.parent`, without hardcoding
/// an absolute path that would only work on one specific machine.
private let repoRoot = URL(fileURLWithPath: #filePath)
    .deletingLastPathComponent() // MacPhpProcessTests.swift
    .deletingLastPathComponent() // PhpNitroMacEngineTests
    .deletingLastPathComponent() // Tests
    .deletingLastPathComponent() // macos

final class MacPhpProcessTests: XCTestCase {
    func testStartThrowsAClearErrorForADirectoryWithNoPublicFolder() {
        let process = MacPhpProcess(projectDirectory: URL(fileURLWithPath: NSTemporaryDirectory()))

        XCTAssertThrowsError(try process.start()) { error in
            guard case MacPhpProcessError.noPublicDirectory = error else {
                return XCTFail("expected .noPublicDirectory, got \(error)")
            }
        }
    }

    /// A real integration test — actually spawns `php -S` against this
    /// repo, same as Linux's test_php_process.py — but only where `php`
    /// and this checkout's own `vendor/autoload.php` are both available
    /// (CI installs PHP via Homebrew specifically so this exercises the
    /// real path there rather than skipping; a plain `xcodebuild test`
    /// run on a machine without PHP set up skips gracefully instead of
    /// failing through no fault of the code under test).
    func testStartBindsARealPortThatAnswersHttpAndStopTearsItDown() throws {
        guard FileManager.default.fileExists(atPath: repoRoot.appendingPathComponent("vendor/autoload.php").path) else {
            throw XCTSkip("composer install hasn't run in this checkout")
        }
        guard Self.phpIsAvailable() else {
            throw XCTSkip("no `php` binary available on this machine")
        }

        let process = MacPhpProcess(projectDirectory: repoRoot)
        let port = try process.start()

        XCTAssertTrue(process.isRunning)
        XCTAssertGreaterThan(port, 0)

        let url = URL(string: "http://127.0.0.1:\(port)/native/layout-demo?screen=home")!
        let expectation = expectation(description: "real HTTP fetch against the spawned server")
        var statusCode: Int?
        URLSession.shared.dataTask(with: url) { _, response, _ in
            statusCode = (response as? HTTPURLResponse)?.statusCode
            expectation.fulfill()
        }.resume()
        wait(for: [expectation], timeout: 8)

        XCTAssertEqual(statusCode, 200)

        process.stop()
        XCTAssertFalse(process.isRunning)
    }

    private static func phpIsAvailable() -> Bool {
        let process = Process()
        process.executableURL = URL(fileURLWithPath: "/usr/bin/env")
        process.arguments = ["php", "--version"]
        process.standardOutput = FileHandle.nullDevice
        process.standardError = FileHandle.nullDevice
        do {
            try process.run()
            process.waitUntilExit()
            return process.terminationStatus == 0
        } catch {
            return false
        }
    }
}
