import XCTest
import PhpNitroProtocol
@testable import PhpNitroNativeEngine

/// The real bug this guards against: `EmbeddedScreenDataSource` used to
/// point PHP straight at `PhpEmbedRuntime.wwwDirectoryURL` — the app
/// BUNDLE's own copy of `www/`, read-only on a real device (confirmed
/// the hard way on a physical iPhone: "Uncaught PDOException" the
/// instant a screen touched the database). `screen=home` alone never
/// exposed this (see `PhpEmbedRuntimeTests`' own home-screen test) — it
/// never touches the database — so this test deliberately drives the
/// one route that does: `screen=login&action=login`, which calls
/// `UserRepository::verifyCredentials()` (`public/index.php`), a real
/// Doctrine/PDO/SQLite read. Mirrors Android's own fix for the exact
/// same read-only-assets problem (`PhpServer.kt`'s `copyAssets()`,
/// copying into `context.filesDir` before ever running PHP against it).
final class EmbeddedScreenDataSourceTests: XCTestCase {
    func testLoginActionTouchesTheRealDatabaseWithoutCrashing() throws {
        guard PhpEmbedRuntime.wwwDirectoryURL != nil else {
            throw XCTSkip("Resources/www not staged — run `php bin/phpx bundle:ios` first")
        }

        // A throwaway runtime this test fully owns — not `.shared`,
        // which is never shut down (see EmbeddedScreenDataSource's own
        // docblock): sharing it here would collide with
        // PhpEmbedRuntimeTests' own start()/shutdown() pairs depending
        // purely on which test class XCTest happens to run first.
        let runtime = PhpEmbedRuntime()
        defer { runtime.shutdown() }
        let dataSource = EmbeddedScreenDataSource(runtime: runtime)

        let expectation = expectation(description: "embedded login fetch completes")
        var result: Result<DrawCommandPayload, ScreenFetchError>?

        dataSource.fetchScreen(
            "login",
            action: "login",
            width: 390,
            height: 844,
            fieldValues: ["username": "demo", "password": "demo"]
        ) {
            result = $0
            expectation.fulfill()
        }

        wait(for: [expectation], timeout: 10)

        switch try XCTUnwrap(result) {
        case .success:
            break
        case .failure(let error):
            XCTFail("expected the real demo/demo login to succeed against the seeded SQLite DB, got: \(error)")
        }

        // The Simulator's own app bundle happens to be writable on the
        // host Mac's filesystem (unlike a real device), so the fetch
        // above passing on its own does NOT prove
        // stageWritableWwwDirectory() is what actually got used — this
        // exact test still passed even pointed straight at the
        // read-only `wwwDirectoryURL` when tried by hand on Simulator.
        // Assert the staged copy itself exists instead, which only
        // stageWritableWwwDirectory() (not the raw bundle path) ever
        // creates — a real, host-independent proof the fix is wired in,
        // not just "happened to work on this particular host".
        let appSupport = try XCTUnwrap(FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask).first)
        let stagedIndexPath = appSupport.appendingPathComponent("www/public/index.php").path
        XCTAssertTrue(
            FileManager.default.fileExists(atPath: stagedIndexPath),
            "expected stageWritableWwwDirectory()'s own copy at \(stagedIndexPath) to exist"
        )
    }
}
