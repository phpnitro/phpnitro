import AVFoundation
import Contacts
import CoreLocation
import CoreMotion
import EventKit
import LocalAuthentication
import Network
import PhotosUI
import Security
import UniformTypeIdentifiers
import StoreKit
import UIKit
import UserNotifications

/// The iOS counterpart of NativeDeviceBridge.kt — one native capability at
/// a time (2026-09-09: starting with `vibrate`, the simplest one, chosen
/// deliberately small rather than attempting all ~40 Android has at once).
/// `device:*` actions are handled entirely client-side, intercepted in
/// NativeScreenViewController.handle(action:rect:) BEFORE
/// ScreenNavigation.reduce(_:_:) — the exact same "never funneled through
/// a fetch" treatment `focus:`/`video:play:`/`map:open:` already get
/// there, matching NativeRenderPocActivity.kt's own onTap() branching on
/// "device:" before any action that ends in a refetch.
public enum NativeDeviceBridge {
    /// Engine\Device\Vibrate::vibrateAction($ms) always builds
    /// "device:vibrate:<ms>" — see that PHP file's own docblock.
    /// `milliseconds` is accepted (parsed from the action string) for
    /// parity with the PHP call site and NativeDeviceBridge.kt's own
    /// vibrate(milliseconds:), but ends up unused here: iOS exposes no
    /// public API for a variable-duration vibration to third-party apps
    /// (confirmed in ios/README.md's own capability table, same
    /// limitation PhpNitroWebViewBridge's own WebAppInterface.swift
    /// already documents and works around the same way (`.medium`
    /// impact, not a real vibration-motor buzz) — reused verbatim here
    /// for the exact same reason, not a bug to fix later.
    public static func vibrate(milliseconds: Int) {
        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
    }

    /// Mirrors NativeDeviceBridge.kt's own toggleTorch(): finds the
    /// back camera's torch (AVCaptureDevice.default(.builtInWideAngleCamera,
    /// for: .video, position: .back), the closest iOS equivalent of
    /// Android's own "first camera whose FLASH_INFO_AVAILABLE is true"
    /// scan) and flips it — `false` (never toggled, torch stays off) on
    /// a device/simulator with no torch-capable camera at all, same
    /// "return false, no crash" contract Android's own missing-camera
    /// branch has.
    private static var torchOn = false

    public static func toggleTorch() -> Bool {
        guard let device = AVCaptureDevice.default(.builtInWideAngleCamera, for: .video, position: .back), device.hasTorch else {
            return false
        }
        do {
            try device.lockForConfiguration()
            torchOn.toggle()
            device.torchMode = torchOn ? .on : .off
            device.unlockForConfiguration()
            return torchOn
        } catch {
            return false
        }
    }

    /// Mirrors NativeDeviceBridge.kt's own batteryLevel() (0-100, an
    /// Int) — UIDevice's own batteryLevel is a Float in [0, 1], or
    /// exactly -1.0 when monitoring hasn't been enabled yet/is
    /// unsupported (every iOS Simulator prior to enabling monitoring,
    /// and any device with battery monitoring off). Enabling monitoring
    /// here, on first use, mirrors Android needing no such opt-in at
    /// all — BatteryManager is always live there.
    public static func batteryLevel() -> Int {
        UIDevice.current.isBatteryMonitoringEnabled = true
        let level = UIDevice.current.batteryLevel
        return level < 0 ? 0 : Int((level * 100).rounded())
    }

    /// Mirrors NativeDeviceBridge.kt's own deviceId() — Settings.Secure.
    /// ANDROID_ID there, UIDevice.identifierForVendor here: the closest
    /// same-shape iOS analog (a per-vendor, not per-device-globally,
    /// UUID — Apple's own privacy-motivated design, not a narrower
    /// port of Android's own value; a real difference worth documenting,
    /// not hiding behind an identical-looking API name).
    public static func deviceId() -> String {
        UIDevice.current.identifierForVendor?.uuidString ?? ""
    }

    // MARK: - Secure storage (Keychain)

    /// Mirrors NativeDeviceBridge.kt's own secureStore()/secureRetrieve()
    /// — Android backs theirs with a Keystore-encrypted
    /// EncryptedSharedPreferences file named "phpx_secure_storage"; the
    /// Keychain is the direct iOS equivalent (hardware-backed encryption
    /// at rest, same threat model), with `kSecAttrService` playing the
    /// same "which app's bucket" role that file name does. Unlike
    /// Android, there's no single shared file also reachable from a
    /// WebView bridge to keep in sync with — PhpNitroWebViewBridge's own
    /// WebAppInterface.swift has never had a secure-storage bridge at
    /// all, so there's no existing iOS convention to match here, only
    /// Android's to mirror.
    private static let keychainService = "phpx_secure_storage"

    public static func secureStore(key: String, value: String) {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: keychainService,
            kSecAttrAccount as String: key,
        ]
        SecItemDelete(query as CFDictionary)

        var attributes = query
        attributes[kSecValueData as String] = Data(value.utf8)
        SecItemAdd(attributes as CFDictionary, nil)
    }

    public static func secureRetrieve(key: String) -> String {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: keychainService,
            kSecAttrAccount as String: key,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne,
        ]
        var result: AnyObject?
        guard SecItemCopyMatching(query as CFDictionary, &result) == errSecSuccess, let data = result as? Data else {
            return ""
        }
        return String(data: data, encoding: .utf8) ?? ""
    }

    // MARK: - Contacts (read-only count)

    /// Mirrors NativeDeviceBridge.kt's own contactsCount() — same
    /// "check, never request" contract (see that file's own docblock):
    /// `.notDetermined` is treated the same as `.denied`, both return -1,
    /// because actually prompting belongs to a separate Permission
    /// action a caller taps first, not something a read action should
    /// trigger as a side effect.
    public static func contactsCount() -> Int {
        guard CNContactStore.authorizationStatus(for: .contacts) == .authorized else { return -1 }

        var count = 0
        let request = CNContactFetchRequest(keysToFetch: [CNContactIdentifierKey as CNKeyDescriptor])
        try? CNContactStore().enumerateContacts(with: request) { _, _ in count += 1 }
        return count
    }

    // MARK: - Calendar (read-only count)

    /// Mirrors NativeDeviceBridge.kt's own upcomingEventsCount() — same
    /// 30-day window, same "check, never request" contract as
    /// contactsCount() above.
    public static func upcomingEventsCount() -> Int {
        let status = EKEventStore.authorizationStatus(for: .event)
        let isAuthorized: Bool
        if #available(iOS 17.0, *) {
            isAuthorized = status == .fullAccess
        } else {
            isAuthorized = status == .authorized
        }
        guard isAuthorized else { return -1 }

        let store = EKEventStore()
        let now = Date()
        let in30Days = now.addingTimeInterval(30 * 24 * 60 * 60)
        let predicate = store.predicateForEvents(withStart: now, end: in30Days, calendars: nil)
        return store.events(matching: predicate).count
    }

    // MARK: - Sound

    /// Mirrors NativeDeviceBridge.kt's own playSound() — same
    /// fire-and-forget MediaPlayer idea. Held in a static var (not a
    /// local one) for the same reason WebAppInterface.swift's own
    /// audioPlayer is an instance property: an unretained player is
    /// deallocated before playback finishes.
    ///
    /// A first version used AVPlayer(url:).play() directly — silent on
    /// a real device with no error and no crash, only found by
    /// physically listening (Simulator audio came from the Mac's own
    /// speakers either way, so this never got a real "is it actually
    /// audible" check there). AVPlayer.play() has no built-in "wait
    /// until buffered" for a fire-and-forget one-shot like this: on a
    /// real network fetch, play() often returns before the remote
    /// asset has buffered anything at all, and there's no second
    /// chance to call it again. Downloading the (small, effects-length)
    /// file first and handing the bytes to AVAudioPlayer sidesteps that
    /// race entirely — by the time .play() runs, every byte is already
    /// in memory.
    private static var soundPlayer: AVAudioPlayer?

    public static func playSound(_ urlString: String) {
        guard let url = URL(string: urlString) else { return }
        // .playback (not the default .soloAmbient) so this ignores the
        // physical Ring/Silent switch — invisible on the Simulator (no
        // such switch), only surfaced testing on a real device.
        try? AVAudioSession.sharedInstance().setCategory(.playback)
        try? AVAudioSession.sharedInstance().setActive(true)
        URLSession.shared.dataTask(with: url) { data, _, _ in
            guard let data, let player = try? AVAudioPlayer(data: data) else { return }
            DispatchQueue.main.async {
                soundPlayer = player
                player.play()
            }
        }.resume()
    }

    // MARK: - Notify

    /// iOS suppresses a local notification's banner entirely whenever
    /// the posting app is in the foreground — which this demo always
    /// is, tapping its own button — unless a UNUserNotificationCenter
    /// delegate explicitly opts back in via willPresent's own
    /// completionHandler. Found on a real device (2026-09-10): the
    /// request was actually succeeding the whole time (no error, no
    /// crash, permission granted), just never rendered, because
    /// nothing had ever set this. Held statically so it outlives the
    /// showNotification(title:message:) call that installs it, exactly
    /// like soundPlayer above and for the same reason.
    private final class ForegroundNotificationDelegate: NSObject, UNUserNotificationCenterDelegate {
        func userNotificationCenter(
            _ center: UNUserNotificationCenter,
            willPresent notification: UNNotification,
            withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
        ) {
            completionHandler([.banner, .sound, .list])
        }
    }

    private static let notificationDelegate = ForegroundNotificationDelegate()

    /// Mirrors NativeDeviceBridge.kt's own showNotification() (channel
    /// "phpx_default") — UNUserNotificationCenter's local notifications,
    /// same "request authorization inline, silently no-op if denied"
    /// contract WebAppInterface.swift's own showNotification() already
    /// uses for the WebView path (iOS caches the user's answer, so
    /// re-requesting on every call is harmless, not a repeated prompt).
    public static func showNotification(title: String, message: String) {
        let center = UNUserNotificationCenter.current()
        center.delegate = notificationDelegate
        center.requestAuthorization(options: [.alert, .sound]) { granted, _ in
            guard granted else { return }
            let content = UNMutableNotificationContent()
            content.title = title
            content.body = message
            center.add(UNNotificationRequest(identifier: UUID().uuidString, content: content, trigger: nil))
        }
    }

    // MARK: - Share

    /// Mirrors NativeDeviceBridge.kt's own share() (Intent.ACTION_SEND
    /// chooser) — UIActivityViewController is the direct iOS equivalent.
    /// `title` is accepted for call-shape parity with the Android/PHP
    /// action-string builder (Engine\Device\Share::shareAction()) but
    /// unused here, same as WebAppInterface.swift's own share(text:):
    /// UIActivityViewController has no "chooser title" parameter.
    public static func share(text: String, title: String, from presenter: UIViewController) {
        let activity = UIActivityViewController(activityItems: [text], applicationActivities: nil)
        presenter.present(activity, animated: true)
    }

    // MARK: - Brightness

    /// Mirrors NativeDeviceBridge.kt's own setBrightness() in effect,
    /// not mechanism — Android overrides one Activity window's own
    /// WindowManager.LayoutParams.screenBrightness, but iOS exposes no
    /// per-window brightness at all: UIScreen.main.brightness is the
    /// only lever, and it changes the SYSTEM-WIDE setting (visible in
    /// Control Center, persists after leaving the app). A real platform
    /// difference worth documenting, not a narrower port of an API that
    /// doesn't exist here.
    public static func setBrightness(_ level: Float) {
        UIScreen.main.brightness = CGFloat(level.clamped(to: 0.01...1.0))
    }

    // MARK: - Connectivity

    /// Mirrors NativeDeviceBridge.kt's own isOnline() — real
    /// NWPathMonitor status, not a guess from whether this very request
    /// reached the server. NWPathMonitor is inherently async (it only
    /// ever reports state via a callback), so this is too: a first
    /// version blocked the calling thread on a semaphore instead, which
    /// seemed safe on paper (the first update normally arrives in well
    /// under a millisecond) but actually froze the UI thread for up to
    /// its full 1s timeout on a real device/simulator run, dropping the
    /// very next tap — caught via UI test screenshots showing NO effect
    /// at all from either this action or the one right after it, not a
    /// coordinate problem like it first looked.
    public static func isOnline(completion: @escaping (Bool) -> Void) {
        let monitor = NWPathMonitor()
        monitor.pathUpdateHandler = { path in
            monitor.cancel()
            DispatchQueue.main.async {
                completion(path.status == .satisfied)
            }
        }
        monitor.start(queue: DispatchQueue(label: "phpnitro.connectivity-check"))
    }

    // MARK: - URL launcher

    /// Mirrors NativeDeviceBridge.kt's own openWebView() call site's
    /// sibling — Engine\Device\UrlLauncher::openAction() targets any
    /// scheme the OS can resolve (http/https/tel/mailto/sms/geo/...),
    /// same as Android's Intent.ACTION_VIEW; UIApplication.open(_:) is
    /// the direct iOS equivalent. Must run on the main thread (UIKit
    /// requirement) — safe here since handle(action:rect:) itself
    /// always runs on the main thread already.
    public static func openURL(_ urlString: String) {
        guard let url = URL(string: urlString) else { return }
        UIApplication.shared.open(url)
    }

    // MARK: - App icon

    /// Mirrors NativeDeviceBridge.kt's own setAppIcon() in intent, not
    /// mechanism — Android enables/disables manifest-declared
    /// activity-aliases, iOS uses UIApplication's own alternate-icon
    /// API (setAlternateIconName(_:)), which needs every variant
    /// declared as CFBundleAlternateIcons in Info.plist at build time —
    /// the same real "every icon file must be shipped up front" OS
    /// constraint DynamicIcon.php's own docblock already calls out for
    /// Android, not a narrower iOS-only limitation. `iconKey` "default"
    /// (or empty) resets to the primary icon; anything else is looked
    /// up by that exact key in CFBundleAlternateIcons (see
    /// HostApp/project.yml's own Info.plist entry for the one variant
    /// this demo ships: "blue").
    public static func setAppIcon(_ iconKey: String) {
        guard UIApplication.shared.supportsAlternateIcons else { return }
        let name = (iconKey.isEmpty || iconKey == "default") ? nil : iconKey
        guard UIApplication.shared.alternateIconName != name else { return }
        UIApplication.shared.setAlternateIconName(name)
    }

    // MARK: - Clipboard

    /// Mirrors NativeDeviceBridge.kt's own clipboardcopy/clipboardpaste —
    /// UIPasteboard is the direct iOS equivalent of ClipboardManager, no
    /// permission or restriction like Android 10+'s background-read
    /// limits (Engine\Device\Clipboard's own docblock calls that out as
    /// an Android-specific wrinkle, not something to replicate here).
    public static func clipboardCopy(_ text: String) {
        UIPasteboard.general.string = text
    }

    public static func clipboardPaste() -> String {
        UIPasteboard.general.string?.isEmpty == false
            ? UIPasteboard.general.string!
            : "Presse-papiers vide ou inaccessible"
    }

    // MARK: - Email

    /// Mirrors NativeDeviceBridge.kt's own sendemail — Android's
    /// Intent.ACTION_SENDTO with a "mailto:" Uri only ever matches real
    /// mail apps (unlike ACTION_SEND, which lists other share targets
    /// too); a "mailto:" URL opened via UIApplication.open(_:) has the
    /// same effect here, same fire-and-forget contract (no result field
    /// — "the mail app opened with a draft," not "it sent").
    public static func sendEmail(to: String, subject: String, body: String) {
        var components = URLComponents()
        components.scheme = "mailto"
        components.path = to
        components.queryItems = [
            URLQueryItem(name: "subject", value: subject),
            URLQueryItem(name: "body", value: body),
        ]
        guard let url = components.url else { return }
        UIApplication.shared.open(url)
    }

    // MARK: - App settings

    /// Mirrors NativeDeviceBridge.kt's own appsettings — Android maps a
    /// small whitelist ('app'/'wifi'/'location'/'notifications'/
    /// 'bluetooth') to distinct Settings screens; iOS only exposes ONE
    /// public deep link at all (UIApplication.openSettingsURLString,
    /// this app's own permissions page) — every per-category Settings
    /// screen Android can jump to directly has no iOS equivalent
    /// UIApplication is allowed to open. A real platform gap, not a
    /// narrower implementation of the same capability: `screen` is
    /// accepted for call-shape parity with AppSettings::openAction() but
    /// always opens the same page here.
    public static func openAppSettings() {
        guard let url = URL(string: UIApplication.openSettingsURLString) else { return }
        UIApplication.shared.open(url)
    }

    // MARK: - Generic permission request

    /// Retained for the lifetime of one requestWhenInUseAuthorization()
    /// call — CLLocationManager only ever reports back through a
    /// delegate, unlike every other permission check here, which takes
    /// a completion handler. A local `let` would be deallocated before
    /// the callback fires.
    private final class LocationPermissionRequester: NSObject, CLLocationManagerDelegate {
        private let manager = CLLocationManager()
        private let completion: (String) -> Void

        init(completion: @escaping (String) -> Void) {
            self.completion = completion
            super.init()
            manager.delegate = self
        }

        func request() {
            manager.requestWhenInUseAuthorization()
        }

        func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
            switch manager.authorizationStatus {
            case .notDetermined:
                return
            case .authorizedWhenInUse, .authorizedAlways:
                completion("granted")
            default:
                completion("denied")
            }
            NativeDeviceBridge.pendingLocationPermission = nil
        }
    }

    private static var pendingLocationPermission: LocationPermissionRequester?

    /// Mirrors NativeDeviceBridge.kt's own handlePermissionAction() —
    /// same fixed whitelist (Engine\Device\Permission's own docblock:
    /// 'camera', 'microphone', 'location', 'coarse_location', 'contacts',
    /// 'calendar', 'notifications', 'bluetooth'), same three possible
    /// results ('granted'/'denied'/'unknown_permission'). Two real
    /// platform gaps, not narrower ports of the same capability:
    /// 'coarse_location' has no separate iOS permission (CoreLocation
    /// has no "approximate only" request, unlike Android's own ACCESS_
    /// COARSE_LOCATION) so it's treated identically to 'location' here;
    /// 'bluetooth' has no explicit iOS request API at all (creating a
    /// CBCentralManager triggers the system prompt as a side effect,
    /// not something this can ask for up front), so it reports
    /// "unknown_permission" the same way an unrecognised key would,
    /// same "not yet implemented" stance bluetoothState() itself
    /// documents (still todo as of this writing).
    public static func requestPermission(_ key: String, completion: @escaping (String) -> Void) {
        switch key {
        case "camera":
            AVCaptureDevice.requestAccess(for: .video) { granted in
                DispatchQueue.main.async { completion(granted ? "granted" : "denied") }
            }
        case "microphone":
            AVAudioSession.sharedInstance().requestRecordPermission { granted in
                DispatchQueue.main.async { completion(granted ? "granted" : "denied") }
            }
        case "location", "coarse_location":
            let requester = LocationPermissionRequester(completion: completion)
            pendingLocationPermission = requester
            requester.request()
        case "contacts":
            CNContactStore().requestAccess(for: .contacts) { granted, _ in
                DispatchQueue.main.async { completion(granted ? "granted" : "denied") }
            }
        case "calendar":
            if #available(iOS 17.0, *) {
                EKEventStore().requestFullAccessToEvents { granted, _ in
                    DispatchQueue.main.async { completion(granted ? "granted" : "denied") }
                }
            } else {
                EKEventStore().requestAccess(to: .event) { granted, _ in
                    DispatchQueue.main.async { completion(granted ? "granted" : "denied") }
                }
            }
        case "notifications":
            UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound]) { granted, _ in
                DispatchQueue.main.async { completion(granted ? "granted" : "denied") }
            }
        default:
            completion("unknown_permission")
        }
    }

    // MARK: - Sensor

    /// Held for the lifetime of one one-shot accelerometer read —
    /// CMMotionManager's own deviceMotion/accelerometer updates only
    /// ever arrive via a still-running instance's handler block, same
    /// "must outlive the async call" reasoning as soundPlayer and
    /// LocationPermissionRequester above.
    private static var motionManager: CMMotionManager?

    /// Mirrors NativeDeviceBridge.kt's own readSensor(TYPE_ACCELEROMETER)
    /// — a single reading, not a stream, matching Sensors.php's own
    /// docblock ("this pipeline's paint model is one render per
    /// request"). CMMotionManager has no "give me exactly one sample"
    /// call, only startAccelerometerUpdates(to:), so this starts it,
    /// takes the first sample handed back, and immediately stops —
    /// the Simulator (no real accelerometer) never calls the handler at
    /// all, reported as "Capteur indisponible" the same way Android's
    /// own missing-sensor branch is.
    public static func readAccelerometer(completion: @escaping (String) -> Void) {
        guard CMMotionManager().isAccelerometerAvailable else {
            completion("Capteur indisponible")
            return
        }
        let manager = CMMotionManager()
        motionManager = manager
        manager.accelerometerUpdateInterval = 0.1
        manager.startAccelerometerUpdates(to: .main) { data, _ in
            guard let data else { return }
            manager.stopAccelerometerUpdates()
            motionManager = nil
            let x = String(format: "%.2f", data.acceleration.x)
            let y = String(format: "%.2f", data.acceleration.y)
            let z = String(format: "%.2f", data.acceleration.z)
            completion("\(x), \(y), \(z)")
        }
    }

    // MARK: - Alarm

    /// Mirrors NativeDeviceBridge.kt's own scheduleAlarm() in effect —
    /// Android's AlarmManager + a separate AlarmReceiver survives this
    /// app's own process being killed; a UNTimeIntervalNotificationTrigger
    /// local notification is the closest iOS has to the same "fires
    /// later, independent of this process" guarantee, reusing the exact
    /// delegate/authorization path showNotification() above already
    /// sets up (so it also shows as a banner if the app happens to
    /// still be foregrounded when it fires). `requestCode` becomes the
    /// notification's own identifier — same "same code replaces the
    /// previous request" semantics AlarmScheduler.php's own docblock
    /// documents for PendingIntent.FLAG_UPDATE_CURRENT, since
    /// UNUserNotificationCenter.add(_:) with a repeated identifier
    /// already replaces rather than duplicates.
    public static func scheduleAlarm(requestCode: Int, delaySeconds: Int, title: String, message: String) {
        let center = UNUserNotificationCenter.current()
        center.delegate = notificationDelegate
        center.requestAuthorization(options: [.alert, .sound]) { granted, _ in
            guard granted else { return }
            let content = UNMutableNotificationContent()
            content.title = title
            content.body = message
            let trigger = UNTimeIntervalNotificationTrigger(timeInterval: max(1, Double(delaySeconds)), repeats: false)
            let request = UNNotificationRequest(identifier: "phpnitro.alarm.\(requestCode)", content: content, trigger: trigger)
            center.add(request)
        }
    }

    // MARK: - In-app review

    /// Mirrors NativeDeviceBridge.kt's own inappreview — SKStoreReviewController
    /// is iOS's own equivalent of Play Core's ReviewManager: same "no
    /// guarantee the prompt actually shows" contract (StoreKit throttles
    /// how often this can trigger per app per year, same spirit as
    /// Play's own quota — InAppReview.php's own docblock already covers
    /// this as an expected, not a buggy, silent no-op), fire-and-forget,
    /// no result field.
    public static func requestInAppReview(from presenter: UIViewController) {
        guard let scene = presenter.view.window?.windowScene else { return }
        SKStoreReviewController.requestReview(in: scene)
    }

    // MARK: - Biometric (Face ID / Touch ID)

    /// Mirrors NativeDeviceBridge.kt's own showBiometricPrompt() — reuses
    /// the exact LAContext.evaluatePolicy() approach
    /// PhpNitroWebViewBridge's own WebAppInterface.swift already has for
    /// the WebView path (WKWebView implements no platform authenticator,
    /// same reason that path needed this natively too), just reported
    /// back through fieldValues/refetch instead of a JS callback.
    public static func authenticateBiometric(completion: @escaping (Bool, String) -> Void) {
        let context = LAContext()
        var error: NSError?
        guard context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &error) else {
            completion(false, biometricUnavailableReason(error))
            return
        }
        context.evaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, localizedReason: "Confirme ton identité") { success, evaluationError in
            DispatchQueue.main.async {
                completion(success, success ? "" : (evaluationError?.localizedDescription ?? "Authentification échouée."))
            }
        }
    }

    private static func biometricUnavailableReason(_ error: NSError?) -> String {
        switch error?.code {
        case LAError.biometryNotEnrolled.rawValue:
            return "Aucune empreinte/visage enregistré sur ce téléphone."
        case LAError.biometryNotAvailable.rawValue:
            return "Ce device n'a pas de capteur biométrique."
        default:
            return "Authentification biométrique indisponible."
        }
    }

    // MARK: - Image picker (gallery)

    /// Retained for the lifetime of one picker presentation —
    /// PHPickerViewControllerDelegate only ever reports back on a still-
    /// alive delegate, same "must outlive the async call" reasoning as
    /// every other holder in this file. Mirrors NativeDeviceBridge.kt's
    /// own pickImage launcher, reusing PHPickerViewController the same
    /// way WebAppInterface.swift's own pickImage() already does for the
    /// WebView path — needs no NSPhotoLibraryUsageDescription at all,
    /// unlike the legacy UIImagePickerController gallery mode.
    private final class ImagePickerDelegate: NSObject, PHPickerViewControllerDelegate {
        private let completion: (String) -> Void

        init(completion: @escaping (String) -> Void) {
            self.completion = completion
        }

        func picker(_ picker: PHPickerViewController, didFinishPicking results: [PHPickerResult]) {
            picker.dismiss(animated: true)
            NativeDeviceBridge.pendingImagePicker = nil

            guard let provider = results.first?.itemProvider, provider.canLoadObject(ofClass: UIImage.self) else {
                completion("Annulé")
                return
            }
            provider.loadObject(ofClass: UIImage.self) { [completion] object, _ in
                DispatchQueue.main.async {
                    guard let image = object as? UIImage, let data = image.jpegData(compressionQuality: 0.8) else {
                        completion("Erreur")
                        return
                    }
                    completion("Image sélectionnée (\(data.count) octets)")
                }
            }
        }
    }

    private static var pendingImagePicker: ImagePickerDelegate?

    public static func pickImage(from presenter: UIViewController, completion: @escaping (String) -> Void) {
        var config = PHPickerConfiguration()
        config.filter = .images
        config.selectionLimit = 1

        let delegate = ImagePickerDelegate(completion: completion)
        pendingImagePicker = delegate

        let picker = PHPickerViewController(configuration: config)
        picker.delegate = delegate
        presenter.present(picker, animated: true)
    }

    // MARK: - Map launcher

    /// Mirrors NativeDeviceBridge.kt's own openWebView() call site's
    /// sibling for MapLauncher — Android resolves a "geo:" Uri to
    /// whichever maps app (or chooser) the OS has; Apple Maps' own
    /// "maps://" URL scheme (or the https://maps.apple.com fallback,
    /// which every device can open even without Apple Maps set as
    /// default) is the direct iOS equivalent — no MapKit view needed,
    /// this only ever hands off to an external app.
    public static func openMap(latitude: Double, longitude: Double, label: String) {
        var components = URLComponents(string: "https://maps.apple.com/")!
        components.queryItems = [
            URLQueryItem(name: "ll", value: "\(latitude),\(longitude)"),
            URLQueryItem(name: "q", value: label.isEmpty ? "\(latitude),\(longitude)" : label),
        ]
        guard let url = components.url else { return }
        UIApplication.shared.open(url)
    }

    // MARK: - In-app update

    /// Mirrors NativeDeviceBridge.kt's own checkupdate — Android checks
    /// Play Core's AppUpdateManager, which always reports
    /// 'update_not_available' outside a real Play Store install
    /// (InAppUpdate.php's own docblock). iOS has no equivalent of Play
    /// Core's own update-availability API at all (App Store Connect
    /// exposes no public "is a newer version available" check) — this
    /// always reports the same 'update_not_available' Android's own
    /// wrapper reports in every non-Play-Store dev/test scenario, which
    /// covers every real run of this demo either way.
    public static func checkForUpdate() -> String {
        "update_not_available"
    }

    // MARK: - Location (one-shot)

    /// Retained for the lifetime of one location fetch — CLLocationManager
    /// only ever reports back through a delegate, same reasoning as
    /// LocationPermissionRequester above (a different class: that one
    /// requests the PERMISSION, this one requests a FIX once already
    /// granted — Android keeps these as two separate calls too,
    /// getLocation() vs. the permission check in handlePermissionAction()).
    private final class LocationFetcher: NSObject, CLLocationManagerDelegate {
        private let manager = CLLocationManager()
        private let completion: (String) -> Void

        init(completion: @escaping (String) -> Void) {
            self.completion = completion
            super.init()
            manager.delegate = self
        }

        func fetch() {
            manager.requestLocation()
        }

        func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
            NativeDeviceBridge.pendingLocationFetch = nil
            guard let location = locations.last else {
                completion("Position inconnue")
                return
            }
            completion(String(format: "%.5f, %.5f", location.coordinate.latitude, location.coordinate.longitude))
        }

        func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
            NativeDeviceBridge.pendingLocationFetch = nil
            completion("Erreur de localisation")
        }
    }

    private static var pendingLocationFetch: LocationFetcher?

    /// Mirrors NativeDeviceBridge.kt's own getLocation() — same "check,
    /// never request" contract every other permission-gated read in this
    /// file already follows (contactsCount(), upcomingEventsCount()):
    /// actually prompting is Permission::requestAction('location')'s own
    /// job, tapped separately first. requestLocation() (not
    /// startUpdatingLocation(), which streams continuously) matches
    /// FusedLocationProviderClient's own one-shot lastLocation the exact
    /// same way readAccelerometer() above takes one accelerometer sample
    /// and stops, not a stream.
    public static func getLocation(completion: @escaping (String) -> Void) {
        let status = CLLocationManager().authorizationStatus
        guard status == .authorizedWhenInUse || status == .authorizedAlways else {
            completion("Permission requise")
            return
        }
        let fetcher = LocationFetcher(completion: completion)
        pendingLocationFetch = fetcher
        fetcher.fetch()
    }

    // MARK: - File picker

    /// Retained for the lifetime of one picker presentation, same
    /// reasoning as ImagePickerDelegate above. Mirrors
    /// NativeDeviceBridge.kt's own pickFile launcher (ActivityResultContracts.
    /// OpenDocument) — UIDocumentPickerViewController is the direct iOS
    /// equivalent, reporting back the picked file's display name only
    /// (FileSelector.php's own docblock: "not the file's actual bytes",
    /// same scope this mirrors).
    private final class FilePickerDelegate: NSObject, UIDocumentPickerDelegate {
        private let completion: (String) -> Void

        init(completion: @escaping (String) -> Void) {
            self.completion = completion
        }

        func documentPicker(_ controller: UIDocumentPickerViewController, didPickDocumentsAt urls: [URL]) {
            NativeDeviceBridge.pendingFilePicker = nil
            completion(urls.first?.lastPathComponent ?? "Annulé")
        }

        func documentPickerWasCancelled(_ controller: UIDocumentPickerViewController) {
            NativeDeviceBridge.pendingFilePicker = nil
            completion("Annulé")
        }
    }

    private static var pendingFilePicker: FilePickerDelegate?

    public static func pickFile(from presenter: UIViewController, completion: @escaping (String) -> Void) {
        let delegate = FilePickerDelegate(completion: completion)
        pendingFilePicker = delegate

        let picker = UIDocumentPickerViewController(forOpeningContentTypes: [.item])
        picker.delegate = delegate
        presenter.present(picker, animated: true)
    }

    // MARK: - File saver

    /// Mirrors NativeDeviceBridge.kt's own savefile in effect, not
    /// mechanism — Android's MediaStore.Downloads (API 29+, scoped
    /// storage, no WRITE_EXTERNAL_STORAGE) writes somewhere the Files
    /// app/other apps can browse directly; iOS's own sandbox has no
    /// equivalent shared "Downloads" location an app can write into
    /// unprompted. The closest same-shape iOS analog is this app's own
    /// Documents directory, made externally browsable via Info.plist's
    /// UIFileSharingEnabled/LSSupportsOpeningDocumentsInPlace — a real
    /// platform difference (only visible inside THIS app's own folder in
    /// Files, not a device-wide Downloads folder), not a narrower port
    /// of the same capability.
    public static func saveFile(fileName: String, content: String) -> String {
        guard let documentsURL = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask).first else {
            return "Erreur d'enregistrement"
        }
        let fileURL = documentsURL.appendingPathComponent((fileName as NSString).lastPathComponent)
        do {
            try content.write(to: fileURL, atomically: true, encoding: .utf8)
            return "Enregistré"
        } catch {
            return error.localizedDescription
        }
    }

    // MARK: - App links (deep link)

    /// Mirrors NativeDeviceBridge.kt's own applink — Android reads the
    /// current Intent's own data URI directly (kept current via
    /// onNewIntent()'s own setIntent() call). iOS has no equivalent
    /// "ask the OS for the URL that's already open" — the only way to
    /// learn a launch/open URL at all is a UIApplicationDelegate/
    /// UISceneDelegate callback firing once, at the moment it happens,
    /// so this needs to be told, not asked: AppDelegate calls
    /// recordAppLink(_:) from both didFinishLaunchingWithOptions's own
    /// launchOptions[.url] and application(_:open:options:), and this
    /// just remembers the last one — same "full URI string, or 'Aucun
    /// lien'" result AppLinks.php's own docblock documents.
    public static var lastAppLink = "Aucun lien"

    public static func recordAppLink(_ url: URL) {
        lastAppLink = url.absoluteString
    }

    // MARK: - Camera

    /// Retained for the lifetime of one picker presentation, same
    /// reasoning as ImagePickerDelegate/FilePickerDelegate above.
    /// Mirrors NativeDeviceBridge.kt's own takePicturePreview launcher —
    /// UIImagePickerController's .camera source is the direct iOS
    /// equivalent, same "the system camera app handles its own
    /// permission" contract Camera.php's own docblock documents (no
    /// NSCameraUsageDescription check needed here beyond what the OS
    /// itself already prompts for the first time this runs).
    private final class CameraDelegate: NSObject, UIImagePickerControllerDelegate, UINavigationControllerDelegate {
        private let completion: (String) -> Void

        init(completion: @escaping (String) -> Void) {
            self.completion = completion
        }

        func imagePickerController(_ picker: UIImagePickerController, didFinishPickingMediaWithInfo info: [UIImagePickerController.InfoKey: Any]) {
            picker.dismiss(animated: true)
            NativeDeviceBridge.pendingCameraPicker = nil
            guard let image = info[.originalImage] as? UIImage else {
                completion("Erreur")
                return
            }
            completion("Photo capturée (\(Int(image.size.width))x\(Int(image.size.height)))")
        }

        func imagePickerControllerDidCancel(_ picker: UIImagePickerController) {
            picker.dismiss(animated: true)
            NativeDeviceBridge.pendingCameraPicker = nil
            completion("Annulé")
        }
    }

    private static var pendingCameraPicker: CameraDelegate?

    public static func capturePhoto(from presenter: UIViewController, completion: @escaping (String) -> Void) {
        guard UIImagePickerController.isSourceTypeAvailable(.camera) else {
            completion("Aucune caméra disponible")
            return
        }
        let delegate = CameraDelegate(completion: completion)
        pendingCameraPicker = delegate

        let picker = UIImagePickerController()
        picker.sourceType = .camera
        picker.delegate = delegate
        presenter.present(picker, animated: true)
    }

    // MARK: - Microphone

    /// Held for the lifetime of one timed recording — a local `let`
    /// would be deallocated (and its underlying file/session torn down)
    /// before the timer even fires, same "must outlive the async call"
    /// reasoning as every other holder in this file.
    private static var activeRecorder: AVAudioRecorder?

    /// Mirrors NativeDeviceBridge.kt's own recordAudioClip() — unlike
    /// contactsCount()/getLocation()/every other permission-gated read
    /// in this file (check, never request), VoiceRecorder.php's own
    /// docblock documents mic as the ONE capability whose Android
    /// counterpart prompts for RECORD_AUDIO inline as part of this same
    /// action, not a separate Permission::requestAction() step — this
    /// mirrors that exact contract with AVAudioSession's own
    /// requestRecordPermission(_:).
    public static func recordAudioClip(durationMs: Int, completion: @escaping (String) -> Void) {
        func startRecording() {
            let url = FileManager.default.temporaryDirectory.appendingPathComponent("phpnitro-mic-\(UUID().uuidString).m4a")
            let settings: [String: Any] = [
                AVFormatIDKey: kAudioFormatMPEG4AAC,
                AVSampleRateKey: 44100,
                AVNumberOfChannelsKey: 1,
            ]
            guard let recorder = try? AVAudioRecorder(url: url, settings: settings) else {
                completion("Erreur d'enregistrement")
                return
            }
            activeRecorder = recorder
            recorder.record()
            DispatchQueue.main.asyncAfter(deadline: .now() + Double(durationMs) / 1000) {
                recorder.stop()
                activeRecorder = nil
                completion("Enregistré (\(durationMs)ms)")
            }
        }

        switch AVAudioSession.sharedInstance().recordPermission {
        case .granted:
            startRecording()
        case .denied:
            completion("permission_denied")
        case .undetermined:
            AVAudioSession.sharedInstance().requestRecordPermission { granted in
                DispatchQueue.main.async {
                    granted ? startRecording() : completion("permission_denied")
                }
            }
        @unknown default:
            completion("permission_denied")
        }
    }

    // MARK: - Geofence

    /// One shared manager for every registered region — CLLocationManager
    /// itself is the object that owns "which regions am I monitoring",
    /// so a single instance (not one per add/remove call) is required
    /// for startMonitoring(for:)/stopMonitoring(for:) to see each
    /// other's state at all.
    private static let geofenceManager = CLLocationManager()

    /// Mirrors NativeDeviceBridge.kt's own addGeofence() — same "check,
    /// never request" contract Geofence.php's own docblock documents
    /// (a missing grant is a silent no-op, not a crash or a prompt).
    /// CLCircularRegion is the direct iOS equivalent of Android's own
    /// Geofence + GeofencingRequest pair; `identifier` plays the exact
    /// role $id does — the same string passed to removeGeofence(_:)
    /// removes this specific region, not "the most recent one".
    public static func addGeofence(id: String, latitude: Double, longitude: Double, radiusMeters: Double) {
        let status = geofenceManager.authorizationStatus
        guard status == .authorizedWhenInUse || status == .authorizedAlways else { return }
        guard CLLocationManager.isMonitoringAvailable(for: CLCircularRegion.self) else { return }
        let center = CLLocationCoordinate2D(latitude: latitude, longitude: longitude)
        let region = CLCircularRegion(center: center, radius: radiusMeters, identifier: id)
        region.notifyOnEntry = true
        region.notifyOnExit = true
        geofenceManager.startMonitoring(for: region)
    }

    public static func removeGeofence(id: String) {
        guard let region = geofenceManager.monitoredRegions.first(where: { $0.identifier == id }) else { return }
        geofenceManager.stopMonitoring(for: region)
    }
}

private extension Comparable {
    func clamped(to range: ClosedRange<Self>) -> Self {
        min(max(self, range.lowerBound), range.upperBound)
    }
}
