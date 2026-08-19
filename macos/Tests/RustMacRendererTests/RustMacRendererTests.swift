import XCTest
@testable import RustMacRenderer

/// Real tests against the ACTUAL compiled rust/phpnitro-render library —
/// linked at BUILD time via Package.swift's own linker flags (see that
/// manifest's own comment), so unlike the Linux/Windows ports there is
/// no "library not found, skip" case to guard here: if the Rust crate
/// wasn't built for real (see .github/workflows/ci.yml's macos-build
/// job, which now runs `cargo build --release` before xcodebuild), the
/// whole package fails to LINK, not just this one test file — a louder,
/// earlier failure than a silent skip would give, which is the point:
/// this dependency is not optional once RustMacRenderer exists at all.
final class RustMacRendererTests: XCTestCase {
    private static func repoRoot(file: StaticString = #filePath) -> URL {
        // macos/Tests/RustMacRendererTests/RustMacRendererTests.swift ->
        // .../RustMacRendererTests/ -> .../Tests/ -> .../macos/ -> repo root.
        URL(fileURLWithPath: "\(file)")
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .deletingLastPathComponent()
    }

    private static func fixtureJSON(_ name: String) throws -> String {
        let path = repoRoot()
            .appendingPathComponent("packages/ui/tests/Golden/__fixtures__")
            .appendingPathComponent(name)
        return try String(contentsOf: path, encoding: .utf8)
    }

    func testVersionReturnsANonEmptyString() {
        XCTAssertFalse(RustRenderer.version.isEmpty)
    }

    func testRenderFrameProducesTheExpectedPixelForAPlainRedRect() throws {
        let renderer = try RustRenderer()
        let envelope = """
            {"commands":[{"type":"rect","x":0,"y":0,"width":10,"height":10,"color":"#FF0000","radius":0}],
             "hitRegions":[],"contentHeight":10}
            """
        let frame = renderer.renderFrame(envelopeJSON: envelope, widthPx: 20, heightPx: 20)
        let unwrapped = try XCTUnwrap(frame)
        XCTAssertEqual(unwrapped.width, 20)
        XCTAssertEqual(unwrapped.height, 20)
        XCTAssertEqual(unwrapped.stride, 80)

        // (5, 5) sits inside the 10x10 red rect — RGBA8 premultiplied,
        // opaque red: [255, 0, 0, 255].
        let offset = Int(5 * unwrapped.stride + 5 * 4)
        XCTAssertEqual(unwrapped.data[offset], 255)
        XCTAssertEqual(unwrapped.data[offset + 1], 0)
        XCTAssertEqual(unwrapped.data[offset + 2], 0)
        XCTAssertEqual(unwrapped.data[offset + 3], 255)
    }

    func testRenderFrameReturnsNilAndSetsLastErrorOnMalformedJSON() throws {
        let renderer = try RustRenderer()
        let frame = renderer.renderFrame(envelopeJSON: "{not valid json", widthPx: 10, heightPx: 10)
        XCTAssertNil(frame)
        XCTAssertNotNil(RustRenderer.lastError)
    }

    func testRenderFrameMatchesTheRealButtonWithIconGoldenFixture() throws {
        let renderer = try RustRenderer()
        let envelope = try Self.fixtureJSON("button_with_icon.json")
        let frame = renderer.renderFrame(envelopeJSON: envelope, widthPx: 200, heightPx: 54)
        let unwrapped = try XCTUnwrap(frame)

        // The button's dark pill background (#111827) — (10, 27) is well
        // left of both the icon (starts ~x=63) and the text ("Valider",
        // starts ~x=89), so this samples pure background fill, not
        // glyph ink — same sample point chosen for the same reason in
        // windows/PhpNitroDesktop.Render.Tests/RustRendererTests.cs.
        let offset = Int(27 * unwrapped.stride + 10 * 4)
        XCTAssertEqual(unwrapped.data[offset], 0x11)
        XCTAssertEqual(unwrapped.data[offset + 1], 0x18)
        XCTAssertEqual(unwrapped.data[offset + 2], 0x27)
    }

    func testHitTestFindsTheRealButtonActionAtItsCenter() throws {
        let envelope = try Self.fixtureJSON("button_with_icon.json")
        let hit = rustHitTest(envelopeJSON: envelope, tapX: 100, tapY: 27)
        let unwrapped = try XCTUnwrap(hit)
        XCTAssertEqual(unwrapped.action, "submit:demo")
        XCTAssertEqual(unwrapped.metaJSON, "null")
    }

    func testHitTestOnEmptySpaceReturnsNilWithoutCrashing() {
        let envelope = #"{"commands":[],"hitRegions":[],"contentHeight":0}"#
        let hit = rustHitTest(envelopeJSON: envelope, tapX: 999, tapY: 999)
        XCTAssertNil(hit)
    }
}
