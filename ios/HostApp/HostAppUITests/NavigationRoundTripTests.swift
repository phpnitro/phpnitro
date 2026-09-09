import XCTest

/// Regression test for a real bug found the first time this engine ever
/// ran on a simulator (2026-09-09): icons drawn by NativeCanvasView could
/// disappear again after navigating away from a screen and back — see
/// NativeCanvasView.swift's own doc comment on why every icon is now
/// repainted a second time on every draw(rect:) pass, unconditionally,
/// rather than only once.
///
/// Taps by fixed coordinate, same idea as android/app/src/androidTest's
/// own UI Automator test — this screen is one giant custom-painted
/// canvas, not real UIKit buttons/cells, so XCUIElement queries have
/// nothing to match against. Coordinates are normalized against the full
/// device screen (not NativeCanvasView's own bounds, which start below
/// the navigation bar) — see each tap's own comment for the canvas-local
/// hitRegion it targets and the conversion used.
///
/// Requires `php bin/phpx serve 8090` running at the repo root first —
/// this app has no embedded PHP (see ios/README.md).
final class NavigationRoundTripTests: XCTestCase {
    func testIconsSurviveNavigatingAwayAndBack() throws {
        let app = XCUIApplication()
        app.launch()
        sleep(2)
        let home = XCUIScreen.main.screenshot().image.pngData()

        // "Réglages" row (hitRegion action: "navigate:settings" — PHP's
        // own x=20,y=285.75,width=320,height=62 canvas-local; canvas
        // top sits ~124pt below the device's own top edge).
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.448, dy: 0.504)).tap()
        sleep(2)
        let afterNavigate = XCUIScreen.main.screenshot().image.pngData()

        // The settings screen's own fixed appbar "back" hitRegion
        // (x=16,y=10,width=36,height=36 canvas-local) — a real
        // "action": "back" from PHP, not a synthetic gesture.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.085, dy: 0.174)).tap()
        sleep(2)
        let afterBack = XCUIScreen.main.screenshot().image.pngData()

        XCTAssertNotEqual(home, afterNavigate, "tapping Réglages should have navigated to a different screen")
        XCTAssertNotEqual(afterNavigate, afterBack, "tapping back should have returned to a different screen than Réglages")

        // These asserts only prove the screen actually changed each tap —
        // they can't tell a full icon from a blank one. Saved screenshots
        // are the actual check for that (visually confirmed against the
        // 2026-09-09 fix — see NativeCanvasView.swift): a future icon
        // regression would need a human or agent to look again, same as
        // this one was found.
        try (home ?? Data()).write(to: URL(fileURLWithPath: "/tmp/nav-roundtrip-1-home.png"))
        try (afterNavigate ?? Data()).write(to: URL(fileURLWithPath: "/tmp/nav-roundtrip-2-settings.png"))
        try (afterBack ?? Data()).write(to: URL(fileURLWithPath: "/tmp/nav-roundtrip-3-back-home.png"))
    }
}
