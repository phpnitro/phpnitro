import CoreGraphics
import XCTest
@testable import PhpNitroProtocol

/// Decodes real JSON shapes Engine\Native\Canvas::toJson() actually
/// produces — the "button_with_icon" fixture below is copied verbatim
/// from packages/ui/tests/Golden/__fixtures__/button_with_icon.json
/// (this PHP-side framework's own golden-file test for the exact same
/// payload), so a change to Canvas.php's JSON shape that breaks this
/// decoder is a change a human would actually make on purpose, not
/// something invented for this test alone.
///
/// Pure decoding only — no UIKit here at all (see
/// PhpNitroNativeEngineTests' own NativeRenderingSupportTests.swift for
/// the iOS-specific rendering tests, like icon font registration, that
/// used to live in this same file before PhpNitroProtocol split off
/// into its own cross-platform target).
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

    func testDecodesImageCommand() throws {
        let json = """
        {"type":"image","x":10,"y":20,"width":100,"height":80,"url":"https://example.com/photo.jpg","radius":12}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .image(let image) = command else { return XCTFail("expected .image") }
        XCTAssertEqual(image.url, "https://example.com/photo.jpg")
        XCTAssertEqual(image.radius, 12)
    }

    func testDecodesImageCommandWithDataUri() throws {
        let json = """
        {"type":"image","x":0,"y":0,"width":50,"height":50,"url":"data:image/png;base64,AAAA","radius":0}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .image(let image) = command else { return XCTFail("expected .image") }
        XCTAssertTrue(image.url.hasPrefix("data:"))
    }

    func testDecodesSpinnerCommand() throws {
        let json = """
        {"type":"spinner","x":0,"y":0,"size":24,"color":"#111827","trackColor":"#E5E7EB","strokeWidth":3}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .spinner(let spinner) = command else { return XCTFail("expected .spinner") }
        XCTAssertEqual(spinner.size, 24)
        XCTAssertEqual(spinner.trackColor, "#E5E7EB")
    }

    func testDecodesSkeletonCommand() throws {
        let json = """
        {"type":"skeleton","x":0,"y":0,"width":200,"height":16,"color":"#E5E7EB","radius":8}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .skeleton(let skeleton) = command else { return XCTFail("expected .skeleton") }
        XCTAssertEqual(skeleton.width, 200)
        XCTAssertEqual(skeleton.radius, 8)
    }

    func testDecodesClientPanelCommandWithNestedCommands() throws {
        let json = """
        {
            "type": "clientPanel",
            "key": "tabs1",
            "index": 1,
            "initiallyActive": false,
            "x": 0,
            "y": 40,
            "commands": [
                {"type":"text","x":0,"y":0,"text":"Onglet 2","color":"#111827"}
            ],
            "hitRegions": []
        }
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .clientPanel(let panel) = command else { return XCTFail("expected .clientPanel") }
        XCTAssertEqual(panel.key, "tabs1")
        XCTAssertEqual(panel.index, 1)
        XCTAssertFalse(panel.initiallyActive)
        XCTAssertEqual(panel.commands.count, 1)
    }

    func testDecodesHScrollCommand() throws {
        let json = """
        {"type":"hScroll","key":"carousel","x":0,"y":0,"width":300,"height":120,"contentWidth":900,"commands":[],"hitRegions":[]}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .hScroll(let scroll) = command else { return XCTFail("expected .hScroll") }
        XCTAssertEqual(scroll.key, "carousel")
        XCTAssertEqual(scroll.contentWidth, 900)
    }

    func testDecodesVScrollCommand() throws {
        let json = """
        {"type":"vScroll","key":"comments","x":0,"y":0,"width":300,"height":200,"contentHeight":600,"commands":[],"hitRegions":[]}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .vScroll(let scroll) = command else { return XCTFail("expected .vScroll") }
        XCTAssertEqual(scroll.key, "comments")
        XCTAssertEqual(scroll.contentHeight, 600)
    }

    func testDecodesSliderCommand() throws {
        let json = """
        {"type":"slider","key":"volume","x":0,"y":0,"width":260,"height":32,"trackHeight":4,"thumbSize":20,"value":0.4,"trackColor":"#E5E7EB","activeColor":"#111827","thumbColor":"#FFFFFF"}
        """
        let command = try JSONDecoder().decode(DrawCommand.self, from: Data(json.utf8))

        guard case .slider(let slider) = command else { return XCTFail("expected .slider") }
        XCTAssertEqual(slider.key, "volume")
        XCTAssertEqual(slider.value, 0.4)
    }

    /// The real sliderRegions[] entry from
    /// packages/ui/tests/Golden/__fixtures__/screen_widgets_forms.json.
    func testDecodesTopLevelSliderRegions() throws {
        let json = """
        {"commands":[],"hitRegions":[],"contentHeight":0,
         "sliderRegions":[{"key":"volume","x":20,"y":592.5,"width":360,"height":44,
         "trackHeight":6,"thumbSize":22,"value":0.5,"action":"toggle:volume"}]}
        """
        let payload = try JSONDecoder().decode(DrawCommandPayload.self, from: Data(json.utf8))

        XCTAssertEqual(payload.sliderRegions.count, 1)
        XCTAssertEqual(payload.sliderRegions[0].key, "volume")
        XCTAssertEqual(payload.sliderRegions[0].action, "toggle:volume")
        XCTAssertEqual(payload.sliderRegions[0].thumbSize, 22)
    }

    func testDecodesWithNoSliderRegionsAtAll() throws {
        let json = """
        {"commands":[],"hitRegions":[],"contentHeight":0}
        """
        let payload = try JSONDecoder().decode(DrawCommandPayload.self, from: Data(json.utf8))

        XCTAssertTrue(payload.sliderRegions.isEmpty)
    }
}
