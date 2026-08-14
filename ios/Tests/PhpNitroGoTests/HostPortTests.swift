import XCTest
@testable import PhpNitroGo

final class HostPortTests: XCTestCase {
    func testParsesAPlainHostPort() {
        let parsed = HostPort.parse("192.168.1.23:8090")

        XCTAssertEqual(parsed?.host, "192.168.1.23")
        XCTAssertEqual(parsed?.port, 8090)
    }

    func testStripsAnHttpScheme() {
        let parsed = HostPort.parse("http://192.168.1.23:8090")

        XCTAssertEqual(parsed?.host, "192.168.1.23")
        XCTAssertEqual(parsed?.port, 8090)
    }

    func testStripsAnHttpsSchemeAndTrailingSlash() {
        let parsed = HostPort.parse("https://192.168.1.23:8090/")

        XCTAssertEqual(parsed?.host, "192.168.1.23")
        XCTAssertEqual(parsed?.port, 8090)
    }

    func testTrimsWhitespace() {
        let parsed = HostPort.parse("  192.168.1.23:8090  \n")

        XCTAssertEqual(parsed?.host, "192.168.1.23")
        XCTAssertEqual(parsed?.port, 8090)
    }

    func testRejectsMissingColon() {
        XCTAssertNil(HostPort.parse("192.168.1.23"))
    }

    func testRejectsMissingHost() {
        XCTAssertNil(HostPort.parse(":8090"))
    }

    func testRejectsMissingPort() {
        XCTAssertNil(HostPort.parse("192.168.1.23:"))
    }

    func testRejectsANonNumericPort() {
        XCTAssertNil(HostPort.parse("192.168.1.23:abc"))
    }

    func testRejectsAPortOutOfRange() {
        XCTAssertNil(HostPort.parse("192.168.1.23:70000"))
        XCTAssertNil(HostPort.parse("192.168.1.23:0"))
    }

    func testRejectsAnEmptyString() {
        XCTAssertNil(HostPort.parse(""))
    }
}
