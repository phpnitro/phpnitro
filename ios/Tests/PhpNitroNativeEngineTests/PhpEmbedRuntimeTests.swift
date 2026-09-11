import XCTest
@testable import PhpNitroNativeEngine

/// The real, CI-verifiable proof that on-device PHP works on iOS at
/// all — not a smoke-test C binary run by hand in a terminal anymore
/// (that was .github/workflows/ci.yml's own ios-php-embed-step1..4
/// jobs, which proved the toolchain/API/cross-compile targets one at a
/// time before any of this was wired into the actual app), but the
/// genuine Swift API surface (PhpEmbedRuntime) running inside this
/// package's own XCTest bundle on the iOS Simulator — the exact same
/// `xcodebuild ... test` invocation ci.yml's ios-build job already
/// runs for every other PhpNitroNativeEngineTests case.
final class PhpEmbedRuntimeTests: XCTestCase {
    func testEvalReturnsEchoedOutput() {
        let runtime = PhpEmbedRuntime()
        runtime.start()
        defer { runtime.shutdown() }

        XCTAssertEqual(runtime.eval("echo 'hello from PhpEmbedRuntime';"), "hello from PhpEmbedRuntime")
    }

    func testEvalRunsRealPhpArithmetic() {
        let runtime = PhpEmbedRuntime()
        runtime.start()
        defer { runtime.shutdown() }

        XCTAssertEqual(runtime.eval("echo 6 * 7;"), "42")
    }

    func testEvalReturnsEmptyStringWhenScriptProducesNoOutput() {
        let runtime = PhpEmbedRuntime()
        runtime.start()
        defer { runtime.shutdown() }

        XCTAssertEqual(runtime.eval("$x = 1 + 1;"), "")
    }

    /// The real problem handleRequest(_:) exists to solve: requiring
    /// the SAME script more than once in one process — exactly what
    /// serving multiple screen fetches from one embedded PHP runtime
    /// needs — fails with "Cannot redeclare class" via plain eval(_:),
    /// since PHP only resets declared-symbol tracking at a request
    /// boundary. Written to a real temp file (not inline PHP) because
    /// the failure mode this guards against is specifically about
    /// `require`, which plain `eval`-style code sharing a process never
    /// exercises the same way.
    func testHandleRequestAllowsRequiringTheSameScriptTwice() throws {
        let scriptURL = FileManager.default.temporaryDirectory.appendingPathComponent("phpx_embed_test_script.php")
        let script = """
        <?php
        class Greeter {
            public static function hello(string $name): string {
                return "Hello, {$name}!";
            }
        }
        echo Greeter::hello($_GET['name'] ?? 'world');
        """
        try script.write(to: scriptURL, atomically: true, encoding: .utf8)
        defer { try? FileManager.default.removeItem(at: scriptURL) }

        let runtime = PhpEmbedRuntime()
        runtime.start()
        defer { runtime.shutdown() }

        let requirePhp = "require '\(scriptURL.path)';"
        XCTAssertEqual(runtime.handleRequest("$_GET['name'] = 'Alice'; \(requirePhp)"), "Hello, Alice!")
        XCTAssertEqual(runtime.handleRequest("$_GET['name'] = 'Bob'; \(requirePhp)"), "Hello, Bob!")
        XCTAssertEqual(runtime.handleRequest("$_GET['name'] = 'Carol'; \(requirePhp)"), "Hello, Carol!")
    }

    /// The actual point of all of this: not a toy script, but this
    /// app's own real public/index.php (staged by `phpx bundle:ios`,
    /// see Package.swift's own `.copy("Resources/www")`), hit the exact
    /// same way NativeScreenViewController's network client currently
    /// does — REQUEST_URI = /native/layout-demo, $_GET = screen/width/
    /// height/etc — producing the exact same JSON draw-command payload
    /// a real `phpx serve` round-trip would. If `phpx bundle:ios`
    /// hasn't been run, wwwDirectoryURL is nil and this test is skipped
    /// rather than failing the whole suite for an environment gap
    /// unrelated to a real code regression — this project's own
    /// android-build CI job has the same "bundle first" prerequisite,
    /// just enforced by SPM's own resource-path validation instead of
    /// a runtime skip there.
    func testHandleRequestServesTheRealHomeScreenAsJson() throws {
        guard let wwwURL = PhpEmbedRuntime.wwwDirectoryURL else {
            throw XCTSkip("Resources/www not staged — run `php bin/phpx bundle:ios` first")
        }
        let indexPath = wwwURL.appendingPathComponent("public/index.php").path

        let runtime = PhpEmbedRuntime()
        runtime.start()
        defer { runtime.shutdown() }

        let requestPhp = """
        $_SERVER['REQUEST_URI'] = '/native/layout-demo?screen=home&width=390&height=844';
        $_GET = ['screen' => 'home', 'width' => '390', 'height' => '844'];
        require '\(indexPath)';
        """
        guard let output = runtime.handleRequest(requestPhp) else {
            XCTFail("handleRequest returned nil — index.php raised an uncaught exception")
            return
        }

        let json = try XCTUnwrap(output.data(using: .utf8))
        let decoded = try JSONSerialization.jsonObject(with: json) as? [String: Any]
        XCTAssertNotNil(decoded, "expected valid JSON, got: \(output.prefix(500))")
        XCTAssertNil(decoded?["error"], "expected a real draw-command payload, got an error: \(output.prefix(500))")
    }
}
