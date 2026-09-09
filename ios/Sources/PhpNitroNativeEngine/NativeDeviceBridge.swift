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
}
