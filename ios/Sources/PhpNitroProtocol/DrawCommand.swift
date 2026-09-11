import CoreGraphics
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
    case image(ImageCommand)
    case spinner(SpinnerCommand)
    case skeleton(SkeletonCommand)
    case clientPanel(ClientPanelCommand)
    case hScroll(HScrollCommand)
    case vScroll(VScrollCommand)
    case slider(SliderCommand)
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
        case "image": self = .image(try ImageCommand(from: decoder))
        case "spinner": self = .spinner(try SpinnerCommand(from: decoder))
        case "skeleton": self = .skeleton(try SkeletonCommand(from: decoder))
        case "clientPanel": self = .clientPanel(try ClientPanelCommand(from: decoder))
        case "hScroll": self = .hScroll(try HScrollCommand(from: decoder))
        case "vScroll": self = .vScroll(try VScrollCommand(from: decoder))
        case "slider": self = .slider(try SliderCommand(from: decoder))
        default: self = .unknown(type: type)
        }
    }

    /// Whether NativeCanvasView.swift should draw this command in its
    /// untranslated "fixed" pass (app bar, bottom tab bar, a FAB) rather
    /// than the pass translated by `-scrollY` — mirrors
    /// NativeCanvasView.kt's own `drawCommands(..., fixed: Boolean, ...)`
    /// filter (`command.optBoolean("fixed", false) != fixed`). The
    /// container types (clientPanel/hScroll/vScroll/slider) have no
    /// `fixed` field of their own on the PHP side — Canvas::beginFixed()/
    /// endFixed() only ever wraps flat primitives — so they default to
    /// `false` (scrollable) here, same as Android's own `optBoolean`
    /// would for a JSON object missing the key entirely.
    public var isFixed: Bool {
        switch self {
        case .rect(let c): return c.fixed ?? false
        case .text(let c): return c.fixed ?? false
        case .icon(let c): return c.fixed ?? false
        case .circle(let c): return c.fixed ?? false
        case .line(let c): return c.fixed ?? false
        case .arc(let c): return c.fixed ?? false
        case .image(let c): return c.fixed ?? false
        case .spinner(let c): return c.fixed ?? false
        case .skeleton(let c): return c.fixed ?? false
        case .clientPanel, .hScroll, .vScroll, .slider, .unknown: return false
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
    public let fixed: Bool?
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
    public let fixed: Bool?
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
    public let fixed: Bool?
}

public struct CircleCommand: Decodable {
    public let cx: Double
    public let cy: Double
    public let radius: Double
    public let color: String?
    public let borderColor: String?
    public let borderWidth: Double?
    public let fixed: Bool?
}

public struct LineCommand: Decodable {
    public let x1: Double
    public let y1: Double
    public let x2: Double
    public let y2: Double
    public let color: String
    public let width: Double?
    public let fixed: Bool?
}

public struct ArcCommand: Decodable {
    public let cx: Double
    public let cy: Double
    public let radius: Double
    public let startDegrees: Double
    public let sweepDegrees: Double
    public let color: String
    public let strokeWidth: Double
    public let fixed: Bool?
}

/// Mirrors Canvas::image()'s field set (Image.php's own $url is passed
/// through verbatim — including a `data:` URI, when it's a camera-
/// captured/gallery-picked photo, not a real network location). `radius`
/// is 0.0 (not omitted) when unset — Canvas::image()'s array_filter()
/// only strips null, and 0.0 is never null.
public struct ImageCommand: Decodable {
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let url: String
    public let radius: Double?
    public let fixed: Bool?
}

/// Mirrors Canvas::spinner()'s field set. No rotation angle travels with
/// this command at all (same reasoning documented on the PHP side) —
/// NativeCanvasView.swift's own draw(_:in:) computes it fresh from the
/// system clock every frame, the same idea as NativeCanvasView.kt's
/// drawSpinnerCommand().
public struct SpinnerCommand: Decodable {
    public let x: Double
    public let y: Double
    public let size: Double
    public let color: String
    public let trackColor: String
    public let strokeWidth: Double
    public let fixed: Bool?
}

/// Mirrors Canvas::skeleton()'s field set — a loading placeholder with a
/// continuously-sweeping shimmer, same "no honest way to travel as one
/// static JSON response" reasoning as spinner() above.
public struct SkeletonCommand: Decodable {
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let color: String
    public let radius: Double
    public let fixed: Bool?
}

/// Mirrors Canvas::clientTabPanel()'s field set — one embedded, already
/// laid-out-and-painted panel (ClientTabs). Only the panel whose `index`
/// matches this `key`'s current LOCAL selection actually draws (see
/// NativeCanvasView.swift's own clientTabState) — switching tabs is a
/// local redraw, never a server round trip, same as
/// drawClientPanelCommand()'s own `clientTabState`/`clientTabCrossfade`
/// on Android. `hitRegions` here isn't wired into
/// DrawCommandPayload.action(at:) yet — nested hit-testing (a tap landing
/// on a hitRegion inside a client-side panel/scroll) is real, separate
/// follow-up work.
public struct ClientPanelCommand: Decodable {
    public let key: String
    public let index: Int
    public let initiallyActive: Bool
    public let x: Double
    public let y: Double
    public let commands: [DrawCommand]
    public let hitRegions: [HitRegion]
}

/// Mirrors Canvas::horizontalScroll()'s field set — a "carousel inside a
/// list" (HorizontalScroll), scrolled along a local drag axis rather than
/// switching between discrete panels like ClientPanelCommand above.
/// NativeCanvasView.swift only renders this at a fixed (never dragged)
/// offset for now — see that file's own hScrollOffsets.
public struct HScrollCommand: Decodable {
    public let key: String
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let contentWidth: Double
    public let commands: [DrawCommand]
    public let hitRegions: [HitRegion]
}

/// Vertical counterpart to HScrollCommand above — mirrors
/// Canvas::verticalScroll()'s field set (NestedScroll).
public struct VScrollCommand: Decodable {
    public let key: String
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let contentHeight: Double
    public let commands: [DrawCommand]
    public let hitRegions: [HitRegion]
}

/// Mirrors Canvas::slider()'s field set (Slider). NativeCanvasView.swift
/// only renders this at the server-authored `value` for now — dragging
/// the thumb client-side (drawSliderCommand()'s own `sliderValues` map on
/// Android) is real, separate follow-up work, same as the scroll
/// commands above.
public struct SliderCommand: Decodable {
    public let key: String
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let trackHeight: Double
    public let thumbSize: Double
    public let value: Double
    public let trackColor: String
    public let activeColor: String
    public let thumbColor: String
}

/// Mirrors one entry of Canvas::toJson()'s "hitRegions" array — see
/// Tappable.php/Canvas::hitRegion() on the PHP side. `meta` models every
/// real usage found in `packages/ui/src/Native/*.php` (Checkbox/
/// NumberPicker/Drawer's `next`, AlertButton/ConfirmButton's `message`/
/// `title`/etc, GestureDetector's `onDoubleClick`/…) — all flat
/// string-valued maps — as `[String: String]?` rather than a fully
/// generic JSON value, which `ScreenNavigation.reduce(_:_:metaJson:)`
/// only ever re-parses looking for a `"next"` key anyway.
public struct HitRegion: Decodable {
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let action: String
    public let fixed: Bool?
    public let meta: [String: String]?
}

/// One entry of the envelope's own top-level `sliderRegions[]` — a
/// slider has no `HitRegion` of its own (the commit value depends on
/// where within the track you tapped, not a single precomputed action;
/// see `rust/phpnitro-render/src/hittest.rs`'s own doc comment on
/// `slider_hit_test`). Absolute coordinates already baked in
/// server-side, same convention every other region type here uses.
public struct SliderRegion: Decodable {
    public let key: String
    public let x: Double
    public let y: Double
    public let width: Double
    public let height: Double
    public let thumbSize: Double
    public let action: String
}

/// The envelope Canvas::toJson() wraps every render in. `hitRegions` is
/// always present (possibly empty), never omitted — Canvas::toJson()'s
/// own array_filter() only strips null values, and an empty array isn't
/// null. Still missing: heroRegions, autoNavigate, snackbar, and the
/// rest — exactly as real a porting task as this file itself, just not
/// attempted yet (see ios/README.md).
public struct DrawCommandPayload: Decodable {
    public let commands: [DrawCommand]
    public let hitRegions: [HitRegion]
    public let contentHeight: Double
    /// Unlike `hitRegions`, genuinely absent on most screens (only ones
    /// using a `Slider` widget emit this array at all) — decoded via a
    /// custom `init(from:)` below rather than auto-synthesis specifically
    /// so a missing key defaults to `[]`, not a decode failure.
    public let sliderRegions: [SliderRegion]
    /// How long PHP itself took to compute this response — surfaced by
    /// NativeRenderPocActivity.kt's own DevTools panel (extracted there
    /// via a regex against the raw response body; decoded properly here
    /// instead) as "php: X ms", split apart from the surrounding
    /// network/parse round trip so a slow frame can be blamed on the
    /// right side.
    public let renderTimeMs: Double?

    private enum CodingKeys: String, CodingKey {
        case commands, hitRegions, contentHeight, sliderRegions, renderTimeMs
    }

    public init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        commands = try container.decode([DrawCommand].self, forKey: .commands)
        hitRegions = try container.decode([HitRegion].self, forKey: .hitRegions)
        contentHeight = try container.decode(Double.self, forKey: .contentHeight)
        sliderRegions = try container.decodeIfPresent([SliderRegion].self, forKey: .sliderRegions) ?? []
        renderTimeMs = try container.decodeIfPresent(Double.self, forKey: .renderTimeMs)
    }

    /// Which hitRegion (if any) a tap at $point should fire — checked in
    /// REVERSE declaration order, since a later region in the array was
    /// painted later (Tappable.php wraps widgets depth-first, later
    /// siblings/ancestors paint on top), so a tap where two regions
    /// overlap should hit whichever one is visually on top. Mirrors
    /// NativeCanvasView.kt's own hit-testing intent (last-drawn wins),
    /// not a literal port of its implementation.
    ///
    /// `scrollY` mirrors NativeCanvasView.kt's own `handleTap()`: a
    /// region's own x/y/width/height are always in UNSCROLLED content
    /// space (the same space draw commands use before the `-scrollY`
    /// translate), so a raw tap point needs `scrollY` added back before
    /// comparing — except for a `fixed` region (the bottom tab bar, a
    /// FAB), which was never translated in the first place and must be
    /// compared against the raw point as-is.
    public func action(at point: CGPoint, scrollY: Double = 0) -> String? {
        region(at: point, scrollY: scrollY)?.action
    }

    /// Same matching order/logic as `action(at:)` above, but returns the
    /// whole matched `HitRegion` — a `focus:` action needs its rect to
    /// position a text-input overlay (see `NativeScreenViewController.swift`'s
    /// own `handle(action:rect:)`).
    public func region(at point: CGPoint, scrollY: Double = 0) -> HitRegion? {
        for region in hitRegions.reversed() {
            let rect = CGRect(x: region.x, y: region.y, width: region.width, height: region.height)
            let effectivePoint = (region.fixed ?? false) ? point : CGPoint(x: point.x, y: point.y + scrollY)
            if rect.contains(effectivePoint) {
                return region
            }
        }

        return nil
    }
}
