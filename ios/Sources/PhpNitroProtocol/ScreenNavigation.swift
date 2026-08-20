import Foundation

/// The iOS counterpart of the action-dispatch `when` block at the bottom
/// of NativeRenderPocActivity.kt's own tap handling — deliberately the
/// MINIMAL slice: `navigate:`/`tab:`/`back`/`clientTab:`/`toggle:` and the
/// plain fallback (any other action refetches the current screen with
/// it). Missing on purpose (real, separate follow-up work): deep links,
/// dialogs, device-capability actions (vibrate, biometrics, ...), OAuth,
/// `submit:`/date-time pickers — anything NativeRenderPocActivity itself
/// handles via a native API this framework hasn't ported to iOS yet
/// rather than via this navigation switch.
///
/// A pure function, not a method on NativeScreenViewController — same
/// reasoning HostPort.parse(_:) already follows: the actual decision
/// (what should the stack become, what should be (re)fetched) is fully
/// unit-testable without a UIViewController, a network call, or a
/// simulator.
public enum ScreenNavigationResult: Equatable {
    /// `clientTab:key:index` — a ClientTabs tab switch, entirely local
    /// (see NativeCanvasView.setClientTab(_:index:)), no fetch at all.
    /// Mirrors NativeRenderPocActivity.kt's own `clientTab:` branch,
    /// which calls `canvasView.setClientTab(...)` and nothing else.
    case clientTabOnly(key: String, index: Int)
    /// `toggle:name` (Checkbox/Toggle/Slider's shared commit action, see
    /// `packages/ui/src/Native/Checkbox.php`/`Slider.php`) — a local
    /// `fieldValues[name] = value` update followed by a same-screen
    /// refetch with no `action` param, mirroring
    /// NativeRenderPocActivity.kt's own generic `"toggle:"` handler
    /// exactly (`fieldValues[name] = meta.next; refetch(action = null,
    /// includeFields = true)`). Only ever produced when the caller passes
    /// a real `metaJson` to `reduce(...)` containing a `"next"` key — a
    /// caller that never passes one (no meta source available) keeps
    /// falling through to the generic `.fetch` case below, unchanged.
    case fieldUpdate(key: String, value: String)
    /// Everything else ends in a fetch — `stack` is what the screen
    /// stack should become BEFORE fetching (already pushed/popped/reset
    /// for navigate:/tab:/back), `action` is what to pass to
    /// ScreenClient.fetchScreen(_:action:...) (nil for navigate:/tab:/
    /// back, which always fetch fresh; the original action string for
    /// the plain fallback case).
    case fetch(stack: [String], action: String?)
}

public enum ScreenNavigation {
    /// `metaJson` is the tapped hit region's own `meta` object as a JSON
    /// string (or, for a slider, a caller-synthesized `{"next":"<value
    /// formatted to 3 decimals>"}, see `RustSliderHitTest`'s own
    /// consumers) — needed only to resolve a `toggle:` action's value.
    /// Omit it (the default) for a caller with no meta source at all;
    /// `toggle:` then falls through to the generic `.fetch` case exactly
    /// as it always has, unchanged.
    public static func reduce(action: String, stack: [String], metaJson: String? = nil) -> ScreenNavigationResult {
        if action.hasPrefix("clientTab:") {
            let parts = action.dropFirst("clientTab:".count).split(separator: ":", maxSplits: 1)
            if parts.count == 2, let index = Int(parts[1]) {
                return .clientTabOnly(key: String(parts[0]), index: index)
            }
            return .fetch(stack: stack, action: nil)
        }

        if action.hasPrefix("toggle:"), let metaJson, let next = nextValue(fromMetaJSON: metaJson) {
            return .fieldUpdate(key: String(action.dropFirst("toggle:".count)), value: next)
        }

        if action.hasPrefix("navigate:") {
            return .fetch(stack: stack + [String(action.dropFirst("navigate:".count))], action: nil)
        }

        // A BottomNavigation tab switch — resets the whole stack to that
        // one screen instead of pushing, so hopping between tabs
        // repeatedly doesn't grow an ever-longer back stack the way
        // drilling into a real detail screen should.
        if action.hasPrefix("tab:") {
            return .fetch(stack: [String(action.dropFirst("tab:".count))], action: nil)
        }

        if action == "back" {
            let newStack = stack.count > 1 ? Array(stack.dropLast()) : stack
            return .fetch(stack: newStack, action: nil)
        }

        return .fetch(stack: stack, action: action)
    }

    /// Extracts `meta.next` from a hit region's meta JSON (e.g.
    /// `{"next":"1"}` — see `Checkbox.php`'s own docblock) as a string,
    /// same loose `optString("next", "")`-style tolerance
    /// NativeRenderPocActivity.kt's own reader has: a present-but-empty
    /// `next` still counts (an unchecked Checkbox's own `next` IS ""),
    /// only a missing/malformed meta blob returns nil.
    private static func nextValue(fromMetaJSON metaJson: String) -> String? {
        guard let data = metaJson.data(using: .utf8),
              let object = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            return nil
        }
        if let next = object["next"] as? String { return next }
        if let next = object["next"] { return "\(next)" }
        return nil
    }
}
