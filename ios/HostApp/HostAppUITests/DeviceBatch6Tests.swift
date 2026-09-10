import XCTest

/// Manual verification for airplanemode/wifi/hotspot/wallpaper/health/
/// reminders/apn/filesapp device: bridges — coordinates measured
/// directly off real screenshots after each XCUIApplication.swipeUp(),
/// same approach DeviceBatch3Tests/DeviceBatch4Tests/DeviceBatch5Tests
/// needed. Kept as a permanent regression suite.
final class DeviceBatch6Tests: XCTestCase {
    func testAirplaneWifiHotspotWallpaperHealth() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        // No scroll needed — all five rows visible on a fresh launch.
        // Each tap's result row grows the list, shifting rows below it
        // down — re-measured off a real screenshot after the first two
        // taps landed, same "coordinates drift as results appear"
        // caveat DeviceBatch3Tests/DeviceBatch4Tests already document.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.4545)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch6-1a-after-airplane.png"))
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.5118)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch6-1b-after-wifi.png"))
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.7325)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch6-1c-after-hotspot.png"))
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.81)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch6-1d-after-wallpaper.png"))
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.885)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch6-1-airplane-wifi-hotspot-wallpaper-health.png"))
    }

    func testRemindersApn() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        app.swipeUp()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch6-2-after-swipe.png"))
    }
}
