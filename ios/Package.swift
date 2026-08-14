// swift-tools-version:5.9
import PackageDescription

/// Two SEPARATE libraries, matching two separate Android modules:
///
/// - PhpNitroWebViewBridge — the WKWebView fallback path, mirroring
///   MainActivity.kt/WebAppInterface.kt (the pieces `android/app`'s own
///   native Canvas engine still can't reach yet: a live camera/mic
///   preview surface, NFC foreground dispatch, etc. — see
///   NativeDeviceBridge.kt's own docblock). This was already written
///   (see README.md's own history) before this package manifest existed;
///   moving it here doesn't change a line of it, just makes it
///   `xcodebuild`-able for the first time.
///
/// - PhpNitroNativeEngine — the iOS side of THIS framework's actual
///   primary rendering path (android/engine's NativeCanvasView.kt
///   equivalent): decodes the same draw-command JSON
///   Engine\Native\Canvas::toJson() already produces and replays it with
///   Core Graphics. Brand new, added alongside this manifest — until now
///   iOS had no awareness of this protocol at all, only of the older
///   WebView bridge above.
///
/// Neither target embeds PHP itself (see PhpEmbedBridge.swift's own
/// TODOs) — an actual on-device PHP process/SAPI-embed build for iOS is
/// real, separate, substantial work no file in this directory attempts
/// to fake.
let package = Package(
    name: "PhpNitroEngine",
    platforms: [.iOS(.v15)],
    products: [
        .library(name: "PhpNitroWebViewBridge", targets: ["PhpNitroWebViewBridge"]),
        .library(name: "PhpNitroNativeEngine", targets: ["PhpNitroNativeEngine"]),
    ],
    targets: [
        .target(name: "PhpNitroWebViewBridge", path: "Sources/PhpNitroWebViewBridge"),
        .target(
            name: "PhpNitroNativeEngine",
            path: "Sources/PhpNitroNativeEngine",
            // Verbatim copies of the SAME two font files
            // android/engine/src/main/assets/fonts/ already bundles —
            // MaterialIcons/FontAwesome glyphs are just Unicode
            // codepoints in a font, not Android-specific in any way, so
            // there's nothing to "port" here, only to copy. `.copy`
            // (not `.process`) because font files need to reach the
            // bundle byte-for-byte, not go through a resource
            // processing pipeline meant for things like asset catalogs.
            resources: [
                .copy("Resources/MaterialIcons-Regular.ttf"),
                .copy("Resources/FontAwesome-Solid.ttf")
            ]
        ),
        .testTarget(
            name: "PhpNitroNativeEngineTests",
            dependencies: ["PhpNitroNativeEngine"],
            path: "Tests/PhpNitroNativeEngineTests"
        ),
    ]
)
