import AVFoundation
import PhpNitroNativeEngine
import UIKit

/// The iOS counterpart of android/go's ScanActivity — a live camera
/// preview + QR decoding, pointed at whatever URL `phpx serve` encoded
/// via bin/QrCode.php, then handed off the same way ConnectViewController's
/// manual "Connecter" path is. Unlike ScanActivity.kt (CameraX + ML Kit,
/// two separate dependencies), AVFoundation's own `AVCaptureMetadataOutput`
/// decodes QR codes directly — no on-device ML model to bundle, no
/// third-party dependency added just for this.
public final class ScanViewController: UIViewController, AVCaptureMetadataOutputObjectsDelegate {
    /// Fires with the parsed (host, port) on a successful scan — same
    /// observation-hook role as ConnectViewController's own `onConnect`.
    public var onScanned: ((_ host: String, _ port: Int) -> Void)?

    /// See ConnectViewController's own docblock on this property — same
    /// default-on, same opt-out for a caller that wants to drive
    /// navigation itself or a test that doesn't want a real push.
    public var navigatesAutomatically = true

    private let captureSession = AVCaptureSession()
    private let statusLabel = UILabel()
    private var previewLayer: AVCaptureVideoPreviewLayer?

    /// Guards against decoding and handling more than once — the metadata
    /// output keeps calling the delegate on every frame, and the preview
    /// stays live (and decoding) for the brief moment it takes the actual
    /// screen transition to take over, same "handled" AtomicBoolean role
    /// ScanActivity.kt's own processFrame() plays.
    private var handled = false

    override public func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .black
        configureLayout()
        configureCaptureSessionIfAuthorized()
    }

    override public func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        if captureSession.isRunning == false, AVCaptureDevice.authorizationStatus(for: .video) == .authorized {
            DispatchQueue.global(qos: .userInitiated).async { [captureSession] in captureSession.startRunning() }
        }
    }

    override public func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        if captureSession.isRunning {
            captureSession.stopRunning()
        }
    }

    private func configureLayout() {
        let titleLabel = UILabel()
        titleLabel.text = "Scanner un QR code"
        titleLabel.font = .boldSystemFont(ofSize: 17)
        titleLabel.textColor = .white
        titleLabel.textAlignment = .center

        statusLabel.text = "Vise le QR code affiché par `phpx serve`"
        statusLabel.font = .systemFont(ofSize: 14)
        statusLabel.textColor = UIColor(white: 0.95, alpha: 1)
        statusLabel.textAlignment = .center
        statusLabel.numberOfLines = 0

        titleLabel.translatesAutoresizingMaskIntoConstraints = false
        statusLabel.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(titleLabel)
        view.addSubview(statusLabel)

        NSLayoutConstraint.activate([
            titleLabel.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor, constant: 12),
            titleLabel.leadingAnchor.constraint(equalTo: view.leadingAnchor, constant: 24),
            titleLabel.trailingAnchor.constraint(equalTo: view.trailingAnchor, constant: -24),

            statusLabel.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -48),
            statusLabel.leadingAnchor.constraint(equalTo: view.leadingAnchor, constant: 24),
            statusLabel.trailingAnchor.constraint(equalTo: view.trailingAnchor, constant: -24),
        ])
    }

    private func configureCaptureSessionIfAuthorized() {
        switch AVCaptureDevice.authorizationStatus(for: .video) {
        case .authorized:
            configureCaptureSession()
        case .notDetermined:
            AVCaptureDevice.requestAccess(for: .video) { [weak self] granted in
                DispatchQueue.main.async {
                    if granted {
                        self?.configureCaptureSession()
                        DispatchQueue.global(qos: .userInitiated).async { [weak self] in self?.captureSession.startRunning() }
                    } else {
                        self?.statusLabel.text = "Permission caméra refusée — reviens en arrière et utilise la saisie manuelle."
                    }
                }
            }
        case .denied, .restricted:
            statusLabel.text = "Permission caméra refusée — reviens en arrière et utilise la saisie manuelle."
        @unknown default:
            statusLabel.text = "Permission caméra refusée — reviens en arrière et utilise la saisie manuelle."
        }
    }

    private func configureCaptureSession() {
        guard let device = AVCaptureDevice.default(for: .video), let input = try? AVCaptureDeviceInput(device: device) else {
            statusLabel.text = "Aucune caméra disponible."
            return
        }
        guard captureSession.canAddInput(input) else { return }
        captureSession.addInput(input)

        let output = AVCaptureMetadataOutput()
        guard captureSession.canAddOutput(output) else { return }
        captureSession.addOutput(output)
        output.setMetadataObjectsDelegate(self, queue: .main)
        output.metadataObjectTypes = [.qr]

        let previewLayer = AVCaptureVideoPreviewLayer(session: captureSession)
        previewLayer.videoGravity = .resizeAspectFill
        previewLayer.frame = view.bounds
        view.layer.insertSublayer(previewLayer, at: 0)
        self.previewLayer = previewLayer
    }

    // AVCaptureVideoPreviewLayer has no autoresizingMask of its own to
    // keep pace with the view (unlike a UIView, which gets one for free
    // from Auto Layout/autoresizing) — resizing it manually here is the
    // standard way to keep a raw CALayer in sync with its host view's
    // bounds across rotation/size-class changes.
    override public func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        previewLayer?.frame = view.bounds
    }

    public func metadataOutput(_ output: AVCaptureMetadataOutput, didOutput metadataObjects: [AVMetadataObject], from connection: AVCaptureConnection) {
        guard !handled else { return }
        guard let qrCode = metadataObjects.first as? AVMetadataMachineReadableCodeObject, qrCode.type == .qr else { return }
        guard let rawValue = qrCode.stringValue, let parsed = HostPort.parse(rawValue) else { return }

        handled = true
        captureSession.stopRunning()
        onScanned?(parsed.host, parsed.port)

        if navigatesAutomatically {
            navigationController?.pushViewController(NativeScreenViewController(host: parsed.host, port: parsed.port), animated: true)
        }
    }
}
