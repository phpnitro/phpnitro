import AVFoundation
import CoreLocation
import MapKit
import PhpNitroProtocol
import QuartzCore
import UIKit

/// The iOS counterpart of NativeCanvasView.kt — replays a decoded
/// DrawCommandPayload with Core Graphics inside `draw(rect:)`, the same
/// "PHP computes one frame, the client just replays flat draw commands"
/// contract the whole native-render-engine proposal is built on
/// (docs/proposals/moteur-rendu-natif.md), just against UIKit/Core
/// Graphics instead of android.graphics.Canvas.
///
/// Not a 1:1 port of NativeCanvasView.kt's full feature set — no scroll
/// handling, no hero transitions, no Lottie overlay. `focus:`
/// (showTextInput), `video:play:` (showVideoOverlay), and `map:open:`
/// (showMapOverlay) below DO use the same "no Canvas concept for this,
/// overlay a real View" idiom NativeRenderPocActivity.kt's own
/// showTextInput()/showVideoOverlay()/showMapOverlay() use — everything
/// else interactive is real, separate follow-up work, not something to
/// fake here.
///
/// setPayload(_:) triggers `setNeedsDisplay()`; nothing here fetches
/// draw commands from a server itself — same separation of concerns
/// NativeCanvasView.kt has from NativeRenderPocActivity's own
/// fetchDrawCommands(), just not yet built on this side (there is no
/// iOS equivalent of that fetch loop, or of PhpServer.kt's embedded PHP
/// process — see ios/README.md for what that would take).
public final class NativeCanvasView: UIView {
    private var payload: DrawCommandPayload?

    /// Real bug found the first time this engine ever ran on an actual
    /// simulator/device (2026-09-09, first Mac available for this
    /// project): drawing an `icon` command sometimes renders nothing —
    /// confirmed by redrawing the exact same IconCommand a second time
    /// immediately afterward, in the same draw(rect:) call, which then
    /// painted correctly. `text` (Roboto) never showed this — only the
    /// icon fonts (MaterialIcons/FontAwesome), registered at runtime via
    /// CTFontManagerRegisterGraphicsFont() (see IconFont.swift) rather
    /// than declared in Info.plist, are affected.
    ///
    /// Two narrower fixes were tried and both failed a real
    /// navigate-away-and-back test in HostApp before this one: a
    /// process-wide "only the very first icon ever" static flag missed
    /// every NEW NativeCanvasView instance (a fresh CALayer/CGContext),
    /// and a per-instance "only this view's first paint" flag still
    /// missed cases where the SAME instance's later payload introduced
    /// icon glyphs/sizes it hadn't drawn before. Rather than chase the
    /// exact CoreText/UIKit precondition further, every draw(rect:) pass
    /// simply repaints every icon command a second time, unconditionally
    /// — icons are few and cheap per screen, and this is the one
    /// approach confirmed to survive restart, in-app navigation, and
    /// revisiting a screen.

    /// Fired from handleTap(_:) when a tap lands inside one of the
    /// current payload's hitRegions — the caller (whatever eventually
    /// plays the role of NativeRenderPocActivity's own tap dispatch)
    /// wires this to actually act on the hitRegion's `action` string. No
    /// dispatch logic lives here — same separation `action(at:)` on
    /// DrawCommandPayload already keeps (geometry only, no side effects).
    /// `rect` is always the tapped region's own rect — unused for most
    /// actions, but needed by the caller to position a `showTextInput`
    /// overlay for a `focus:` action, mirroring
    /// `NativeRenderPocActivity.kt`'s own `onAction?.invoke(action,
    /// region.rect, meta)`, which always passes the rect too, not just
    /// for `focus:` specifically.
    public var onAction: ((_ action: String, _ rect: CGRect) -> Void)?

    /// `(fieldName, value)` — fires on every keystroke in the active
    /// text-input overlay (see `showTextInput`'s own doc comment).
    public var onFieldValueChanged: ((String, String) -> Void)?

    // TextField.php/PasswordField.php's "focus:" commit destination —
    // one real UITextField/UITextView at a time, mirroring
    // NativeRenderPocActivity.kt's own single-nullable-field
    // activeEditText (never a map — a second focus: tap always replaces
    // the first).
    private var activeTextInput: UIView?
    private var activeFieldName: String?

    // VideoPlayer.php's "video:play:<url>" commit destination — one real
    // AVPlayer/AVPlayerLayer at a time, mirroring
    // NativeRenderPocActivity.kt's own single-nullable-field
    // activeVideoView. No transport bar (unlike Android's system-
    // provided MediaController) — autoplay only, a real, larger
    // undertaking of its own, not attempted here.
    private var activeVideoPlayer: AVPlayer?
    private var activeVideoContainer: UIView?

    // MapView.php's "map:open:<lat>:<lon>:<zoom>" commit destination —
    // one real MKMapView at a time, mirroring
    // NativeRenderPocActivity.kt's own single-nullable-field
    // activeMapView. No API key needed (MapKit, like osmdroid), pan/zoom
    // built in once added — no extra gesture wiring here either.
    private var activeMapView: MKMapView?

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

    // MARK: - Page scroll (NativeCanvasView.kt's own scrollY)
    //
    // A real bug found comparing side-by-side against a booted Android
    // emulator (2026-09-09): this view had NO page-scroll support at
    // all — every screen taller than one viewport just drew everything
    // starting at y=0, silently clipping anything past the bottom edge,
    // with nothing to reveal it. Ported from NativeCanvasView.kt's own
    // scrollY/maxScrollY()/flingScroll() (a hand-rolled Float tracked by
    // this view, translated at draw time — NOT a UIScrollView wrapping
    // this view, so `fixed` content can be drawn a second time,
    // untranslated, on top, matching Android's own two-pass
    // drawCommands(..., fixed:) split exactly).

    /// Current scroll offset in points, content space (same units
    /// draw-command coordinates use) — 0 at the top. Mirrors
    /// NativeCanvasView.kt:202's own `scrollY`.
    private var scrollY: CGFloat = 0

    private var panGesture: UIPanGestureRecognizer?
    private var flingDisplayLink: CADisplayLink?
    private var flingStartValue: CGFloat = 0
    private var flingTargetValue: CGFloat = 0
    private var flingStartTime: CFTimeInterval = 0
    private let flingDuration: CFTimeInterval = 0.35

    /// Mirrors NativeCanvasView.kt:632-635's own `maxScrollY()` —
    /// `contentHeight` comes from the server-computed payload, never a
    /// local Auto Layout measurement.
    private func maxScrollY() -> CGFloat {
        guard let payload else { return 0 }
        return max(0, CGFloat(payload.contentHeight) - bounds.height)
    }

    override public init(frame: CGRect) {
        super.init(frame: frame)
        backgroundColor = .white
        isOpaque = true
        configureTapRecognizer()
        configurePanRecognizer()
    }

    public required init?(coder: NSCoder) {
        super.init(coder: coder)
        backgroundColor = .white
        isOpaque = true
        configureTapRecognizer()
        configurePanRecognizer()
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
        // A new payload just replaced whatever the current overlay (if
        // any) was positioned/typed against — NativeRenderPocActivity.kt
        // only tears its own overlay down on navigate:/tab:/back/submit:,
        // leaving it alone across other same-screen refetches (toggle:,
        // etc); this port simplifies to "any new payload ends the
        // current editing session", safer than trying to reposition a
        // stale overlay against content it was never laid out for.
        clearTextInput()
        clearVideoOverlay()
        clearMapOverlay()
        updateAnimationState()
        // Every new payload is a simplification of NativeCanvasView.kt's
        // own scroll-preserving refetch (that one only resets scrollY on
        // navigate:/tab:/back, keeping it across a same-screen toggle:
        // refetch) — this always resets to the top. A stale scrollY
        // surviving into a screen with a very different contentHeight
        // (or a genuinely new screen after tab:/navigate:) is worse than
        // losing scroll position on same-screen refetches scroll support
        // doesn't distinguish here yet.
        scrollY = 0
        stopFling()
        setNeedsDisplay()
    }

    /// `focus:[multiline:][secure:]name` — ports
    /// `NativeRenderPocActivity.kt`'s `showTextInput()`: one real
    /// `UITextField`/`UITextView` positioned over the static rect+text
    /// `TextField.php` already painted underneath (which stays in the
    /// command list, just visually covered while focused), styled by
    /// hand from `Tokens.php`'s own constants since none of this is sent
    /// over the wire.
    public func showTextInput(fieldName: String, initialValue: String, rect: CGRect, multiline: Bool, secure: Bool) {
        clearTextInput()

        let ink = UIColor(red: 0x11 / 255, green: 0x18 / 255, blue: 0x27 / 255, alpha: 1)
        let border = UIColor(red: 0xE5 / 255, green: 0xE7 / 255, blue: 0xEB / 255, alpha: 1)

        let textInput: UIView
        if multiline {
            let textView = UITextView(frame: rect)
            textView.text = initialValue
            textView.font = .systemFont(ofSize: 15)
            textView.textColor = ink
            textView.delegate = self
            textInput = textView
        } else {
            let textField = UITextField(frame: rect)
            textField.text = initialValue
            textField.isSecureTextEntry = secure
            textField.font = .systemFont(ofSize: 15)
            textField.textColor = ink
            textField.borderStyle = .none
            textField.addTarget(self, action: #selector(textFieldChanged(_:)), for: .editingChanged)
            textInput = textField
        }
        textInput.backgroundColor = .white
        textInput.layer.borderColor = border.cgColor
        textInput.layer.borderWidth = 1
        textInput.layer.cornerRadius = 14

        addSubview(textInput)
        textInput.becomeFirstResponder()
        activeTextInput = textInput
        activeFieldName = fieldName
    }

    private func clearTextInput() {
        guard let activeTextInput else { return }
        activeTextInput.resignFirstResponder()
        activeTextInput.removeFromSuperview()
        self.activeTextInput = nil
        activeFieldName = nil
    }

    /// `video:play:<url>` (VideoPlayer.php) — ports
    /// `NativeRenderPocActivity.kt`'s `showVideoOverlay()`: a real
    /// `AVPlayerLayer` positioned over the static "play" box already
    /// painted underneath, autoplaying immediately (mirrors `VideoView`'s
    /// own `setOnPreparedListener { it.start() }`).
    public func showVideoOverlay(url: String, rect: CGRect) {
        clearVideoOverlay()

        guard let videoURL = URL(string: url) else { return }
        let player = AVPlayer(url: videoURL)
        let playerLayer = AVPlayerLayer(player: player)
        playerLayer.videoGravity = .resizeAspect

        let container = UIView(frame: rect)
        playerLayer.frame = container.bounds
        container.layer.addSublayer(playerLayer)

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

    /// `map:open:<lat>:<lon>:<zoom>` (MapView.php) — ports
    /// `NativeRenderPocActivity.kt`'s `showMapOverlay()`: a real,
    /// pannable/zoomable `MKMapView` centered at (latitude, longitude)
    /// positioned over the static "open map" box already painted
    /// underneath. `zoom` follows the same web-mercator convention
    /// osmdroid/Google Maps/`Slippy map` tile URLs already use (each
    /// level halves the visible span) — no exact equivalent property on
    /// `MKCoordinateSpan`, so this converts it by hand.
    public func showMapOverlay(latitude: Double, longitude: Double, zoom: Int, rect: CGRect) {
        clearMapOverlay()

        let mapView = MKMapView(frame: rect)
        let span = 360.0 / pow(2.0, Double(zoom))
        mapView.setRegion(
            MKCoordinateRegion(
                center: CLLocationCoordinate2D(latitude: latitude, longitude: longitude),
                span: MKCoordinateSpan(latitudeDelta: span, longitudeDelta: span)
            ),
            animated: false
        )

        addSubview(mapView)
        activeMapView = mapView
    }

    private func clearMapOverlay() {
        activeMapView?.removeFromSuperview()
        activeMapView = nil
    }

    @objc private func textFieldChanged(_ textField: UITextField) {
        guard let activeFieldName else { return }
        onFieldValueChanged?(activeFieldName, textField.text ?? "")
    }

    override public func draw(_ rect: CGRect) {
        guard let context = UIGraphicsGetCurrentContext(), let payload else { return }

        // Two passes, mirroring NativeCanvasView.kt's own onDraw(): the
        // scrollable pass translated by -scrollY, then the "fixed" pass
        // (app bar, bottom tab bar, a FAB) drawn a second time with no
        // translate at all, on top — see this class's own `scrollY`
        // doc comment for why this was missing entirely before.
        let scrollableCommands = payload.commands.filter { !$0.isFixed }
        let fixedCommands = payload.commands.filter { $0.isFixed }

        context.saveGState()
        context.translateBy(x: 0, y: -scrollY)
        for command in scrollableCommands {
            drawCommand(command, in: context)
        }
        // See the doc comment above this class's own properties for why
        // this unconditionally repeats on every pass. Walks into
        // clientPanel/hScroll/vScroll's own nested `commands` too — an
        // icon inside one of those is just as affected as a top-level one.
        for icon in Self.allIconCommands(in: scrollableCommands) {
            draw(icon, in: context)
        }
        context.restoreGState()

        for command in fixedCommands {
            drawCommand(command, in: context)
        }
        for icon in Self.allIconCommands(in: fixedCommands) {
            draw(icon, in: context)
        }
    }

    private static func allIconCommands(in commands: [DrawCommand]) -> [IconCommand] {
        commands.flatMap { command -> [IconCommand] in
            switch command {
            case .icon(let icon): return [icon]
            case .clientPanel(let panel): return allIconCommands(in: panel.commands)
            case .hScroll(let scroll): return allIconCommands(in: scroll.commands)
            case .vScroll(let scroll): return allIconCommands(in: scroll.commands)
            default: return []
            }
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
        guard let payload, let region = payload.region(at: recognizer.location(in: self), scrollY: scrollY) else { return }
        let contentRect = CGRect(x: region.x, y: region.y, width: region.width, height: region.height)
        // showTextInput/showVideoOverlay/showMapOverlay all use this
        // rect directly as a real subview's frame — VIEW space, not the
        // draw commands' own CONTENT space. A `fixed` region was never
        // translated by scrollY in the first place (see draw(rect:)'s
        // own fixed pass), so only a scrollable region's rect needs
        // shifting back by -scrollY to land in the same place on screen
        // the tap actually landed.
        let viewRect = (region.fixed ?? false) ? contentRect : contentRect.offsetBy(dx: 0, dy: -scrollY)
        onAction?(region.action, viewRect)
    }

    // MARK: - Page scroll (drag + fling)

    private func configurePanRecognizer() {
        let pan = UIPanGestureRecognizer(target: self, action: #selector(handlePan(_:)))
        addGestureRecognizer(pan)
        panGesture = pan
    }

    @objc private func handlePan(_ recognizer: UIPanGestureRecognizer) {
        let maxScroll = maxScrollY()
        guard maxScroll > 0 else { return }

        switch recognizer.state {
        case .began:
            stopFling()

        case .changed:
            // translation is CUMULATIVE since .began, so this recomputes
            // scrollY from a fixed reference each call rather than
            // accumulating a delta — recognizer.setTranslation(_: in:)
            // keeps that reference at the drag's start point.
            let translationY = recognizer.translation(in: self).y
            scrollY = (scrollY - translationY).clamped(to: 0...maxScroll)
            recognizer.setTranslation(.zero, in: self)
            setNeedsDisplay()

        case .ended, .cancelled:
            // Mirrors NativeCanvasView.kt's own flingScroll(): velocity
            // in points/sec, a fixed 0.35 factor for total travel
            // distance, clamped to the scrollable range, eased out over
            // flingDuration. -velocity because a fling continuing an
            // upward drag (negative translation, content moving up)
            // should keep INCREASING scrollY, same sign convention as
            // the `.changed` branch above.
            let velocityY = recognizer.velocity(in: self).y
            guard abs(velocityY) >= 50 else { return }
            let distance = -velocityY * 0.35
            startFling(to: (scrollY + distance).clamped(to: 0...maxScroll))

        default:
            break
        }
    }

    private func startFling(to target: CGFloat) {
        flingStartValue = scrollY
        flingTargetValue = target
        flingStartTime = CACurrentMediaTime()

        stopFlingDisplayLinkOnly()
        let link = CADisplayLink(target: self, selector: #selector(flingTick))
        link.add(to: .main, forMode: .common)
        flingDisplayLink = link
    }

    @objc private func flingTick() {
        let elapsed = CACurrentMediaTime() - flingStartTime
        let t = min(1, elapsed / flingDuration)
        // DecelerateInterpolator-equivalent ease-out (1 - (1-t)^2) —
        // matches Android's own fling curve shape closely enough that
        // the two platforms feel the same, without pulling in a real
        // physics/friction simulation neither implementation actually uses.
        let eased = 1 - (1 - t) * (1 - t)
        scrollY = flingStartValue + (flingTargetValue - flingStartValue) * eased
        setNeedsDisplay()

        if t >= 1 { stopFling() }
    }

    private func stopFling() {
        stopFlingDisplayLinkOnly()
    }

    private func stopFlingDisplayLinkOnly() {
        flingDisplayLink?.invalidate()
        flingDisplayLink = nil
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

        // Real bug found the first time a screen with many consecutive
        // borderless rects (no PHP `borderColor`/`borderWidth`) ever
        // rendered on a device/simulator — every earlier screen this was
        // checked against happened to have a bordered card early in its
        // command list, which hid this completely. A CGContext's current
        // path is NOT part of the graphics state stack (Apple's own
        // docs on CGContext.saveGState() are explicit about this) — so
        // it is NOT restored by restoreGState() the way fill/stroke
        // color, line width, etc. are. `fillPath()`/`strokePath()` DO
        // clear the current path as a side effect once they run, but a
        // path added and never consumed by either (this rect has a
        // fill, no border) used to stay in the context, silently
        // prepended to the NEXT rect's own path — so that next rect's
        // fillPath() painted its own shape AND every unconsumed leftover
        // shape before it, in whatever color came last. A screen with
        // many borderless rows compounds this every single row, quickly
        // painting almost the entire background in the last fill color
        // used. Fixed by only ever adding the path immediately before
        // the call that consumes it, never leaving one dangling.
        context.saveGState()

        if let color = command.color, let uiColor = UIColor(hex: color) {
            context.addPath(path)
            context.setFillColor(uiColor.cgColor)
            context.fillPath()
        }

        if let borderColor = command.borderColor, let uiColor = UIColor(hex: borderColor), (command.borderWidth ?? 0) > 0 {
            context.addPath(path)
            context.setStrokeColor(uiColor.cgColor)
            context.setLineWidth(command.borderWidth ?? 1)
            context.strokePath()
        }

        context.restoreGState()
    }

    private func draw(_ command: TextCommand, in context: CGContext) {
        let color = command.color.flatMap(UIColor.init(hex:)) ?? .black
        let size = command.size ?? 16
        let font = IconFont.robotoFont(size: size, bold: command.bold ?? false)

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

private extension Comparable {
    func clamped(to range: ClosedRange<Self>) -> Self {
        min(max(self, range.lowerBound), range.upperBound)
    }
}

public extension UIColor {
    /// Parses "#RRGGBB" or "#RRGGBBAA" — the exact two shapes every
    /// Engine\Color::toHex()/Tokens color constant on the PHP side
    /// produces. Returns nil (never crashes) on anything else, same
    /// "malformed input degrades gracefully" contract the rest of this
    /// renderer follows for an unrecognized command type.
    ///
    /// Public (not just internal to this target) — PhpNitroGo's own
    /// ConnectViewController needs the exact same hex parsing to match
    /// ConnectActivity.kt's colors verbatim, and duplicating this parser
    /// there just to keep it target-private isn't worth it.
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

extension NativeCanvasView: UITextViewDelegate {
    /// Every keystroke, not just on blur/submit — mirrors
    /// `NativeRenderPocActivity.kt`'s `TextWatcher.afterTextChanged()`
    /// exactly; every platform here already sends field values on EVERY
    /// fetch regardless of what triggered it (unlike Android's own
    /// selective `includeFields` flag), so there's no separate "commit"
    /// step to wire beyond keeping the caller's dictionary current.
    public func textViewDidChange(_ textView: UITextView) {
        guard let activeFieldName else { return }
        onFieldValueChanged?(activeFieldName, textView.text ?? "")
    }
}
