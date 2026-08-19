import CPhpNitroRender
import Foundation

/// Swift wrapper around rust/phpnitro-render's C ABI
/// (include/phpnitro_render.h, copied into
/// Sources/CPhpNitroRender/include/ verbatim) — the macOS counterpart
/// of linux/phpnitro_desktop/rust_render.py's ctypes bindings and
/// windows/PhpNitroDesktop.Render/RustRenderer.cs's P/Invoke bindings.
/// Every function name below matches phpnitro_render.h one-for-one.
///
/// Unlike the ctypes/P-Invoke ports, this one needs no path-searching at
/// runtime at all: linking happens at BUILD time via Package.swift's own
/// `-L`/`-l`/`-rpath` linker flags (computed from the manifest's own
/// #filePath), so by the time this Swift code runs, the Rust symbols are
/// already resolved — calling them is exactly like calling any other C
/// function Swift already knows about.

public enum RustRenderError: Error {
    case rendererUnavailable
}

/// RGBA8, premultiplied alpha — tiny-skia's native Pixmap layout, NOT
/// Core Graphics' usual byte order. A caller painting this through a
/// CGContext needs a CGBitmapInfo matching RGBA (not the platform's
/// typical BGRA), not to assume it lines up for free.
public struct RenderedFrame {
    public let width: UInt32
    public let height: UInt32
    public let stride: UInt32
    public let data: [UInt8]
}

public struct HitResult {
    public let action: String
    public let metaJSON: String
    public let left: Float
    public let top: Float
    public let right: Float
    public let bottom: Float
}

private func utf8OrEmpty(_ pointer: UnsafePointer<CChar>?) -> String {
    guard let pointer else { return "" }
    return String(cString: pointer)
}

private func utf8OrNil(_ pointer: UnsafePointer<CChar>?) -> String? {
    guard let pointer else { return nil }
    return String(cString: pointer)
}

/// Owns the loaded fonts (rust/phpnitro-render's FontSystem) — create
/// ONE of these per app lifetime (or per screen at most), never one per
/// frame, same guidance phpnitro_render.h gives every other consumer.
public final class RustRenderer {
    private var handle: OpaquePointer?

    public init() throws {
        guard let handle = phpnitro_render_new() else {
            throw RustRenderError.rendererUnavailable
        }
        self.handle = handle
    }

    deinit {
        if let handle {
            phpnitro_render_free(handle)
        }
    }

    public static var version: String {
        utf8OrEmpty(phpnitro_render_version())
    }

    public static var lastError: String? {
        utf8OrNil(phpnitro_render_last_error())
    }

    /// Returns nil on failure (malformed JSON, zero width/height) — check
    /// `RustRenderer.lastError` for why.
    public func renderFrame(envelopeJSON: String, widthPx: UInt32, heightPx: UInt32, elapsedMs: UInt64 = 0) -> RenderedFrame? {
        guard let handle else { return nil }
        let frame: OpaquePointer? = envelopeJSON.withCString { cString in
            phpnitro_render_frame(handle, cString, widthPx, heightPx, elapsedMs)
        }
        guard let frame else { return nil }
        defer { phpnitro_render_free_frame(frame) }

        let stride = phpnitro_render_frame_stride(frame)
        let actualWidth = phpnitro_render_frame_width(frame)
        let actualHeight = phpnitro_render_frame_height(frame)
        let byteCount = Int(stride) * Int(actualHeight)
        var data = [UInt8](repeating: 0, count: byteCount)
        if let pixels = phpnitro_render_frame_pixels(frame), byteCount > 0 {
            // UnsafeBufferPointer<UInt8> matches phpnitro_render_frame_pixels'
            // own UnsafePointer<UInt8> return type directly — no raw-pointer
            // reinterpretation needed, so no risk of a type mismatch there.
            data = Array(UnsafeBufferPointer(start: pixels, count: byteCount))
        }
        return RenderedFrame(width: actualWidth, height: actualHeight, stride: stride, data: data)
    }
}

/// Free function (not a RustRenderer method) since hit-testing needs no
/// loaded fonts at all — mirrors phpnitro_render_hit_test not taking a
/// renderer handle either, same as the Python/C# ports.
public func rustHitTest(envelopeJSON: String, tapX: Float, tapY: Float, interactionStateJSON: String? = nil) -> HitResult? {
    // "" and nil are handled identically on the Rust side
    // (interaction_state_from_json treats a blank string the same as
    // absent — see rust/phpnitro-render/src/lib.rs) — using "" here
    // sidesteps a nested-optional-closure return-type question this
    // environment has no Swift compiler to check either way.
    let stateJSON = interactionStateJSON ?? ""
    let hit: OpaquePointer? = envelopeJSON.withCString { envelopeCString in
        stateJSON.withCString { stateCString in
            phpnitro_render_hit_test(envelopeCString, tapX, tapY, stateCString)
        }
    }
    guard let hit else { return nil }
    defer { phpnitro_render_free_hit(hit) }

    let action = utf8OrEmpty(phpnitro_render_hit_action(hit))
    let metaJSON = utf8OrNil(phpnitro_render_hit_meta_json(hit)) ?? "null"
    var left: Float = 0, top: Float = 0, right: Float = 0, bottom: Float = 0
    phpnitro_render_hit_rect(hit, &left, &top, &right, &bottom)
    return HitResult(action: action, metaJSON: metaJSON, left: left, top: top, right: right, bottom: bottom)
}
