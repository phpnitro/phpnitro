import XCTest

/// Regression test for a real bug: tapping the home screen's hamburger
/// icon (leading: new IconCircle('menu', ..., action: 'toggle:drawer_open',
/// meta: ['next' => '1']) in NativeHomeScreen.php) did nothing on iOS —
/// `HitRegion` never decoded `meta` at all, `NativeCanvasView.onAction`
/// never forwarded it, and `NativeScreenViewController`'s call to
/// `ScreenNavigation.reduce(_:_:)` never passed a `metaJson`, so
/// `toggle:` always fell through to a no-op fetch of the same screen
/// with no field changed — see each file's own updated doc comments.
/// Android never had this gap (`NativeRenderPocActivity.kt`'s own
/// `toggle:` branch always read `meta` directly).
///
/// Same "tap by normalized coordinate, no accessibility tree" approach
/// NavigationRoundTripTests.swift already uses — the menu icon sits in
/// the AppBar's `leading` slot, the exact same canvas-local position
/// (x=16,y=10,width=36,height=36) that test's own back-button tap
/// documents, hence the identical normalized offset.
final class DrawerToggleTests: XCTestCase {
    func testTappingTheHamburgerIconOpensAndClosesTheDrawer() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "home"]
        app.launch()
        sleep(2)
        let before = XCUIScreen.main.screenshot().image.pngData()

        // AppBar leading icon (toggle:drawer_open, meta.next="1").
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.085, dy: 0.10)).tap()
        sleep(2)
        let afterOpen = XCUIScreen.main.screenshot().image.pngData()

        // The drawer's own scrim (Tappable wrapping the full-screen rect,
        // action: 'toggle:drawer_open', meta.next="") — tapping anywhere
        // in the right two-thirds of the screen (outside the 288pt-wide
        // panel) hits it.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.9, dy: 0.5)).tap()
        sleep(2)
        let afterClose = XCUIScreen.main.screenshot().image.pngData()

        XCTAssertNotEqual(before, afterOpen, "tapping the hamburger icon should have opened the drawer")
        XCTAssertNotEqual(afterOpen, afterClose, "tapping the scrim should have closed the drawer again")

        try (before ?? Data()).write(to: URL(fileURLWithPath: "/tmp/drawer-1-closed.png"))
        try (afterOpen ?? Data()).write(to: URL(fileURLWithPath: "/tmp/drawer-2-open.png"))
        try (afterClose ?? Data()).write(to: URL(fileURLWithPath: "/tmp/drawer-3-closed-again.png"))
    }
}
