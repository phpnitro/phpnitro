import Darwin
import Foundation

/// The macOS counterpart of Linux's php_process.py — spawns the
/// project's own PHP server as a real child process. Simpler than
/// iOS/Android for the exact same structural reason Linux's own
/// php_process.py documents: a desktop OS doesn't sandbox a process away
/// from its own filesystem, so this just runs the SYSTEM `php` binary
/// straight against the project's own `public/`, the same invocation
/// `bin/phpx serve` already uses for local dev.
///
/// This is also the ONE piece of capability iOS structurally cannot
/// have at all (see PhpEmbedBridge.swift's own TODOs): `Foundation.
/// Process` (subprocess spawning) exists on macOS but NOT on iOS — an
/// Apple sandbox restriction, not a missing API. macOS never had that
/// restriction for an ordinary (non-Mac-App-Store-sandboxed) app, which
/// is exactly what makes this whole file possible where the iOS
/// equivalent never could be.
public final class MacPhpProcess {
    public let projectDirectory: URL
    public private(set) var port: Int = 0

    private var process: Process?

    public init(projectDirectory: URL) {
        self.projectDirectory = projectDirectory
    }

    @discardableResult
    public func start() throws -> Int {
        let publicDir = projectDirectory.appendingPathComponent("public")
        var isDirectory: ObjCBool = false
        guard FileManager.default.fileExists(atPath: publicDir.path, isDirectory: &isDirectory), isDirectory.boolValue else {
            throw MacPhpProcessError.noPublicDirectory(publicDir)
        }

        let freePort = try Self.findFreePort()
        port = freePort

        let process = Process()
        process.executableURL = URL(fileURLWithPath: "/usr/bin/env")
        var arguments = ["php", "-S", "127.0.0.1:\(freePort)", "-t", publicDir.path]
        let router = publicDir.appendingPathComponent("router.php")
        if FileManager.default.fileExists(atPath: router.path) {
            arguments.append(router.path)
        }
        process.arguments = arguments
        process.currentDirectoryURL = projectDirectory
        process.standardOutput = FileHandle.nullDevice
        process.standardError = FileHandle.nullDevice

        try process.run()
        self.process = process

        try Self.waitUntilListening(port: freePort)
        return freePort
    }

    public func stop() {
        process?.terminate()
        process?.waitUntilExit()
        process = nil
    }

    public var isRunning: Bool {
        process?.isRunning ?? false
    }

    // MARK: - Free port selection

    /// Binds a UDP-free (well, here TCP) ephemeral port by asking the OS
    /// for port 0 and reading back what it actually assigned — the same
    /// "bind, read, close" trick php_process.py's own find_free_port()
    /// and PhpServer.kt's own `ServerSocket(0).use { it.localPort }`
    /// both already use, just via raw POSIX sockets (Darwin) since
    /// Foundation itself has no portable "give me a free port" API.
    private static func findFreePort() throws -> Int {
        let fd = socket(AF_INET, SOCK_STREAM, 0)
        guard fd >= 0 else { throw MacPhpProcessError.socketFailed }
        defer { close(fd) }

        var addr = sockaddr_in()
        addr.sin_family = sa_family_t(AF_INET)
        addr.sin_port = 0
        addr.sin_addr.s_addr = inet_addr("127.0.0.1")

        let bindResult = withUnsafePointer(to: &addr) { pointer -> Int32 in
            pointer.withMemoryRebound(to: sockaddr.self, capacity: 1) { sockaddrPointer in
                bind(fd, sockaddrPointer, socklen_t(MemoryLayout<sockaddr_in>.size))
            }
        }
        guard bindResult == 0 else { throw MacPhpProcessError.socketFailed }

        var assigned = sockaddr_in()
        var length = socklen_t(MemoryLayout<sockaddr_in>.size)
        let getNameResult = withUnsafeMutablePointer(to: &assigned) { pointer -> Int32 in
            pointer.withMemoryRebound(to: sockaddr.self, capacity: 1) { sockaddrPointer in
                getsockname(fd, sockaddrPointer, &length)
            }
        }
        guard getNameResult == 0 else { throw MacPhpProcessError.socketFailed }

        return Int(UInt16(bigEndian: assigned.sin_port))
    }

    private static func isListening(port: Int) -> Bool {
        let fd = socket(AF_INET, SOCK_STREAM, 0)
        guard fd >= 0 else { return false }
        defer { close(fd) }

        var addr = sockaddr_in()
        addr.sin_family = sa_family_t(AF_INET)
        addr.sin_port = UInt16(port).bigEndian
        addr.sin_addr.s_addr = inet_addr("127.0.0.1")

        let result = withUnsafePointer(to: &addr) { pointer -> Int32 in
            pointer.withMemoryRebound(to: sockaddr.self, capacity: 1) { sockaddrPointer in
                connect(fd, sockaddrPointer, socklen_t(MemoryLayout<sockaddr_in>.size))
            }
        }
        return result == 0
    }

    private static func waitUntilListening(port: Int, timeout: TimeInterval = 12) throws {
        let deadline = Date().addingTimeInterval(timeout)
        while Date() < deadline {
            if isListening(port: port) { return }
            Thread.sleep(forTimeInterval: 0.15)
        }
        throw MacPhpProcessError.neverStartedListening(port)
    }
}

public enum MacPhpProcessError: Error, Equatable {
    case noPublicDirectory(URL)
    case socketFailed
    case neverStartedListening(Int)
}
