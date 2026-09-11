import AppKit
import XCTest
@testable import PhpNitroMacEngine

/// The macOS counterpart of PhpNitroNativeEngineTests' own
/// NativeRenderingSupportTests.swift — same three things verified for
/// real (font registration, hex color parsing, a data: URI image
/// decoding without any network access), through AppKit types instead
/// of UIKit ones.
final class MacRenderingSupportTests: XCTestCase {
    func testColorHexParsing() {
        XCTAssertNotNil(NSColor(hex: "#111827"))
        XCTAssertNotNil(NSColor(hex: "111827"))
        XCTAssertNotNil(NSColor(hex: "#11182780"))
        XCTAssertNil(NSColor(hex: "not-a-color"))
    }

    /// 8-digit hex is #AARRGGBB (Android's Color.parseColor() order) —
    /// Drawer.php's scrim ('#66000000') was only ever correctly opaque-ish
    /// on Android until every backend agreed on this byte order.
    func testEightDigitHexIsAlphaFirst() {
        let color = NSColor(hex: "#8000FF00")
        XCTAssertEqual(color?.alphaComponent ?? -1, 0x80 / 255, accuracy: 0.01)
        XCTAssertEqual(color?.greenComponent ?? -1, 1.0, accuracy: 0.01)
        XCTAssertEqual(color?.redComponent ?? -1, 0.0, accuracy: 0.01)
    }

    func testImageLoaderDecodesADataUriWithoutNetworkAccess() {
        let expectation = expectation(description: "data: URI decodes synchronously enough to load")
        let pngBase64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="

        MacImageLoader.load("data:image/png;base64,\(pngBase64)") {
            expectation.fulfill()
        }

        wait(for: [expectation], timeout: 5)
        XCTAssertNotNil(MacImageLoader.get("data:image/png;base64,\(pngBase64)"))
    }

    func testIconFontsRegisterAndProduceARealNSFont() {
        XCTAssertNotNil(MacIconFont.materialName, "MaterialIcons-Regular.ttf should register from the bundled SPM resource")
        XCTAssertNotNil(MacIconFont.fontAwesomeName, "FontAwesome-Solid.ttf should register from the bundled SPM resource")

        let materialFont = MacIconFont.font(forKey: nil, size: 24)
        XCTAssertNotNil(materialFont, "font(forKey: nil, ...) should default to Material Icons")
        XCTAssertEqual(materialFont?.pointSize, 24)

        let fontAwesomeFont = MacIconFont.font(forKey: "fontawesome", size: 18)
        XCTAssertNotNil(fontAwesomeFont)
        XCTAssertEqual(fontAwesomeFont?.pointSize, 18)
    }
}
