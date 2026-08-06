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

    override public init(frame: CGRect) {
        super.init(frame: frame)
        backgroundColor = .white
        isOpaque = true
    }

    public required init?(coder: NSCoder) {
        super.init(coder: coder)
        backgroundColor = .white
        isOpaque = true
    }

    public func setPayload(_ payload: DrawCommandPayload) {
        self.payload = payload
        setNeedsDisplay()
    }

    override public func draw(_ rect: CGRect) {
        guard let context = UIGraphicsGetCurrentContext(), let payload else { return }

        for command in payload.commands {
            switch command {
            case .rect(let rect): draw(rect, in: context)
            case .text(let text): draw(text, in: context)
            case .icon: break // Needs MaterialIcons/FontAwesome font assets bundled on iOS first — see IconCommand's own docblock.
            case .circle(let circle): draw(circle, in: context)
            case .line(let line): draw(line, in: context)
            case .arc(let arc): draw(arc, in: context)
            case .unknown: break // Same "an unhandled command is a no-op, not a crash" contract DrawCommand.init(from:) already documents.
            }
        }
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
