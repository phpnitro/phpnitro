import AppKit
import CoreText

/// The macOS counterpart of IconFont.swift (iOS) — registers the same
/// bundled font files (MaterialIcons-Regular.ttf/FontAwesome-Solid.ttf/
/// Roboto-Regular.ttf, verbatim copies, see Package.swift) with Core
/// Text, then hands back the real PostScript name each one registered
/// under. Core Text's own registration API
/// (CTFontManagerRegisterGraphicsFont) is a plain CoreText/CoreGraphics
/// call, not a UIKit one — identical on both platforms; only the final
/// `NSFont(name:size:)` lookup at the bottom differs from
/// IconFont.swift's own `UIFont(name:size:)`.
enum MacIconFont {
    static let materialName: String? = register(resource: "MaterialIcons-Regular")
    static let fontAwesomeName: String? = register(resource: "FontAwesome-Solid")
    static let robotoName: String? = register(resource: "Roboto-Regular")

    static func font(forKey key: String?, size: CGFloat) -> NSFont? {
        let name = (key == "fontawesome") ? fontAwesomeName : materialName
        guard let name else { return nil }
        return NSFont(name: name, size: size)
    }

    /// Body text (the "text" draw command) — NOT `NSFont.systemFont`.
    /// packages/ui/src/Native/TextMetrics.php's per-character
    /// advance-width table was measured against real Roboto (see
    /// NativeCanvasView.kt's own robotoTypeface() docblock for the
    /// confirmed device bug this fixed on Android: a non-stock OEM's
    /// system font has different glyph widths, so PHP's computed box
    /// sizes/wrap points silently disagree with what's actually drawn).
    /// San Francisco is exactly such a mismatched font here — bundling
    /// the same Roboto-Regular.ttf every other platform ships keeps
    /// drawn width matching measured width on macOS too.
    ///
    /// Only one weight is bundled (same as Android/Rust) — bold is
    /// synthesized via NSFontManager, mirroring Typeface.create(...,
    /// Typeface.BOLD)'s own synthetic emboldening on Android.
    static func robotoFont(size: CGFloat, bold: Bool) -> NSFont {
        let base = robotoName.flatMap { NSFont(name: $0, size: size) }
            ?? .systemFont(ofSize: size)
        guard bold else { return base }
        return NSFontManager.shared.convert(base, toHaveTrait: .boldFontMask)
    }

    private static func register(resource: String) -> String? {
        guard let url = Bundle.module.url(forResource: resource, withExtension: "ttf") else { return nil }
        guard let data = try? Data(contentsOf: url) else { return nil }
        guard let provider = CGDataProvider(data: data as CFData) else { return nil }
        guard let cgFont = CGFont(provider) else { return nil }
        var registrationError: Unmanaged<CFError>?
        CTFontManagerRegisterGraphicsFont(cgFont, &registrationError)
        return cgFont.postScriptName as String?
    }
}
