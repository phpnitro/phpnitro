import AppKit
import RustMacRenderer

/// The Rust-driven counterpart of `PhpNitroMacEngine`'s own `MacCanvasView`
/// (Core Graphics) — deliberately a SEPARATE, self-contained view rather
/// than adding a Rust toggle to `MacCanvasView` itself: this view works
/// directly off the raw envelope JSON string (both `RustRenderer.renderFrame`
/// and `rustHitTest` take that JSON directly, not a decoded Swift model),
/// so it never needs `PhpNitroProtocol`'s `DrawCommandPayload` at all. This
/// keeps every already-working file in `PhpNitroMacEngine` completely
/// untouched — zero regression risk to the existing, proven Core Graphics
/// path — while still proving `RustMacRenderer` genuinely drives real,
/// on-screen pixels end to end, which is the whole point of this app.
public final class RustScreenView: NSView {
    private let renderer: RustRenderer
    private var rawJSON: String?

    public var onAction: ((String) -> Void)?

    public override var isFlipped: Bool { true }

    public init(frame frameRect: NSRect, renderer: RustRenderer) {
        self.renderer = renderer
        super.init(frame: frameRect)
    }

    public required init?(coder: NSCoder) {
        fatalError("RustScreenView is only ever constructed programmatically")
    }

    public func setEnvelope(rawJSON: String) {
        self.rawJSON = rawJSON
        needsDisplay = true
    }

    public override func draw(_ dirtyRect: NSRect) {
        guard let context = NSGraphicsContext.current?.cgContext else { return }
        context.setFillColor(NSColor.white.cgColor)
        context.fill(bounds)

        guard let rawJSON else { return }
        guard let frame = renderer.renderFrame(envelopeJSON: rawJSON, widthPx: UInt32(bounds.width), heightPx: UInt32(bounds.height)) else {
            return
        }
        guard let image = Self.cgImage(from: frame) else { return }
        context.draw(image, in: bounds)
    }

    public override func mouseDown(with event: NSEvent) {
        guard let rawJSON else { return }
        let point = convert(event.locationInWindow, from: nil)
        guard let hit = rustHitTest(envelopeJSON: rawJSON, tapX: Float(point.x), tapY: Float(point.y)), !hit.action.isEmpty else {
            return
        }
        onAction?(hit.action)
    }

    /// tiny-skia's `RenderedFrame.data` is RGBA8, premultiplied alpha —
    /// unlike GDI+ on Windows (which needs a channel swap to BGRA), Core
    /// Graphics natively supports RGBA-premultiplied-last via
    /// `CGImageAlphaInfo.premultipliedLast`, so this is a direct wrap, no
    /// byte reordering needed.
    private static func cgImage(from frame: RenderedFrame) -> CGImage? {
        guard let provider = CGDataProvider(data: Data(frame.data) as CFData) else { return nil }
        return CGImage(
            width: Int(frame.width),
            height: Int(frame.height),
            bitsPerComponent: 8,
            bitsPerPixel: 32,
            bytesPerRow: Int(frame.stride),
            space: CGColorSpaceCreateDeviceRGB(),
            bitmapInfo: CGBitmapInfo(rawValue: CGImageAlphaInfo.premultipliedLast.rawValue),
            provider: provider,
            decode: nil,
            shouldInterpolate: false,
            intent: .defaultIntent
        )
    }
}
