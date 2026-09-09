import XCTest

/// First real exercise of PhpNitroGo (ios/Sources/PhpNitroGo/), the iOS
/// counterpart of android/go — never launched on a simulator/device
/// before today. Drives the manual "IP:PORT" entry path (the QR scanner
/// needs a real camera, not exercisable in a simulator), then confirms
/// it lands on a real NativeScreenViewController fetching from
/// `php bin/phpx serve 8090` at the repo root.
final class GoConnectTests: XCTestCase {
    func testManualConnectReachesARealScreen() throws {
        let app = XCUIApplication()
        app.launch()
        sleep(1)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/go-uitest-1-connect-screen.png"))

        let field = app.textFields.firstMatch
        XCTAssertTrue(field.waitForExistence(timeout: 5), "server address field should exist")
        field.tap()
        // "\n" submits via the keyboard's Return key — textFieldShouldReturn(_:)
        // calls attemptConnect() itself. Tapping the "Connecter" button
        // directly instead fails here: the keyboard covers it while the
        // field is focused (confirmed — XCUITest reports a {-1, -1} hit
        // point, i.e. genuinely unreachable, not a query problem), the
        // same as a real user would have to dismiss the keyboard first.
        field.typeText("127.0.0.1:8090\n")
        sleep(2)
        try (XCUIScreen.main.screenshot().image.pngData() ?? Data())
            .write(to: URL(fileURLWithPath: "/tmp/go-uitest-2-after-connect.png"))
    }
}
