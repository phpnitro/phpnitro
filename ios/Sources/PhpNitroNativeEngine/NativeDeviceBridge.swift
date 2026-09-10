import AVFoundation
import Contacts
import Security
import UIKit

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
}
