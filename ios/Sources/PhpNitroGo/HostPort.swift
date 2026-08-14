import Foundation

/// The iOS counterpart of android/go's HostPort.kt — shared by
/// ConnectViewController's manual entry field and (once it exists) an
/// iOS QR scanner, since `phpx serve`'s printed URL and its QR code
/// encode the exact same "IP:PORT, optionally prefixed with a scheme"
/// string on both platforms.
enum HostPort {
    static func parse(_ input: String) -> (host: String, port: Int)? {
        var withoutScheme = input.trimmingCharacters(in: .whitespacesAndNewlines)
        for prefix in ["http://", "https://"] where withoutScheme.hasPrefix(prefix) {
            withoutScheme.removeFirst(prefix.count)
            break
        }
        while withoutScheme.hasSuffix("/") {
            withoutScheme.removeLast()
        }

        guard let colonIndex = withoutScheme.lastIndex(of: ":"), colonIndex != withoutScheme.startIndex else { return nil }

        let host = String(withoutScheme[withoutScheme.startIndex..<colonIndex])
        let portString = String(withoutScheme[withoutScheme.index(after: colonIndex)...])

        guard !host.isEmpty, !portString.isEmpty, let port = Int(portString), (1...65535).contains(port) else { return nil }

        return (host, port)
    }
}
