//! Crossfade + hero (FLIP) transitions between two envelopes — the Rust
//! port of `NativeCanvasView.kt`'s `startCrossfade()`/`onDraw()`'s two-pass
//! draw/`startHeroTransition()`/`drawHeroTransition()`/`drawInterpolated()`.
//! Every constant/formula below is copied from that file's real behavior
//! (not re-derived) — AOSP's own stock `Interpolator`/`ArgbEvaluator`
//! classes have no source in this repo either, so their formulas are
//! hand-transcribed from the documented Android SDK behavior those classes
//! implement.
//!
//! Unlike `raster.rs`'s per-primitive drawing, this module works at the
//! WHOLE-FRAME level: it renders each pass (outgoing envelope, incoming
//! envelope, each flying hero subtree) into its own same-size scratch
//! `Pixmap` via the existing `render_commands()`, then composites each
//! layer onto the real destination via `draw_pixmap` — opacity for the
//! ordinary crossfade passes, a translate+scale `Transform` for hero
//! flights. Same scratch-layer-and-composite technique `raster.rs` already
//! uses for `clientPanel`/`hScroll`/`vScroll`, extended to whole envelopes.

use crate::hittest::InteractionState;
use crate::protocol::{DrawCommand, Envelope, HeroRegion, Transition};
use crate::raster::{parse_color, render_commands};
use crate::text::TextRenderer;
use std::collections::HashMap;
use tiny_skia::{Pixmap, PixmapPaint, Transform};

/// `fadeAnimator`'s duration (`NativeCanvasView.kt:641`).
pub const CROSSFADE_DURATION_MS: f32 = 220.0;
/// `heroAnimator`'s duration (`NativeCanvasView.kt:219` in the port notes).
pub const HERO_DURATION_MS: f32 = 280.0;

fn decelerate(t: f32) -> f32 {
    let x = 1.0 - t;
    1.0 - x * x
}
fn accelerate(t: f32) -> f32 {
    t * t
}
fn accelerate_decelerate(t: f32) -> f32 {
    ((t + 1.0) * std::f32::consts::PI).cos() / 2.0 + 0.5
}
fn bounce_inner(x: f32) -> f32 {
    x * x * 8.0
}
/// Stock `android.view.animation.BounceInterpolator`'s exact piecewise formula.
fn bounce(t: f32) -> f32 {
    let t = t * 1.1226;
    if t < 0.3535 {
        bounce_inner(t)
    } else if t < 0.7408 {
        bounce_inner(t - 0.54719) + 0.7
    } else if t < 0.9644 {
        bounce_inner(t - 0.8526) + 0.9
    } else {
        bounce_inner(t - 1.0435) + 0.95
    }
}
/// `OvershootInterpolator(2f)`'s exact formula (tension = 2).
fn overshoot(t: f32) -> f32 {
    let x = t - 1.0;
    x * x * (3.0 * x + 2.0) + 1.0
}

/// `curveInterpolator()` — `Curve::name` -> eased value. `EASE_OUT`, `null`,
/// and any unrecognized name all fall to `DecelerateInterpolator` (the
/// pipeline's original default), matching the Kotlin `else` branch exactly.
fn curve(name: Option<&str>, t: f32) -> f32 {
    match name {
        Some("LINEAR") => t,
        Some("EASE_IN") => accelerate(t),
        Some("EASE_IN_OUT") => accelerate_decelerate(t),
        Some("BOUNCE") => bounce(t),
        Some("ELASTIC") => overshoot(t),
        _ => decelerate(t),
    }
}

fn lerp(a: f64, b: f64, t: f32) -> f64 {
    a + (b - a) * t as f64
}
fn lerp_opt(a: Option<f64>, b: Option<f64>, t: f32) -> Option<f64> {
    match (a, b) {
        (Some(a), Some(b)) => Some(lerp(a, b, t)),
        _ => b,
    }
}

/// `ArgbEvaluator.evaluate()`'s exact formula — straight per-channel linear
/// blend in normalized float space, `+0.5` round-half-up before truncating.
/// Colors are re-parsed/re-formatted through THIS crate's own
/// `#RRGGBB`/`#RRGGBBAA` (alpha-LAST) convention throughout — the wire
/// protocol's own format (see `raster::parse_color`'s doc), not Android's
/// `Color.parseColor` (alpha-FIRST `#AARRGGBB`), since the blended string
/// is only ever re-parsed by this crate's own parser, never Android's.
fn blend_color(from_hex: &str, to_hex: &str, t: f32) -> String {
    let from = parse_color(from_hex);
    let to = parse_color(to_hex);
    let channel = |a: f32, b: f32| -> u8 { ((a + (b - a) * t) * 255.0 + 0.5).floor().clamp(0.0, 255.0) as u8 };
    format!(
        "#{:02X}{:02X}{:02X}{:02X}",
        channel(from.red(), to.red()),
        channel(from.green(), to.green()),
        channel(from.blue(), to.blue()),
        channel(from.alpha(), to.alpha()),
    )
}
fn blend_color_opt(from: &Option<String>, to: &Option<String>, t: f32) -> Option<String> {
    match (from, to) {
        (Some(from), Some(to)) => Some(blend_color(from, to, t)),
        _ => to.clone(),
    }
}

/// Port of `drawInterpolated()`: builds a synthetic command with numeric
/// fields lerped and color fields ARGB-blended between `old` and `new`,
/// per `numericFieldsByType`/`colorFieldsByType`'s exact field lists.
/// Falls back to `new.clone()` (no interpolation) when there's no old
/// counterpart, the type changed, or the type isn't one of the 7 this
/// applies to (matches Android's `old == null || old.type != type`
/// fallback, and its two maps having no entry for other types either).
fn interpolate_command(old: Option<&DrawCommand>, new: &DrawCommand, t: f32) -> DrawCommand {
    let Some(old) = old else { return new.clone() };
    match (old, new) {
        (DrawCommand::Rect(o), DrawCommand::Rect(n)) => {
            let mut blended = n.clone();
            blended.x = lerp(o.x, n.x, t);
            blended.y = lerp(o.y, n.y, t);
            blended.width = lerp(o.width, n.width, t);
            blended.height = lerp(o.height, n.height, t);
            blended.radius = lerp(o.radius, n.radius, t);
            blended.border_width = lerp(o.border_width, n.border_width, t);
            blended.elevation = lerp_opt(o.elevation, n.elevation, t);
            blended.color = blend_color_opt(&o.color, &n.color, t);
            blended.border_color = blend_color_opt(&o.border_color, &n.border_color, t);
            blended.gradient_from = blend_color_opt(&o.gradient_from, &n.gradient_from, t);
            blended.gradient_to = blend_color_opt(&o.gradient_to, &n.gradient_to, t);
            DrawCommand::Rect(blended)
        }
        (DrawCommand::Circle(o), DrawCommand::Circle(n)) => {
            let mut blended = n.clone();
            blended.cx = lerp(o.cx, n.cx, t);
            blended.cy = lerp(o.cy, n.cy, t);
            blended.radius = lerp(o.radius, n.radius, t);
            blended.border_width = lerp_opt(o.border_width, n.border_width, t);
            blended.color = blend_color_opt(&o.color, &n.color, t);
            blended.border_color = blend_color_opt(&o.border_color, &n.border_color, t);
            DrawCommand::Circle(blended)
        }
        (DrawCommand::Arc(o), DrawCommand::Arc(n)) => {
            let mut blended = n.clone();
            blended.cx = lerp(o.cx, n.cx, t);
            blended.cy = lerp(o.cy, n.cy, t);
            blended.radius = lerp(o.radius, n.radius, t);
            blended.start_degrees = lerp(o.start_degrees, n.start_degrees, t);
            blended.sweep_degrees = lerp(o.sweep_degrees, n.sweep_degrees, t);
            blended.stroke_width = lerp(o.stroke_width, n.stroke_width, t);
            blended.color = blend_color(&o.color, &n.color, t);
            DrawCommand::Arc(blended)
        }
        (DrawCommand::Line(o), DrawCommand::Line(n)) => {
            let mut blended = n.clone();
            blended.x1 = lerp(o.x1, n.x1, t);
            blended.y1 = lerp(o.y1, n.y1, t);
            blended.x2 = lerp(o.x2, n.x2, t);
            blended.y2 = lerp(o.y2, n.y2, t);
            blended.width = lerp(o.width, n.width, t);
            blended.color = blend_color(&o.color, &n.color, t);
            DrawCommand::Line(blended)
        }
        (DrawCommand::Text(o), DrawCommand::Text(n)) => {
            let mut blended = n.clone();
            blended.x = lerp(o.x, n.x, t);
            blended.y = lerp(o.y, n.y, t);
            blended.size = lerp(o.size, n.size, t);
            blended.letter_spacing = lerp_opt(o.letter_spacing, n.letter_spacing, t);
            blended.color = blend_color(&o.color, &n.color, t);
            DrawCommand::Text(blended)
        }
        (DrawCommand::Icon(o), DrawCommand::Icon(n)) => {
            let mut blended = n.clone();
            blended.x = lerp(o.x, n.x, t);
            blended.y = lerp(o.y, n.y, t);
            blended.size = lerp(o.size, n.size, t);
            blended.color = blend_color(&o.color, &n.color, t);
            DrawCommand::Icon(blended)
        }
        (DrawCommand::Image(o), DrawCommand::Image(n)) => {
            let mut blended = n.clone();
            blended.x = lerp(o.x, n.x, t);
            blended.y = lerp(o.y, n.y, t);
            blended.width = lerp(o.width, n.width, t);
            blended.height = lerp(o.height, n.height, t);
            blended.radius = lerp(o.radius, n.radius, t);
            DrawCommand::Image(blended)
        }
        _ => new.clone(),
    }
}

fn collect_by_hero_tag<'a>(commands: &'a [DrawCommand], tag: &str) -> Vec<&'a DrawCommand> {
    commands.iter().filter(|c| c.hero_tag() == Some(tag)).collect()
}

fn hero_rect_eq(a: &HeroRegion, b: &HeroRegion) -> bool {
    a.x == b.x && a.y == b.y && a.width == b.width && a.height == b.height
}

/// `Canvas::setTransition()`'s pixel-offset table (`"fade"`/`None`/anything
/// unmatched leaves every offset at 0 — a plain crossfade).
fn transition_offsets(transition: Option<&Transition>, viewport_w: f32, viewport_h: f32, fade_progress: f32) -> (f32, f32, f32, f32) {
    match transition {
        Some(Transition::SlideLeft) => (
            viewport_w * (1.0 - fade_progress),
            -viewport_w * fade_progress,
            0.0,
            0.0,
        ),
        Some(Transition::SlideRight) => (
            -viewport_w * (1.0 - fade_progress),
            viewport_w * fade_progress,
            0.0,
            0.0,
        ),
        Some(Transition::SlideUp) => (0.0, 0.0, viewport_h * (1.0 - fade_progress), 0.0),
        _ => (0.0, 0.0, 0.0, 0.0),
    }
}

/// Renders `commands` into a same-size scratch layer, skipping any
/// top-level command whose own `hero` tag is currently flying (mirrors
/// `drawCommands()`'s `excludeHeroTags` check — flat, not recursive, since
/// a `hero`-tagged command must live at the top level to fly at all).
#[allow(clippy::too_many_arguments)]
fn render_layer_excluding(width: u32, height: u32, commands: &[DrawCommand], elapsed_ms: u64, text_renderer: &mut TextRenderer, exclude: &[String], state: &InteractionState) -> Pixmap {
    let mut layer = Pixmap::new(width, height).unwrap_or_else(|| Pixmap::new(1, 1).unwrap());
    let filtered: Vec<DrawCommand> = commands
        .iter()
        .filter(|c| c.hero_tag().is_none_or(|tag| !exclude.iter().any(|t| t == tag)))
        .cloned()
        .collect();
    render_commands(&mut layer, &filtered, elapsed_ms, text_renderer, state);
    layer
}

#[allow(clippy::too_many_arguments)]
fn draw_hero_flight(
    pixmap: &mut Pixmap,
    old_region: &HeroRegion,
    new_region: &HeroRegion,
    hero_linear_t: f32,
    previous_commands: &[DrawCommand],
    new_commands: &[DrawCommand],
    text_renderer: &mut TextRenderer,
    state: &InteractionState,
) {
    let eased = curve(new_region.curve.as_deref(), hero_linear_t);

    let interp_x = lerp(old_region.x, new_region.x, eased);
    let interp_y = lerp(old_region.y, new_region.y, eased);
    let interp_w = lerp(old_region.width, new_region.width, eased);
    let interp_h = lerp(old_region.height, new_region.height, eased);

    // p' = (p - new.origin) * scale + interp.origin — matrix stays
    // translate-only (no scale) if the new rect is degenerate, matching
    // Android's divide-by-zero guard.
    let transform = if new_region.width > 0.0 && new_region.height > 0.0 {
        Transform::from_translate(-new_region.x as f32, -new_region.y as f32)
            .post_scale((interp_w / new_region.width) as f32, (interp_h / new_region.height) as f32)
            .post_translate(interp_x as f32, interp_y as f32)
    } else {
        Transform::from_translate((interp_x - new_region.x) as f32, (interp_y - new_region.y) as f32)
    };

    let new_tagged = collect_by_hero_tag(new_commands, &new_region.tag);
    let old_tagged = collect_by_hero_tag(previous_commands, &new_region.tag);

    let mut layer = Pixmap::new(pixmap.width(), pixmap.height()).unwrap_or_else(|| Pixmap::new(1, 1).unwrap());
    let blended: Vec<DrawCommand> = new_tagged
        .iter()
        .enumerate()
        .map(|(index, new_cmd)| interpolate_command(old_tagged.get(index).copied(), new_cmd, eased))
        .collect();
    // Always drawn fully opaque — drawInterpolated() hardcodes alpha=1f in
    // every branch; only geometry/color animate during a flight, never opacity.
    render_commands(&mut layer, &blended, 0, text_renderer, state);
    pixmap.draw_pixmap(0, 0, layer.as_ref(), &PixmapPaint::default(), transform, None);
}

/// Renders one frame of a potential crossfade + hero transition between
/// `new_envelope` and `previous_envelope`. `transition_elapsed_ms` is
/// wall-clock time since `new_envelope` became the active screen (a
/// single shared clock, exactly like `fadeProgress`/`heroProgress` both
/// derive from the moment `startCrossfade()`/`startHeroTransition()` fired)
/// — irrelevant when `previous_envelope` is `None`. `animation_elapsed_ms`
/// is the existing, unrelated clock spinner/skeleton animate against.
///
/// With no previous envelope, this degenerates to a plain
/// `render_commands()` call — the common case (first render, or a
/// same-screen refetch that never calls this at all) pays zero extra cost.
/// `state` is the same caller-owned live interaction state `render_commands`
/// takes — threaded through every pass (outgoing, incoming, each hero
/// flight) so a live scroll/tab/slider state is honored no matter which
/// envelope/pass is currently painting it.
#[allow(clippy::too_many_arguments)]
pub fn render_transition(
    pixmap: &mut Pixmap,
    new_envelope: &Envelope,
    previous_envelope: Option<&Envelope>,
    transition_elapsed_ms: u64,
    animation_elapsed_ms: u64,
    text_renderer: &mut TextRenderer,
    state: &InteractionState,
) {
    let Some(previous) = previous_envelope else {
        render_commands(pixmap, &new_envelope.commands, animation_elapsed_ms, text_renderer, state);
        return;
    };

    let width = pixmap.width();
    let height = pixmap.height();

    let fade_t = (transition_elapsed_ms as f32 / CROSSFADE_DURATION_MS).clamp(0.0, 1.0);
    let fade_progress = decelerate(fade_t);
    let hero_linear_t = (transition_elapsed_ms as f32 / HERO_DURATION_MS).clamp(0.0, 1.0);

    // Diff heroRegions by tag: a tag present in both, with a genuinely
    // different rect, is "flying" this frame.
    let mut flights: HashMap<&str, (&HeroRegion, &HeroRegion)> = HashMap::new();
    for new_region in &new_envelope.hero_regions {
        if let Some(old_region) = previous.hero_regions.iter().find(|r| r.tag == new_region.tag) {
            if !hero_rect_eq(old_region, new_region) {
                flights.insert(new_region.tag.as_str(), (old_region, new_region));
            }
        }
    }
    let flying_tags: Vec<String> = flights.keys().map(|s| s.to_string()).collect();

    let viewport_w = width as f32;
    let viewport_h = height as f32;
    let (incoming_x, outgoing_x, incoming_y, outgoing_y) =
        transition_offsets(new_envelope.transition.as_ref(), viewport_w, viewport_h, fade_progress);

    if fade_progress < 1.0 {
        let layer = render_layer_excluding(width, height, &previous.commands, animation_elapsed_ms, text_renderer, &flying_tags, state);
        let paint = PixmapPaint { opacity: 1.0 - fade_progress, ..PixmapPaint::default() };
        pixmap.draw_pixmap(outgoing_x.round() as i32, outgoing_y.round() as i32, layer.as_ref(), &paint, Transform::identity(), None);
    }
    {
        let layer = render_layer_excluding(width, height, &new_envelope.commands, animation_elapsed_ms, text_renderer, &flying_tags, state);
        let paint = PixmapPaint { opacity: fade_progress, ..PixmapPaint::default() };
        pixmap.draw_pixmap(incoming_x.round() as i32, incoming_y.round() as i32, layer.as_ref(), &paint, Transform::identity(), None);
    }

    for (old_region, new_region) in flights.values() {
        draw_hero_flight(pixmap, old_region, new_region, hero_linear_t, &previous.commands, &new_envelope.commands, text_renderer, state);
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::protocol::decode_envelope;

    fn envelope(json: &str) -> Envelope {
        decode_envelope(json).unwrap()
    }

    #[test]
    fn decelerate_matches_androids_factor_one_formula() {
        assert!((decelerate(0.0) - 0.0).abs() < 1e-6);
        assert!((decelerate(1.0) - 1.0).abs() < 1e-6);
        assert!((decelerate(0.5) - 0.75).abs() < 1e-6); // 1 - 0.5^2
    }

    #[test]
    fn curve_dispatches_every_named_curve_and_falls_back_to_decelerate() {
        assert_eq!(curve(Some("LINEAR"), 0.5), 0.5);
        assert!((curve(Some("EASE_IN"), 0.5) - 0.25).abs() < 1e-6);
        assert!((curve(None, 0.5) - decelerate(0.5)).abs() < 1e-6);
        assert!((curve(Some("EASE_OUT"), 0.5) - decelerate(0.5)).abs() < 1e-6);
        assert!((curve(Some("nonsense"), 0.3) - decelerate(0.3)).abs() < 1e-6);
    }

    #[test]
    fn blend_color_reproduces_argb_evaluator_at_the_midpoint() {
        // #FF0000FF (opaque red) -> #0000FFFF (opaque blue) at t=0.5
        // should land exactly on opaque, evenly-mixed purple.
        let blended = blend_color("#FF0000FF", "#0000FFFF", 0.5);
        assert_eq!(blended, "#800080FF");
    }

    #[test]
    fn with_no_previous_envelope_renders_the_new_one_plainly() {
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let new_envelope = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":40,"height":40,"color":"#FF0000"}],"hitRegions":[],"contentHeight":40}"##,
        );
        let mut text_renderer = TextRenderer::new();
        render_transition(&mut pixmap, &new_envelope, None, 0, 0, &mut text_renderer, &InteractionState::default());
        let p = pixmap.pixel(20, 20).unwrap();
        assert_eq!((p.red(), p.green(), p.blue(), p.alpha()), (255, 0, 0, 255));
    }

    #[test]
    fn mid_crossfade_blends_both_envelopes_by_opacity() {
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let old_envelope = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":40,"height":40,"color":"#FF0000"}],"hitRegions":[],"contentHeight":40}"##,
        );
        let new_envelope = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":40,"height":40,"color":"#0000FF"}],"hitRegions":[],"contentHeight":40}"##,
        );
        let mut text_renderer = TextRenderer::new();
        // transition_elapsed_ms = 0 -> fade_progress = decelerate(0) = 0,
        // i.e. still fully showing the OLD envelope.
        render_transition(&mut pixmap, &new_envelope, Some(&old_envelope), 0, 0, &mut text_renderer, &InteractionState::default());
        let p = pixmap.pixel(20, 20).unwrap();
        assert_eq!((p.red(), p.green(), p.blue()), (255, 0, 0), "at t=0 only the outgoing envelope should be visible");
    }

    #[test]
    fn after_crossfade_duration_only_the_new_envelope_shows() {
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let old_envelope = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":40,"height":40,"color":"#FF0000"}],"hitRegions":[],"contentHeight":40}"##,
        );
        let new_envelope = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":40,"height":40,"color":"#0000FF"}],"hitRegions":[],"contentHeight":40}"##,
        );
        let mut text_renderer = TextRenderer::new();
        render_transition(&mut pixmap, &new_envelope, Some(&old_envelope), 220, 0, &mut text_renderer, &InteractionState::default());
        let p = pixmap.pixel(20, 20).unwrap();
        assert_eq!((p.red(), p.green(), p.blue(), p.alpha()), (0, 0, 255, 255), "past the crossfade duration only the new envelope should show");
    }

    #[test]
    fn slide_left_offsets_the_incoming_and_outgoing_passes() {
        let (incoming_x, outgoing_x, incoming_y, outgoing_y) =
            transition_offsets(Some(&Transition::SlideLeft), 100.0, 200.0, 0.25);
        assert_eq!(incoming_x, 75.0); // 100 * (1 - 0.25)
        assert_eq!(outgoing_x, -25.0); // -100 * 0.25
        assert_eq!(incoming_y, 0.0);
        assert_eq!(outgoing_y, 0.0);
    }

    #[test]
    fn fade_transition_leaves_every_offset_at_zero() {
        assert_eq!(transition_offsets(None, 100.0, 200.0, 0.5), (0.0, 0.0, 0.0, 0.0));
        assert_eq!(transition_offsets(Some(&Transition::Fade), 100.0, 200.0, 0.5), (0.0, 0.0, 0.0, 0.0));
    }

    #[test]
    fn interpolate_command_lerps_numeric_fields_and_blends_color() {
        let old = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":10,"height":10,"color":"#FF0000","radius":0}],"hitRegions":[],"contentHeight":10}"##,
        );
        let new = envelope(
            r##"{"commands":[{"type":"rect","x":20,"y":20,"width":30,"height":30,"color":"#0000FF","radius":0}],"hitRegions":[],"contentHeight":30}"##,
        );
        let blended = interpolate_command(Some(&old.commands[0]), &new.commands[0], 0.5);
        match blended {
            DrawCommand::Rect(r) => {
                assert_eq!(r.x, 10.0);
                assert_eq!(r.y, 10.0);
                assert_eq!(r.width, 20.0);
                assert_eq!(r.height, 20.0);
                assert_eq!(r.color.as_deref(), Some("#800080FF"));
            }
            other => panic!("expected Rect, got {other:?}"),
        }
    }

    #[test]
    fn interpolate_command_with_no_old_counterpart_draws_new_unmodified() {
        let new = envelope(
            r##"{"commands":[{"type":"rect","x":20,"y":20,"width":30,"height":30,"color":"#0000FF","radius":0}],"hitRegions":[],"contentHeight":30}"##,
        );
        let blended = interpolate_command(None, &new.commands[0], 0.5);
        assert_eq!(blended, new.commands[0]);
    }

    #[test]
    fn hero_flight_transforms_the_new_rects_own_authored_coordinates() {
        // Old hero rect (0,0,10,10) -> new hero rect (0,0,20,20), eased=0.5
        // -> interpRect (0,0,15,15), scale=0.75. A rect painted at the new
        // envelope's own local origin (0,0,20,20) — the same box the hero
        // region itself describes — must land scaled down to (0,0,15,15)
        // in the composited output.
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let old_region = HeroRegion { tag: "avatar".to_string(), x: 0.0, y: 0.0, width: 10.0, height: 10.0, curve: None };
        let new_region = HeroRegion { tag: "avatar".to_string(), x: 0.0, y: 0.0, width: 20.0, height: 20.0, curve: Some("LINEAR".to_string()) };
        let old_commands = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":10,"height":10,"color":"#FF0000","radius":0,"hero":"avatar"}],"hitRegions":[],"contentHeight":10}"##,
        );
        let new_commands = envelope(
            r##"{"commands":[{"type":"rect","x":0,"y":0,"width":20,"height":20,"color":"#0000FF","radius":0,"hero":"avatar"}],"hitRegions":[],"contentHeight":20}"##,
        );
        let mut text_renderer = TextRenderer::new();
        draw_hero_flight(&mut pixmap, &old_region, &new_region, 0.5, &old_commands.commands, &new_commands.commands, &mut text_renderer, &InteractionState::default());

        let inside = pixmap.pixel(10, 10).unwrap();
        assert_eq!(inside.alpha(), 255, "the scaled-down 15x15 box should cover (10,10)");
        let outside = pixmap.pixel(18, 18).unwrap();
        assert_eq!(outside.alpha(), 0, "past the 15x15 scaled box must stay unpainted, proving the scale actually applied");
    }
}
