import XCTest

/// Manual verification for sound/notify/share device: bridges — scrolls
/// the "device" screen down (they sit past the bottom tab bar's fixed
/// area at scrollY=0) then taps each row in sequence and screenshots
/// the result. Kept as a permanent regression test, same pattern as
/// DeviceBatch2Tests.
final class DeviceBatch3Tests: XCTestCase {
    func testSoundAndNotifyAndShare() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        // Drag content up by ~400pt so "Jouer un son"/"Notification"/
        // "Partager" clear the fixed bottom tab bar.
        let start = app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.85))
        let end = app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.376))
        start.press(forDuration: 0.2, thenDragTo: end)
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch3-0-scrolled.png"))

        // "Jouer un son" — post-scroll center norm dy≈0.334, derived
        // from the content's own row math (content-space top 698.25,
        // scrollY≈443 recovered from where "Contacts" actually rendered
        // in a screenshot, row height 54, screen height 844) rather than
        // eyeballing pixels — the eyeballed guess this test started with
        // undershot by almost half a row and silently tapped empty
        // space between buttons instead.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.334)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch3-1-sound.png"))

        // "Notification" — post-scroll center norm dy≈0.4125. First tap
        // (on a simulator with the permission not yet decided) triggers
        // the system permission prompt.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.4125)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch3-2-notify-prompt.png"))

        let allow = app.buttons["Allow"].exists ? app.buttons["Allow"] : app.buttons["Autoriser"]
        if allow.exists {
            allow.tap()
            sleep(1)
        }
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch3-3-notify-after.png"))

        // "Partager" — post-scroll center norm dy≈0.51.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.51)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch3-4-share.png"))
    }
}
