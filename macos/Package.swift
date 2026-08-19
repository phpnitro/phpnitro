// swift-tools-version:5.9
import Foundation
import PackageDescription

/// Absolute path to rust/phpnitro-render's own cargo build output —
/// computed from this manifest's own compile-time-known location
/// (#filePath) rather than assumed relative to whatever working
/// directory `xcodebuild`/`swift build` happens to use internally
/// (unverified here — no local Xcode to confirm it against), the same
/// idea as [CallerFilePath]/Path(__file__) elsewhere in this repo. Only
/// "release" is referenced: CI (see .github/workflows/ci.yml's
/// macos-build job) runs `cargo build --release` before xcodebuild, the
/// same convention rust/phpnitro-render's own README documents for
/// every other consumer.
let rustReleaseDir = URL(fileURLWithPath: #filePath)
    .deletingLastPathComponent()
    .appendingPathComponent("../rust/phpnitro-render/target/release")
    .standardized
    .path

/// A genuinely SEPARATE package from ../ios/, on purpose — not merged in
/// as a second platform on the same Package.swift. ../ios/'s own
/// PhpNitroWebViewBridge/PhpNitroNativeEngine/PhpNitroGo targets all
/// import UIKit unconditionally; if PhpNitroMacEngine lived in that same
/// package, its own aggregate `-Package` scheme (the one actually wired
/// for the Test action — see ios/README.md's own note on that) would try
/// to build EVERY target when targeted at macOS, including those
/// UIKit-only ones, and fail. A separate package with its own aggregate
/// scheme means a macOS build never touches iOS-only code at all — no
/// `#if os(iOS)` guards needed anywhere in ../ios/'s already-working
/// files, zero regression risk to them from adding this.
///
/// PhpNitroMacEngine depends on ../ios/'s own PhpNitroProtocol product
/// via a local path dependency below — that target has zero UIKit
/// dependency (Foundation/CoreGraphics only), so it's genuinely safe to
/// pull into a macOS build unchanged; this package doesn't duplicate a
/// single line of it.
let package = Package(
    name: "PhpNitroMacEngine",
    platforms: [.macOS(.v13)],
    products: [
        .library(name: "PhpNitroMacEngine", targets: ["PhpNitroMacEngine"]),
        .library(name: "RustMacRenderer", targets: ["RustMacRenderer"]),
    ],
    dependencies: [
        .package(path: "../ios"),
    ],
    targets: [
        .target(
            name: "PhpNitroMacEngine",
            // `package:` here must match the DEPENDENCY'S DIRECTORY NAME
            // ("ios"), not the `name:` field ios/Package.swift declares
            // for itself ("PhpNitroEngine") — confirmed by a real CI
            // failure: "unknown package 'PhpNitroEngine' in dependencies
            // of target 'PhpNitroMacEngine'; valid packages are: 'ios'".
            // SPM resolves a local path dependency's identity from its
            // path, not its own manifest's self-declared name.
            dependencies: [.product(name: "PhpNitroProtocol", package: "ios")],
            path: "Sources/PhpNitroMacEngine",
            // Verbatim copies of the same two font files
            // ios/Sources/PhpNitroNativeEngine/Resources/ and
            // android/engine/src/main/assets/fonts/ already bundle —
            // see this package's own MacIconFont.swift.
            resources: [
                .copy("Resources/MaterialIcons-Regular.ttf"),
                .copy("Resources/FontAwesome-Solid.ttf")
            ]
        ),
        .testTarget(
            name: "PhpNitroMacEngineTests",
            dependencies: ["PhpNitroMacEngine"],
            path: "Tests/PhpNitroMacEngineTests"
        ),
        // A plain C target exposing rust/phpnitro-render's own
        // include/phpnitro_render.h (copied verbatim here, verified
        // identical — same "copy the shared asset into this platform's
        // own tree" idiom this package already uses for the 2 bundled
        // fonts) via a hand-written module map, so RustMacRenderer below
        // can `import CPhpNitroRender` and call its C functions directly
        // — no name-mangling shim needed, unlike JNI on Android.
        .target(
            name: "CPhpNitroRender",
            path: "Sources/CPhpNitroRender"
        ),
        .target(
            name: "RustMacRenderer",
            dependencies: ["CPhpNitroRender"],
            path: "Sources/RustMacRenderer",
            linkerSettings: [
                // -L/-l find the .dylib at LINK time; -rpath bakes an
                // absolute search path into the built binary so dyld can
                // find it at RUN time too, without relying on
                // DYLD_LIBRARY_PATH being set by whatever eventually
                // runs it (xcodebuild's own test runner included).
                .unsafeFlags([
                    "-L", rustReleaseDir,
                    "-lphpnitro_render",
                    "-Xlinker", "-rpath", "-Xlinker", rustReleaseDir,
                ])
            ]
        ),
        .testTarget(
            name: "RustMacRendererTests",
            dependencies: ["RustMacRenderer"],
            path: "Tests/RustMacRendererTests"
        ),
    ]
)
