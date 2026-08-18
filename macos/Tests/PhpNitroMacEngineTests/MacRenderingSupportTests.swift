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
