import Foundation

/// One entry from Engine\Native\Canvas::toJson()'s "commands" array —
/// the exact same wire format NativeCanvasView.kt's setCommands()/
/// drawCommands() already decodes on Android (see that file's own
/// `"rect" -> drawRectCommand(...)`-style dispatch). This is the
/// platform-agnostic HALF of the protocol: the JSON shape itself was
/// never Android-specific, only its consumer was — this type proves
/// that by being a second, independent consumer of the identical bytes
/// PHP already emits, no server-side change required.
///
/// Only the "phase 0" geometric primitives (rect/text/icon/circle/line/
/// arc) are modeled — the same scope PhpNitro's own Android port started
/// from (see docs/proposals/moteur-rendu-natif.md's phased plan) before
/// growing into scroll containers, sliders, embedded panels, etc. An
/// unrecognized "type" (image, clientPanel, hScroll/vScroll, slider,
/// skeleton, spinner, custom:*) decodes to `.unknown(type:)` rather than
/// throwing — the same "PHP decides, the renderer owns the pixels, an
/// unhandled command is a silent no-op, not a crash" resilience
/// NativeCanvasView.kt's own registerCustomCommandHandler() escape hatch
/// already assumes.
public enum DrawCommand: Decodable {
    case rect(RectCommand)
    case text(TextCommand)
    case icon(IconCommand)
    case circle(CircleCommand)
    case line(LineCommand)
    case arc(ArcCommand)
    case unknown(type: String)

    private enum CodingKeys: String, CodingKey {
        case type
    }

    public init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        let type = try container.decode(String.self, forKey: .type)

        switch type {
        case "rect": self = .rect(try RectCommand(from: decoder))
        case "text": self = .text(try TextCommand(from: decoder))
        case "icon": self = .icon(try IconCommand(from: decoder))
        case "circle": self = .circle(try CircleCommand(from: decoder))
        case "line": self = .line(try LineCommand(from: decoder))
        case "arc": self = .arc(try ArcCommand(from: decoder))
        default: self = .unknown(type: type)
        }
    }
}

/// Mirrors Canvas::rect()'s exact field set — $color/$borderColor are
/// omitted (not null/empty-string) on the PHP side when unset, hence
/// Optional here rather than a default-valued String.
public struct RectCommand: Decodable {
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let color: String?
    public let radius: Double?
    public let borderColor: String?
    public let borderWidth: Double?
}

public struct TextCommand: Decodable {
    public let x: Double
    public let y: Double
    public let text: String
    public let color: String?
    public let size: Double?
    public let bold: Bool?
    public let letterSpacing: Double?
    /// A Google Font family name (Engine\Native\GoogleFontText) — no
    /// on-device font-download API exists here yet (see
    /// GoogleFontLoader.kt's own Android-only Downloadable Fonts API
    /// usage); a renderer should fall back to the system font when set,
    /// same as any other font it doesn't have.
    public let fontFamily: String?
}

public struct IconCommand: Decodable {
    public let x: Double
    public let y: Double
    public let size: Double
    public let codepoint: Int
    public let color: String?
    /// "material" (default, omitted) or "fontawesome" — see Icon.php's
    /// own $font parameter. Neither icon font is bundled on the iOS side
    /// yet (see MaterialIcons-Regular.ttf/FontAwesome-Solid.ttf under
    /// android/engine/src/main/assets/fonts/ for what would need
    /// porting over as real font assets here too).
    public let font: String?
}

public struct CircleCommand: Decodable {
    public let cx: Double
    public let cy: Double
    public let radius: Double
    public let color: String?
    public let borderColor: String?
    public let borderWidth: Double?
}

public struct LineCommand: Decodable {
    public let x1: Double
    public let y1: Double
    public let x2: Double
    public let y2: Double
    public let color: String
    public let width: Double?
}

public struct ArcCommand: Decodable {
    public let cx: Double
    public let cy: Double
    public let radius: Double
    public let startDegrees: Double
    public let sweepDegrees: Double
    public let color: String
    public let strokeWidth: Double
}

/// The envelope Canvas::toJson() wraps every render in — only the two
/// fields a first renderer needs are modeled; the rest (hitRegions,
/// heroRegions, autoNavigate, snackbar...) are exactly as real a porting
/// task as this file itself, just not attempted yet (see ios/README.md).
public struct DrawCommandPayload: Decodable {
    public let commands: [DrawCommand]
    public let contentHeight: Double
}
