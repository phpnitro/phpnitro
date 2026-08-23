import UIKit
import XCTest
@testable import PhpNitroNativeEngine

/// The iOS-specific rendering support pieces that stayed behind in
/// PhpNitroNativeEngine when the platform-agnostic decoding/networking/
/// navigation logic split off into PhpNitroProtocol (a real UIColor, a
/// real registered UIFont, a real async ImageLoader callback — none of
/// that exists without UIKit, so none of it could move).
final class NativeRenderingSupportTests: XCTestCase {
    func testColorHexParsing() {
        XCTAssertNotNil(UIColor(hex: "#111827"))
        XCTAssertNotNil(UIColor(hex: "111827"))
        XCTAssertNotNil(UIColor(hex: "#11182780"))
        XCTAssertNil(UIColor(hex: "not-a-color"))
    }

    func testImageLoaderDecodesADataUriWithoutNetworkAccess() {
        let expectation = expectation(description: "data: URI decodes synchronously enough to load")
        let pngBase64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="

        ImageLoader.load("data:image/png;base64,\(pngBase64)") {
            expectation.fulfill()
        }

        // 15s, not 5s: this decode is near-instant in practice, but the
        // real observed flake (this exact test, twice in one CI session)
        // was ImageLoader's .utility-QoS dispatch getting deprioritized
        // under a loaded/shared runner, not a genuine slowdown — fixed
        // at the source (ImageLoader now uses .userInitiated), this is
        // just a safety margin on top, not a mask for a real bug.
        wait(for: [expectation], timeout: 15)
        XCTAssertNotNil(ImageLoader.get("data:image/png;base64,\(pngBase64)"))
    }

    func testIconFontsRegisterAndProduceARealUIFont() {
        XCTAssertNotNil(IconFont.materialName, "MaterialIcons-Regular.ttf should register from the bundled SPM resource")
        XCTAssertNotNil(IconFont.fontAwesomeName, "FontAwesome-Solid.ttf should register from the bundled SPM resource")

        let materialFont = IconFont.font(forKey: nil, size: 24)
        XCTAssertNotNil(materialFont, "font(forKey: nil, ...) should default to Material Icons")
        XCTAssertEqual(materialFont?.pointSize, 24)

        let fontAwesomeFont = IconFont.font(forKey: "fontawesome", size: 18)
        XCTAssertNotNil(fontAwesomeFont)
        XCTAssertEqual(fontAwesomeFont?.pointSize, 18)
    }
}
