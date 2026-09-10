import XCTest

/// Manual verification for clipboardcopy/clipboardpaste/sendemail/
/// appsettings device: bridges — coordinates measured directly off real
/// screenshots after each XCUIApplication.swipeUp(), same approach
/// DeviceBatch3Tests/DeviceBatch4Tests needed. Split into two methods
/// since "Réglages de l'app" and "Envoyer un email" both background the
/// app (Settings / Mail) — same reasoning DeviceBatch4Tests's own split
/// documents. Kept as a permanent regression suite.
final class DeviceBatch5Tests: XCTestCase {
    func testClipboardAndEmail() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        app.swipeUp()
        app.swipeUp()
        app.swipeUp()
        app.swipeUp()
        app.swipeUp()
        sleep(1)

        // "Copier dans le presse-papiers" (device:clipboardcopy:...) — post-scroll (5 swipes) dy≈0.5495.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.5495)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch5-1-clipboardcopy.png"))

        // "Lire le presse-papiers" (device:clipboardpaste:clipboard_out) — dy≈0.6245.
        // Should show back "Copié depuis PhpNitro !" (copied just above).
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.6245)).tap()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch5-2-clipboardpaste.png"))

        // "Envoyer un email" (device:sendemail:...) — dy≈0.6995. Last
        // action: opens Mail (or "no account configured" on a fresh
        // Simulator with no Mail account).
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.6995)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch5-3-sendemail.png"))
    }

    func testAppSettings() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        app.swipeUp()
        app.swipeUp()
        app.swipeUp()
        app.swipeUp()
        sleep(1)

        // "Réglages de l'app" (device:appsettings:app) — post-scroll (4 swipes) dy≈0.7255.
        // Opens this app's own page in Settings.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.7255)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch5-4-appsettings.png"))
    }
}
