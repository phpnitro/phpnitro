import AVFoundation
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
}
