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
}
