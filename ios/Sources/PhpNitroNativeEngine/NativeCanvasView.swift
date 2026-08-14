import QuartzCore
import UIKit

/// The iOS counterpart of NativeCanvasView.kt — replays a decoded
/// DrawCommandPayload with Core Graphics inside `draw(rect:)`, the same
/// "PHP computes one frame, the client just replays flat draw commands"
/// contract the whole native-render-engine proposal is built on
/// (docs/proposals/moteur-rendu-natif.md), just against UIKit/Core
/// Graphics instead of android.graphics.Canvas.
///
/// Deliberately NOT a 1:1 port of NativeCanvasView.kt's full feature set
/// — no touch/hit-region dispatch, no scroll handling, no hero
/// transitions, no overlay views (VideoPlayer/MapView/Lottie's own "no
/// Canvas concept for this, overlay a real View" idiom). This proves the
/// wire protocol renders correctly on iOS at all — everything
/// interactive on top of that is real, separate follow-up work, not
/// something to fake here.
///
/// setPayload(_:) triggers `setNeedsDisplay()`; nothing here fetches
/// draw commands from a server itself — same separation of concerns
/// NativeCanvasView.kt has from NativeRenderPocActivity's own
/// fetchDrawCommands(), just not yet built on this side (there is no
/// iOS equivalent of that fetch loop, or of PhpServer.kt's embedded PHP
/// process — see ios/README.md for what that would take).
public final class NativeCanvasView: UIView {
    private var payload: DrawCommandPayload?

    /// Fired from handleTap(_:) when a tap lands inside one of the
    /// current payload's hitRegions — the caller (whatever eventually
    /// plays the role of NativeRenderPocActivity's own tap dispatch)
    /// wires this to actually act on the hitRegion's `action` string. No
    /// dispatch logic lives here — same separation `action(at:)` on
    /// DrawCommandPayload already keeps (geometry only, no side effects).
    public var onAction: ((String) -> Void)?

    /// Drives drawSpinnerCommand()/drawSkeletonCommand()'s own continuous
    /// redraw on Android (a ValueAnimator started/stopped based on
    /// whether the current payload has one of those commands) —
    /// CADisplayLink is the direct iOS counterpart, same
    /// started-only-when-needed lifecycle so an otherwise-static screen
    /// doesn't redraw 60x/sec for nothing.
    private var displayLink: CADisplayLink?

    /// `key -> selected panel index`, seeded once from whichever panel
    /// has `initiallyActive == true` and never overwritten by a later
    /// render for the same key — mirrors NativeCanvasView.kt's own
    /// `clientTabState`. No tap-to-switch-tab wiring yet (see
    /// ClientPanelCommand's own docblock), so this only ever reflects
    /// whatever PHP marked active on the most recent render that
    /// introduced this key.
    private var clientTabState: [String: Int] = [:]

    /// Reserved for future client-side drag support (see HScrollCommand's
    /// own docblock) — always 0 for now, so every hScroll command renders
    /// at its server-authored, undragged position.
    private let hScrollOffsets: [String: CGFloat] = [:]
    private let vScrollOffsets: [String: CGFloat] = [:]

    override public init(frame: CGRect) {
        super.init(frame: frame)
        backgroundColor = .white
        isOpaque = true
        configureTapRecognizer()
    }

    public required init?(coder: NSCoder) {
        super.init(coder: coder)
        backgroundColor = .white
        isOpaque = true
        configureTapRecognizer()
    }

    deinit {
        displayLink?.invalidate()
    }

    /// A ClientTabs tab switch — entirely local, no fetch (see
    /// ScreenNavigationResult.clientTabOnly), same role
    /// canvasView.setClientTab(key, index) plays in
    /// NativeRenderPocActivity.kt's own `clientTab:` dispatch branch.
    public func setClientTab(_ key: String, index: Int) {
        clientTabState[key] = index
        setNeedsDisplay()
    }

    public func setPayload(_ payload: DrawCommandPayload) {
        self.payload = payload
        updateAnimationState()
        setNeedsDisplay()
    }

    override public func draw(_ rect: CGRect) {
        guard let context = UIGraphicsGetCurrentContext(), let payload else { return }

        for command in payload.commands {
            drawCommand(command, in: context)
        }
    }

    /// Single dispatch point for one DrawCommand — pulled out of
    /// `draw(rect:)` so drawClientPanel/drawHScroll/drawVScroll below can
    /// recurse into their own nested `commands` array through the exact
    /// same switch, same idea as NativeCanvasView.kt's own
    /// drawSingleCommand() helper.
    private func drawCommand(_ command: DrawCommand, in context: CGContext) {
        switch command {
        case .rect(let rect): draw(rect, in: context)
        case .text(let text): draw(text, in: context)
        case .icon(let icon): draw(icon, in: context)
        case .circle(let circle): draw(circle, in: context)
        case .line(let line): draw(line, in: context)
        case .arc(let arc): draw(arc, in: context)
        case .image(let image): draw(image, in: context)
        case .spinner(let spinner): draw(spinner, in: context)
        case .skeleton(let skeleton): draw(skeleton, in: context)
        case .clientPanel(let panel): draw(panel, in: context)
        case .hScroll(let scroll): draw(scroll, in: context)
        case .vScroll(let scroll): draw(scroll, in: context)
        case .slider(let slider): draw(slider, in: context)
        case .unknown: break // Same "an unhandled command is a no-op, not a crash" contract DrawCommand.init(from:) already documents.
        }
    }

    // MARK: - Tap dispatch

    private func configureTapRecognizer() {
        addGestureRecognizer(UITapGestureRecognizer(target: self, action: #selector(handleTap(_:))))
    }

    @objc private func handleTap(_ recognizer: UITapGestureRecognizer) {
        guard let payload, let action = payload.action(at: recognizer.location(in: self)) else { return }
        onAction?(action)
    }

    // MARK: - Animation loop (spinner/skeleton only)

    private func updateAnimationState() {
        let needsAnimation = payload?.commands.contains { command in
            switch command {
            case .spinner, .skeleton: return true
            default: return false
            }
        } ?? false

        if needsAnimation, displayLink == nil {
            let link = CADisplayLink(target: self, selector: #selector(animationTick))
            link.add(to: .main, forMode: .common)
            displayLink = link
        } else if !needsAnimation, let link = displayLink {
            link.invalidate()
            displayLink = nil
        }
    }

    @objc private func animationTick() {
        setNeedsDisplay()
    }

    private func draw(_ command: RectCommand, in context: CGContext) {
        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)
        let radius = command.radius ?? 0

        let path = radius > 0
            ? UIBezierPath(roundedRect: rect, cornerRadius: radius).cgPath
            : UIBezierPath(rect: rect).cgPath

        context.saveGState()
        context.addPath(path)

        if let color = command.color, let uiColor = UIColor(hex: color) {
            context.setFillColor(uiColor.cgColor)
            context.fillPath()
            context.addPath(path)
        }

        if let borderColor = command.borderColor, let uiColor = UIColor(hex: borderColor), (command.borderWidth ?? 0) > 0 {
            context.setStrokeColor(uiColor.cgColor)
            context.setLineWidth(command.borderWidth ?? 1)
            context.strokePath()
        }

        context.restoreGState()
    }

    private func draw(_ command: TextCommand, in context: CGContext) {
        let color = command.color.flatMap(UIColor.init(hex:)) ?? .black
        let size = command.size ?? 16
        let font = (command.bold ?? false) ? UIFont.boldSystemFont(ofSize: size) : UIFont.systemFont(ofSize: size)

        let attributes: [NSAttributedString.Key: Any] = [.font: font, .foregroundColor: color]
        let attributed = NSAttributedString(string: command.text, attributes: attributes)

        // Canvas::text()'s (x, y) is the drawText BASELINE, same
        // convention android.graphics.Canvas.drawText() uses — UIKit's
        // NSAttributedString.draw(at:) instead anchors at the top-left
        // of the glyph box, so the y needs shifting up by roughly the
        // font's ascent to land on the same visual baseline PHP's own
        // TextMetrics.php assumed when it computed this y in the first
        // place.
        let origin = CGPoint(x: command.x, y: command.y - font.ascender)
        attributed.draw(at: origin)
    }

    private func draw(_ command: IconCommand, in context: CGContext) {
        guard let font = IconFont.font(forKey: command.font, size: CGFloat(command.size)) else { return }
        guard let scalar = Unicode.Scalar(command.codepoint) else { return }

        let color = command.color.flatMap(UIColor.init(hex:)) ?? (UIColor(hex: "#111827") ?? .black)
        let attributed = NSAttributedString(string: String(Character(scalar)), attributes: [.font: font, .foregroundColor: color])

        // NativeCanvasView.kt's drawIconCommand() treats (x, y) as the
        // top-left of a size×size box and centers the glyph inside it
        // (textAlign = CENTER, a baseline offset tuned to 86% of size
        // for Android's own font metrics) — rather than replicate that
        // Android-specific magic number against a different font
        // renderer (Core Text), this measures the glyph's REAL size and
        // centers it directly, landing on the same visual result
        // (centered in the box) without assuming Android's metrics.
        let measured = attributed.size()
        let origin = CGPoint(
            x: command.x + (CGFloat(command.size) - measured.width) / 2,
            y: command.y + (CGFloat(command.size) - measured.height) / 2
        )
        attributed.draw(at: origin)
    }

    private func draw(_ command: CircleCommand, in context: CGContext) {
        let rect = CGRect(
            x: command.cx - command.radius,
            y: command.cy - command.radius,
            width: command.radius * 2,
            height: command.radius * 2
        )
        let path = UIBezierPath(ovalIn: rect).cgPath

        context.saveGState()
        context.addPath(path)

        if let color = command.color, let uiColor = UIColor(hex: color) {
            context.setFillColor(uiColor.cgColor)
            context.fillPath()
            context.addPath(path)
        }

        if let borderColor = command.borderColor, let uiColor = UIColor(hex: borderColor), (command.borderWidth ?? 0) > 0 {
            context.setStrokeColor(uiColor.cgColor)
            context.setLineWidth(command.borderWidth ?? 1)
            context.strokePath()
        }

        context.restoreGState()
    }

    private func draw(_ command: LineCommand, in context: CGContext) {
        guard let color = UIColor(hex: command.color) else { return }

        context.saveGState()
        context.setStrokeColor(color.cgColor)
        context.setLineWidth(command.width ?? 1)
        context.move(to: CGPoint(x: command.x1, y: command.y1))
        context.addLine(to: CGPoint(x: command.x2, y: command.y2))
        context.strokePath()
        context.restoreGState()
    }

    private func draw(_ command: ArcCommand, in context: CGContext) {
        guard let color = UIColor(hex: command.color) else { return }

        // Canvas::arc()'s convention (documented on the PHP side) is
        // Android's: 0deg = 3 o'clock, sweeping CLOCKWISE. Core
        // Graphics' addArc(clockwise:) parameter is the OPPOSITE sense
        // (true = counter-clockwise in its own flipped-Y default
        // coordinate space) — negating the angles is the standard fix
        // for replaying an Android-authored arc on Core Graphics
        // without silently mirroring it.
        let startRadians = -command.startDegrees * .pi / 180
        let endRadians = -(command.startDegrees + command.sweepDegrees) * .pi / 180

        context.saveGState()
        context.setStrokeColor(color.cgColor)
        context.setLineWidth(command.strokeWidth)
        context.addArc(
            center: CGPoint(x: command.cx, y: command.cy),
            radius: command.radius,
            startAngle: startRadians,
            endAngle: endRadians,
            clockwise: true
        )
        context.strokePath()
        context.restoreGState()
    }

    // ImageLoader owns the actual network fetch + decode + cache; this
    // just asks for whatever's cached and draws it if present, or kicks
    // off a load and redraws once ImageLoader has it — same two-path
    // shape as drawImageCommand() on the Android side. Unlike that
    // Kotlin original (which aspect-fills via a BitmapShader for the
    // rounded-corner path, but stretches for the plain path — an
    // Android-side inconsistency, not something to replicate on faith),
    // this always stretches the image to fill `rect`; a real aspect-fill
    // mode is real, separate follow-up work, not attempted here.
    private func draw(_ command: ImageCommand, in context: CGContext) {
        guard let image = ImageLoader.get(command.url) else {
            ImageLoader.load(command.url) { [weak self] in self?.setNeedsDisplay() }
            return
        }

        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)
        let radius = CGFloat(command.radius ?? 0)

        context.saveGState()
        if radius > 0 {
            context.addPath(UIBezierPath(roundedRect: rect, cornerRadius: radius).cgPath)
            context.clip()
        }
        image.draw(in: rect)
        context.restoreGState()
    }

    // No rotation angle travels with this command at all (see
    // SpinnerCommand's own docblock) — computed fresh from
    // CACurrentMediaTime() every frame, driven by the animation loop
    // updateAnimationState() starts whenever a "spinner" command is
    // present, same idea as drawSpinnerCommand()'s own
    // SystemClock.uptimeMillis() on Android. The exact rotation
    // direction hasn't been checked against the Android original on a
    // real device/simulator (no Mac available) — it spins, which is the
    // part that matters for a loading indicator; matching Android's
    // handedness exactly is a cosmetic follow-up, not a correctness bug.
    private func draw(_ command: SpinnerCommand, in context: CGContext) {
        guard let trackColor = UIColor(hex: command.trackColor), let color = UIColor(hex: command.color) else { return }

        let center = CGFloat(command.size) / 2
        let strokeWidth = CGFloat(command.strokeWidth)
        let radius = center - strokeWidth / 2
        let cx = CGFloat(command.x) + center
        let cy = CGFloat(command.y) + center

        context.saveGState()
        context.setLineWidth(strokeWidth)

        context.setStrokeColor(trackColor.cgColor)
        context.addArc(center: CGPoint(x: cx, y: cy), radius: radius, startAngle: 0, endAngle: .pi * 2, clockwise: false)
        context.strokePath()

        let periodMs = 1100.0
        let elapsedMs = CACurrentMediaTime() * 1000
        let rotationRadians = (elapsedMs.truncatingRemainder(dividingBy: periodMs)) / periodMs * (.pi * 2)
        let sweepRadians: Double = 110 * .pi / 180

        context.setStrokeColor(color.cgColor)
        context.setLineCap(.round)
        context.addArc(
            center: CGPoint(x: cx, y: cy),
            radius: radius,
            startAngle: rotationRadians,
            endAngle: rotationRadians + sweepRadians,
            clockwise: false
        )
        context.strokePath()
        context.restoreGState()
    }

    // Base fill + a translucent band sweeping left-to-right on a loop,
    // same idea as drawSkeletonCommand() on Android (a shimmer blended
    // toward white rather than a flat white, so it still reads right in
    // dark mode). Driven by CACurrentMediaTime() exactly like the
    // spinner's own rotation above, through the same animation loop.
    private func draw(_ command: SkeletonCommand, in context: CGContext) {
        guard let baseColor = UIColor(hex: command.color) else { return }

        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)
        let radius = CGFloat(command.radius)
        let path = UIBezierPath(roundedRect: rect, cornerRadius: radius).cgPath

        context.saveGState()
        context.addPath(path)
        context.setFillColor(baseColor.cgColor)
        context.fillPath()

        var r: CGFloat = 0, g: CGFloat = 0, b: CGFloat = 0, a: CGFloat = 0
        baseColor.getRed(&r, green: &g, blue: &b, alpha: &a)
        let highlight = UIColor(red: r + (1 - r) * 0.5, green: g + (1 - g) * 0.5, blue: b + (1 - b) * 0.5, alpha: a)

        let sweepWidth = max(rect.width * 0.6, 1)
        let periodMs = 1300.0
        let elapsedMs = CACurrentMediaTime() * 1000
        let phase = CGFloat((elapsedMs.truncatingRemainder(dividingBy: periodMs)) / periodMs)
        let sweepX = rect.minX - sweepWidth + (rect.width + sweepWidth) * phase

        context.addPath(path)
        context.clip()

        let colors = [UIColor.clear.cgColor, highlight.withAlphaComponent(0.8).cgColor, UIColor.clear.cgColor] as CFArray
        if let gradient = CGGradient(colorsSpace: CGColorSpaceCreateDeviceRGB(), colors: colors, locations: [0, 0.5, 1]) {
            context.drawLinearGradient(
                gradient,
                start: CGPoint(x: sweepX, y: rect.midY),
                end: CGPoint(x: sweepX + sweepWidth, y: rect.midY),
                options: []
            )
        }

        context.restoreGState()
    }

    // Only the panel matching this key's current local selection draws —
    // every other panel this same command list carries (one clientPanel
    // command per ClientTabs panel, all sharing the same key) is skipped
    // outright. No crossfade between tabs yet (see
    // drawClientPanelCommand()'s own clientTabCrossfade on Android) — a
    // tab switch here would jump-cut rather than fade, real, separate
    // follow-up work.
    private func draw(_ command: ClientPanelCommand, in context: CGContext) {
        if clientTabState[command.key] == nil, command.initiallyActive {
            clientTabState[command.key] = command.index
        }
        guard clientTabState[command.key] == command.index else { return }

        context.saveGState()
        context.translateBy(x: CGFloat(command.x), y: CGFloat(command.y))
        for nested in command.commands {
            drawCommand(nested, in: context)
        }
        context.restoreGState()
    }

    // Clips to the viewport rect so content past its edge doesn't paint
    // over neighboring content, then shifts by -offset along the local
    // drag axis — offset is always 0 for now (see hScrollOffsets' own
    // docblock), so this always renders the undragged start of the
    // content.
    private func draw(_ command: HScrollCommand, in context: CGContext) {
        let offset = hScrollOffsets[command.key] ?? 0
        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)

        context.saveGState()
        context.clip(to: rect)
        context.translateBy(x: CGFloat(command.x) - offset, y: CGFloat(command.y))
        for nested in command.commands {
            drawCommand(nested, in: context)
        }
        context.restoreGState()
    }

    // Vertical counterpart to the hScroll draw method right above — same
    // clip-then-translate shape, just along the other axis.
    private func draw(_ command: VScrollCommand, in context: CGContext) {
        let offset = vScrollOffsets[command.key] ?? 0
        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)

        context.saveGState()
        context.clip(to: rect)
        context.translateBy(x: CGFloat(command.x), y: CGFloat(command.y) - offset)
        for nested in command.commands {
            drawCommand(nested, in: context)
        }
        context.restoreGState()
    }

    // Thumb travel is [x + thumbSize/2, x + width - thumbSize/2] — the
    // thumb's CENTER, not its edge, tracks `value` linearly, mirroring
    // drawSliderCommand()'s own formula exactly so a future drag handler
    // can invert it the same way hitTestSlider() does on Android. Always
    // renders at the server-authored `value` — no local drag override
    // yet, see SliderCommand's own docblock.
    private func draw(_ command: SliderCommand, in context: CGContext) {
        guard let trackColor = UIColor(hex: command.trackColor),
              let activeColor = UIColor(hex: command.activeColor),
              let thumbColor = UIColor(hex: command.thumbColor) else { return }

        let x = CGFloat(command.x)
        let y = CGFloat(command.y)
        let width = CGFloat(command.width)
        let height = CGFloat(command.height)
        let trackHeight = CGFloat(command.trackHeight)
        let thumbSize = CGFloat(command.thumbSize)
        let value = min(max(CGFloat(command.value), 0), 1)

        let trackY = y + (height - trackHeight) / 2
        let thumbCx = x + thumbSize / 2 + (width - thumbSize) * value
        let thumbCy = y + height / 2

        context.saveGState()

        context.setFillColor(trackColor.cgColor)
        context.addPath(UIBezierPath(roundedRect: CGRect(x: x, y: trackY, width: width, height: trackHeight), cornerRadius: trackHeight / 2).cgPath)
        context.fillPath()

        context.setFillColor(activeColor.cgColor)
        let activeWidth = max(thumbCx - x, 0)
        context.addPath(UIBezierPath(roundedRect: CGRect(x: x, y: trackY, width: activeWidth, height: trackHeight), cornerRadius: trackHeight / 2).cgPath)
        context.fillPath()

        let thumbRect = CGRect(x: thumbCx - thumbSize / 2, y: thumbCy - thumbSize / 2, width: thumbSize, height: thumbSize)
        context.setFillColor(thumbColor.cgColor)
        context.addPath(UIBezierPath(ovalIn: thumbRect).cgPath)
        context.fillPath()

        context.setStrokeColor(activeColor.cgColor)
        context.setLineWidth(1.5)
        context.addPath(UIBezierPath(ovalIn: thumbRect).cgPath)
        context.strokePath()

        context.restoreGState()
    }
}

extension UIColor {
    /// Parses "#RRGGBB" or "#RRGGBBAA" — the exact two shapes every
    /// Engine\Color::toHex()/Tokens color constant on the PHP side
    /// produces. Returns nil (never crashes) on anything else, same
    /// "malformed input degrades gracefully" contract the rest of this
    /// renderer follows for an unrecognized command type.
    convenience init?(hex: String) {
        var value = hex
        if value.hasPrefix("#") { value.removeFirst() }
        guard value.count == 6 || value.count == 8, let intValue = UInt64(value, radix: 16) else { return nil }

        let hasAlpha = value.count == 8
        let r, g, b, a: UInt64
        if hasAlpha {
            r = (intValue >> 24) & 0xFF
            g = (intValue >> 16) & 0xFF
            b = (intValue >> 8) & 0xFF
            a = intValue & 0xFF
        } else {
            r = (intValue >> 16) & 0xFF
            g = (intValue >> 8) & 0xFF
            b = intValue & 0xFF
            a = 0xFF
        }

        self.init(
            red: CGFloat(r) / 255,
            green: CGFloat(g) / 255,
            blue: CGFloat(b) / 255,
            alpha: CGFloat(a) / 255
        )
    }
}
