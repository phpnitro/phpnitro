//! Pure wall-clock math for `spinner`/`skeleton` — the "Class A" animation
//! from the protocol research: neither command carries a rotation/phase
//! field on the wire, every existing renderer independently derives its
//! current animation state from `now % period`. Constants copied verbatim
//! from the two reference implementations (Android's `NativeCanvasView.kt`
//! is the real-device-verified one; Linux's `canvas.py` independently
//! arrived at the same numbers) rather than re-guessed.
//!
//! Deliberately just math here, no `Pixmap` involved — wiring this into
//! `raster.rs`'s actual spinner/skeleton drawing is a separate, later
//! step. A caller only needs to supply an `elapsed_ms: u64` (wall-clock
//! milliseconds since any fixed epoch the caller chooses — the formulas
//! only ever look at it modulo the period, so the epoch itself doesn't
//! matter, only that it's monotonic and shared across consecutive frames
//! of the same screen).

/// `NativeCanvasView.kt::drawSpinnerCommand()`: `val periodMs = 1100f`.
pub const SPINNER_PERIOD_MS: u64 = 1100;
/// Same function: `canvas.drawArc(rect, rotation, 110f, false, sweepPaint)`.
pub const SPINNER_SWEEP_DEGREES: f32 = 110.0;

/// `NativeCanvasView.kt::drawSkeletonCommand()`: `val periodMs = 1300f`.
pub const SKELETON_PERIOD_MS: u64 = 1300;
/// Same function: `val sweepWidth = (w * 0.6f).coerceAtLeast(1f)`.
pub const SKELETON_SWEEP_WIDTH_RATIO: f32 = 0.6;

/// Current rotation of the spinner's sweeping arc, in degrees, matching
/// Android's `(uptimeMillis % periodMs.toLong()) / periodMs * 360f`
/// exactly (integer modulo first, then float division — replicated in
/// that order so rounding behaves identically at the same instants).
pub fn spinner_rotation_degrees(elapsed_ms: u64) -> f32 {
    let phase = (elapsed_ms % SPINNER_PERIOD_MS) as f32 / SPINNER_PERIOD_MS as f32;
    phase * 360.0
}

/// Width of the skeleton's shimmer band, clamped to at least 1px so a
/// near-zero-width skeleton box still gets a visible (if tiny) highlight
/// instead of a degenerate zero-width gradient.
pub fn skeleton_sweep_width(box_width: f32) -> f32 {
    (box_width * SKELETON_SWEEP_WIDTH_RATIO).max(1.0)
}

/// Left edge of the shimmer band's current position, sweeping from just
/// off the box's left edge to just past its right edge and looping —
/// matches `sweepX = x - sweepWidth + (w + sweepWidth) * phase` exactly.
pub fn skeleton_sweep_x(elapsed_ms: u64, box_x: f32, box_width: f32) -> f32 {
    let sweep_width = skeleton_sweep_width(box_width);
    let phase = (elapsed_ms % SKELETON_PERIOD_MS) as f32 / SKELETON_PERIOD_MS as f32;
    box_x - sweep_width + (box_width + sweep_width) * phase
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn spinner_is_at_zero_degrees_at_the_start_of_a_cycle() {
        assert_eq!(spinner_rotation_degrees(0), 0.0);
        assert_eq!(spinner_rotation_degrees(SPINNER_PERIOD_MS), 0.0, "a full period wraps back to 0");
    }

    #[test]
    fn spinner_is_halfway_at_half_the_period() {
        let rotation = spinner_rotation_degrees(SPINNER_PERIOD_MS / 2);
        assert!((rotation - 180.0).abs() < 0.01, "expected ~180deg, got {rotation}");
    }

    #[test]
    fn spinner_rotation_never_reaches_a_full_360() {
        // Sampling just before the period boundary should stay just under
        // 360, never AT or past it (would double-draw the same angle).
        let rotation = spinner_rotation_degrees(SPINNER_PERIOD_MS - 1);
        assert!(rotation < 360.0);
        assert!(rotation > 359.0);
    }

    #[test]
    fn skeleton_sweep_starts_just_off_the_left_edge() {
        let x = skeleton_sweep_x(0, 100.0, 50.0);
        let width = skeleton_sweep_width(50.0);
        assert_eq!(x, 100.0 - width);
    }

    #[test]
    fn skeleton_sweep_ends_just_past_the_right_edge() {
        let x = skeleton_sweep_x(SKELETON_PERIOD_MS - 1, 100.0, 50.0);
        let width = skeleton_sweep_width(50.0);
        // At phase ~1.0 (not quite, one ms short of the period), the band
        // should be almost all the way to box_x + box_width.
        let end = x + width;
        assert!(end > 100.0 + 50.0 - 1.0, "band should have almost fully crossed the box, got end={end}");
    }

    #[test]
    fn skeleton_sweep_width_never_degenerates_to_zero() {
        assert_eq!(skeleton_sweep_width(0.0), 1.0);
        assert_eq!(skeleton_sweep_width(1.0), 1.0, "0.6 * 1.0 = 0.6, clamped up to 1.0");
    }

    #[test]
    fn skeleton_sweep_width_is_60_percent_of_the_box_for_wide_boxes() {
        assert!((skeleton_sweep_width(100.0) - 60.0).abs() < 0.001);
    }
}
