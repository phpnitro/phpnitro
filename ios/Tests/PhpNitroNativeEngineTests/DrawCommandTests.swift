import UIKit
import XCTest
@testable import PhpNitroNativeEngine

/// Decodes real JSON shapes Engine\Native\Canvas::toJson() actually
/// produces — the "button_with_icon" fixture below is copied verbatim
/// from packages/ui/tests/Golden/__fixtures__/button_with_icon.json
/// (this PHP-side framework's own golden-file test for the exact same
/// payload), so a change to Canvas.php's JSON shape that breaks this
/// decoder is a change a human would actually make on purpose, not
/// something invented for this test alone.
final class DrawCommandTests: XCTestCase {
    func testDecodesRectCommand() throws {
        let json = """
        {"type":"rect","x":0,"y":0,"width":200,"height":54,"color":"#111827","radius":999,"borderWidth":0}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .rect(let rect) = command else { return XCTFail("expected .rect") }
        XCTAssertEqual(rect.width, 200)
        XCTAssertEqual(rect.height, 54)
        XCTAssertEqual(rect.color, "#111827")
        XCTAssertEqual(rect.radius, 999)
    }

    func testDecodesTextCommand() throws {
        let json = """
        {"type":"text","x":89.1,"y":29.6,"text":"Valider","color":"#FFFFFF","size":15,"bold":true}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .text(let text) = command else { return XCTFail("expected .text") }
        XCTAssertEqual(text.text, "Valider")
        XCTAssertEqual(text.bold, true)
        XCTAssertNil(text.fontFamily)
    }

    func testDecodesIconCommandWithFontAwesomeFont() throws {
        let json = """
        {"type":"icon","x":63.1,"y":18,"size":18,"codepoint":58826,"color":"#FFFFFF","font":"fontawesome"}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .icon(let icon) = command else { return XCTFail("expected .icon") }
        XCTAssertEqual(icon.codepoint, 58826)
        XCTAssertEqual(icon.font, "fontawesome")
    }

    func testUnrecognizedTypeDecodesToUnknownInsteadOfThrowing() throws {
        let json = """
        {"type":"custom:sparkline","values":[1,2,3]}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .unknown(let type) = command else { return XCTFail("expected .unknown") }
        XCTAssertEqual(type, "custom:sparkline")
    }

    /// Verbatim from packages/ui/tests/Golden/__fixtures__/button_with_icon.json.
    func testDecodesAFullRealButtonPayload() throws {
        let json = """
        {
            "commands": [
                {"type":"rect","x":0,"y":0,"width":200,"height":54,"color":"#111827","radius":999,"borderWidth":0},
                {"type":"icon","x":63.09825000000001,"y":18,"size":18,"codepoint":58826,"color":"#FFFFFF"},
                {"type":"text","x":89.09825000000001,"y":29.625,"text":"Valider","color":"#FFFFFF","size":15,"bold":true}
            ],
            "hitRegions": [
                {"x":0,"y":0,"width":200,"height":54,"action":"submit:demo"}
            ],
            "contentHeight": 0
        }
        """
        let payload = try JSONDecoder().decode(DrawCommandPayload.self, from: Data(json.utf8))

        XCTAssertEqual(payload.commands.count, 3)
        XCTAssertEqual(payload.contentHeight, 0)
        XCTAssertEqual(payload.hitRegions.count, 1)
        XCTAssertEqual(payload.hitRegions[0].action, "submit:demo")
    }

    func testColorHexParsing() {
        XCTAssertNotNil(UIColor(hex: "#111827"))
        XCTAssertNotNil(UIColor(hex: "111827"))
        XCTAssertNotNil(UIColor(hex: "#11182780"))
        XCTAssertNil(UIColor(hex: "not-a-color"))
    }

    func testEmptyHitRegionsArrayDecodesNotThrows() throws {
        let json = """
        {"commands":[],"hitRegions":[],"contentHeight":0}
        """
        let payload = try JSONDecoder().decode(DrawCommandPayload.self, from: Data(json.utf8))

        XCTAssertEqual(payload.hitRegions.count, 0)
    }

    func testActionAtPointHitsTheContainingRegion() throws {
        let json = """
        {
            "commands": [],
            "hitRegions": [
                {"x":0,"y":0,"width":100,"height":50,"action":"navigate:home"},
                {"x":100,"y":0,"width":100,"height":50,"action":"navigate:settings"}
            ],
            "contentHeight": 0
        }
        """
        let payload = try JSONDecoder().decode(DrawCommandPayload.self, from: Data(json.utf8))

        XCTAssertEqual(payload.action(at: CGPoint(x: 50, y: 25)), "navigate:home")
        XCTAssertEqual(payload.action(at: CGPoint(x: 150, y: 25)), "navigate:settings")
        XCTAssertNil(payload.action(at: CGPoint(x: 500, y: 500)))
    }

    func testActionAtPointPrefersTheLastRegionWhenOverlapping() throws {
        let json = """
        {
            "commands": [],
            "hitRegions": [
                {"x":0,"y":0,"width":200,"height":200,"action":"background"},
                {"x":50,"y":50,"width":50,"height":50,"action":"foreground_button"}
            ],
            "contentHeight": 0
        }
        """
        let payload = try JSONDecoder().decode(DrawCommandPayload.self, from: Data(json.utf8))

        // Inside both rects — the later-declared (visually on top) one should win.
        XCTAssertEqual(payload.action(at: CGPoint(x: 60, y: 60)), "foreground_button")
        // Inside only the background rect.
        XCTAssertEqual(payload.action(at: CGPoint(x: 10, y: 10)), "background")
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
