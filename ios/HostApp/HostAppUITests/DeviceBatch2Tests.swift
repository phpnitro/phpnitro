import XCTest

/// Manual verification for securestore/secureretrieve/contacts/calendar
/// device: bridges — taps each row on the "device" screen in sequence
/// and screenshots the result.
final class DeviceBatch2Tests: XCTestCase {
    func testSecureStorageAndContactsAndCalendar() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "device"]
        app.launch()
        sleep(3)

        // "Stocker un secret" — canvas-local y=434.25, center norm dy≈0.595.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.595)).tap()
        sleep(1)

        // "Lire le secret" — canvas-local y=500.25, center norm dy≈0.671.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.671)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch2-1-secure.png"))

        // "Contacts" — canvas-local y=566.25, center norm dy≈0.746.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.746)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch2-2-contacts.png"))

        // "Calendrier" — canvas-local y=632.25, center norm dy≈0.822.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.822)).tap()
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/batch2-3-calendar.png"))
    }
}
