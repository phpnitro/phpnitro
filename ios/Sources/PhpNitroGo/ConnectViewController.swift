import PhpNitroNativeEngine
import UIKit

/// The iOS counterpart of android/go's ConnectActivity — everything past
/// a successful "Connecter" tap is PhpNitroNativeEngine's own
/// NativeScreenViewController pointed at a remote `phpx serve`, the same
/// relationship PhpNitro Go on Android has to `NativeRenderPocActivity`.
/// This app never bundles a single line of any project's PHP: a pure
/// client for whatever `phpx serve` happens to be running on the same
/// network, same as Expo Go's relationship to a Metro dev server.
///
/// "Scanner un QR code" pushes ScanViewController (see that file), same
/// AVFoundation-based decoding ScanActivity.kt does with CameraX + ML Kit
/// on Android — both ultimately funnel into the same HostPort.parse(_:)
/// + push-a-real-screen path as the manual field below.
///
/// Built as plain views in code, not a `.xib`/storyboard — matches this
/// module's Android counterpart (also built as plain Views, see
/// ConnectActivity.kt's own docblock). Visual design is a deliberate,
/// literal port of ConnectActivity.kt's own layout (same colors, same
/// copy, same gradient hero, same outlined "Connecter" button) — found
/// to have drifted into a bare default-UIKit look before this rewrite
/// (2026-09-09, first real side-by-side comparison against a booted
/// Android emulator), even though the whole point of this module is
/// "same codebase, same result" parity with its Android counterpart.
public final class ConnectViewController: UIViewController, UITextFieldDelegate {
    /// Fires with the parsed (host, port) on a successful "Connecter" —
    /// purely an observation hook (tests, analytics, a caller that wants
    /// to know a connection was attempted); it does NOT gate navigation,
    /// see `navigatesAutomatically` below for that.
    public var onConnect: ((_ host: String, _ port: Int) -> Void)?

    /// When true (the default) and this controller has a
    /// `navigationController`, a successful "Connecter" pushes a real
    /// `NativeScreenViewController` — same "tap Connecter, land on the
    /// actual remote screen" behavior `renderIntent()`/ConnectActivity.kt
    /// gives on Android. Set false to opt out (e.g. a host app that wants
    /// to drive navigation itself from `onConnect`, or a unit test that
    /// doesn't want a real network fetch to start).
    public var navigatesAutomatically = true

    // Same two stops ConnectActivity.kt's own gradientStart/gradientEnd use.
    private let gradientStart = UIColor(hex: "#F97316")!
    private let gradientEnd = UIColor(hex: "#DC2626")!

    private let urlField = UITextField()
    private let errorLabel = UILabel()
    private let heroGradient = CAGradientLayer()
    private let scanButtonGradient = CAGradientLayer()

    override public func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        // See NativeScreenViewController's own identical override for
        // why — ConnectActivity.kt runs edge-to-edge with no ActionBar
        // at all, and this screen's hero gradient is drawn assuming the
        // same (it should start right at the safe area's top, not below
        // an extra system nav bar).
        navigationController?.setNavigationBarHidden(true, animated: animated)
    }

    override public func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = UIColor(hex: "#F9FAFB")
        configureLayout()
    }

    override public func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        heroGradient.frame = heroGradient.superlayer?.bounds ?? .zero
        scanButtonGradient.frame = scanButtonGradient.superlayer?.bounds ?? .zero
    }

    private func configureLayout() {
        let scroll = UIScrollView()
        scroll.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(scroll)
        NSLayoutConstraint.activate([
            scroll.topAnchor.constraint(equalTo: view.topAnchor),
            scroll.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            scroll.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            scroll.bottomAnchor.constraint(equalTo: view.bottomAnchor),
        ])

        let root = UIStackView()
        root.axis = .vertical
        root.translatesAutoresizingMaskIntoConstraints = false
        scroll.addSubview(root)
        NSLayoutConstraint.activate([
            root.topAnchor.constraint(equalTo: scroll.topAnchor),
            root.leadingAnchor.constraint(equalTo: scroll.leadingAnchor),
            root.trailingAnchor.constraint(equalTo: scroll.trailingAnchor),
            root.bottomAnchor.constraint(equalTo: scroll.bottomAnchor),
            root.widthAnchor.constraint(equalTo: scroll.widthAnchor),
        ])

        root.addArrangedSubview(buildHero())
        root.addArrangedSubview(buildContent())
    }

    // MARK: - Hero (ConnectActivity.kt's own buildHero())

    private func buildHero() -> UIView {
        let hero = UIView()
        heroGradient.colors = [gradientStart.cgColor, gradientEnd.cgColor]
        heroGradient.startPoint = CGPoint(x: 0, y: 0)
        heroGradient.endPoint = CGPoint(x: 1, y: 1)
        hero.layer.insertSublayer(heroGradient, at: 0)
        hero.layer.cornerRadius = 28
        hero.layer.maskedCorners = [.layerMinXMaxYCorner, .layerMaxXMaxYCorner]
        hero.layer.masksToBounds = true

        let badge = UILabel()
        badge.text = "⚡"
        badge.font = .systemFont(ofSize: 30)
        badge.textAlignment = .center
        badge.backgroundColor = UIColor.white.withAlphaComponent(0.2)
        badge.layer.cornerRadius = 32
        badge.layer.masksToBounds = true
        badge.translatesAutoresizingMaskIntoConstraints = false

        let title = UILabel()
        title.text = "PhpNitro Go"
        title.font = .boldSystemFont(ofSize: 26)
        title.textColor = .white
        title.textAlignment = .center

        let subtitle = UILabel()
        subtitle.text = "Visualise ton projet PhpNitro en direct, sans jamais recompiler l'app."
        subtitle.font = .systemFont(ofSize: 14)
        subtitle.textColor = UIColor(hex: "#FFE4D6")
        subtitle.textAlignment = .center
        subtitle.numberOfLines = 0

        let inner = UIStackView(arrangedSubviews: [badge, title, subtitle])
        inner.axis = .vertical
        inner.alignment = .center
        inner.spacing = 4
        inner.setCustomSpacing(16, after: badge)
        inner.translatesAutoresizingMaskIntoConstraints = false
        hero.addSubview(inner)

        NSLayoutConstraint.activate([
            badge.widthAnchor.constraint(equalToConstant: 64),
            badge.heightAnchor.constraint(equalToConstant: 64),
            inner.topAnchor.constraint(equalTo: hero.topAnchor, constant: 72),
            inner.leadingAnchor.constraint(equalTo: hero.leadingAnchor, constant: 28),
            inner.trailingAnchor.constraint(equalTo: hero.trailingAnchor, constant: -28),
            inner.bottomAnchor.constraint(equalTo: hero.bottomAnchor, constant: -40),
        ])

        return hero
    }

    // MARK: - Content (scan button, divider, field, connect button, footer)

    private func buildContent() -> UIView {
        let scanButton = UIButton(type: .system)
        scanButton.setTitle("📷  Scanner un QR code", for: .normal)
        scanButton.titleLabel?.font = .boldSystemFont(ofSize: 16)
        scanButton.setTitleColor(.white, for: .normal)
        scanButtonGradient.colors = [gradientStart.cgColor, gradientEnd.cgColor]
        scanButtonGradient.startPoint = CGPoint(x: 0, y: 0.5)
        scanButtonGradient.endPoint = CGPoint(x: 1, y: 0.5)
        scanButtonGradient.cornerRadius = 14
        scanButton.layer.insertSublayer(scanButtonGradient, at: 0)
        scanButton.layer.shadowColor = gradientEnd.cgColor
        scanButton.layer.shadowOpacity = 0.3
        scanButton.layer.shadowOffset = CGSize(width: 0, height: 2)
        scanButton.layer.shadowRadius = 4
        scanButton.heightAnchor.constraint(equalToConstant: 54).isActive = true
        scanButton.addTarget(self, action: #selector(scanTapped), for: .touchUpInside)

        let fieldLabel = UILabel()
        fieldLabel.text = "ADRESSE DU SERVEUR"
        fieldLabel.font = .boldSystemFont(ofSize: 12)
        fieldLabel.textColor = UIColor(hex: "#9CA3AF")
        fieldLabel.setTextSpacing(0.8)

        let fieldIcon = UILabel()
        fieldIcon.text = "🌐"
        fieldIcon.font = .systemFont(ofSize: 16)

        urlField.placeholder = "192.168.1.23:8090"
        urlField.textColor = UIColor(hex: "#111827")
        urlField.keyboardType = .URL
        urlField.autocapitalizationType = .none
        urlField.autocorrectionType = .no
        urlField.delegate = self
        urlField.borderStyle = .none

        let fieldBox = UIStackView(arrangedSubviews: [fieldIcon, urlField])
        fieldBox.axis = .horizontal
        fieldBox.alignment = .center
        fieldBox.spacing = 10
        fieldBox.isLayoutMarginsRelativeArrangement = true
        fieldBox.layoutMargins = UIEdgeInsets(top: 4, left: 16, bottom: 4, right: 16)
        fieldBox.layer.borderWidth = 1
        fieldBox.layer.borderColor = UIColor(hex: "#D1D5DB")?.cgColor
        fieldBox.layer.cornerRadius = 12
        fieldBox.heightAnchor.constraint(equalToConstant: 48).isActive = true

        errorLabel.textColor = UIColor(hex: "#DC2626")
        errorLabel.font = .systemFont(ofSize: 13)
        errorLabel.numberOfLines = 0
        errorLabel.isHidden = true

        let connectButton = UIButton(type: .system)
        connectButton.setTitle("Connecter", for: .normal)
        connectButton.titleLabel?.font = .boldSystemFont(ofSize: 15)
        connectButton.setTitleColor(gradientEnd, for: .normal)
        connectButton.layer.borderWidth = 2
        connectButton.layer.borderColor = gradientEnd.cgColor
        connectButton.layer.cornerRadius = 14
        connectButton.heightAnchor.constraint(equalToConstant: 50).isActive = true
        connectButton.addTarget(self, action: #selector(connectTapped), for: .touchUpInside)

        let footer = UILabel()
        footer.text = "Assure-toi que ce téléphone et ta machine de dev sont sur le même réseau Wi-Fi, et que `phpx serve` tourne."
        footer.font = .systemFont(ofSize: 12)
        footer.textColor = UIColor(hex: "#9CA3AF")
        footer.textAlignment = .center
        footer.numberOfLines = 0

        let content = UIStackView(arrangedSubviews: [
            scanButton, buildDivider(), fieldLabel, fieldBox, errorLabel, connectButton, footer,
        ])
        content.axis = .vertical
        content.spacing = 12
        content.setCustomSpacing(24, after: scanButton)
        content.setCustomSpacing(28, after: connectButton)
        content.isLayoutMarginsRelativeArrangement = true
        content.layoutMargins = UIEdgeInsets(top: 28, left: 28, bottom: 28, right: 28)

        return content
    }

    // MARK: - Divider (ConnectActivity.kt's own buildDivider())

    private func buildDivider() -> UIView {
        func line() -> UIView {
            let view = UIView()
            view.backgroundColor = UIColor(hex: "#E5E7EB")
            view.heightAnchor.constraint(equalToConstant: 1).isActive = true
            return view
        }

        let label = UILabel()
        label.text = "ou saisis l'adresse à la main"
        label.font = .systemFont(ofSize: 12)
        label.textColor = UIColor(hex: "#9CA3AF")
        label.setContentHuggingPriority(.required, for: .horizontal)

        let leadingLine = line()
        let trailingLine = line()

        let row = UIStackView(arrangedSubviews: [leadingLine, label, trailingLine])
        row.axis = .horizontal
        row.alignment = .center
        row.spacing = 12
        row.isLayoutMarginsRelativeArrangement = true
        row.layoutMargins = UIEdgeInsets(top: 24, left: 0, bottom: 24, right: 0)

        // UIStackView won't split zero-intrinsic-content-size views
        // evenly on its own the way Android's LinearLayout weight=1 does
        // — an explicit equal-width tie is what actually reproduces
        // ConnectActivity.kt's own buildDivider() (two even lines around
        // the label), not just "a line on one side".
        leadingLine.widthAnchor.constraint(equalTo: trailingLine.widthAnchor).isActive = true

        return row
    }

    @objc private func connectTapped() {
        attemptConnect()
    }

    @objc private func scanTapped() {
        navigationController?.pushViewController(ScanViewController(), animated: true)
    }

    public func textFieldShouldReturn(_ textField: UITextField) -> Bool {
        attemptConnect()
        return true
    }

    private func attemptConnect() {
        guard let parsed = HostPort.parse(urlField.text ?? "") else {
            errorLabel.text = "Format attendu : IP:PORT (ex. 192.168.1.23:8090)"
            errorLabel.isHidden = false
            return
        }

        errorLabel.isHidden = true
        onConnect?(parsed.host, parsed.port)

        if navigatesAutomatically {
            navigationController?.pushViewController(NativeScreenViewController(host: parsed.host, port: parsed.port), animated: true)
        }
    }
}

private extension UILabel {
    /// UIKit has no direct "letterSpacing" property setter the way
    /// Android's TextView.letterSpacing is — NSAttributedString's
    /// .kern is the equivalent, applied here so fieldLabel's own
    /// "ADRESSE DU SERVEUR" gets the same slightly-tracked-out look
    /// ConnectActivity.kt's `letterSpacing = 0.08f` gives it.
    func setTextSpacing(_ points: Double) {
        guard let text else { return }
        let attributed = NSMutableAttributedString(string: text, attributes: [.font: font as Any, .foregroundColor: textColor as Any])
        attributed.addAttribute(.kern, value: points, range: NSRange(location: 0, length: text.count))
        attributedText = attributed
    }
}
