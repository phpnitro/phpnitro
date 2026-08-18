// swift-tools-version:5.9
import PackageDescription

/// Despite the directory's name (`ios/` — historical), one of these
/// targets is now genuinely cross-platform:
///
/// - PhpNitroProtocol — the platform-agnostic wire protocol (draw-command
///   decoding, the network fetch client, the screen-navigation reducer).
///   Only imports Foundation/CoreGraphics, never UIKit, which is exactly
///   what makes it buildable for iOS AND macOS unchanged — split out from
///   PhpNitroNativeEngine once ../macos/'s own PhpNitroMacEngine needed
///   the exact same logic (see ../macos/Package.swift), rather than
///   duplicating it or `#if os()`-guarding it inline. Deliberately kept
///   in THIS package (not moved to ../macos/) and consumed from there via
///   a local path dependency — PhpNitroWebViewBridge/PhpNitroNativeEngine/
///   PhpNitroGo all import UIKit unconditionally, so this whole package's
///   own aggregate `-Package` scheme can never be built FOR macOS without
///   those failing to compile; ../macos/'s own separate package is what
///   keeps a macOS build from ever touching them at all, rather than
///   guarding every iOS file with `#if os(iOS)` to make one shared
///   aggregate scheme safe for both platforms.
///
/// - PhpNitroWebViewBridge — the iOS-only WKWebView fallback path, mirroring
///   MainActivity.kt/WebAppInterface.kt (the pieces `android/app`'s own
///   native Canvas engine still can't reach yet: a live camera/mic
///   preview surface, NFC foreground dispatch, etc. — see
///   NativeDeviceBridge.kt's own docblock).
///
/// - PhpNitroNativeEngine — the iOS side of THIS framework's actual
///   primary rendering path (android/engine's NativeCanvasView.kt
///   equivalent): replays PhpNitroProtocol's DrawCommandPayload with
///   Core Graphics, through UIKit (UIView/UIColor/UIFont).
///
/// - PhpNitroGo — the iOS counterpart of android/go: a companion-app
///   entry screen (ConnectViewController) that validates a manually
///   entered "IP:PORT" and, by default, pushes PhpNitroNativeEngine's own
///   NativeScreenViewController pointed at it — no project PHP bundled,
///   a pure client for whatever `phpx serve` happens to be running on the
///   same network.
///
/// Neither PhpNitroWebViewBridge nor PhpNitroNativeEngine embeds PHP
/// itself (see PhpEmbedBridge.swift's own TODOs) — an actual on-device
/// PHP process/SAPI-embed build for iOS is real, separate, substantial
/// work no file in this directory attempts to fake. See ../macos/README.md
/// for why macOS doesn't need any such thing at all.
let package = Package(
    name: "PhpNitroEngine",
    platforms: [.iOS(.v15)],
    products: [
        .library(name: "PhpNitroProtocol", targets: ["PhpNitroProtocol"]),
        .library(name: "PhpNitroWebViewBridge", targets: ["PhpNitroWebViewBridge"]),
        .library(name: "PhpNitroNativeEngine", targets: ["PhpNitroNativeEngine"]),
        .library(name: "PhpNitroGo", targets: ["PhpNitroGo"]),
    ],
    targets: [
        .target(name: "PhpNitroProtocol", path: "Sources/PhpNitroProtocol"),
        .testTarget(
            name: "PhpNitroProtocolTests",
            dependencies: ["PhpNitroProtocol"],
            path: "Tests/PhpNitroProtocolTests"
        ),

        .target(name: "PhpNitroWebViewBridge", path: "Sources/PhpNitroWebViewBridge"),

        .target(
            name: "PhpNitroNativeEngine",
            dependencies: ["PhpNitroProtocol"],
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
        .target(name: "PhpNitroGo", dependencies: ["PhpNitroNativeEngine"], path: "Sources/PhpNitroGo"),
        .testTarget(
            name: "PhpNitroGoTests",
            dependencies: ["PhpNitroGo"],
            path: "Tests/PhpNitroGoTests"
        ),
    ]
)
