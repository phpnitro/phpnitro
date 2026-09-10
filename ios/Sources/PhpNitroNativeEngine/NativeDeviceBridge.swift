import AVFoundation
import Contacts
import EventKit
import Network
import Security
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
}

private extension Comparable {
    func clamped(to range: ClosedRange<Self>) -> Self {
        min(max(self, range.lowerBound), range.upperBound)
    }
}
