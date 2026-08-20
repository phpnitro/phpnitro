import AppKit
import AVFoundation
import Foundation
import PhpNitroProtocol
import RustMacRenderer

/// The Rust-driven counterpart of `PhpNitroMacEngine`'s own `MacCanvasView`
/// (Core Graphics) — deliberately a SEPARATE, self-contained view rather
/// than adding a Rust toggle to `MacCanvasView` itself: this view works
/// directly off the raw envelope JSON string (both `RustRenderer.renderFrame`
/// and `rustHitTest` take that JSON directly, not a decoded Swift model),
/// so it never needs `PhpNitroProtocol`'s `DrawCommandPayload` for
/// RENDERING — it's only decoded here (read-only, never re-serialized)
/// to find slider/scroll region geometry for gesture hit-testing. This
/// keeps every already-working file in `PhpNitroMacEngine` completely
/// untouched — zero regression risk to the existing, proven Core Graphics
/// path — while still proving `RustMacRenderer` genuinely drives real,
/// on-screen pixels end to end, which is the whole point of this app.
public final class RustScreenView: NSView {
    private let renderer: RustRenderer
    private var rawJSON: String?

    /// `(action, metaJSON, rect)` — `metaJSON` is the tapped hit region's
    /// own meta (or, for a slider commit, a synthesized
    /// `{"next":"..."}`), fed straight into `ScreenNavigation.reduce(...)`'s
    /// own `metaJson` parameter by `RustScreenController`. `rect` is
    /// always the tapped region's own rect — unused for most actions, but
    /// needed by the controller to position a `showTextInput` overlay for
    /// a `focus:` action, mirroring `NativeRenderPocActivity.kt`'s own
    /// `onAction?.invoke(action, region.rect, meta)`, which always passes
    /// the rect too, not just for `focus:` specifically.
    public var onAction: ((_ action: String, _ metaJSON: String?, _ rect: NSRect) -> Void)?

    /// `(fieldName, value)` — fires on every keystroke in the active
    /// text-input overlay (see `showTextInput`'s own doc comment).
    public var onFieldValueChanged: ((String, String) -> Void)?

    /// Fires on every real frame-size change — a live window resize
    /// included. `RustScreenController` decides whether that's actually
    /// a NEW size worth refetching for (see its own `handleResize`).
    public var onResize: ((CGFloat, CGFloat) -> Void)?

    // Interaction state this view owns entirely — mirrors
    // NativeCanvasView.kt's own clientTabState/hScrollOffsets/
    // vScrollOffsets/sliderValues. Only ever touched from outside via
    // setClientTab(_:index:) below (called from RustScreenController's
    // own .clientTabOnly handling) — same division of responsibility
    // Android has between NativeCanvasView and NativeRenderPocActivity.
    private var activePanel: [String: Int] = [:]
    private var axisOffset: [String: Float] = [:]
    private var sliderValue: [String: Float] = [:]

    // Drag-gesture bookkeeping — mirrors NativeCanvasView.kt's own
    // touchDownX/Y, lastTouchX/Y, pendingHScroll/VScroll, activeHScroll/
    // VScroll, touchSlop, simplified to just the two gesture types this
    // port implements (no dismiss/reorder/sheet-drag/pull-to-refresh).
    private static let touchSlop: CGFloat = 4.0
    private var mouseDownPoint: NSPoint = .zero
    private var lastDragPoint: NSPoint = .zero
    private var pendingScroll: ScrollTarget?
    private var activeScroll: ScrollTarget?
    private var activeSlider: SliderDrag?

    private struct ScrollTarget {
        let key: String
        let isHorizontal: Bool
        let rect: NSRect
        let contentExtent: CGFloat
    }

    private struct SliderDrag {
        let key: String
        let action: String
        let rect: NSRect
        let thumbSize: CGFloat
    }

    // TextField.php/PasswordField.php's "focus:" commit destination —
    // one real NSTextField/NSSecureTextField at a time, mirroring
    // NativeRenderPocActivity.kt's own single-nullable-field
    // activeEditText (never a map — a second focus: tap always replaces
    // the first, see showTextInput's own doc comment).
    private var activeTextField: NSTextField?
    private var activeFieldName: String?

    // VideoPlayer.php's "video:play:<url>" commit destination — one real
    // AVPlayer/AVPlayerLayer at a time, mirroring
    // NativeRenderPocActivity.kt's own single-nullable-field
    // activeVideoView. No transport bar (unlike Android's system-
    // provided MediaController) — autoplay only.
    private var activeVideoPlayer: AVPlayer?
    private var activeVideoContainer: NSView?

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
        // A new payload just replaced whatever the current overlay (if
        // any) was positioned/typed against — NativeRenderPocActivity.kt
        // only tears its own overlay down on navigate:/tab:/back/submit:,
        // leaving it alone across other same-screen refetches (toggle:,
        // etc); this port simplifies to "any new payload ends the
        // current editing session", safer than trying to reposition a
        // stale overlay against content it was never laid out for.
        clearTextInput()
        clearVideoOverlay()
        needsDisplay = true
    }

    /// `focus:[multiline:][secure:]name` — ports
    /// `NativeRenderPocActivity.kt`'s `showTextInput()`: one real
    /// `NSTextField`/`NSSecureTextField` positioned over the static
    /// rect+text `TextField.php` already painted underneath (which stays
    /// in the command list, just visually covered while focused), styled
    /// by hand from `Tokens.php`'s own constants since none of this is
    /// sent over the wire.
    public func showTextInput(fieldName: String, initialValue: String, rect: NSRect, multiline: Bool, secure: Bool) {
        clearTextInput()

        let textField: NSTextField = secure ? NSSecureTextField(frame: rect) : NSTextField(frame: rect)
        textField.stringValue = initialValue
        textField.isBordered = true
        textField.bezelStyle = .squareBezel
        textField.backgroundColor = .white
        textField.textColor = NSColor(srgbRed: 0x11 / 255, green: 0x18 / 255, blue: 0x27 / 255, alpha: 1)
        textField.font = .systemFont(ofSize: 15)
        textField.usesSingleLineMode = !multiline
        textField.cell?.wraps = multiline
        textField.cell?.isScrollable = !multiline
        textField.delegate = self

        addSubview(textField)
        window?.makeFirstResponder(textField)
        activeTextField = textField
        activeFieldName = fieldName
    }

    private func clearTextInput() {
        guard let activeTextField else { return }
        activeTextField.removeFromSuperview()
        self.activeTextField = nil
        activeFieldName = nil
    }

    /// `video:play:<url>` (VideoPlayer.php) — ports
    /// `NativeRenderPocActivity.kt`'s `showVideoOverlay()`: a real
    /// `AVPlayerLayer` positioned over the static "play" box already
    /// painted underneath, autoplaying immediately.
    public func showVideoOverlay(url: String, rect: NSRect) {
        clearVideoOverlay()

        guard let videoURL = URL(string: url) else { return }
        let player = AVPlayer(url: videoURL)
        let playerLayer = AVPlayerLayer(player: player)
        playerLayer.videoGravity = .resizeAspect

        let container = NSView(frame: rect)
        container.wantsLayer = true
        container.layer = playerLayer

        addSubview(container)
        player.play()
        activeVideoPlayer = player
        activeVideoContainer = container
    }

    private func clearVideoOverlay() {
        activeVideoPlayer?.pause()
        activeVideoContainer?.removeFromSuperview()
        activeVideoPlayer = nil
        activeVideoContainer = nil
    }

    /// Fires on every real size change to this view's own frame —
    /// programmatic ones too, not just a live window-border drag, but
    /// `RustScreenController` only ever acts on a genuinely NEW size.
    public override func setFrameSize(_ newSize: NSSize) {
        super.setFrameSize(newSize)
        onResize?(newSize.width, newSize.height)
    }

    /// Called by `RustScreenController` in response to a real
    /// `clientTab:key:index` action — the one piece of interaction state
    /// this view doesn't discover through its own mouse events.
    public func setClientTab(_ key: String, index: Int) {
        activePanel[key] = index
        needsDisplay = true
    }

    /// `{"activePanel":{...},"axisOffset":{...},"sliderValue":{...}}` —
    /// the same shape `rust/phpnitro-render/src/hittest.rs`'s
    /// `InteractionState` decodes. Built via `JSONSerialization` rather
    /// than a `Codable` wrapper type — a passthrough dictionary doesn't
    /// need one.
    private func interactionStateJSON() -> String? {
        guard let data = try? JSONSerialization.data(withJSONObject: [
            "activePanel": activePanel, "axisOffset": axisOffset, "sliderValue": sliderValue,
        ]) else { return nil }
        return String(data: data, encoding: .utf8)
    }

    public override func draw(_ dirtyRect: NSRect) {
        guard let context = NSGraphicsContext.current?.cgContext else { return }
        context.setFillColor(NSColor.white.cgColor)
        context.fill(bounds)

        guard let rawJSON else { return }
        guard let frame = renderer.renderFrame(envelopeJSON: rawJSON, widthPx: UInt32(bounds.width), heightPx: UInt32(bounds.height), interactionStateJSON: interactionStateJSON()) else {
            return
        }
        guard let image = Self.cgImage(from: frame) else { return }
        context.draw(image, in: bounds)
    }

    public override func mouseDown(with event: NSEvent) {
        pendingScroll = nil
        activeScroll = nil
        activeSlider = nil

        guard let rawJSON else { return }
        let point = convert(event.locationInWindow, from: nil)
        mouseDownPoint = point
        lastDragPoint = point

        // Read-only geometry lookup against the freshly-decoded command
        // tree — never re-serialized back to Rust, just used to find
        // which slider/scroll region this point falls within, exactly
        // like NativeCanvasView.kt's own hitTestSlider()/hitTestHScroll()/
        // hitTestVScroll() do against its own locally-parsed regions.
        guard let payload = try? JSONDecoder().decode(DrawCommandPayload.self, from: Data(rawJSON.utf8)) else { return }

        // Slider commits immediately on down, not after a decisive-move
        // threshold like hScroll/vScroll — a slider's whole touch box IS
        // the gesture, there's no "was this actually meant as a page
        // scroll" ambiguity to resolve (see NativeCanvasView.kt's own
        // comment on this exact point).
        for region in payload.sliderRegions {
            let rect = NSRect(x: CGFloat(region.x), y: CGFloat(region.y), width: CGFloat(region.width), height: CGFloat(region.height))
            if rect.contains(point) {
                activeSlider = SliderDrag(key: region.key, action: region.action, rect: rect, thumbSize: CGFloat(region.thumbSize))
                sliderValue[region.key] = Self.sliderValueForTouch(rect: rect, thumbSize: CGFloat(region.thumbSize), touchX: point.x)
                needsDisplay = true
                return
            }
        }

        // Only TOP-LEVEL hScroll/vScroll commands are considered — same
        // limitation NativeCanvasView.kt's own region-parsing has (a flat
        // scan over `commands`, no recursion into a nested clientPanel),
        // not a gap introduced here.
        for command in payload.commands {
            switch command {
            case .hScroll(let hScroll):
                let rect = NSRect(x: CGFloat(hScroll.x), y: CGFloat(hScroll.y), width: CGFloat(hScroll.width), height: CGFloat(hScroll.height))
                if rect.contains(point) {
                    pendingScroll = ScrollTarget(key: hScroll.key, isHorizontal: true, rect: rect, contentExtent: CGFloat(hScroll.contentWidth))
                    return
                }
            case .vScroll(let vScroll):
                let rect = NSRect(x: CGFloat(vScroll.x), y: CGFloat(vScroll.y), width: CGFloat(vScroll.width), height: CGFloat(vScroll.height))
                if rect.contains(point) {
                    pendingScroll = ScrollTarget(key: vScroll.key, isHorizontal: false, rect: rect, contentExtent: CGFloat(vScroll.contentHeight))
                    return
                }
            default:
                continue
            }
        }
    }

    public override func mouseDragged(with event: NSEvent) {
        guard rawJSON != nil else { return }
        let point = convert(event.locationInWindow, from: nil)

        if let slider = activeSlider {
            sliderValue[slider.key] = Self.sliderValueForTouch(rect: slider.rect, thumbSize: slider.thumbSize, touchX: point.x)
            needsDisplay = true
            return
        }

        let totalDeltaX = point.x - mouseDownPoint.x
        let totalDeltaY = point.y - mouseDownPoint.y

        if let pending = pendingScroll, activeScroll == nil {
            if pending.isHorizontal && abs(totalDeltaX) > Self.touchSlop && abs(totalDeltaX) > abs(totalDeltaY) {
                activeScroll = pending
                pendingScroll = nil
            } else if !pending.isHorizontal && abs(totalDeltaY) > Self.touchSlop {
                activeScroll = pending
                pendingScroll = nil
            } else if pending.isHorizontal && abs(totalDeltaY) > Self.touchSlop && abs(totalDeltaY) > abs(totalDeltaX) {
                // Decisive move was vertical over a pending HORIZONTAL
                // target — this was never an hScroll gesture.
                pendingScroll = nil
            }
        }

        if let scroll = activeScroll {
            let viewportExtent = scroll.isHorizontal ? scroll.rect.width : scroll.rect.height
            let maxOffset = max(scroll.contentExtent - viewportExtent, 0)
            let current = axisOffset[scroll.key] ?? 0
            let delta = scroll.isHorizontal ? Float(lastDragPoint.x - point.x) : Float(lastDragPoint.y - point.y)
            axisOffset[scroll.key] = min(max(current + delta, 0), Float(maxOffset))
            lastDragPoint = point
            needsDisplay = true
        }
    }

    public override func mouseUp(with event: NSEvent) {
        guard let rawJSON else { return }
        let point = convert(event.locationInWindow, from: nil)

        if let slider = activeSlider {
            activeSlider = nil
            let value = sliderValue[slider.key] ?? 0
            // en_US_POSIX, not the current locale — a French/Belgian/
            // etc. locale's decimal COMMA sent as a literal query-string
            // value would have PHP's (float) cast stop parsing at the
            // first non-digit character, silently truncating every
            // dragged value to its integer part (same bug
            // NativeCanvasView.kt's own Locale.US comment warns about).
            let formatted = String(format: "%.3f", locale: Locale(identifier: "en_US_POSIX"), value)
            onAction?(slider.action, "{\"next\":\"\(formatted)\"}", slider.rect)
            return
        }

        if activeScroll != nil {
            // A real scroll drag happened this gesture — no tap fires,
            // matching NativeCanvasView.kt's own ACTION_UP handling
            // (activeHScroll/activeVScroll just get cleared, nothing else).
            activeScroll = nil
            pendingScroll = nil
            return
        }
        pendingScroll = nil

        // Never became a drag — a plain tap, hit-tested at the RELEASE
        // position (mirrors NativeCanvasView.kt's own handleTap(event),
        // called with the ACTION_UP event's coordinates).
        guard let hit = rustHitTest(envelopeJSON: rawJSON, tapX: Float(point.x), tapY: Float(point.y), interactionStateJSON: interactionStateJSON()), !hit.action.isEmpty else {
            return
        }
        let hitRect = NSRect(x: CGFloat(hit.left), y: CGFloat(hit.top), width: CGFloat(hit.right - hit.left), height: CGFloat(hit.bottom - hit.top))
        onAction?(hit.action, hit.metaJSON, hitRect)
    }

    /// Inverse of `drawSliderCommand()`'s own `thumbCx` formula (see
    /// `rust/phpnitro-render/src/raster.rs`'s `draw_slider`) — mirrors
    /// `NativeCanvasView.kt`'s own `sliderValueForTouch()` exactly.
    private static func sliderValueForTouch(rect: NSRect, thumbSize: CGFloat, touchX: CGFloat) -> Float {
        let trackWidth = max(rect.width - thumbSize, 1)
        let value = (touchX - rect.minX - thumbSize / 2) / trackWidth
        return Float(min(max(value, 0), 1))
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

extension RustScreenView: NSTextFieldDelegate {
    /// Every keystroke, not just on blur/submit — mirrors
    /// `NativeRenderPocActivity.kt`'s `TextWatcher.afterTextChanged()`
    /// exactly; every platform here already sends `fieldValues` on EVERY
    /// fetch regardless of what triggered it (unlike Android's own
    /// selective `includeFields` flag), so there's no separate "commit"
    /// step to wire beyond keeping the controller's dictionary current.
    public func controlTextDidChange(_ obj: Notification) {
        guard let textField = obj.object as? NSTextField, let name = activeFieldName else { return }
        onFieldValueChanged?(name, textField.stringValue)
    }
}
