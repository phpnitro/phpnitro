import XCTest

/// Regression test for NativeDeviceBridge.vibrate() — HostApp's
/// AppDelegate currently points at screen "device" for this. Confirms
/// tapping "Vibrer" doesn't crash and doesn't trigger an unwanted
/// network refetch: the screen must look pixel-identical before and
/// after, since `device:*` actions are handled entirely client-side
/// (see NativeScreenViewController.handle(action:rect:)) — a refetch
/// here would be a real regression (e.g. accidentally falling through to
/// ScreenNavigation.reduce(_:_:) again). The vibration itself (a haptic)
/// can't be asserted from a screenshot; verified manually via
/// `log show --predicate 'eventMessage CONTAINS "vibrate"'` while
/// writing this bridge (2026-09-09) instead.
final class DeviceVibrateTests: XCTestCase {
    func testTappingVibrateDoesNotRefetchOrCrash() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(2)
        let before = XCUIScreen.main.screenshot().image.pngData()

        // "Vibrer" row — canvas-local (20,104.25,320,54), canvas top at
        // ~59pt (status bar only, no nav bar). Center ≈ (200, 190.25) —
        // measured directly against a real screenshot, not assumed: the
        // PHP-computed width at this device's real screen width (362,
        // not the 320 a narrower test fetch returns) put the row's true
        // center a bit right of x=180.
        app.coordinate(withNormalizedOffset: CGVector(dx: 200.0 / 402.0, dy: 190.25 / 874.0)).tap()
        sleep(1)
        let after = XCUIScreen.main.screenshot().image.pngData()

        XCTAssertEqual(before, after, "tapping a device: action must not trigger a refetch")
    }
}
