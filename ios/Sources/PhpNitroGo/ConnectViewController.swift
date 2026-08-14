import UIKit

/// The iOS counterpart of android/go's ConnectActivity — everything past
/// a successful "Connecter" tap is PhpNitroNativeEngine's own
/// NativeCanvasView pointed at a remote `phpx serve`, the same relationship
/// PhpNitro Go on Android has to `NativeRenderPocActivity`. This app never
/// bundles a single line of any project's PHP: a pure client for whatever
/// `phpx serve` happens to be running on the same network, same as Expo
/// Go's relationship to a Metro dev server.
///
/// Unlike ConnectActivity.kt, there is no QR scanner yet (no AVFoundation
/// capture session wired up here) — manual "IP:PORT" entry only, same v1
/// scoping android/go itself started from before ScanActivity existed.
/// `onConnect` fires with the parsed (host, port) on success; navigating
/// to a live NativeCanvasView screen is real, separate follow-up work —
/// PhpNitroNativeEngine has no network fetch loop yet (see ios/README.md),
/// so there is nowhere to navigate TO yet, only a form that validates and
/// hands back a well-formed destination once one exists.
///
/// Built as plain views in code, not a `.xib`/storyboard — matches this
/// module's Android counterpart (also built as plain Views, see
/// ConnectActivity.kt's own docblock) and keeps this target dependency-free.
public final class ConnectViewController: UIViewController, UITextFieldDelegate {
    public var onConnect: ((_ host: String, _ port: Int) -> Void)?

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

        let stack = UIStackView(arrangedSubviews: [titleLabel, subtitleLabel, urlField, errorLabel, connectButton])
        stack.axis = .vertical
        stack.spacing = 12
        stack.setCustomSpacing(28, after: titleLabel)
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
    }
}
