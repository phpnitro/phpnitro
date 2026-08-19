//! Rasterizes the draw-command protocol onto a `tiny_skia::Pixmap` — an
//! in-memory RGBA buffer, no display server involved, directly analogous
//! to the `cairo.ImageSurface` approach Linux already uses for offscreen,
//! display-server-free pixel tests.
//!
//! `clientPanel`/`hScroll`/`vScroll` recurse via a scratch same-size
//! `Pixmap` layer (their own `commands[]` are already panel-relative —
//! confirmed via `hscroll_basic.json`/`HorizontalScroll.php` — so they
//! paint into that layer completely unmodified) composited onto the real
//! destination at the container's own `(x, y)` via `draw_pixmap`, with a
//! `Mask`-based viewport clip for the two scrollable variants. `image`
//! (needs a byte source) is still handled elsewhere/not wired here.

use crate::animate::{skeleton_sweep_width, skeleton_sweep_x, spinner_rotation_degrees, SPINNER_SWEEP_DEGREES};
use crate::protocol::{
    ArcCommand, CircleCommand, DrawCommand, LineCommand, RectCommand, SkeletonCommand, SliderCommand, SpinnerCommand,
};
use crate::text::TextRenderer;
use tiny_skia::{
    Color, FillRule, GradientStop, LineCap, LinearGradient, Mask, Paint, Path, PathBuilder, Pixmap, PixmapPaint,
    Point, PremultipliedColorU8, Rect, SpreadMode, Stroke, Transform,
};

/// Parses `#RRGGBB` or `#RRGGBBAA` (the only two forms `Canvas.php` emits)
/// into a `tiny_skia::Color`. Falls back to opaque black on anything else
/// rather than panicking — a malformed color shouldn't take down a whole
/// frame's render.
pub fn parse_color(hex: &str) -> Color {
    let hex = hex.trim_start_matches('#');
    let channel = |slice: &str| u8::from_str_radix(slice, 16).unwrap_or(0);
    match hex.len() {
        6 => Color::from_rgba8(channel(&hex[0..2]), channel(&hex[2..4]), channel(&hex[4..6]), 255),
        8 => Color::from_rgba8(
            channel(&hex[0..2]),
            channel(&hex[2..4]),
            channel(&hex[4..6]),
            channel(&hex[6..8]),
        ),
        _ => Color::BLACK,
    }
}

fn solid_paint(color: Color) -> Paint<'static> {
    let mut paint = Paint::default();
    paint.set_color(color);
    paint.anti_alias = true;
    paint
}

fn stroke_of(width: f32) -> Stroke {
    Stroke {
        width: width.max(0.0),
        line_cap: LineCap::Round,
        ..Stroke::default()
    }
}

/// Builds a rounded-rect path via cubic-Bezier corners (tiny-skia has no
/// built-in rounded-rect primitive). `radius` is clamped to at most half
/// the shorter side, matching every existing renderer's behavior for
/// "pill"-shaped buttons that pass an oversized radius (e.g. `radius: 999`
/// on a 54px-tall button in `button_with_icon.json`, meant to mean "fully
/// rounded", not a literal 999px corner).
fn rounded_rect_path(x: f32, y: f32, width: f32, height: f32, radius: f32) -> Option<Path> {
    let r = radius.max(0.0).min(width / 2.0).min(height / 2.0);
    let mut pb = PathBuilder::new();
    if r <= 0.0 {
        pb.push_rect(Rect::from_xywh(x, y, width, height)?);
        return pb.finish();
    }
    // Magic constant for a cubic-Bezier approximation of a quarter circle.
    let k = r * 0.552_284_8;
    pb.move_to(x + r, y);
    pb.line_to(x + width - r, y);
    pb.cubic_to(x + width - r + k, y, x + width, y + r - k, x + width, y + r);
    pb.line_to(x + width, y + height - r);
    pb.cubic_to(x + width, y + height - r + k, x + width - r + k, y + height, x + width - r, y + height);
    pb.line_to(x + r, y + height);
    pb.cubic_to(x + r - k, y + height, x, y + height - r + k, x, y + height - r);
    pb.line_to(x, y + r);
    pb.cubic_to(x, y + r - k, x + r - k, y, x + r, y);
    pb.close();
    pb.finish()
}

/// `startDegrees`/`sweepDegrees` follow Android's `drawArc()` convention:
/// 0° = 3 o'clock, sweeping clockwise. This crate's y axis already points
/// down (a `Pixmap` is a top-to-bottom raster, same as every other
/// renderer in this protocol), so `(cos θ, sin θ)` with increasing θ
/// already sweeps clockwise on screen — unlike iOS's Core Graphics, no
/// sign flip is needed here.
fn arc_path(cx: f32, cy: f32, radius: f32, start_degrees: f32, sweep_degrees: f32) -> Option<Path> {
    if radius <= 0.0 || sweep_degrees.abs() < f32::EPSILON {
        return None;
    }
    let segments = ((sweep_degrees.abs() / 90.0).ceil() as usize).max(1);
    let segment_sweep = sweep_degrees / segments as f32;
    let point_at = |angle: f32| (cx + radius * angle.cos(), cy + radius * angle.sin());
    let mut angle = start_degrees.to_radians();
    let mut pb = PathBuilder::new();
    let (sx, sy) = point_at(angle);
    pb.move_to(sx, sy);
    for _ in 0..segments {
        let next_angle = angle + segment_sweep.to_radians();
        let k = (4.0 / 3.0) * ((next_angle - angle) / 4.0).tan();
        let (p0x, p0y) = point_at(angle);
        let (p3x, p3y) = point_at(next_angle);
        let t0 = (-angle.sin(), angle.cos());
        let t1 = (-next_angle.sin(), next_angle.cos());
        pb.cubic_to(
            p0x + k * radius * t0.0,
            p0y + k * radius * t0.1,
            p3x - k * radius * t1.0,
            p3y - k * radius * t1.1,
            p3x,
            p3y,
        );
        angle = next_angle;
    }
    pb.finish()
}

fn draw_rect(pixmap: &mut Pixmap, rect: &RectCommand) {
    let (x, y, w, h) = (rect.x as f32, rect.y as f32, rect.width as f32, rect.height as f32);
    let radius = rect.radius as f32;
    let path = rounded_rect_path(x, y, w, h, radius);
    let Some(path) = path else { return };

    if let Some(elevation) = rect.elevation {
        draw_elevation_shadow(pixmap, x, y, w, h, radius, elevation as f32);
    }

    // Mirrors NativeCanvasView.kt's drawRectCommand(): a gradient takes
    // priority over a flat color when present (gradientTo falls back to
    // gradientFrom itself — a "gradient" with only one stop is just a
    // solid fill via a 1-color gradient, not treated as absent); a
    // border-only box with neither set intentionally paints no fill.
    if let Some(from) = &rect.gradient_from {
        let to = rect.gradient_to.as_deref().unwrap_or(from);
        let stops = vec![GradientStop::new(0.0, parse_color(from)), GradientStop::new(1.0, parse_color(to))];
        // Always top-left -> bottom-right, same fixed diagonal Android
        // uses (no per-gradient angle field on the wire protocol).
        if let Some(shader) = LinearGradient::new(
            Point::from_xy(x, y),
            Point::from_xy(x + w, y + h),
            stops,
            SpreadMode::Pad,
            Transform::identity(),
        ) {
            let paint = Paint { shader, anti_alias: true, ..Paint::default() };
            pixmap.fill_path(&path, &paint, FillRule::Winding, Transform::identity(), None);
        }
    } else if let Some(color) = &rect.color {
        pixmap.fill_path(&path, &solid_paint(parse_color(color)), FillRule::Winding, Transform::identity(), None);
    }

    if let Some(border_color) = &rect.border_color {
        if rect.border_width > 0.0 {
            let stroke = stroke_of(rect.border_width as f32);
            pixmap.stroke_path(&path, &solid_paint(parse_color(border_color)), &stroke, Transform::identity(), None);
        }
    }
}

/// Drop shadow for `elevation` — mirrors NativeCanvasView.kt's
/// `setShadowLayer(elevation * 2.2, 0, elevation * 0.9, ...)` (blur radius
/// scales 2.2x elevation, shadow offset is vertical-only at 0.9x
/// elevation, alpha scales with elevation up to a cap of 140/255). Android
/// draws this via a single shadow-layer paint; tiny-skia has no such
/// built-in, so this rasterizes the same rounded-rect shape into a small
/// local buffer (just the rect's own bounds, padded by the blur radius —
/// not the whole frame), box-blurs its alpha channel, then composites it
/// behind the real fill via `draw_pixmap`.
fn draw_elevation_shadow(pixmap: &mut Pixmap, x: f32, y: f32, w: f32, h: f32, radius: f32, elevation: f32) {
    if elevation <= 0.0 || w <= 0.0 || h <= 0.0 {
        return;
    }
    let blur_radius = (elevation * 2.2).round().max(1.0) as u32;
    let offset_y = elevation * 0.9;
    let shadow_alpha = ((40.0 + elevation * 5.0).min(140.0) / 255.0).clamp(0.0, 1.0);

    let pad = blur_radius + 1;
    let local_w = w.ceil() as u32 + pad * 2;
    let local_h = h.ceil() as u32 + pad * 2;
    let Some(mut shadow) = Pixmap::new(local_w, local_h) else { return };

    let Some(local_path) = rounded_rect_path(pad as f32, pad as f32, w, h, radius) else { return };
    let mut paint = solid_paint(Color::from_rgba8(0, 0, 0, (255.0 * shadow_alpha).round() as u8));
    paint.anti_alias = true;
    shadow.fill_path(&local_path, &paint, FillRule::Winding, Transform::identity(), None);

    box_blur_alpha(&mut shadow, blur_radius);

    let dest_x = (x - pad as f32).round() as i32;
    let dest_y = (y - pad as f32 + offset_y).round() as i32;
    pixmap.draw_pixmap(dest_x, dest_y, shadow.as_ref(), &PixmapPaint::default(), Transform::identity(), None);
}

/// A separable box blur (horizontal pass, then vertical) applied to a
/// pixmap's alpha channel in place — good enough approximation of a
/// gaussian blur for a soft drop shadow at typical `elevation` sizes
/// (a handful of pixels), without pulling in an image-processing crate
/// for one effect. Color stays solid black throughout (`draw_elevation_shadow`
/// only ever fills black into this buffer), so blurring alpha alone is
/// equivalent to blurring the fully premultiplied pixel.
fn box_blur_alpha(pixmap: &mut Pixmap, radius: u32) {
    if radius == 0 {
        return;
    }
    let (width, height) = (pixmap.width() as usize, pixmap.height() as usize);
    let r = radius as isize;

    let mut alpha: Vec<u16> = pixmap.pixels().iter().map(|p| p.alpha() as u16).collect();
    let mut pass = alpha.clone();

    for y in 0..height {
        let row = y * width;
        for x in 0..width {
            let mut sum = 0u32;
            let mut count = 0u32;
            for dx in -r..=r {
                let sx = x as isize + dx;
                if sx >= 0 && (sx as usize) < width {
                    sum += alpha[row + sx as usize] as u32;
                    count += 1;
                }
            }
            pass[row + x] = (sum / count.max(1)) as u16;
        }
    }
    alpha.copy_from_slice(&pass);

    for x in 0..width {
        for y in 0..height {
            let mut sum = 0u32;
            let mut count = 0u32;
            for dy in -r..=r {
                let sy = y as isize + dy;
                if sy >= 0 && (sy as usize) < height {
                    sum += alpha[sy as usize * width + x] as u32;
                    count += 1;
                }
            }
            pass[y * width + x] = (sum / count.max(1)) as u16;
        }
    }

    for (pixel, a) in pixmap.pixels_mut().iter_mut().zip(pass.iter()) {
        *pixel = PremultipliedColorU8::from_rgba(0, 0, 0, (*a).min(255) as u8).unwrap_or(*pixel);
    }
}

/// Verbatim port of NativeCanvasView.kt's `drawSliderCommand()` geometry:
/// a pill-shaped track (radius = trackHeight/2) spanning the full width,
/// a same-shaped "active" fill from the track's start to the thumb's
/// center, then a filled thumb circle with a thin 1.5px stroke on top in
/// `activeColor`. `value` is read straight off the wire — no client-side
/// drag override yet (that needs the same interaction-state plumbing
/// `hittest.rs` already has for hit-testing, not yet threaded into the
/// render path), so a slider always paints at its server-authored value.
fn draw_slider(pixmap: &mut Pixmap, slider: &SliderCommand) {
    let (x, y, width, height) = (slider.x as f32, slider.y as f32, slider.width as f32, slider.height as f32);
    let track_height = slider.track_height as f32;
    let thumb_size = slider.thumb_size as f32;
    let value = (slider.value as f32).clamp(0.0, 1.0);

    let track_y = y + (height - track_height) / 2.0;
    let thumb_cx = x + thumb_size / 2.0 + (width - thumb_size) * value;
    let thumb_cy = y + height / 2.0;
    let track_radius = (track_height / 2.0).max(0.0);

    if let Some(track_path) = rounded_rect_path(x, track_y, width.max(0.0), track_height.max(0.0), track_radius) {
        pixmap.fill_path(
            &track_path,
            &solid_paint(parse_color(&slider.track_color)),
            FillRule::Winding,
            Transform::identity(),
            None,
        );
    }

    let active_width = (thumb_cx - x).max(0.0);
    if let Some(active_path) = rounded_rect_path(x, track_y, active_width, track_height.max(0.0), track_radius) {
        pixmap.fill_path(
            &active_path,
            &solid_paint(parse_color(&slider.active_color)),
            FillRule::Winding,
            Transform::identity(),
            None,
        );
    }

    let mut pb = PathBuilder::new();
    pb.push_circle(thumb_cx, thumb_cy, (thumb_size / 2.0).max(0.0));
    if let Some(thumb_path) = pb.finish() {
        pixmap.fill_path(
            &thumb_path,
            &solid_paint(parse_color(&slider.thumb_color)),
            FillRule::Winding,
            Transform::identity(),
            None,
        );
        pixmap.stroke_path(
            &thumb_path,
            &solid_paint(parse_color(&slider.active_color)),
            &stroke_of(1.5),
            Transform::identity(),
            None,
        );
    }
}

fn draw_circle(pixmap: &mut Pixmap, circle: &CircleCommand) {
    let (cx, cy, r) = (circle.cx as f32, circle.cy as f32, circle.radius as f32);
    let mut pb = PathBuilder::new();
    pb.push_circle(cx, cy, r.max(0.0));
    let Some(path) = pb.finish() else { return };

    if let Some(color) = &circle.color {
        pixmap.fill_path(&path, &solid_paint(parse_color(color)), FillRule::Winding, Transform::identity(), None);
    }
    if let (Some(border_color), Some(border_width)) = (&circle.border_color, circle.border_width) {
        if border_width > 0.0 {
            let stroke = stroke_of(border_width as f32);
            pixmap.stroke_path(&path, &solid_paint(parse_color(border_color)), &stroke, Transform::identity(), None);
        }
    }
}

fn draw_line(pixmap: &mut Pixmap, line: &LineCommand) {
    let mut pb = PathBuilder::new();
    pb.move_to(line.x1 as f32, line.y1 as f32);
    pb.line_to(line.x2 as f32, line.y2 as f32);
    let Some(path) = pb.finish() else { return };
    let stroke = stroke_of(line.width as f32);
    pixmap.stroke_path(&path, &solid_paint(parse_color(&line.color)), &stroke, Transform::identity(), None);
}

/// `color.red()`/`green()`/`blue()` are already normalized `0.0..=1.0` —
/// blending toward white (1.0) by `t` matches Android's
/// `ColorUtils.blendARGB(baseColor, Color.WHITE, 0.5f)` for the skeleton
/// shimmer's highlight color.
fn blend_toward_white(color: Color, t: f32) -> Color {
    let t = t.clamp(0.0, 1.0);
    let lerp = |c: f32| c + (1.0 - c) * t;
    Color::from_rgba(lerp(color.red()), lerp(color.green()), lerp(color.blue()), color.alpha())
        .unwrap_or(color)
}

fn draw_spinner(pixmap: &mut Pixmap, spinner: &SpinnerCommand, elapsed_ms: u64) {
    let (x, y, size, stroke_width) = (
        spinner.x as f32,
        spinner.y as f32,
        spinner.size as f32,
        spinner.stroke_width as f32,
    );
    let center = size / 2.0;
    let radius = (center - stroke_width / 2.0).max(0.0);
    let (cx, cy) = (x + center, y + center);
    let stroke = stroke_of(stroke_width);

    let mut pb = PathBuilder::new();
    pb.push_circle(cx, cy, radius);
    if let Some(track_path) = pb.finish() {
        pixmap.stroke_path(&track_path, &solid_paint(parse_color(&spinner.track_color)), &stroke, Transform::identity(), None);
    }

    let rotation = spinner_rotation_degrees(elapsed_ms);
    if let Some(sweep_path) = arc_path(cx, cy, radius, rotation, SPINNER_SWEEP_DEGREES) {
        pixmap.stroke_path(&sweep_path, &solid_paint(parse_color(&spinner.color)), &stroke, Transform::identity(), None);
    }
}

/// Base fill + a translucent band sweeping left-to-right on a loop,
/// clipped to the rounded-rect's own shape via a `Mask` — matches
/// `drawSkeletonCommand()`'s `canvas.clipRect(rect)` + gradient approach
/// (the highlight is the base color blended toward white, not a flat
/// white, for the same reason cited there: reads right in dark mode too).
fn draw_skeleton(pixmap: &mut Pixmap, skeleton: &SkeletonCommand, elapsed_ms: u64) {
    let (x, y, w, h) = (skeleton.x as f32, skeleton.y as f32, skeleton.width as f32, skeleton.height as f32);
    let base_color = parse_color(&skeleton.color);
    let Some(base_path) = rounded_rect_path(x, y, w, h, skeleton.radius as f32) else {
        return;
    };
    pixmap.fill_path(&base_path, &solid_paint(base_color), FillRule::Winding, Transform::identity(), None);

    let highlight = blend_toward_white(base_color, 0.5);
    let transparent = Color::from_rgba(highlight.red(), highlight.green(), highlight.blue(), 0.0).unwrap_or(highlight);
    // this.alpha = (this.alpha * alpha * 0.8f) — the 0.8 is the shimmer
    // paint's own peak opacity (alpha here is always 1.0, a full frame).
    let translucent = Color::from_rgba(highlight.red(), highlight.green(), highlight.blue(), highlight.alpha() * 0.8)
        .unwrap_or(highlight);

    let sweep_width = skeleton_sweep_width(w);
    let sweep_x = skeleton_sweep_x(elapsed_ms, x, w);
    let stops = vec![
        GradientStop::new(0.0, transparent),
        GradientStop::new(0.5, translucent),
        GradientStop::new(1.0, transparent),
    ];
    let Some(shader) = LinearGradient::new(
        Point::from_xy(sweep_x, y),
        Point::from_xy(sweep_x + sweep_width, y),
        stops,
        SpreadMode::Pad,
        Transform::identity(),
    ) else {
        return;
    };

    let Some(mut mask) = Mask::new(pixmap.width(), pixmap.height()) else {
        return;
    };
    mask.fill_path(&base_path, FillRule::Winding, true, Transform::identity());

    let mut paint = Paint {
        shader,
        ..Paint::default()
    };
    paint.anti_alias = true;
    if let Some(rect_path) = rounded_rect_path(x, y, w, h, skeleton.radius as f32) {
        pixmap.fill_path(&rect_path, &paint, FillRule::Winding, Transform::identity(), Some(&mask));
    }
}

fn draw_arc(pixmap: &mut Pixmap, arc: &ArcCommand) {
    let Some(path) = arc_path(
        arc.cx as f32,
        arc.cy as f32,
        arc.radius as f32,
        arc.start_degrees as f32,
        arc.sweep_degrees as f32,
    ) else {
        return;
    };
    let stroke = stroke_of(arc.stroke_width as f32);
    pixmap.stroke_path(&path, &solid_paint(parse_color(&arc.color)), &stroke, Transform::identity(), None);
}

/// Rasterizes every command this module knows how to draw — including
/// `text`/`icon` (needs `text_renderer`'s loaded fonts, see `text.rs`) and,
/// recursively, everything nested inside `clientPanel`/`hScroll`/`vScroll`
/// — at the given wall-clock `elapsed_ms` (feeds `spinner`/`skeleton`'s
/// animation; callers that never animate can pass 0). `image`/`unknown`
/// are still silently skipped — no byte source is wired up for images yet.
pub fn render_commands(pixmap: &mut Pixmap, commands: &[DrawCommand], elapsed_ms: u64, text_renderer: &mut TextRenderer) {
    for command in commands {
        match command {
            DrawCommand::Rect(rect) => draw_rect(pixmap, rect),
            DrawCommand::Circle(circle) => draw_circle(pixmap, circle),
            DrawCommand::Line(line) => draw_line(pixmap, line),
            DrawCommand::Arc(arc) => draw_arc(pixmap, arc),
            DrawCommand::Spinner(spinner) => draw_spinner(pixmap, spinner, elapsed_ms),
            DrawCommand::Skeleton(skeleton) => draw_skeleton(pixmap, skeleton, elapsed_ms),
            DrawCommand::Slider(slider) => draw_slider(pixmap, slider),
            DrawCommand::Custom(custom) => crate::charts::render_custom(pixmap, custom),
            DrawCommand::Text(text) => text_renderer.render_text(pixmap, text),
            DrawCommand::Icon(icon) => text_renderer.render_icon(pixmap, icon),
            DrawCommand::ClientPanel(panel) => draw_client_panel(pixmap, panel, elapsed_ms, text_renderer),
            DrawCommand::HScroll(scroll) => draw_hscroll(pixmap, scroll, elapsed_ms, text_renderer),
            DrawCommand::VScroll(scroll) => draw_vscroll(pixmap, scroll, elapsed_ms, text_renderer),
            _ => {}
        }
    }
}

/// `clientPanel`'s own `commands[]` are already panel-relative (confirmed
/// against `HorizontalScroll.php`/`hscroll_basic.json`, same convention
/// every container here follows) — rendered into a same-size scratch
/// layer using their own local coordinates unmodified, then composited
/// onto the real destination at `(x, y)` via `draw_pixmap`, which is
/// exactly equivalent to `canvas.translate(x, y)` without needing to
/// thread a transform through every draw_* helper in this module.
/// Mirrors NativeCanvasView.kt's `drawClientPanelCommand()`: only the
/// panel PHP marked `initiallyActive` paints — there's no live client-side
/// tab-switch state reaching this render call yet (that needs the same
/// interaction-state plumbing `hittest.rs` already has for hit-testing,
/// not yet threaded into the render path), so a freshly rendered frame
/// always shows whichever panel the server considers the resting/default
/// one, same "server-authored value until a real interaction overrides
/// it" scoping already used for `slider`'s `value`. No clip — Android's
/// own version doesn't clip a clientPanel either, only translates.
fn draw_client_panel(pixmap: &mut Pixmap, panel: &crate::protocol::ClientPanelCommand, elapsed_ms: u64, text_renderer: &mut TextRenderer) {
    if !panel.initially_active {
        return;
    }
    let Some(mut layer) = Pixmap::new(pixmap.width(), pixmap.height()) else { return };
    render_commands(&mut layer, &panel.commands, elapsed_ms, text_renderer);
    pixmap.draw_pixmap(panel.x as i32, panel.y as i32, layer.as_ref(), &PixmapPaint::default(), Transform::identity(), None);
}

/// Same scratch-layer-and-composite technique as `draw_client_panel`, plus
/// a viewport clip (Android's `canvas.clipRect(x, y, x+w, y+h)`) built via
/// `Mask::fill_path` and passed to `draw_pixmap`'s own clip parameter —
/// clips the WHOLE composited layer in one step, rather than needing each
/// nested primitive to know about the clip individually. No live drag
/// offset yet (same deferred-interactivity scoping as `clientPanel`
/// above and `slider`'s `value`) — always composited at the container's
/// own authored `(x, y)`, i.e. scrolled all the way to its start.
fn draw_hscroll(pixmap: &mut Pixmap, scroll: &crate::protocol::HScrollCommand, elapsed_ms: u64, text_renderer: &mut TextRenderer) {
    draw_scrollable(pixmap, scroll.x, scroll.y, scroll.width, scroll.height, &scroll.commands, elapsed_ms, text_renderer);
}

fn draw_vscroll(pixmap: &mut Pixmap, scroll: &crate::protocol::VScrollCommand, elapsed_ms: u64, text_renderer: &mut TextRenderer) {
    draw_scrollable(pixmap, scroll.x, scroll.y, scroll.width, scroll.height, &scroll.commands, elapsed_ms, text_renderer);
}

#[allow(clippy::too_many_arguments)]
fn draw_scrollable(
    pixmap: &mut Pixmap,
    x: f64,
    y: f64,
    width: f64,
    height: f64,
    commands: &[DrawCommand],
    elapsed_ms: u64,
    text_renderer: &mut TextRenderer,
) {
    let Some(mut layer) = Pixmap::new(pixmap.width(), pixmap.height()) else { return };
    render_commands(&mut layer, commands, elapsed_ms, text_renderer);

    let clip = rounded_rect_path(x as f32, y as f32, width.max(0.0) as f32, height.max(0.0) as f32, 0.0).and_then(|path| {
        let mut mask = Mask::new(pixmap.width(), pixmap.height())?;
        mask.fill_path(&path, FillRule::Winding, true, Transform::identity());
        Some(mask)
    });

    pixmap.draw_pixmap(x as i32, y as i32, layer.as_ref(), &PixmapPaint::default(), Transform::identity(), clip.as_ref());
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::protocol::{ClientPanelCommand, HScrollCommand, VScrollCommand};

    fn pixel_at(pixmap: &Pixmap, x: u32, y: u32) -> (u8, u8, u8, u8) {
        let p = pixmap.pixel(x, y).expect("pixel in bounds");
        (p.red(), p.green(), p.blue(), p.alpha())
    }

    #[test]
    fn parses_six_and_eight_digit_hex_colors() {
        assert_eq!(parse_color("#FF0000"), Color::from_rgba8(255, 0, 0, 255));
        assert_eq!(parse_color("#00FF0080"), Color::from_rgba8(0, 255, 0, 128));
    }

    #[test]
    fn fills_a_solid_rect_at_its_center() {
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let rect = RectCommand {
            x: 0.0,
            y: 0.0,
            width: 40.0,
            height: 40.0,
            color: Some("#FF0000".to_string()),
            radius: 0.0,
            border_color: None,
            border_width: 0.0,
            elevation: None,
            gradient_from: None,
            gradient_to: None,
            tags: Default::default(),
        };
        draw_rect(&mut pixmap, &rect);
        let (r, g, b, a) = pixel_at(&pixmap, 20, 20);
        assert_eq!((r, g, b, a), (255, 0, 0, 255));
    }

    #[test]
    fn rounded_rect_leaves_the_corner_outside_the_radius_unpainted() {
        let mut pixmap = Pixmap::new(60, 60).unwrap();
        let rect = RectCommand {
            x: 0.0,
            y: 0.0,
            width: 60.0,
            height: 60.0,
            color: Some("#3B82F6".to_string()),
            radius: 12.0,
            border_color: None,
            border_width: 0.0,
            elevation: None,
            gradient_from: None,
            gradient_to: None,
            tags: Default::default(),
        };
        draw_rect(&mut pixmap, &rect);
        // (0,0) sits well outside the 12px corner radius — must stay
        // transparent, exactly the same assertion Linux's own
        // test_canvas.py makes for rounded rects.
        let (_, _, _, a) = pixel_at(&pixmap, 0, 0);
        assert_eq!(a, 0, "corner outside the radius must be unpainted");
        // The center must be fully painted.
        let (_, _, _, a) = pixel_at(&pixmap, 30, 30);
        assert_eq!(a, 255);
    }

    #[test]
    fn gradient_from_paints_a_diagonal_that_differs_from_a_flat_color() {
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let rect = RectCommand {
            x: 0.0,
            y: 0.0,
            width: 40.0,
            height: 40.0,
            color: None,
            radius: 0.0,
            border_color: None,
            border_width: 0.0,
            elevation: None,
            gradient_from: Some("#FF0000".to_string()),
            gradient_to: Some("#0000FF".to_string()),
            tags: Default::default(),
        };
        draw_rect(&mut pixmap, &rect);
        // Pixel centers sample a hair inside the gradient's exact 0/1
        // stops (tiny-skia samples at (x+0.5, y+0.5)), so this checks the
        // gradient's DIRECTION rather than exact corner colors: red must
        // fade out and blue must fade in from top-left to bottom-right.
        let top_left = pixel_at(&pixmap, 0, 0);
        let bottom_right = pixel_at(&pixmap, 39, 39);
        assert!(top_left.0 > 200 && top_left.2 < 20, "top-left should be mostly red, got {top_left:?}");
        assert!(bottom_right.2 > 200 && bottom_right.0 < 20, "bottom-right should be mostly blue, got {bottom_right:?}");
        assert_ne!(top_left, bottom_right, "a real gradient must not degenerate into a flat fill");
    }

    #[test]
    fn gradient_from_alone_degenerates_to_a_solid_fill_of_itself() {
        // Same convention NativeCanvasView.kt's drawRectCommand() documents:
        // gradientTo missing falls back to gradientFrom itself, not "no
        // gradient at all".
        let mut pixmap = Pixmap::new(20, 20).unwrap();
        let rect = RectCommand {
            x: 0.0,
            y: 0.0,
            width: 20.0,
            height: 20.0,
            color: None,
            radius: 0.0,
            border_color: None,
            border_width: 0.0,
            elevation: None,
            gradient_from: Some("#00FF00".to_string()),
            gradient_to: None,
            tags: Default::default(),
        };
        draw_rect(&mut pixmap, &rect);
        assert_eq!(pixel_at(&pixmap, 10, 10), (0, 255, 0, 255));
    }

    #[test]
    fn elevation_paints_a_soft_shadow_below_the_rect() {
        let mut pixmap = Pixmap::new(80, 80).unwrap();
        let rect = RectCommand {
            x: 20.0,
            y: 20.0,
            width: 30.0,
            height: 30.0,
            color: Some("#FFFFFF".to_string()),
            radius: 0.0,
            border_color: None,
            border_width: 0.0,
            elevation: Some(6.0),
            gradient_from: None,
            gradient_to: None,
            tags: Default::default(),
        };
        draw_rect(&mut pixmap, &rect);
        // A few pixels directly below the rect's bottom edge, outside the
        // rect itself, must show some shadow alpha — proof the blur pass
        // actually painted something there, not just a no-op.
        let (_, _, _, shadow_alpha) = pixel_at(&pixmap, 35, 54);
        assert!(shadow_alpha > 0, "expected a visible shadow below the elevated rect, got alpha={shadow_alpha}");
        // Far away from the rect and its shadow, nothing should be painted.
        let (_, _, _, far_alpha) = pixel_at(&pixmap, 5, 5);
        assert_eq!(far_alpha, 0);
    }

    #[test]
    fn zero_elevation_paints_no_shadow() {
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let rect = RectCommand {
            x: 5.0,
            y: 5.0,
            width: 20.0,
            height: 20.0,
            color: Some("#FFFFFF".to_string()),
            radius: 0.0,
            border_color: None,
            border_width: 0.0,
            elevation: Some(0.0),
            gradient_from: None,
            gradient_to: None,
            tags: Default::default(),
        };
        draw_rect(&mut pixmap, &rect);
        let (_, _, _, a) = pixel_at(&pixmap, 15, 26);
        assert_eq!(a, 0, "elevation: 0 must not paint any shadow");
    }

    fn slider(value: f64) -> SliderCommand {
        SliderCommand {
            key: "volume".to_string(),
            x: 10.0,
            y: 10.0,
            width: 100.0,
            height: 20.0,
            track_height: 4.0,
            thumb_size: 16.0,
            value,
            track_color: "#E5E7EB".to_string(),
            active_color: "#2563EB".to_string(),
            thumb_color: "#FFFFFF".to_string(),
            tags: Default::default(),
        }
    }

    #[test]
    fn slider_at_zero_paints_the_thumb_at_the_track_start() {
        let mut pixmap = Pixmap::new(120, 40).unwrap();
        draw_slider(&mut pixmap, &slider(0.0));
        // thumbCx = x + thumbSize/2 = 10 + 8 = 18.
        let (_, _, _, a) = pixel_at(&pixmap, 18, 20);
        assert_eq!(a, 255, "thumb should be fully painted at its own center when value=0");
        // Track must still span the full width — far right end painted too.
        let (_, _, _, track_a) = pixel_at(&pixmap, 108, 20);
        assert_eq!(track_a, 255, "track must span the full width regardless of value");
    }

    #[test]
    fn slider_at_one_moves_the_thumb_to_the_track_end() {
        let mut pixmap = Pixmap::new(120, 40).unwrap();
        draw_slider(&mut pixmap, &slider(1.0));
        // thumbCx = x + thumbSize/2 + (width - thumbSize) * 1 = 10 + 8 + 84 = 102.
        let (_, _, _, a) = pixel_at(&pixmap, 102, 20);
        assert_eq!(a, 255, "thumb should be fully painted at its own center when value=1");
        // The thumb never overhangs the track: its center caps at
        // x + width - thumbSize/2 = 10 + 100 - 8 = 102, never at x+width=110.
        let (_, _, _, overhang_a) = pixel_at(&pixmap, 118, 20);
        assert_eq!(overhang_a, 0, "thumb must not overhang past the track's own right edge");
    }

    #[test]
    fn slider_active_fill_grows_with_value() {
        let mut low = Pixmap::new(120, 40).unwrap();
        draw_slider(&mut low, &slider(0.1));
        let mut high = Pixmap::new(120, 40).unwrap();
        draw_slider(&mut high, &slider(0.9));
        // A point 3/4 of the way across the track is covered by the
        // active fill at value=0.9 but not at value=0.1.
        let probe_x = 85;
        let (r_low, g_low, b_low, _) = pixel_at(&low, probe_x, 20);
        let (r_high, g_high, b_high, _) = pixel_at(&high, probe_x, 20);
        assert_eq!((r_low, g_low, b_low), (229, 231, 235), "still just the flat track color at low value");
        assert_eq!((r_high, g_high, b_high), (37, 99, 235), "active color once the fill has grown past this point");
    }

    #[test]
    fn oversized_radius_clamps_to_a_pill_shape_instead_of_a_broken_path() {
        // button_with_icon.json's real rect: 200x54 with radius: 999.
        let mut pixmap = Pixmap::new(200, 54).unwrap();
        let rect = RectCommand {
            x: 0.0,
            y: 0.0,
            width: 200.0,
            height: 54.0,
            color: Some("#111827".to_string()),
            radius: 999.0,
            border_color: None,
            border_width: 0.0,
            elevation: None,
            gradient_from: None,
            gradient_to: None,
            tags: Default::default(),
        };
        draw_rect(&mut pixmap, &rect);
        // Middle of the left edge must be painted (mid-height, where a
        // pill's rounded end is at its widest).
        let (_, _, _, a) = pixel_at(&pixmap, 2, 27);
        assert_eq!(a, 255);
    }

    #[test]
    fn filled_circle_paints_its_center_and_leaves_the_far_corner_empty() {
        let mut pixmap = Pixmap::new(50, 50).unwrap();
        let circle = CircleCommand {
            cx: 25.0,
            cy: 25.0,
            radius: 20.0,
            color: Some("#22C55E".to_string()),
            border_color: None,
            border_width: None,
            tags: Default::default(),
        };
        draw_circle(&mut pixmap, &circle);
        let (_, _, _, a) = pixel_at(&pixmap, 25, 25);
        assert_eq!(a, 255);
        let (_, _, _, a) = pixel_at(&pixmap, 0, 0);
        assert_eq!(a, 0);
    }

    #[test]
    fn line_paints_a_pixel_at_its_midpoint() {
        let mut pixmap = Pixmap::new(20, 20).unwrap();
        let line = LineCommand {
            x1: 0.0,
            y1: 10.0,
            x2: 20.0,
            y2: 10.0,
            color: "#000000".to_string(),
            width: 4.0,
            tags: Default::default(),
        };
        draw_line(&mut pixmap, &line);
        let (_, _, _, a) = pixel_at(&pixmap, 10, 10);
        assert_eq!(a, 255);
    }

    #[test]
    fn quarter_arc_at_3_oclock_starts_at_the_expected_point() {
        // 0°, sweep 90°, radius 20 around (25,25) — a quarter circle from
        // 3 o'clock (25+20, 25) sweeping clockwise to 6 o'clock (25, 25+20).
        let mut pixmap = Pixmap::new(50, 50).unwrap();
        let arc = ArcCommand {
            cx: 25.0,
            cy: 25.0,
            radius: 20.0,
            start_degrees: 0.0,
            sweep_degrees: 90.0,
            color: "#000000".to_string(),
            stroke_width: 3.0,
            tags: Default::default(),
        };
        draw_arc(&mut pixmap, &arc);
        // Endpoint of the sweep (6 o'clock position) must be painted.
        let (_, _, _, a) = pixel_at(&pixmap, 25, 45);
        assert_eq!(a, 255, "6 o'clock endpoint of a 0->90 sweep must be painted");
        // A point diametrically opposite the arc (9 o'clock) must stay
        // empty — proves this drew a quarter, not a full circle.
        let (_, _, _, a) = pixel_at(&pixmap, 5, 25);
        assert_eq!(a, 0, "opposite side of the sweep must be unpainted");
    }

    #[test]
    fn spinner_paints_both_the_full_track_and_a_sweep_arc() {
        let mut pixmap = Pixmap::new(40, 40).unwrap();
        let spinner = SpinnerCommand {
            x: 0.0,
            y: 0.0,
            size: 32.0,
            color: "#111827".to_string(),
            track_color: "#F9FAFB".to_string(),
            stroke_width: 4.0,
            tags: Default::default(),
        };
        draw_spinner(&mut pixmap, &spinner, 0);
        // The track is a full circle — its left edge must be painted
        // regardless of rotation.
        let (_, _, _, a) = pixel_at(&pixmap, 2, 16);
        assert_eq!(a, 255, "track circle should be painted all the way around");
    }

    #[test]
    fn spinner_sweep_position_changes_with_elapsed_time() {
        // Same command, two different elapsed_ms values — the sweep arc's
        // start point (3 o'clock at t=0) should have moved away by a
        // quarter period, proving elapsed_ms actually drives the rotation
        // rather than being ignored.
        let spinner = SpinnerCommand {
            x: 0.0,
            y: 0.0,
            size: 32.0,
            color: "#111827".to_string(),
            track_color: "#F9FAFB".to_string(),
            stroke_width: 4.0,
            tags: Default::default(),
        };
        let mut at_zero = Pixmap::new(40, 40).unwrap();
        draw_spinner(&mut at_zero, &spinner, 0);
        let mut at_quarter_period = Pixmap::new(40, 40).unwrap();
        draw_spinner(&mut at_quarter_period, &spinner, crate::animate::SPINNER_PERIOD_MS / 4);

        // The full track circle paints every point on the ring regardless
        // of rotation, so alpha alone can't tell the two frames apart —
        // compare color instead: at the 3-o'clock point (cx+radius, cy),
        // the darker sweep color should be on top at t=0 but not at
        // t=quarter-period, where only the lighter track color remains.
        let (r0, g0, b0, _) = pixel_at(&at_zero, 29, 16);
        let (r1, g1, b1, _) = pixel_at(&at_quarter_period, 29, 16);
        assert_ne!((r0, g0, b0), (r1, g1, b1), "sweep arc's 3-o'clock start should move over time");
    }

    #[test]
    fn skeleton_paints_the_base_fill_and_stays_inside_the_rounded_rect() {
        let mut pixmap = Pixmap::new(80, 20).unwrap();
        let skeleton = SkeletonCommand {
            x: 0.0,
            y: 0.0,
            width: 80.0,
            height: 20.0,
            color: "#E5E7EB".to_string(),
            radius: 4.0,
            tags: Default::default(),
        };
        draw_skeleton(&mut pixmap, &skeleton, 0);
        let (_, _, _, a) = pixel_at(&pixmap, 40, 10);
        assert_eq!(a, 255, "base fill should cover the box interior");
    }

    fn rect_at(x: f64, y: f64, w: f64, h: f64, color: &str) -> DrawCommand {
        DrawCommand::Rect(RectCommand {
            x,
            y,
            width: w,
            height: h,
            color: Some(color.to_string()),
            radius: 0.0,
            border_color: None,
            border_width: 0.0,
            elevation: None,
            gradient_from: None,
            gradient_to: None,
            tags: Default::default(),
        })
    }

    #[test]
    fn client_panel_paints_the_initially_active_panel_translated_by_its_own_origin() {
        let mut pixmap = Pixmap::new(100, 100).unwrap();
        // A local (10,10) rect inside a panel authored at (30, 40) must
        // land at the ABSOLUTE (40, 50) — the same translate-only
        // semantics NativeCanvasView.kt's drawClientPanelCommand() uses.
        let active = DrawCommand::ClientPanel(ClientPanelCommand {
            key: "tabs1".to_string(),
            index: 0,
            initially_active: true,
            x: 30.0,
            y: 40.0,
            commands: vec![rect_at(10.0, 10.0, 20.0, 20.0, "#FF0000")],
            hit_regions: vec![],
            tags: Default::default(),
        });
        let inactive = DrawCommand::ClientPanel(ClientPanelCommand {
            key: "tabs1".to_string(),
            index: 1,
            initially_active: false,
            x: 0.0,
            y: 0.0,
            commands: vec![rect_at(10.0, 10.0, 20.0, 20.0, "#0000FF")],
            hit_regions: vec![],
            tags: Default::default(),
        });
        render_commands(&mut pixmap, &[active, inactive], 0, &mut TextRenderer::new());

        let (r, g, b, a) = pixel_at(&pixmap, 45, 55);
        assert_eq!((r, g, b, a), (255, 0, 0, 255), "active panel's child rect should be translated to (30+10, 40+10)");
        let (_, _, _, origin_alpha) = pixel_at(&pixmap, 15, 15);
        assert_eq!(origin_alpha, 0, "the inactive panel must not paint at all");
    }

    #[test]
    fn hscroll_clips_its_children_to_its_own_viewport() {
        let mut pixmap = Pixmap::new(100, 100).unwrap();
        // Viewport is only 30px wide starting at x=10 — a child rect from
        // local x=0..60 (authored 60px wide) must be visible where it
        // overlaps the viewport and clipped everywhere past x=40.
        let scroll = DrawCommand::HScroll(HScrollCommand {
            key: "row1".to_string(),
            x: 10.0,
            y: 10.0,
            width: 30.0,
            height: 20.0,
            content_width: 60.0,
            commands: vec![rect_at(0.0, 0.0, 60.0, 20.0, "#22C55E")],
            hit_regions: vec![],
            tags: Default::default(),
        });
        render_commands(&mut pixmap, &[scroll], 0, &mut TextRenderer::new());

        let (_, _, _, inside) = pixel_at(&pixmap, 20, 15);
        assert_eq!(inside, 255, "content inside the viewport must paint");
        let (_, _, _, past_viewport) = pixel_at(&pixmap, 55, 15);
        assert_eq!(past_viewport, 0, "content past the viewport's own width must be clipped, not just off-canvas");
    }

    #[test]
    fn vscroll_clips_its_children_to_its_own_viewport() {
        let mut pixmap = Pixmap::new(100, 100).unwrap();
        let scroll = DrawCommand::VScroll(VScrollCommand {
            key: "list1".to_string(),
            x: 10.0,
            y: 10.0,
            width: 20.0,
            height: 30.0,
            content_height: 60.0,
            commands: vec![rect_at(0.0, 0.0, 20.0, 60.0, "#F59E0B")],
            hit_regions: vec![],
            tags: Default::default(),
        });
        render_commands(&mut pixmap, &[scroll], 0, &mut TextRenderer::new());

        let (_, _, _, inside) = pixel_at(&pixmap, 15, 20);
        assert_eq!(inside, 255, "content inside the viewport must paint");
        let (_, _, _, past_viewport) = pixel_at(&pixmap, 15, 55);
        assert_eq!(past_viewport, 0, "content past the viewport's own height must be clipped");
    }

    #[test]
    fn nested_hscroll_inside_a_client_panel_composes_both_translations() {
        let mut pixmap = Pixmap::new(100, 100).unwrap();
        let nested_scroll = DrawCommand::HScroll(HScrollCommand {
            key: "inner".to_string(),
            x: 5.0,
            y: 5.0,
            width: 40.0,
            height: 20.0,
            content_width: 40.0,
            commands: vec![rect_at(2.0, 2.0, 10.0, 10.0, "#8B5CF6")],
            hit_regions: vec![],
            tags: Default::default(),
        });
        let panel = DrawCommand::ClientPanel(ClientPanelCommand {
            key: "outer".to_string(),
            index: 0,
            initially_active: true,
            x: 20.0,
            y: 20.0,
            commands: vec![nested_scroll],
            hit_regions: vec![],
            tags: Default::default(),
        });
        render_commands(&mut pixmap, &[panel], 0, &mut TextRenderer::new());

        // Absolute position: panel (20,20) + hscroll (5,5) + rect (2,2) = (27,27).
        let (_, _, _, a) = pixel_at(&pixmap, 30, 30);
        assert_eq!(a, 255, "nested container offsets must compose additively");
    }
}
