import XCTest
@testable import PhpNitroNativeEngine

/// The pure, UIKit-free helpers of NativeDeviceBridge. No host app, no
/// view controller and no filesystem are needed here — that is the whole
/// point of keeping safeFileName a plain static function.
final class NativeDeviceBridgeTests: XCTestCase {
    /// `openfile` must never let a caller-supplied name walk out of the
    /// app's Documents directory — the iOS mirror of Android's
    /// `File(URLDecoder.decode(name)).name` path-traversal guard.
    func testSafeFileNameStripsDirectoryComponents() {
        XCTAssertEqual(NativeDeviceBridge.safeFileName("../../../etc/passwd"), "passwd")
        XCTAssertEqual(NativeDeviceBridge.safeFileName("sub/dir/file.pdf"), "file.pdf")
        XCTAssertEqual(NativeDeviceBridge.safeFileName("document.txt"), "document.txt")
    }

    /// A trailing "/" or a bare "." / ".." has no real last component —
    /// fall back to a safe default rather than appending a directory to
    /// the Documents URL (which would fail the write, or worse).
    func testSafeFileNameFallsBackForDegenerateNames() {
        XCTAssertEqual(NativeDeviceBridge.safeFileName(""), "document.txt")
        XCTAssertEqual(NativeDeviceBridge.safeFileName(".."), "document.txt")
        XCTAssertEqual(NativeDeviceBridge.safeFileName("."), "document.txt")
    }
}
