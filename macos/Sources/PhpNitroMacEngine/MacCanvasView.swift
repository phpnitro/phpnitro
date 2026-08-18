import AppKit
import PhpNitroProtocol

/// The macOS counterpart of NativeCanvasView.swift (iOS) — replays a
/// decoded DrawCommandPayload with Core Graphics inside `draw(_:)`,
/// through AppKit (NSView/NSColor/NSFont) instead of UIKit. The actual
/// CGContext drawing calls (addPath/fillPath/strokePath/
/// drawLinearGradient/...) are verbatim identical to the iOS version —
/// Core Graphics is a plain C framework shared by both, not owned by
/// either UIKit or AppKit. Only the surrounding view/color/font/event
/// layer differs, which is exactly what this file's diffs against
/// NativeCanvasView.swift would show if diffed side by side.
///
/// One AppKit-specific correctness detail that has no iOS equivalent at
/// all: NSView's default coordinate space has its origin at the
/// BOTTOM-left with Y increasing upward (a real, historical AppKit
/// default) — every coordinate this whole wire protocol carries assumes
/// top-left-origin, Y-down (the same convention Android's Canvas and
/// iOS's Core Graphics/UIKit already use). `isFlipped` overridden to
/// `true` below is what makes this view's own coordinate space match
/// that convention instead of silently rendering everything upside down.
public final class MacCanvasView: NSView {
    private var payload: DrawCommandPayload?

    public var onAction: ((String) -> Void)?

    /// `key -> selected panel index`, seeded once from whichever panel
    /// has `initiallyActive == true` — mirrors NativeCanvasView.swift's
    /// own clientTabState exactly.
    private var clientTabState: [String: Int] = [:]

    /// A plain Foundation Timer, not CADisplayLink — CADisplayLink only
    /// gained macOS support in macOS 14 (Sonoma); this package's own
    /// minimum deployment target is macOS 13 (Ventura, see Package.swift),
    /// so a portable ~60fps Timer is the safe choice here rather than an
    /// API that would fail to link on the minimum supported OS version.
    private var animationTimer: Timer?

    public override var isFlipped: Bool { true }

    public override init(frame frameRect: NSRect) {
        super.init(frame: frameRect)
    }

    public required init?(coder: NSCoder) {
        super.init(coder: coder)
    }

    deinit {
        animationTimer?.invalidate()
    }

    public func setClientTab(_ key: String, index: Int) {
        clientTabState[key] = index
        needsDisplay = true
    }

    public func setPayload(_ payload: DrawCommandPayload) {
        self.payload = payload
        updateAnimationState()
        needsDisplay = true
    }

    public override func draw(_ dirtyRect: NSRect) {
        guard let context = NSGraphicsContext.current?.cgContext else { return }

        // NSView has no UIView-style `backgroundColor` property to lean
        // on — filling white here once, before replaying commands, is
        // the AppKit equivalent of NativeCanvasView.swift's own
        // `backgroundColor = .white` + `isOpaque = true` at init.
        context.setFillColor(NSColor.white.cgColor)
        context.fill(bounds)

        guard let payload else { return }
        for command in payload.commands {
            drawCommand(command, in: context)
        }
    }

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
        case .unknown: break // Same "an unhandled command is a no-op, not a crash" contract every platform follows.
        }
    }

    // MARK: - Mouse dispatch

    public override func mouseDown(with event: NSEvent) {
        guard let payload else { return }
        let point = convert(event.locationInWindow, from: nil)
        if let action = payload.action(at: point) {
            onAction?(action)
        }
    }

    // MARK: - Animation loop (spinner/skeleton only)

    private func updateAnimationState() {
        let needsAnimation = payload?.commands.contains { needsAnimation(for: $0) } ?? false

        if needsAnimation, animationTimer == nil {
            let timer = Timer(timeInterval: 1.0 / 60.0, repeats: true) { [weak self] _ in
                self?.needsDisplay = true
            }
            RunLoop.current.add(timer, forMode: .common)
            animationTimer = timer
        } else if !needsAnimation, let timer = animationTimer {
            timer.invalidate()
            animationTimer = nil
        }
    }

    private func needsAnimation(for command: DrawCommand) -> Bool {
        switch command {
        case .spinner, .skeleton: return true
        case .clientPanel(let panel): return panel.commands.contains { needsAnimation(for: $0) }
        case .hScroll(let scroll): return scroll.commands.contains { needsAnimation(for: $0) }
        case .vScroll(let scroll): return scroll.commands.contains { needsAnimation(for: $0) }
        default: return false
        }
    }

    // MARK: - Primitive draw methods (verbatim Core Graphics logic, ported from NativeCanvasView.swift)

    private func draw(_ command: RectCommand, in context: CGContext) {
        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)
        let radius = command.radius ?? 0
        let path = radius > 0
            ? CGPath(roundedRect: rect, cornerWidth: radius, cornerHeight: radius, transform: nil)
            : CGPath(rect: rect, transform: nil)

        context.saveGState()
        context.addPath(path)

        if let color = command.color, let nsColor = NSColor(hex: color) {
            context.setFillColor(nsColor.cgColor)
            context.fillPath()
            context.addPath(path)
        }

        if let borderColor = command.borderColor, let nsColor = NSColor(hex: borderColor), (command.borderWidth ?? 0) > 0 {
            context.setStrokeColor(nsColor.cgColor)
            context.setLineWidth(command.borderWidth ?? 1)
            context.strokePath()
        }

        context.restoreGState()
    }

    private func draw(_ command: TextCommand, in context: CGContext) {
        let color = command.color.flatMap(NSColor.init(hex:)) ?? .black
        let size = command.size ?? 16
        let font = (command.bold ?? false)
            ? NSFont.boldSystemFont(ofSize: size)
            : NSFont.systemFont(ofSize: size)

        let attributed = NSAttributedString(string: command.text, attributes: [.font: font, .foregroundColor: color])

        // Same baseline-vs-top-left reconciliation NativeCanvasView.swift's
        // own draw(_ command: TextCommand...) documents — Canvas::text()'s
        // (x, y) is the drawText BASELINE, NSAttributedString.draw(at:)
        // anchors at the top-left of the glyph box instead.
        let origin = CGPoint(x: command.x, y: command.y - font.ascender)
        attributed.draw(at: origin)
    }

    private func draw(_ command: IconCommand, in context: CGContext) {
        guard let font = MacIconFont.font(forKey: command.font, size: CGFloat(command.size)) else { return }
        guard let scalar = Unicode.Scalar(command.codepoint) else { return }

        let color = command.color.flatMap(NSColor.init(hex:)) ?? (NSColor(hex: "#111827") ?? .black)
        let attributed = NSAttributedString(string: String(Character(scalar)), attributes: [.font: font, .foregroundColor: color])

        // Measures the real glyph size and centers it in the size×size
        // box — same approach NativeCanvasView.swift's own
        // drawIconCommand() takes (real measurement) rather than
        // NativeCanvasView.kt's fixed baseline-offset percentage tuned
        // for a different font renderer.
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
        let path = CGPath(ellipseIn: rect, transform: nil)

        context.saveGState()
        context.addPath(path)

        if let color = command.color, let nsColor = NSColor(hex: color) {
            context.setFillColor(nsColor.cgColor)
            context.fillPath()
            context.addPath(path)
        }

        if let borderColor = command.borderColor, let nsColor = NSColor(hex: borderColor), (command.borderWidth ?? 0) > 0 {
            context.setStrokeColor(nsColor.cgColor)
            context.setLineWidth(command.borderWidth ?? 1)
            context.strokePath()
        }

        context.restoreGState()
    }

    private func draw(_ command: LineCommand, in context: CGContext) {
        guard let color = NSColor(hex: command.color) else { return }

        context.saveGState()
        context.setStrokeColor(color.cgColor)
        context.setLineWidth(command.width ?? 1)
        context.move(to: CGPoint(x: command.x1, y: command.y1))
        context.addLine(to: CGPoint(x: command.x2, y: command.y2))
        context.strokePath()
        context.restoreGState()
    }

    private func draw(_ command: ArcCommand, in context: CGContext) {
        guard let color = NSColor(hex: command.color) else { return }

        // Same handedness fix NativeCanvasView.swift's own draw(_
        // command: ArcCommand...) documents: Canvas::arc()'s convention
        // is Android's (0deg = 3 o'clock, sweeping CLOCKWISE); Core
        // Graphics' addArc(clockwise:) is the opposite sense in its own
        // coordinate space — negating the angles replays it correctly
        // without mirroring it. Identical reasoning and identical fix on
        // macOS, since this is Core Graphics behavior, not a UIKit one.
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

    private func draw(_ command: ImageCommand, in context: CGContext) {
        guard let image = MacImageLoader.get(command.url) else {
            MacImageLoader.load(command.url) { [weak self] in self?.needsDisplay = true }
            return
        }

        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)
        let radius = CGFloat(command.radius ?? 0)

        context.saveGState()
        if radius > 0 {
            context.addPath(CGPath(roundedRect: rect, cornerWidth: radius, cornerHeight: radius, transform: nil))
            context.clip()
        }

        // NSImage has no direct "draw into this CGContext" the way
        // UIImage.draw(in:) implicitly uses UIGraphicsGetCurrentContext()
        // — going through a CGImage representation and drawing that
        // directly against `context` is the portable way to target a
        // specific, non-current-graphics-context context.cGContext
        // explicitly rather than relying on NSGraphicsContext.current
        // still being the same one by the time this runs.
        if let cgImage = image.cgImage(forProposedRect: nil, context: nil, hints: nil) {
            context.draw(cgImage, in: rect)
        }
        context.restoreGState()
    }

    private func draw(_ command: SpinnerCommand, in context: CGContext) {
        guard let trackColor = NSColor(hex: command.trackColor), let color = NSColor(hex: command.color) else { return }

        let center = CGFloat(command.size) / 2
        let radius = center - CGFloat(command.strokeWidth) / 2
        let cx = CGFloat(command.x) + center
        let cy = CGFloat(command.y) + center

        context.saveGState()
        context.setLineWidth(CGFloat(command.strokeWidth))

        context.setStrokeColor(trackColor.cgColor)
        context.addArc(center: CGPoint(x: cx, y: cy), radius: radius, startAngle: 0, endAngle: .pi * 2, clockwise: false)
        context.strokePath()

        let periodMs = 1100.0
        let elapsedMs = Date().timeIntervalSinceReferenceDate * 1000
        let rotationRadians = (elapsedMs.truncatingRemainder(dividingBy: periodMs)) / periodMs * (.pi * 2)
        let sweepRadians = 110 * Double.pi / 180

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

    private func draw(_ command: SkeletonCommand, in context: CGContext) {
        guard let baseColor = NSColor(hex: command.color) else { return }

        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)
        let radius = CGFloat(command.radius)
        let path = CGPath(roundedRect: rect, cornerWidth: radius, cornerHeight: radius, transform: nil)

        context.saveGState()
        context.addPath(path)
        context.setFillColor(baseColor.cgColor)
        context.fillPath()

        let rgbColor = baseColor.usingColorSpace(.deviceRGB) ?? baseColor
        var r: CGFloat = 0, g: CGFloat = 0, b: CGFloat = 0, a: CGFloat = 0
        rgbColor.getRed(&r, green: &g, blue: &b, alpha: &a)
        let highlight = NSColor(red: r + (1 - r) * 0.5, green: g + (1 - g) * 0.5, blue: b + (1 - b) * 0.5, alpha: a)

        let sweepWidth = max(rect.width * 0.6, 1)
        let periodMs = 1300.0
        let elapsedMs = Date().timeIntervalSinceReferenceDate * 1000
        let phase = CGFloat((elapsedMs.truncatingRemainder(dividingBy: periodMs)) / periodMs)
        let sweepX = rect.minX - sweepWidth + (rect.width + sweepWidth) * phase

        context.addPath(path)
        context.clip()

        let colors = [NSColor.clear.cgColor, highlight.withAlphaComponent(0.8).cgColor, NSColor.clear.cgColor] as CFArray
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

    private func draw(_ command: HScrollCommand, in context: CGContext) {
        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)

        context.saveGState()
        context.clip(to: rect)
        context.translateBy(x: CGFloat(command.x), y: CGFloat(command.y))
        for nested in command.commands {
            drawCommand(nested, in: context)
        }
        context.restoreGState()
    }

    private func draw(_ command: VScrollCommand, in context: CGContext) {
        let rect = CGRect(x: command.x, y: command.y, width: command.width, height: command.height)

        context.saveGState()
        context.clip(to: rect)
        context.translateBy(x: CGFloat(command.x), y: CGFloat(command.y))
        for nested in command.commands {
            drawCommand(nested, in: context)
        }
        context.restoreGState()
    }

    private func draw(_ command: SliderCommand, in context: CGContext) {
        guard let trackColor = NSColor(hex: command.trackColor),
              let activeColor = NSColor(hex: command.activeColor),
              let thumbColor = NSColor(hex: command.thumbColor) else { return }

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
        context.addPath(CGPath(roundedRect: CGRect(x: x, y: trackY, width: width, height: trackHeight), cornerWidth: trackHeight / 2, cornerHeight: trackHeight / 2, transform: nil))
        context.fillPath()

        context.setFillColor(activeColor.cgColor)
        let activeWidth = max(thumbCx - x, 0)
        context.addPath(CGPath(roundedRect: CGRect(x: x, y: trackY, width: activeWidth, height: trackHeight), cornerWidth: trackHeight / 2, cornerHeight: trackHeight / 2, transform: nil))
        context.fillPath()

        let thumbRect = CGRect(x: thumbCx - thumbSize / 2, y: thumbCy - thumbSize / 2, width: thumbSize, height: thumbSize)
        context.setFillColor(thumbColor.cgColor)
        context.addPath(CGPath(ellipseIn: thumbRect, transform: nil))
        context.fillPath()

        context.setStrokeColor(activeColor.cgColor)
        context.setLineWidth(1.5)
        context.addPath(CGPath(ellipseIn: thumbRect, transform: nil))
        context.strokePath()

        context.restoreGState()
    }
}

extension NSColor {
    /// Parses "#RRGGBB" or "#RRGGBBAA" — the exact two shapes every
    /// Engine\Color::toHex()/Tokens color constant on the PHP side
    /// produces, same contract as UIColor(hex:) on iOS. `srgbRed:` (not
    /// the older `deviceRGB` init) is what makes this resolve to the
    /// exact same visual color a hex triplet implies, matching how
    /// every other platform's own hex parser already treats these values
    /// as sRGB without saying so explicitly.
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
            srgbRed: CGFloat(r) / 255,
            green: CGFloat(g) / 255,
            blue: CGFloat(b) / 255,
            alpha: CGFloat(a) / 255
        )
    }
}
