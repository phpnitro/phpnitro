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
    ///
    /// `lastHash` mirrors NativeRenderPocActivity.kt's own `lastHashParam`
    /// — the `Canvas::stableHash()` of the last payload this caller
    /// actually applied, or nil to always get a full response (a real
    /// navigation, or simply not having one yet). When the hash still
    /// matches server-side, PHP's own `public/index.php` replies
    /// `{"unchanged":true}` instead of the full payload — the success
    /// case then carries `nil` instead of a `DrawCommandPayload`,
    /// meaning "nothing changed, leave the current frame alone" rather
    /// than a payload to apply. Real gap found auditing iOS against
    /// Android: this short-circuit never existed here at all, so every
    /// same-screen refetch re-sent and re-parsed the full payload even
    /// when truly nothing had changed.
    func fetchScreen(
        _ screen: String,
        action: String?,
        width: Double,
        height: Double,
        fieldValues: [String: String],
        lastHash: String?,
        completion: @escaping (Result<DrawCommandPayload?, ScreenFetchError>) -> Void
    )
}
