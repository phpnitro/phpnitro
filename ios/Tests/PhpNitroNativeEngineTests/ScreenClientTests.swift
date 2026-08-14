import XCTest
@testable import PhpNitroNativeEngine

/// Intercepts every request on a URLSession configured with it — lets
/// ScreenClientTests exercise the real URLSession/JSONDecoder pipeline
/// against a canned response, no live `phpx serve` needed (none is
/// reachable from CI anyway).
private final class StubURLProtocol: URLProtocol {
    static var handler: ((URLRequest) -> (Int, Data))?

    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }

    override func startLoading() {
        guard let handler = Self.handler, let url = request.url else {
            client?.urlProtocol(self, didFailWithError: URLError(.badURL))
            return
        }
        let (statusCode, data) = handler(request)
        let response = HTTPURLResponse(url: url, statusCode: statusCode, httpVersion: nil, headerFields: nil)!
        client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
        client?.urlProtocol(self, didLoad: data)
        client?.urlProtocolDidFinishLoading(self)
    }

    override func stopLoading() {}
}

final class ScreenClientTests: XCTestCase {
    private var session: URLSession!

    override func setUp() {
        super.setUp()
        let configuration = URLSessionConfiguration.ephemeral
        configuration.protocolClasses = [StubURLProtocol.self]
        session = URLSession(configuration: configuration)
    }

    override func tearDown() {
        StubURLProtocol.handler = nil
        session = nil
        super.tearDown()
    }

    func testBuildsTheExpectedURL() {
        let url = ScreenClient.url(host: "192.168.1.23", port: 8090, screen: "home", action: "counter:increment", width: 390, height: 844)

        XCTAssertEqual(url?.host, "192.168.1.23")
        XCTAssertEqual(url?.port, 8090)
        XCTAssertEqual(url?.path, "/native/layout-demo")

        let query = url.flatMap { URLComponents(url: $0, resolvingAgainstBaseURL: false)?.queryItems } ?? []
        XCTAssertTrue(query.contains(URLQueryItem(name: "screen", value: "home")))
        XCTAssertTrue(query.contains(URLQueryItem(name: "action", value: "counter:increment")))
        XCTAssertTrue(query.contains(URLQueryItem(name: "width", value: "390.0")))
    }

    func testUrlOmitsActionWhenNil() {
        let url = ScreenClient.url(host: "127.0.0.1", port: 8090, screen: "home", action: nil, width: 390, height: 844)
        let query = url.flatMap { URLComponents(url: $0, resolvingAgainstBaseURL: false)?.queryItems } ?? []

        XCTAssertFalse(query.contains { $0.name == "action" })
    }

    func testUrlIncludesFieldValuesSortedByName() {
        let url = ScreenClient.url(
            host: "127.0.0.1",
            port: 8090,
            screen: "login",
            action: nil,
            width: 390,
            height: 844,
            fieldValues: ["password": "hunter2", "email": "a@b.com"]
        )
        let query = url.flatMap { URLComponents(url: $0, resolvingAgainstBaseURL: false)?.queryItems } ?? []

        XCTAssertTrue(query.contains(URLQueryItem(name: "email", value: "a@b.com")))
        XCTAssertTrue(query.contains(URLQueryItem(name: "password", value: "hunter2")))
    }

    func testFetchScreenDecodesASuccessfulPayload() {
        let json = """
        {"commands":[],"hitRegions":[{"x":0,"y":0,"width":10,"height":10,"action":"noop"}],"contentHeight":100}
        """
        StubURLProtocol.handler = { _ in (200, Data(json.utf8)) }

        let client = ScreenClient(host: "127.0.0.1", port: 8090, session: session)
        let expectation = expectation(description: "fetch completes")

        client.fetchScreen("home", width: 390, height: 844) { result in
            switch result {
            case .success(let payload):
                XCTAssertEqual(payload.hitRegions.first?.action, "noop")
                XCTAssertEqual(payload.contentHeight, 100)
            case .failure(let error):
                XCTFail("expected success, got \(error)")
            }
            expectation.fulfill()
        }

        wait(for: [expectation], timeout: 5)
    }

    func testFetchScreenSurfacesAServerErrorEnvelope() {
        let json = """
        {"error":{"class":"RuntimeException","message":"Ecran introuvable"}}
        """
        StubURLProtocol.handler = { _ in (500, Data(json.utf8)) }

        let client = ScreenClient(host: "127.0.0.1", port: 8090, session: session)
        let expectation = expectation(description: "fetch completes")

        client.fetchScreen("home", width: 390, height: 844) { result in
            switch result {
            case .success:
                XCTFail("expected failure")
            case .failure(let error):
                XCTAssertEqual(error, .server(status: 500, message: "Ecran introuvable"))
            }
            expectation.fulfill()
        }

        wait(for: [expectation], timeout: 5)
    }

    func testFetchScreenFallsBackToAPlainStatusMessageWithoutAnErrorEnvelope() {
        StubURLProtocol.handler = { _ in (503, Data("Service Unavailable".utf8)) }

        let client = ScreenClient(host: "127.0.0.1", port: 8090, session: session)
        let expectation = expectation(description: "fetch completes")

        client.fetchScreen("home", width: 390, height: 844) { result in
            switch result {
            case .success:
                XCTFail("expected failure")
            case .failure(let error):
                XCTAssertEqual(error, .server(status: 503, message: "HTTP 503"))
            }
            expectation.fulfill()
        }

        wait(for: [expectation], timeout: 5)
    }
}
