//! Hit-testing — walks `hitRegions[]` and the nested regions inside
//! `clientPanel`/`hScroll`/`vScroll` commands to find which action a tap
//! lands on. Ported field-for-field from `NativeCanvasView.kt`'s
//! `handleTap()`/`handleClientPanelTap()`/`handleHScrollTap()`/
//! `handleVScrollTap()` — the only real-device-verified implementation of
//! this behavior, so its exact order and offset math is treated as the
//! spec, not re-derived from the (looser, sometimes self-contradicting)
//! prose in other platforms' docblocks.
//!
//! Order, confirmed by reading the Kotlin source directly rather than
//! trusting its own docblocks (one of which claims "last wins" while the
//! code itself returns on the FIRST match): top-level `hitRegions[]` in
//! forward order, then `clientPanel` (only the currently active panel per
//! `key`), then `hScroll`, then `vScroll` — first match anywhere in that
//! order wins.
//!
//! Caller-owned interaction state (`scroll_y`, which `clientPanel` is
//! active per group, each `hScroll`/`vScroll`'s local drag offset) is
//! passed in explicitly rather than tracked here — this crate has no
//! persistent per-screen state of its own, matching every other module
//! in it.

use crate::protocol::{DrawCommand, Envelope};
use serde_json::Value;
use std::collections::HashMap;

#[derive(Debug, Clone, Default)]
pub struct InteractionState {
    /// The whole screen's vertical scroll offset — added to every
    /// non-`fixed` region's tap comparison, exactly like Android's
    /// `scrollY`.
    pub scroll_y: f32,
    /// `clientPanel.key -> currently active panel index`. A key with no
    /// entry here falls back to whichever panel command declares
    /// `initiallyActive: true` — the exact same fallback
    /// `seedClientTabState()` uses on Android.
    pub active_panel: HashMap<String, i64>,
    /// `hScroll`/`vScroll` `.key -> local drag offset` (same key space for
    /// both, since a screen using both would use distinct keys anyway).
    pub axis_offset: HashMap<String, f32>,
}

#[derive(Debug, Clone, PartialEq)]
pub struct HitResult {
    pub action: String,
    pub meta: Option<Value>,
    /// `(left, top, right, bottom)`, the same rect shape `onAction`
    /// receives on Android — useful for e.g. a ripple/haptic-feedback
    /// origin point.
    pub rect: (f32, f32, f32, f32),
}

fn touch_y_for(fixed: Option<bool>, tap_y: f32, scroll_y: f32) -> f32 {
    if fixed == Some(true) {
        tap_y
    } else {
        tap_y + scroll_y
    }
}

fn rect_contains(left: f32, top: f32, width: f32, height: f32, tap_x: f32, touch_y: f32) -> bool {
    tap_x >= left && tap_x <= left + width && touch_y >= top && touch_y <= top + height
}

/// Finds the first action a tap at `(tap_x, tap_y)` lands on, or `None` if
/// it hits nothing — a tap on empty space is a normal outcome, not an
/// error, matching `phpnitro_render_hit_test`'s planned FFI contract of
/// returning NULL for "nothing hit" rather than an error code.
pub fn hit_test(envelope: &Envelope, tap_x: f32, tap_y: f32, state: &InteractionState) -> Option<HitResult> {
    for region in &envelope.hit_regions {
        let touch_y = touch_y_for(region.tags.fixed, tap_y, state.scroll_y);
        let (left, top, width, height) = (region.x as f32, region.y as f32, region.width as f32, region.height as f32);
        if rect_contains(left, top, width, height, tap_x, touch_y) {
            return Some(HitResult {
                action: region.action.clone(),
                meta: region.meta.clone(),
                rect: (left, top, left + width, top + height),
            });
        }
    }

    for command in &envelope.commands {
        if let DrawCommand::ClientPanel(panel) = command {
            let is_active = match state.active_panel.get(&panel.key) {
                Some(active_index) => *active_index == panel.index,
                None => panel.initially_active,
            };
            if !is_active {
                continue;
            }
            let touch_y = touch_y_for(panel.tags.fixed, tap_y, state.scroll_y);
            let (offset_x, offset_y) = (panel.x as f32, panel.y as f32);
            for region in &panel.hit_regions {
                let left = offset_x + region.x as f32;
                let top = offset_y + region.y as f32;
                let (width, height) = (region.width as f32, region.height as f32);
                if rect_contains(left, top, width, height, tap_x, touch_y) {
                    return Some(HitResult {
                        action: region.action.clone(),
                        meta: region.meta.clone(),
                        rect: (left, top, left + width, top + height),
                    });
                }
            }
        }
    }

    for command in &envelope.commands {
        if let DrawCommand::HScroll(scroll) = command {
            let touch_y = touch_y_for(scroll.tags.fixed, tap_y, state.scroll_y);
            let (vx, vy, vw, vh) = (scroll.x as f32, scroll.y as f32, scroll.width as f32, scroll.height as f32);
            if tap_x < vx || tap_x > vx + vw || touch_y < vy || touch_y > vy + vh {
                continue;
            }
            let offset = state.axis_offset.get(&scroll.key).copied().unwrap_or(0.0);
            let offset_x = vx - offset;
            for region in &scroll.hit_regions {
                let left = offset_x + region.x as f32;
                let top = vy + region.y as f32;
                let (width, height) = (region.width as f32, region.height as f32);
                if rect_contains(left, top, width, height, tap_x, touch_y) {
                    return Some(HitResult {
                        action: region.action.clone(),
                        meta: region.meta.clone(),
                        rect: (left, top, left + width, top + height),
                    });
                }
            }
        }
    }

    for command in &envelope.commands {
        if let DrawCommand::VScroll(scroll) = command {
            let touch_y = touch_y_for(scroll.tags.fixed, tap_y, state.scroll_y);
            let (vx, vy, vw, vh) = (scroll.x as f32, scroll.y as f32, scroll.width as f32, scroll.height as f32);
            if tap_x < vx || tap_x > vx + vw || touch_y < vy || touch_y > vy + vh {
                continue;
            }
            let offset = state.axis_offset.get(&scroll.key).copied().unwrap_or(0.0);
            let offset_y = vy - offset;
            for region in &scroll.hit_regions {
                let left = vx + region.x as f32;
                let top = offset_y + region.y as f32;
                let (width, height) = (region.width as f32, region.height as f32);
                if rect_contains(left, top, width, height, tap_x, touch_y) {
                    return Some(HitResult {
                        action: region.action.clone(),
                        meta: region.meta.clone(),
                        rect: (left, top, left + width, top + height),
                    });
                }
            }
        }
    }

    None
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::protocol::decode_envelope;

    fn envelope(json: &str) -> Envelope {
        decode_envelope(json).unwrap()
    }

    #[test]
    fn taps_a_plain_top_level_region() {
        let e = envelope(
            r#"{"commands":[],"hitRegions":[{"x":0,"y":0,"width":100,"height":40,"action":"submit:demo"}],"contentHeight":0}"#,
        );
        let hit = hit_test(&e, 50.0, 20.0, &InteractionState::default());
        assert_eq!(hit.unwrap().action, "submit:demo");
    }

    #[test]
    fn misses_just_outside_the_region_bounds() {
        let e = envelope(
            r#"{"commands":[],"hitRegions":[{"x":0,"y":0,"width":100,"height":40,"action":"submit:demo"}],"contentHeight":0}"#,
        );
        assert!(hit_test(&e, 50.0, 41.0, &InteractionState::default()).is_none());
    }

    #[test]
    fn first_match_wins_when_two_regions_overlap() {
        let e = envelope(
            r#"{"commands":[],"hitRegions":[
                {"x":0,"y":0,"width":100,"height":100,"action":"first"},
                {"x":0,"y":0,"width":100,"height":100,"action":"second"}
            ],"contentHeight":0}"#,
        );
        assert_eq!(hit_test(&e, 50.0, 50.0, &InteractionState::default()).unwrap().action, "first");
    }

    #[test]
    fn non_fixed_region_moves_with_scroll_offset() {
        let e = envelope(
            r#"{"commands":[],"hitRegions":[{"x":0,"y":100,"width":100,"height":40,"action":"item"}],"contentHeight":0}"#,
        );
        let state = InteractionState { scroll_y: 100.0, ..Default::default() };
        // The region sits at y=100 in content space; after scrolling 100px
        // up, its screen-space top is at y=0.
        assert_eq!(hit_test(&e, 50.0, 10.0, &state).unwrap().action, "item");
    }

    #[test]
    fn fixed_region_ignores_scroll_offset() {
        let e = envelope(
            r#"{"commands":[],"hitRegions":[{"x":0,"y":10,"width":100,"height":40,"action":"appbar","fixed":true}],"contentHeight":0}"#,
        );
        let state = InteractionState { scroll_y: 500.0, ..Default::default() };
        assert_eq!(hit_test(&e, 50.0, 20.0, &state).unwrap().action, "appbar");
    }

    #[test]
    fn client_panel_falls_back_to_initially_active_with_no_explicit_state() {
        let e = envelope(
            r#"{"commands":[
                {"type":"clientPanel","key":"tabs","index":0,"initiallyActive":true,"x":0,"y":0,
                 "commands":[],"hitRegions":[{"x":0,"y":0,"width":50,"height":50,"action":"panel0-action"}]},
                {"type":"clientPanel","key":"tabs","index":1,"initiallyActive":false,"x":0,"y":0,
                 "commands":[],"hitRegions":[{"x":0,"y":0,"width":50,"height":50,"action":"panel1-action"}]}
            ],"hitRegions":[],"contentHeight":0}"#,
        );
        assert_eq!(hit_test(&e, 10.0, 10.0, &InteractionState::default()).unwrap().action, "panel0-action");
    }

    #[test]
    fn client_panel_explicit_state_overrides_initially_active() {
        let e = envelope(
            r#"{"commands":[
                {"type":"clientPanel","key":"tabs","index":0,"initiallyActive":true,"x":0,"y":0,
                 "commands":[],"hitRegions":[{"x":0,"y":0,"width":50,"height":50,"action":"panel0-action"}]},
                {"type":"clientPanel","key":"tabs","index":1,"initiallyActive":false,"x":0,"y":0,
                 "commands":[],"hitRegions":[{"x":0,"y":0,"width":50,"height":50,"action":"panel1-action"}]}
            ],"hitRegions":[],"contentHeight":0}"#,
        );
        let mut state = InteractionState::default();
        state.active_panel.insert("tabs".to_string(), 1);
        assert_eq!(hit_test(&e, 10.0, 10.0, &state).unwrap().action, "panel1-action");
    }

    #[test]
    fn client_panel_nested_region_is_offset_by_panel_origin() {
        let e = envelope(
            r#"{"commands":[
                {"type":"clientPanel","key":"sheet","index":0,"initiallyActive":true,"x":20.0,"y":30.0,
                 "commands":[],"hitRegions":[{"x":5.0,"y":5.0,"width":40.0,"height":40.0,"action":"nested"}]}
            ],"hitRegions":[],"contentHeight":0}"#,
        );
        // The nested region's absolute position should be (20+5, 30+5) to (65, 75).
        assert_eq!(hit_test(&e, 30.0, 40.0, &InteractionState::default()).unwrap().action, "nested");
        assert!(hit_test(&e, 3.0, 3.0, &InteractionState::default()).is_none(), "outside the offset region");
    }

    #[test]
    fn hscroll_ignores_taps_outside_its_own_viewport() {
        let e = envelope(
            r#"{"commands":[
                {"type":"hScroll","key":"rail","x":0.0,"y":0.0,"width":100.0,"height":60.0,"contentWidth":300.0,
                 "commands":[],"hitRegions":[{"x":0.0,"y":0.0,"width":60.0,"height":60.0,"action":"card0"}]}
            ],"hitRegions":[],"contentHeight":0}"#,
        );
        assert!(hit_test(&e, 200.0, 30.0, &InteractionState::default()).is_none(), "past the 100px viewport width");
    }

    #[test]
    fn hscroll_nested_region_shifts_with_the_local_drag_offset() {
        let e = envelope(
            r#"{"commands":[
                {"type":"hScroll","key":"rail","x":0.0,"y":0.0,"width":100.0,"height":60.0,"contentWidth":300.0,
                 "commands":[],"hitRegions":[{"x":150.0,"y":0.0,"width":60.0,"height":60.0,"action":"card2"}]}
            ],"hitRegions":[],"contentHeight":0}"#,
        );
        // Card 2 sits at local x=150, out of the 100px viewport unless the
        // rail has been dragged left by 100px (offset=100).
        let mut state = InteractionState::default();
        state.axis_offset.insert("rail".to_string(), 100.0);
        assert_eq!(hit_test(&e, 60.0, 30.0, &state).unwrap().action, "card2");
    }

    #[test]
    fn vscroll_nested_region_shifts_with_the_local_drag_offset() {
        let e = envelope(
            r#"{"commands":[
                {"type":"vScroll","key":"feed","x":0.0,"y":0.0,"width":100.0,"height":60.0,"contentHeight":300.0,
                 "commands":[],"hitRegions":[{"x":0.0,"y":150.0,"width":100.0,"height":60.0,"action":"row2"}]}
            ],"hitRegions":[],"contentHeight":0}"#,
        );
        // Row 2 sits at local y=150..210. Scrolled up by 130px, its
        // screen-space position becomes y=20..80 — overlapping the
        // viewport (0..60) in the y=20..60 band.
        let mut state = InteractionState::default();
        state.axis_offset.insert("feed".to_string(), 130.0);
        assert_eq!(hit_test(&e, 50.0, 30.0, &state).unwrap().action, "row2");
    }

    #[test]
    fn no_match_anywhere_returns_none_not_a_panic() {
        let e = envelope(r#"{"commands":[],"hitRegions":[],"contentHeight":0}"#);
        assert!(hit_test(&e, 999.0, 999.0, &InteractionState::default()).is_none());
    }
}
