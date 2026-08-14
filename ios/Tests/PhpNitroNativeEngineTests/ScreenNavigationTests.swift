import XCTest
@testable import PhpNitroNativeEngine

final class ScreenNavigationTests: XCTestCase {
    func testNavigatePushesOntoTheStack() {
        let result = ScreenNavigation.reduce(action: "navigate:product?id=42", stack: ["home"])

        XCTAssertEqual(result, .fetch(stack: ["home", "product?id=42"], action: nil))
    }

    func testTabResetsTheWholeStack() {
        let result = ScreenNavigation.reduce(action: "tab:profile", stack: ["home", "product?id=42", "reviews"])

        XCTAssertEqual(result, .fetch(stack: ["profile"], action: nil))
    }

    func testBackPopsTheStackWhenMoreThanOneScreen() {
        let result = ScreenNavigation.reduce(action: "back", stack: ["home", "product?id=42"])

        XCTAssertEqual(result, .fetch(stack: ["home"], action: nil))
    }

    func testBackIsANoOpOnTheRootScreen() {
        let result = ScreenNavigation.reduce(action: "back", stack: ["home"])

        XCTAssertEqual(result, .fetch(stack: ["home"], action: nil))
    }

    func testClientTabIsFullyLocalWithNoFetch() {
        let result = ScreenNavigation.reduce(action: "clientTab:tabs1:2", stack: ["home"])

        XCTAssertEqual(result, .clientTabOnly(key: "tabs1", index: 2))
    }

    func testMalformedClientTabFallsBackToAPlainFetch() {
        let result = ScreenNavigation.reduce(action: "clientTab:tabs1", stack: ["home"])

        XCTAssertEqual(result, .fetch(stack: ["home"], action: nil))
    }

    func testAPlainActionRefetchesTheCurrentScreenWithIt() {
        let result = ScreenNavigation.reduce(action: "counter:increment", stack: ["home"])

        XCTAssertEqual(result, .fetch(stack: ["home"], action: "counter:increment"))
    }
}
