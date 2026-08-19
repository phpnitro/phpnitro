//! Rasterizes the geometric primitives of the draw-command protocol onto a
//! `tiny_skia::Pixmap` — an in-memory RGBA buffer, no display server
//! involved, directly analogous to the `cairo.ImageSurface` approach Linux
//! already uses for offscreen, display-server-free pixel tests.
//!
//! Text (`text`/`icon`, need real glyph shaping) and images (`image`,
//! needs a byte source) are handled by later modules/commits — not yet
//! wired here. Nested containers (`clientPanel`/`hScroll`/`vScroll`) are
//! deliberately NOT recursed into yet either: whether their `commands[]`
//! carry absolute or panel-relative coordinates isn't confirmed against
//! `Canvas.php` (only their `hitRegions[]` are documented as
//! panel-relative) — guessing here would silently bake in a wrong
//! coordinate convention, so it's left for a dedicated, verified commit.

use crate::protocol::{ArcCommand, CircleCommand, DrawCommand, LineCommand, RectCommand};
use tiny_skia::{Color, FillRule, LineCap, Paint, Path, PathBuilder, Pixmap, Rect, Stroke, Transform};

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
    let path = rounded_rect_path(x, y, w, h, rect.radius as f32);
    let Some(path) = path else { return };

    if let Some(color) = &rect.color {
        pixmap.fill_path(&path, &solid_paint(parse_color(color)), FillRule::Winding, Transform::identity(), None);
    }
    if let Some(border_color) = &rect.border_color {
        if rect.border_width > 0.0 {
            let stroke = stroke_of(rect.border_width as f32);
            pixmap.stroke_path(&path, &solid_paint(parse_color(border_color)), &stroke, Transform::identity(), None);
        }
    }
    // Elevation (drop shadow) and gradientFrom/gradientTo are decoded but
    // not yet painted — no golden fixture exercises a gradient today, and
    // a shadow needs a second, blurred pass this crate doesn't have yet.
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

/// Rasterizes every command this module already knows how to draw;
/// anything else (text/icon/image/spinner/skeleton/clientPanel/hScroll/
/// vScroll/slider/custom/unknown) is silently skipped for now — each gets
/// its own dedicated module and commit rather than a half-correct guess
/// here.
pub fn render_commands(pixmap: &mut Pixmap, commands: &[DrawCommand]) {
    for command in commands {
        match command {
            DrawCommand::Rect(rect) => draw_rect(pixmap, rect),
            DrawCommand::Circle(circle) => draw_circle(pixmap, circle),
            DrawCommand::Line(line) => draw_line(pixmap, line),
            DrawCommand::Arc(arc) => draw_arc(pixmap, arc),
            _ => {}
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

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
}
