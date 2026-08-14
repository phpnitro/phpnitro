import CoreText
import UIKit

/// Registers MaterialIcons-Regular.ttf/FontAwesome-Solid.ttf (bundled as
/// SPM resources, see Package.swift — verbatim copies of the same two
/// files android/engine/src/main/assets/fonts/ already ships) with Core
/// Text at process start, then hands back the real PostScript name each
/// one registered under so IconCommand's own `font` field can pick the
/// right one at draw time. The registration itself only needs to happen
/// once per process — CTFontManagerRegisterGraphicsFont() on an
/// already-registered font just no-ops (reported via its `error` out
/// param, deliberately ignored here rather than treated as fatal).
enum IconFont {
    static let materialName: String? = register(resource: "MaterialIcons-Regular")
    static let fontAwesomeName: String? = register(resource: "FontAwesome-Solid")

    /// $key matches IconCommand.font ("material"/nil -> Material Icons,
    /// "fontawesome" -> Font Awesome Solid — same convention Icon.php's
    /// own $font parameter uses) at the requested pixel size. Returns
    /// nil if the font never registered (missing resource, corrupt
    /// file) — the caller skips drawing that glyph rather than crash,
    /// same "an unrenderable command is a no-op" contract every other
    /// draw command here follows.
    static func font(forKey key: String?, size: CGFloat) -> UIFont? {
        let name = (key == "fontawesome") ? fontAwesomeName : materialName
        guard let name else { return nil }

        return UIFont(name: name, size: size)
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
