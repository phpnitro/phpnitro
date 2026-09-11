import Foundation

/// The one seam between NativeScreenViewController and wherever its draw
/// commands actually come from — `ScreenClient` (this file's own module,
/// a real HTTP round-trip to `phpx serve`) and `EmbeddedScreenDataSource`
/// (PhpNitroNativeEngine, PhpEmbedRuntime running PHP in-process, no
/// network at all) both implement it, so NativeScreenViewController never
/// needs to know which one it's holding.
///
/// Mirrors `ScreenClient.fetchScreen(_:action:width:height:fieldValues:completion:)`'s
/// exact signature — no default parameter values here on purpose:
/// Swift resolves a protocol requirement's default arguments against the
/// STATIC (declared) type, so a caller holding this protocol type (not
/// the concrete `ScreenClient`) would silently lose them. Every call
/// site in this codebase already passes every parameter explicitly, so
/// this costs nothing in practice.
public protocol ScreenDataSource {
    /// `completion` is called on an arbitrary background queue — same
    /// contract `ScreenClient`'s own implementation already documents. A
    /// caller touching UIKit from it must hop to the main queue itself.
    func fetchScreen(
        _ screen: String,
        action: String?,
        width: Double,
        height: Double,
        fieldValues: [String: String],
        completion: @escaping (Result<DrawCommandPayload, ScreenFetchError>) -> Void
    )
}
