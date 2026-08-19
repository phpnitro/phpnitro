//! `custom:sparkline` / `custom:barChart` / `custom:pieChart` — today
//! these are hardcoded APPLICATION-level handlers in
//! `NativeRenderPocActivity.registerCustomCommandHandlers()` (Android
//! only; every other platform decodes them as `DrawCommand::Custom` and
//! draws nothing), even though `Engine\Native\Sparkline`/`BarChart`/
//! `PieChart` are shipped, first-party PHP widgets. This module makes
//! them an actual capability of the rendering engine instead — the first
//! time they become available on every platform this crate reaches, not
//! just Android with its own app-level registration.
//!
//! Every formula below is copied field-for-field from that Kotlin
//! registration (the only place this drawing logic exists today), not
//! re-derived — including its exact epsilon-avoidance choices (a flat
//! `values` array collapses to `range = 1.0` / `max = 1.0` rather than
//! dividing by zero).

use crate::protocol::CustomCommand;
use crate::raster::parse_color;
use serde_json::{Map, Value};
use tiny_skia::{FillRule, LineCap, LineJoin, Paint, PathBuilder, Pixmap, Stroke, Transform};

fn f64_field(fields: &Map<String, Value>, key: &str) -> Option<f64> {
    fields.get(key).and_then(Value::as_f64)
}

fn str_field<'a>(fields: &'a Map<String, Value>, key: &str) -> Option<&'a str> {
    fields.get(key).and_then(Value::as_str)
}

fn f64_array_field(fields: &Map<String, Value>, key: &str) -> Option<Vec<f64>> {
    fields.get(key)?.as_array()?.iter().map(Value::as_f64).collect()
}

fn str_array_field<'a>(fields: &'a Map<String, Value>, key: &str) -> Option<Vec<&'a str>> {
    fields.get(key)?.as_array()?.iter().map(Value::as_str).collect()
}

/// Draws every `custom:*` type this module recognizes; any other
/// `custom:*` type (a third-party extension this crate has never heard
/// of) is silently skipped, matching how an unknown top-level command
/// type is handled elsewhere in this crate.
pub fn render_custom(pixmap: &mut Pixmap, custom: &CustomCommand) {
    match custom.custom_type.as_str() {
        "sparkline" => render_sparkline(pixmap, &custom.fields),
        "barChart" => render_bar_chart(pixmap, &custom.fields),
        "pieChart" => render_pie_chart(pixmap, &custom.fields),
        _ => {}
    }
}

fn render_sparkline(pixmap: &mut Pixmap, fields: &Map<String, Value>) {
    let (Some(x), Some(y), Some(w), Some(h), Some(values), Some(color)) = (
        f64_field(fields, "x"),
        f64_field(fields, "y"),
        f64_field(fields, "width"),
        f64_field(fields, "height"),
        f64_array_field(fields, "values"),
        str_field(fields, "color"),
    ) else {
        return;
    };
    if values.len() < 2 {
        return;
    }
    let (x, y, w, h) = (x as f32, y as f32, w as f32, h as f32);
    let min = values.iter().cloned().fold(f64::MAX, f64::min);
    let max = values.iter().cloned().fold(f64::MIN, f64::max);
    let range = if max - min > 0.0 { max - min } else { 1.0 };

    let mut pb = PathBuilder::new();
    let last_index = (values.len() - 1) as f64;
    for (i, value) in values.iter().enumerate() {
        let px = x + w * i as f32 / last_index as f32;
        let py = y + h - h * ((value - min) / range) as f32;
        if i == 0 {
            pb.move_to(px, py);
        } else {
            pb.line_to(px, py);
        }
    }
    let Some(path) = pb.finish() else { return };

    let mut paint = Paint::default();
    paint.set_color(parse_color(color));
    paint.anti_alias = true;
    let stroke = Stroke {
        width: 2.5,
        line_cap: LineCap::Round,
        line_join: LineJoin::Round,
        ..Stroke::default()
    };
    pixmap.stroke_path(&path, &paint, &stroke, Transform::identity(), None);
}

fn render_bar_chart(pixmap: &mut Pixmap, fields: &Map<String, Value>) {
    let (Some(x), Some(y), Some(w), Some(h), Some(gap), Some(values), Some(color)) = (
        f64_field(fields, "x"),
        f64_field(fields, "y"),
        f64_field(fields, "width"),
        f64_field(fields, "height"),
        f64_field(fields, "gap"),
        f64_array_field(fields, "values"),
        str_field(fields, "color"),
    ) else {
        return;
    };
    let count = values.len();
    if count == 0 {
        return;
    }
    let (x, y, w, h, gap) = (x as f32, y as f32, w as f32, h as f32, gap as f32);
    let mut max = values.iter().cloned().fold(0.0_f64, f64::max);
    if max <= 0.0 {
        max = 1.0;
    }
    let bar_width = (w - gap * (count - 1) as f32) / count as f32;

    let mut paint = Paint::default();
    paint.set_color(parse_color(color));
    paint.anti_alias = true;

    for (i, value) in values.iter().enumerate() {
        let bar_height = (h * (value / max) as f32).max(0.0);
        let left = x + i as f32 * (bar_width + gap);
        if let Some(rect) = tiny_skia::Rect::from_xywh(left, y + h - bar_height, bar_width, bar_height) {
            pixmap.fill_rect(rect, &paint, Transform::identity(), None);
        }
    }
}

/// A filled pie wedge from `center` out to the circle and back — distinct
/// from `raster.rs`'s `arc_path` (an open stroked arc with no center
/// point), since `useCenter = true` on Android draws a solid slice, not
/// just a curved stroke.
fn pie_slice_path(cx: f32, cy: f32, radius: f32, start_degrees: f32, sweep_degrees: f32) -> Option<tiny_skia::Path> {
    if radius <= 0.0 || sweep_degrees.abs() < f32::EPSILON {
        return None;
    }
    let segments = ((sweep_degrees.abs() / 90.0).ceil() as usize).max(1);
    let segment_sweep = sweep_degrees / segments as f32;
    let point_at = |angle: f32| (cx + radius * angle.cos(), cy + radius * angle.sin());
    let mut angle = start_degrees.to_radians();
    let mut pb = PathBuilder::new();
    pb.move_to(cx, cy);
    let (sx, sy) = point_at(angle);
    pb.line_to(sx, sy);
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
    pb.close();
    pb.finish()
}

fn render_pie_chart(pixmap: &mut Pixmap, fields: &Map<String, Value>) {
    let (Some(x), Some(y), Some(diameter), Some(values), Some(colors)) = (
        f64_field(fields, "x"),
        f64_field(fields, "y"),
        f64_field(fields, "diameter"),
        f64_array_field(fields, "values"),
        str_array_field(fields, "colors"),
    ) else {
        return;
    };
    let count = values.len();
    let total: f64 = values.iter().sum();
    if count == 0 || total <= 0.0 || colors.len() < count {
        return;
    }
    let (x, y, diameter) = (x as f32, y as f32, diameter as f32);
    let (cx, cy, radius) = (x + diameter / 2.0, y + diameter / 2.0, diameter / 2.0);

    let mut start_angle = -90.0_f32;
    for (value, color) in values.iter().zip(colors.iter()) {
        let sweep = (value / total * 360.0) as f32;
        if let Some(path) = pie_slice_path(cx, cy, radius, start_angle, sweep) {
            let mut paint = Paint::default();
            paint.set_color(parse_color(color));
            paint.anti_alias = true;
            pixmap.fill_path(&path, &paint, FillRule::Winding, Transform::identity(), None);
        }
        start_angle += sweep;
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::protocol::{decode_envelope, DrawCommand};

    fn custom_from(json: &str) -> CustomCommand {
        let envelope_json = format!("{{\"commands\":[{json}],\"hitRegions\":[],\"contentHeight\":0}}");
        let envelope = decode_envelope(&envelope_json).unwrap();
        match envelope.commands.into_iter().next().unwrap() {
            DrawCommand::Custom(custom) => custom,
            other => panic!("expected Custom, got {other:?}"),
        }
    }

    #[test]
    fn sparkline_draws_a_line_from_low_to_high() {
        let custom = custom_from(
            r##"{"type":"custom:sparkline","x":0,"y":0,"width":100,"height":40,"values":[0.0,10.0],"color":"#3366FF"}"##,
        );
        let mut pixmap = Pixmap::new(100, 40).unwrap();
        render_custom(&mut pixmap, &custom);
        // Rising from (0,40) to (100,0) — a pixel near the top-right end
        // of the line should be painted, one near the bottom-left too.
        assert!(pixmap.pixel(95, 3).unwrap().alpha() > 0, "top-right end of the line");
        assert!(pixmap.pixel(2, 37).unwrap().alpha() > 0, "bottom-left start of the line");
    }

    #[test]
    fn sparkline_with_fewer_than_two_points_paints_nothing() {
        let custom = custom_from(
            r##"{"type":"custom:sparkline","x":0,"y":0,"width":100,"height":40,"values":[5.0],"color":"#3366FF"}"##,
        );
        let mut pixmap = Pixmap::new(100, 40).unwrap();
        render_custom(&mut pixmap, &custom);
        assert!(pixmap.pixels().iter().all(|p| p.alpha() == 0));
    }

    #[test]
    fn bar_chart_scales_bars_relative_to_the_max_value() {
        let custom = custom_from(
            r##"{"type":"custom:barChart","x":0,"y":0,"width":100,"height":50,"gap":10,"values":[10.0,20.0],"color":"#22C55E"}"##,
        );
        let mut pixmap = Pixmap::new(100, 50).unwrap();
        render_custom(&mut pixmap, &custom);
        // Bar 0 (value 10, half of max 20) should NOT reach halfway up;
        // bar 1 (the max) should reach the very top.
        let bar0_top_half = pixmap.pixel(10, 5).unwrap();
        assert_eq!(bar0_top_half.alpha(), 0, "shorter bar shouldn't paint near the very top");
        let bar1_top = pixmap.pixel(90, 1).unwrap();
        assert!(bar1_top.alpha() > 0, "the max-value bar should reach near the top");
    }

    #[test]
    fn pie_chart_paints_two_slices_in_their_own_colors() {
        let custom = custom_from(
            r##"{"type":"custom:pieChart","x":0,"y":0,"diameter":60,"values":[1.0,1.0],"colors":["#FF0000","#0000FF"]}"##,
        );
        let mut pixmap = Pixmap::new(60, 60).unwrap();
        render_custom(&mut pixmap, &custom);
        // Two equal slices starting at -90 deg (12 o'clock): the first
        // spans to 90 deg (right half), the second the left half.
        let right_side = pixmap.pixel(45, 30).unwrap();
        assert_eq!((right_side.red(), right_side.green(), right_side.blue()), (255, 0, 0));
        let left_side = pixmap.pixel(15, 30).unwrap();
        assert_eq!((left_side.red(), left_side.green(), left_side.blue()), (0, 0, 255));
    }
}
