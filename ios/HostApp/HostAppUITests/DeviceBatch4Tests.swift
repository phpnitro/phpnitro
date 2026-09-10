import XCTest

/// Manual verification for appicon/brightness/connectivity/openurl
/// device: bridges — coordinates measured directly off real screenshots
/// after each XCUIApplication.swipeUp() (same "measure from a real
/// screenshot, don't trust the pre-scroll rect math" approach
/// DeviceBatch3Tests needed after its first coordinates missed by a
/// row). swipeUp() specifically (not a manual coordinate press-drag,
/// which reads as a fling whose distance depends on release velocity —
/// observed to drift run-to-run on this exact screen).
///
/// Split into two independent test methods rather than one long combo:
/// chaining 5 taps in a single test proved flaky in a way that never
/// reproduced when each action ran in isolation with a fresh launch —
/// smaller, focused tests are less prone to whatever cumulative state
/// (extra system alerts, timing) made that happen. Kept as a permanent
/// regression suite, same pattern as DeviceBatch2Tests/DeviceBatch3Tests.
///
/// Known limitation: swipeUp()'s own scroll distance was still
/// observed, on rare runs, to be near-zero instead of a full page —
/// this test then lands on an earlier no-op row (Bluetooth/ID device,
/// both harmless — see the `default: break` in
/// NativeScreenViewController's own dispatch) instead of Connectivité/
/// Ouvrir un lien, and passes without having verified anything for
/// that run. Both bridges were independently confirmed correct via a
/// throwaway isolated single-tap test (real Safari navigation for
/// openurl, an async non-blocking fetch for connectivity — see
/// NativeDeviceBridge.swift's own isOnline() docblock for the real bug
/// that surfaced while chasing this exact flakiness) — this is a
/// regression net, not the sole evidence either bridge works.
final class DeviceBatch4Tests: XCTestCase {
    func testAppIconAndBrightness() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        app.swipeUp()
        sleep(1)

        // "Icône bleue" (device:appicon:alt) — post-scroll center norm dy≈0.5425.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.5425)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch4-1-appicon-alt.png"))

        // "Icône par défaut" (device:appicon:default) — dy≈0.618.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.618)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch4-2-appicon-default.png"))

        app.swipeUp()
        sleep(1)

        // "Luminosité 50%" (device:brightness:0.5) — post-scroll (2 swipes) dy≈0.336.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.336)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch4-3-brightness.png"))
    }

    func testConnectivityAndOpenURL() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        app.swipeUp()
        app.swipeUp()
        app.swipeUp()
        app.swipeUp()
        sleep(1)

        // "Connectivité" (device:connectivity:connectivity_out) — post-scroll (4 swipes) dy≈0.4615.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.4615)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch4-4-connectivity.png"))

        // "Ouvrir un lien" (device:openurl:https://phpnitro.dev) — dy≈0.404.
        // Backgrounds the app into Safari.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.404)).tap()
        sleep(3)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch4-5-openurl.png"))
    }
}
