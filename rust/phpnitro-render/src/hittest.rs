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
use serde::Deserialize;
use serde_json::Value;
use std::collections::HashMap;

/// Deserializable so the FFI boundary can accept this as one JSON object
/// (`{"scrollY":..,"activePanel":{...},"axisOffset":{...}}`) rather than
/// requiring a platform shell to build it field-by-field through more FFI
/// calls — an empty/absent JSON object decodes to `Self::default()`.
#[derive(Debug, Clone, Default, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct InteractionState {
    /// The whole screen's vertical scroll offset — added to every
    /// non-`fixed` region's tap comparison, exactly like Android's
    /// `scrollY`.
    #[serde(default)]
    pub scroll_y: f32,
    /// `clientPanel.key -> currently active panel index`. A key with no
    /// entry here falls back to whichever panel command declares
    /// `initiallyActive: true` — the exact same fallback
    /// `seedClientTabState()` uses on Android.
    #[serde(default)]
    pub active_panel: HashMap<String, i64>,
    /// `hScroll`/`vScroll` `.key -> local drag offset` (same key space for
    /// both, since a screen using both would use distinct keys anyway).
    #[serde(default)]
    pub axis_offset: HashMap<String, f32>,
    /// `slider.key -> live drag value (0..1)`, overriding
    /// `SliderCommand.value` while the user is actively dragging the
    /// thumb — kept as its own map rather than folded into `axis_offset`,
    /// which is a content-space distance, not a normalized fraction.
    #[serde(default)]
    pub slider_value: HashMap<String, f32>,
}

/// `clientPanel.key`/`.index` vs. live state — a key with no entry falls
/// back to `initiallyActive`, the same fallback `seedClientTabState()`
/// uses on Android. Shared between hit-testing and painting so this
/// correlation exists in exactly one place, not re-derived per caller.
pub fn is_client_panel_active(state: &InteractionState, key: &str, index: i64, initially_active: bool) -> bool {
    match state.active_panel.get(key) {
        Some(active_index) => *active_index == index,
        None => initially_active,
    }
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
            if !is_client_panel_active(state, &panel.key, panel.index, panel.initially_active) {
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

/// A tap landing inside a slider's own touch box — `value` is already the
/// live position-derived value (the exact inverse of `raster.rs`'s own
/// `draw_slider`'s `thumb_cx` formula), not the region's server-authored
/// resting `value`. `action`/`key` are the region's own, unmodified — a
/// caller commits `action` (with the final value as its own `meta.next`,
/// e.g. `{"next": "0.819"}`) whenever ITS OWN gesture model decides the
/// drag/tap is done; this crate has no opinion on when that is.
#[derive(Debug, Clone, PartialEq)]
pub struct SliderHit {
    pub key: String,
    pub action: String,
    pub value: f32,
}

/// Finds the first `sliderRegions[]` entry a tap at `(tap_x, tap_y)` lands
/// on, mirroring `NativeCanvasView.kt`'s `hitTestSlider()` +
/// `sliderValueForTouch()` exactly: a plain linear scan (first match
/// wins, same as every other region type here), `tap_y` always shifted
/// by `state.scroll_y` — unlike `hit_test()`'s own regions, a slider
/// region carries no `fixed` tag to check, and (also matching Android)
/// no `clientPanel`/`hScroll`/`vScroll` offset adjustment is applied
/// either, since `sliderRegions[]` only ever gets this one whole-page
/// scroll treatment on the one real-device-verified implementation this
/// crate treats as the spec.
pub fn slider_hit_test(envelope: &Envelope, tap_x: f32, tap_y: f32, state: &InteractionState) -> Option<SliderHit> {
    let touch_y = tap_y + state.scroll_y;
    for region in &envelope.slider_regions {
        let (left, top, width, height) = (region.x as f32, region.y as f32, region.width as f32, region.height as f32);
        if !rect_contains(left, top, width, height, tap_x, touch_y) {
            continue;
        }
        let thumb_size = region.thumb_size as f32;
        let track_width = (width - thumb_size).max(1.0);
        let value = ((tap_x - left - thumb_size / 2.0) / track_width).clamp(0.0, 1.0);
        return Some(SliderHit { key: region.key.clone(), action: region.action.clone(), value });
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

    // The exact sliderRegions[] entry from packages/ui/tests/Golden/
    // __fixtures__/screen_widgets_forms.json (real PHP output, not
    // hand-authored): {"key":"volume","x":20,"y":592.5,"width":360,
    // "height":44,"trackHeight":6,"thumbSize":22,"value":0.5,
    // "action":"toggle:volume"}. track_width = 360 - 22 = 338.
    const SLIDER_ENVELOPE: &str = r#"{"commands":[],"hitRegions":[],"contentHeight":0,
        "sliderRegions":[{"key":"volume","x":20,"y":592.5,"width":360,"height":44,
        "trackHeight":6,"thumbSize":22,"value":0.5,"action":"toggle:volume"}]}"#;

    #[test]
    fn slider_hit_test_at_the_tracks_own_start_computes_zero() {
        let e = envelope(SLIDER_ENVELOPE);
        let hit = slider_hit_test(&e, 31.0, 610.0, &InteractionState::default()).unwrap();
        assert_eq!(hit.key, "volume");
        assert_eq!(hit.action, "toggle:volume");
        assert!((hit.value - 0.0).abs() < 1e-6);
    }

    #[test]
    fn slider_hit_test_at_the_tracks_own_end_computes_one() {
        let e = envelope(SLIDER_ENVELOPE);
        let hit = slider_hit_test(&e, 369.0, 610.0, &InteractionState::default()).unwrap();
        assert!((hit.value - 1.0).abs() < 1e-6);
    }

    #[test]
    fn slider_hit_test_at_the_tracks_own_midpoint_computes_one_half() {
        let e = envelope(SLIDER_ENVELOPE);
        let hit = slider_hit_test(&e, 200.0, 610.0, &InteractionState::default()).unwrap();
        assert!((hit.value - 0.5).abs() < 1e-6);
    }

    #[test]
    fn slider_hit_test_outside_its_touch_box_returns_none() {
        let e = envelope(SLIDER_ENVELOPE);
        assert!(slider_hit_test(&e, 10.0, 610.0, &InteractionState::default()).is_none(), "left of the touch box");
        assert!(slider_hit_test(&e, 200.0, 700.0, &InteractionState::default()).is_none(), "below the touch box");
    }

    #[test]
    fn slider_hit_test_shifts_with_the_whole_page_scroll_offset() {
        let e = envelope(SLIDER_ENVELOPE);
        // The region sits at content-y=592.5..636.5; scrolled up 500px,
        // its screen-space top becomes y=92.5.
        let state = InteractionState { scroll_y: 500.0, ..Default::default() };
        let hit = slider_hit_test(&e, 200.0, 100.0, &state).unwrap();
        assert!((hit.value - 0.5).abs() < 1e-6);
    }
}
