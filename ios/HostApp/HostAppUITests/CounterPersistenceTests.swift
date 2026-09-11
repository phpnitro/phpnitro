import UIKit
import XCTest

/// Regression test for a real bug found on a physical iPhone: closing
/// the app completely and reopening it reset the home screen's counter
/// (`Engine\Preferences\Preferences`, backed by
/// `lib/backend/var/data.sqlite`) back to 0, even though that class's
/// own docblock explicitly promises persistence across app restarts
/// "like SharedPreferences" — `PhpEmbedRuntime.stageWritableWwwDirectory()`
/// used to wipe and re-copy the ENTIRE `www` tree (code AND data) on
/// every single launch. See that method's own updated doc comment for
/// the fix: `lib/backend/var/` now survives the wipe.
///
/// `app.terminate()` + a fresh `XCUIApplication().launch()` is a real
/// process kill and cold relaunch, not just backgrounding — the only way
/// to actually exercise `stageWritableWwwDirectory()`'s "does data
/// survive a fresh process" question at all (an app merely backgrounded
/// never re-runs that method, since `EmbeddedScreenDataSource.shared`
/// stays alive in memory).
final class CounterPersistenceTests: XCTestCase {
    func testCounterSurvivesACompleteAppRestart() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-screen", "home"]
        app.launch()
        sleep(2)

        // "+ Incrémenter" button (home_increment) — measured directly off
        // a real screenshot (not the canvas-local JSON rect, which is
        // relative to a request-time width/height this test doesn't
        // control), same "measure off a real screenshot" approach
        // DeviceBatch5Tests.swift's own coordinates use.
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.323)).tap()
        sleep(1)
        app.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.323)).tap()
        sleep(1)
        let beforeImage = XCUIScreen.main.screenshot().image
        try (beforeImage.pngData() ?? Data()).write(to: URL(fileURLWithPath: "/tmp/counter-1-before-restart.png"))

        app.terminate()
        sleep(1)
        let relaunched = XCUIApplication()
        relaunched.launchArguments = ["-screen", "home"]
        relaunched.launch()
        sleep(2)
        let afterImage = XCUIScreen.main.screenshot().image
        try (afterImage.pngData() ?? Data()).write(to: URL(fileURLWithPath: "/tmp/counter-2-after-restart.png"))

        // Compares everything BELOW the status bar only — the clock
        // ticking a minute forward between the two screenshots would
        // otherwise make an exact full-screen byte comparison flaky for
        // a reason that has nothing to do with what this test actually
        // checks (the counter's own persisted value).
        func belowStatusBar(_ image: UIImage) -> Data? {
            guard let cgImage = image.cgImage else { return nil }
            let statusBarHeight = cgImage.height / 15
            guard let cropped = cgImage.cropping(to: CGRect(
                x: 0, y: statusBarHeight, width: cgImage.width, height: cgImage.height - statusBarHeight
            )) else { return nil }
            return UIImage(cgImage: cropped).pngData()
        }

        XCTAssertEqual(
            belowStatusBar(beforeImage), belowStatusBar(afterImage),
            "the counter should show the same value right after a full app restart as it did right before — a byte-identical screenshot match (status bar cropped out) is the simplest way to prove this without an accessibility tree to read the number from"
        )
    }
}
