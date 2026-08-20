import Foundation

/// The macOS counterpart of `ios/Sources/PhpNitroGo/HostPort.swift` and
/// `android/go/src/main/java/com/phpnitro/go/HostPort.kt` — a fresh,
/// macOS-local copy rather than a dependency on `PhpNitroGo` itself,
/// since that target imports UIKit (its `ConnectViewController`/
/// `ScanViewController`) and pulling it into this macOS package would
/// fail the exact same way `PhpNitroMacEngine` already avoids every
/// other UIKit-only target in `../ios/`'s package (see this package's
/// own `Package.swift` docblock). `PhpNitroMacApp`'s `AppDelegate` uses
/// this for its own `--connect HOST:PORT` flag — the same "IP:PORT,
/// optionally prefixed with a scheme" string `phpx serve` prints on
/// every platform.
public enum HostPort {
    public static func parse(_ input: String) -> (host: String, port: Int)? {
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
