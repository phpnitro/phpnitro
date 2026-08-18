import AppKit
import CoreText

/// The macOS counterpart of IconFont.swift (iOS) — registers the same
/// two bundled font files (MaterialIcons-Regular.ttf/FontAwesome-Solid.ttf,
/// verbatim copies, see Package.swift) with Core Text, then hands back
/// the real PostScript name each one registered under. Core Text's own
/// registration API (CTFontManagerRegisterGraphicsFont) is a plain
/// CoreText/CoreGraphics call, not a UIKit one — identical on both
/// platforms; only the final `NSFont(name:size:)` lookup at the bottom
/// differs from IconFont.swift's own `UIFont(name:size:)`.
enum MacIconFont {
    static let materialName: String? = register(resource: "MaterialIcons-Regular")
    static let fontAwesomeName: String? = register(resource: "FontAwesome-Solid")

    static func font(forKey key: String?, size: CGFloat) -> NSFont? {
        let name = (key == "fontawesome") ? fontAwesomeName : materialName
        guard let name else { return nil }
        return NSFont(name: name, size: size)
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
