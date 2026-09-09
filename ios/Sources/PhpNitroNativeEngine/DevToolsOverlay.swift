import UIKit

/// The iOS counterpart of NativeRenderPocActivity.kt's own
/// setupDevTools()/updateDevToolsPanel() — a minimal DevTools-equivalent,
/// not a separate connected tool: two floating badges (🛠 toggles a
/// monospace stats panel, 🔍 arms `NativeCanvasView.inspectMode` for one
/// tap) plus the panel itself. `#if DEBUG`-gated at the call site
/// (`NativeScreenViewController`), same "never present in a release
/// build, no runtime cost either way" contract Android's own
/// `isDebuggable()` check gives — Swift has no direct runtime
/// equivalent of reading `ApplicationInfo.FLAG_DEBUGGABLE`, and a
/// compile-time check is the more honest one anyway (a release build
/// truly never links this code in, not just hides it at runtime).
final class DevToolsOverlay: UIView {
    var onToggleInspect: (() -> Void)?

    private let toolsBadge = UILabel()
    private let inspectBadge = UILabel()
    private let panel = InsetLabel(insets: UIEdgeInsets(top: 10, left: 12, bottom: 10, right: 12))

    override init(frame: CGRect) {
        super.init(frame: frame)
        configureLayout()
    }

    required init?(coder: NSCoder) {
        super.init(coder: coder)
        configureLayout()
    }

    private func configureLayout() {
        isUserInteractionEnabled = true

        for badge in [toolsBadge, inspectBadge] {
            badge.font = .systemFont(ofSize: 18)
            badge.textAlignment = .center
            badge.backgroundColor = UIColor(red: 0x11 / 255, green: 0x18 / 255, blue: 0x27 / 255, alpha: 0.8)
            badge.textColor = .white
            badge.layer.cornerRadius = 20
            badge.layer.masksToBounds = true
            badge.isUserInteractionEnabled = true
            badge.translatesAutoresizingMaskIntoConstraints = false
            addSubview(badge)
        }
        toolsBadge.text = "🛠"
        toolsBadge.addGestureRecognizer(UITapGestureRecognizer(target: self, action: #selector(toolsBadgeTapped)))

        inspectBadge.text = "🔍"
        inspectBadge.alpha = 0.5
        inspectBadge.addGestureRecognizer(UITapGestureRecognizer(target: self, action: #selector(inspectBadgeTapped)))

        panel.font = .monospacedSystemFont(ofSize: 11, weight: .regular)
        panel.textColor = UIColor(red: 0xE5 / 255, green: 0xE7 / 255, blue: 0xEB / 255, alpha: 1)
        panel.numberOfLines = 0
        panel.backgroundColor = UIColor(red: 0x11 / 255, green: 0x18 / 255, blue: 0x27 / 255, alpha: 0.867)
        panel.layer.cornerRadius = 10
        panel.layer.masksToBounds = true
        panel.isHidden = true
        panel.translatesAutoresizingMaskIntoConstraints = false
        addSubview(panel)

        NSLayoutConstraint.activate([
            toolsBadge.widthAnchor.constraint(equalToConstant: 40),
            toolsBadge.heightAnchor.constraint(equalToConstant: 40),
            toolsBadge.trailingAnchor.constraint(equalTo: safeAreaLayoutGuide.trailingAnchor, constant: -16),
            toolsBadge.bottomAnchor.constraint(equalTo: safeAreaLayoutGuide.bottomAnchor, constant: -24),

            inspectBadge.widthAnchor.constraint(equalToConstant: 40),
            inspectBadge.heightAnchor.constraint(equalToConstant: 40),
            inspectBadge.trailingAnchor.constraint(equalTo: toolsBadge.leadingAnchor, constant: -12),
            inspectBadge.centerYAnchor.constraint(equalTo: toolsBadge.centerYAnchor),

            panel.trailingAnchor.constraint(equalTo: safeAreaLayoutGuide.trailingAnchor, constant: -16),
            panel.leadingAnchor.constraint(greaterThanOrEqualTo: safeAreaLayoutGuide.leadingAnchor, constant: 16),
            panel.bottomAnchor.constraint(equalTo: toolsBadge.topAnchor, constant: -12),
        ])
    }

    // This overlay's own frame covers the FULL screen (so its badges can
    // anchor to safeAreaLayoutGuide's own bottom/trailing edges) — the
    // default UIView.hitTest(_:with:) would therefore swallow every
    // touch that lands outside a badge/the panel too, since a point
    // inside `bounds` with no claiming subview still resolves to `self`.
    // Overridden so only an actual badge/panel tap is claimed here;
    // everything else (every real canvas tap) falls through to
    // NativeCanvasView underneath, exactly like Android's own
    // `rootLayout.addView(badge, ...)` — a small FrameLayout child, never
    // a full-screen click-blocking layer.
    override func hitTest(_ point: CGPoint, with event: UIEvent?) -> UIView? {
        for subview in subviews where !subview.isHidden {
            let converted = subview.convert(point, from: self)
            if subview.bounds.contains(converted) {
                return subview
            }
        }
        return nil
    }

    @objc private func toolsBadgeTapped() {
        panel.isHidden.toggle()
    }

    @objc private func inspectBadgeTapped() {
        onToggleInspect?()
    }

    func setInspecting(_ inspecting: Bool) {
        inspectBadge.alpha = inspecting ? 1 : 0.5
    }

    /// Mirrors updateDevToolsPanel()'s own template string exactly
    /// (screen/stack depth, roundTrip+php ms, commands/hitRegions
    /// counts, last-fetch status) — `wasUnchanged` is always `false`
    /// here for now: `lastHash` short-circuiting (Canvas::stableHash())
    /// isn't ported to this client yet (see ios/README.md), so every
    /// fetch really is "applied", never "skipped (unchanged)".
    func update(screen: String, stackDepth: Int, roundTripMs: Double, phpRenderTimeMs: Double?, commandCount: Int, hitRegionCount: Int, wasUnchanged: Bool) {
        let phpMs = phpRenderTimeMs.map { String(format: "%.2f", $0) } ?? "?"
        panel.text = """
        screen: \(screen) (stack depth \(stackDepth))
        roundTrip: \(String(format: "%.1f", roundTripMs)) ms  php: \(phpMs) ms
        commands: \(commandCount)  hitRegions: \(hitRegionCount)
        last fetch: \(wasUnchanged ? "skipped (unchanged)" : "applied")
        """
    }
}

/// Plain UILabel has no text-inset API of its own — badge.php/Canvas.php
/// widgets on the PHP side get padding for free from their own layout
/// pass, but this panel is hand-built UIKit, so drawText(in:) needs the
/// usual manual override to keep the monospace stats from touching the
/// rounded background's edges.
private final class InsetLabel: UILabel {
    private let insets: UIEdgeInsets

    init(insets: UIEdgeInsets) {
        self.insets = insets
        super.init(frame: .zero)
    }

    required init?(coder: NSCoder) {
        self.insets = .zero
        super.init(coder: coder)
    }

    override func drawText(in rect: CGRect) {
        super.drawText(in: rect.inset(by: insets))
    }

    override var intrinsicContentSize: CGSize {
        let size = super.intrinsicContentSize
        return CGSize(width: size.width + insets.left + insets.right, height: size.height + insets.top + insets.bottom)
    }
}
