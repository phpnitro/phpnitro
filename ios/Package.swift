// swift-tools-version:5.9
import Foundation
import PackageDescription

/// Absolute path to rust/phpnitro-render's build output for the iOS
/// SIMULATOR target — computed from this manifest's own compile-time-known
/// location (#filePath), same idea as [CallerFilePath]/Path(__file__)
/// elsewhere in this repo, and the same technique ../macos/Package.swift
/// uses for its own (host-native, no cross-compilation needed) build.
/// Unlike macOS, this one IS a cross-compiled target — aarch64-apple-ios-sim,
/// matching the Apple Silicon macos-14 CI runner's own simulator
/// architecture (see .github/workflows/ci.yml's ios-build job, which adds
/// `rustup target add aarch64-apple-ios-sim` + `cargo build --release
/// --target aarch64-apple-ios-sim` before xcodebuild links anything
/// against this path) — so the output lives under
/// target/aarch64-apple-ios-sim/release/, not plain target/release/ the
/// way a host-native build would.
let rustIosSimReleaseDir = URL(fileURLWithPath: #filePath)
    .deletingLastPathComponent()
    .appendingPathComponent("../rust/phpnitro-render/target/aarch64-apple-ios-sim/release")
    .standardized
    .path

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
        .library(name: "RustNativeRenderer", targets: ["RustNativeRenderer"]),
    ],
    dependencies: [
        // The direct iOS counterpart of Android's own
        // com.vanniktech:android-image-cropper (see engine/build.gradle.kts) —
        // a freeform, drag-resizable crop rectangle by default
        // (Mantis.CropViewConfig's own presetFixedRatioType defaults to
        // .alwaysUsingOnePresetFixedRatio(...) == nil, i.e. no fixed
        // ratio), matching that library's own canChangeCropWindow=true/
        // fixAspectRatio=false defaults — chosen over TOCropViewController
        // for being pure-Swift and more actively maintained (2026-09-10).
        .package(url: "https://github.com/guoyingtao/Mantis-spm.git", from: "3.1.0"),
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
            dependencies: [
                "PhpNitroProtocol",
                "CPhpEmbed",
                .product(name: "Mantis", package: "Mantis-spm"),
            ],
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
                .copy("Resources/FontAwesome-Solid.ttf"),
                .copy("Resources/Roboto-Regular.ttf"),
                // public/ + lib/ + packages/*/src + composer.json +
                // vendor/, staged by `phpx bundle:ios` (never committed —
                // see .gitignore, same "regenerated at build time"
                // convention android/README.md's own assets/www already
                // documents). Whole-directory `.copy` (not `.process`,
                // same reasoning as the fonts above): these need to reach
                // Bundle.module byte-for-byte, PHP source untouched by
                // any resource pipeline. Must exist before `swift build`/
                // `xcodebuild` even starts resolving this package — SPM
                // validates every resource path up front, so `phpx
                // bundle:ios` is a hard prerequisite, not an optional
                // nice-to-have, exactly like `bundle:android` already is
                // for `gradle assemble` on the other platform.
                .copy("Resources/www"),
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

        // A `.systemLibrary` target, NOT a plain `.target` — confirmed by
        // a real CI failure on ../macos/'s identical setup ("Build input
        // file cannot be found: .../CPhpNitroRender.o ... Did you forget
        // to declare this file as an output of a script phase...") the
        // first time this was written as `.target`: a regular C-family
        // target always expects to COMPILE something into an object
        // file, but this target has no .c/.m source at all, only a
        // header + module map exposing rust/phpnitro-render's own
        // include/phpnitro_render.h (copied verbatim here, verified
        // identical). `.systemLibrary` is SPM's purpose-built target
        // kind for exactly this "headers + module map only" case — the
        // real linking happens via RustNativeRenderer's own
        // linkerSettings below, not through this target at all.
        .systemLibrary(
            name: "CPhpNitroRender",
            path: "Sources/CPhpNitroRender"
        ),
        .target(
            name: "RustNativeRenderer",
            dependencies: ["CPhpNitroRender"],
            path: "Sources/RustNativeRenderer",
            linkerSettings: [
                // -L/-l find the .dylib at LINK time; -rpath bakes an
                // absolute search path into the built binary so dyld can
                // find it at RUN time too (inside the iOS Simulator),
                // without relying on an environment variable being set
                // by whatever eventually runs it (xcodebuild's own test
                // runner included).
                .unsafeFlags([
                    "-L", rustIosSimReleaseDir,
                    "-lphpnitro_render",
                    "-Xlinker", "-rpath", "-Xlinker", rustIosSimReleaseDir,
                ])
            ]
        ),
        .testTarget(
            name: "RustNativeRendererTests",
            dependencies: ["RustNativeRenderer"],
            path: "Tests/RustNativeRendererTests"
        ),

        // vendor/php/libphp.xcframework: PHP 8.4.2's embed SAPI,
        // cross-compiled for both iOS targets this project builds for
        // (device + Simulator) — matching Android's own libphp.so
        // exactly, not arbitrarily: this project's Symfony 8.x
        // dependency genuinely needs 8.4's property hooks syntax (real
        // parser support, not just a declared composer.json floor) —
        // see vendor/php/README.md for the real "Parse error" this
        // fixed on a physical iPhone, and for the full build recipe and
        // why it's committed rather than rebuilt (same reasoning as
        // android/README.md's own libphp.so). An
        // xcframework (not a raw .a + unsafeFlags, unlike
        // RustNativeRenderer above) specifically BECAUSE it needs a
        // device AND a Simulator slice — SPM's `.binaryTarget` +
        // xcframework is the one mechanism that picks the right slice
        // automatically per build destination; a hand-written `-L/-l`
        // unsafeFlags pair (RustNativeRenderer's own approach) has no
        // way to do that, it always points at one fixed path.
        .binaryTarget(name: "libphp", path: "vendor/php/libphp.xcframework"),

        // The thin C shim (phpx_embed.h/.c) making the embed SAPI
        // callable from Swift at all — see that header's own docblock
        // for why (PHP_EMBED_START_BLOCK/END_BLOCK are C macros using
        // setjmp-based zend_first_try/zend_catch, which Swift cannot
        // call, and zend_eval_string()'s own output goes through a SAPI
        // callback meant for a real request/response cycle, not
        // returned as a value). -lresolv/-liconv/-lm/-lsqlite3: system
        // libraries php-src's own embed SAPI (sqlite3 specifically:
        // ext/pdo_sqlite + ext/sqlite3, both statically enabled at
        // configure time — see vendor/php/README.md) needs beyond libc
        // that the static archive doesn't bundle (same libtool-vs-
        // consumer split that README documents).
        .target(
            name: "CPhpEmbed",
            dependencies: ["libphp"],
            path: "Sources/CPhpEmbed",
            publicHeadersPath: "include",
            cSettings: [
                .define("ZEND_ENABLE_STATIC_TSRMLS_CACHE", to: "1"),
            ],
            linkerSettings: [
                .linkedLibrary("resolv"),
                .linkedLibrary("iconv"),
                .linkedLibrary("m"),
                .linkedLibrary("sqlite3"),
            ]
        ),
    ]
)
