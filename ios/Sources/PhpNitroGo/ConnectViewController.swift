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
/// ConnectActivity.kt's own docblock).
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

    private let urlField = UITextField()
    private let errorLabel = UILabel()

    override public func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .systemBackground
        configureLayout()
    }

    private func configureLayout() {
        let titleLabel = UILabel()
        titleLabel.text = "PhpNitro Go"
        titleLabel.font = .boldSystemFont(ofSize: 28)

        let scanButton = UIButton(type: .system)
        scanButton.setTitle("📷  Scanner un QR code", for: .normal)
        scanButton.titleLabel?.font = .boldSystemFont(ofSize: 16)
        scanButton.backgroundColor = .systemOrange
        scanButton.setTitleColor(.white, for: .normal)
        scanButton.layer.cornerRadius = 12
        scanButton.clipsToBounds = true
        scanButton.heightAnchor.constraint(equalToConstant: 48).isActive = true
        scanButton.addTarget(self, action: #selector(scanTapped), for: .touchUpInside)

        let subtitleLabel = UILabel()
        subtitleLabel.text = "Adresse du serveur"
        subtitleLabel.font = .systemFont(ofSize: 13, weight: .semibold)
        subtitleLabel.textColor = .secondaryLabel

        urlField.placeholder = "192.168.1.23:8090"
        urlField.borderStyle = .roundedRect
        urlField.keyboardType = .URL
        urlField.autocapitalizationType = .none
        urlField.autocorrectionType = .no
        urlField.delegate = self

        errorLabel.textColor = .systemRed
        errorLabel.font = .systemFont(ofSize: 13)
        errorLabel.numberOfLines = 0
        errorLabel.isHidden = true

        let connectButton = UIButton(type: .system)
        connectButton.setTitle("Connecter", for: .normal)
        connectButton.titleLabel?.font = .boldSystemFont(ofSize: 16)
        connectButton.addTarget(self, action: #selector(connectTapped), for: .touchUpInside)

        let stack = UIStackView(arrangedSubviews: [titleLabel, scanButton, subtitleLabel, urlField, errorLabel, connectButton])
        stack.axis = .vertical
        stack.spacing = 12
        stack.setCustomSpacing(28, after: titleLabel)
        stack.setCustomSpacing(24, after: scanButton)
        stack.translatesAutoresizingMaskIntoConstraints = false

        view.addSubview(stack)
        NSLayoutConstraint.activate([
            stack.leadingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.leadingAnchor, constant: 24),
            stack.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor, constant: -24),
            stack.centerYAnchor.constraint(equalTo: view.safeAreaLayoutGuide.centerYAnchor),
        ])
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
