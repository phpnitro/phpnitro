//! Decodes the golden fixtures that `packages/ui/tests/Golden/` already
//! generates from real `Canvas::toJson()` output (and that iOS's
//! `DrawCommandTests.swift` and Linux's `test_canvas.py` already consume
//! too) — referenced directly by relative path, never copied by hand, so
//! there is exactly one file per fixture across the whole repo.
//!
//! Mostly decode-only: pixel-rendering assertions exist for command types
//! `raster.rs`/`text.rs` already implement (rect/circle/line/arc/text/
//! icon). This still proves the protocol module handles every shape real
//! PHP output actually produces, not just the hand-built JSON in
//! `protocol.rs`'s own unit tests.

use phpnitro_render::hittest::InteractionState;
use phpnitro_render::protocol::decode_envelope;
use phpnitro_render::raster::render_commands;
use phpnitro_render::text::TextRenderer;
use std::fs;
use std::path::PathBuf;
use tiny_skia::Pixmap;

fn fixture(name: &str) -> String {
    let mut path = PathBuf::from(env!("CARGO_MANIFEST_DIR"));
    path.push("../../packages/ui/tests/Golden/__fixtures__");
    path.push(name);
    fs::read_to_string(&path).unwrap_or_else(|e| panic!("reading {path:?}: {e}"))
}

macro_rules! golden_decode_test {
    ($test_name:ident, $file:literal) => {
        #[test]
        fn $test_name() {
            let json = fixture($file);
            let envelope = decode_envelope(&json)
                .unwrap_or_else(|e| panic!("decoding {}: {e}", $file));
            assert!(
                !envelope.commands.is_empty() || !json.contains("\"type\""),
                "{} decoded with zero commands, that's almost certainly wrong",
                $file
            );
        }
    };
}

golden_decode_test!(decodes_button_with_icon, "button_with_icon.json");
golden_decode_test!(decodes_container_with_padding, "container_with_padding.json");
golden_decode_test!(decodes_flex_row_distribution, "flex_row_distribution.json");
golden_decode_test!(decodes_icon_fontawesome, "icon_fontawesome.json");
golden_decode_test!(decodes_screen_device, "screen_device.json");
golden_decode_test!(decodes_screen_home, "screen_home.json");
golden_decode_test!(decodes_screen_widgets_forms, "screen_widgets_forms.json");
golden_decode_test!(decodes_text_wrapping, "text_wrapping.json");
golden_decode_test!(decodes_circle_basic, "circle_basic.json");
golden_decode_test!(decodes_image_network_and_data_uri, "image_network_and_data_uri.json");
golden_decode_test!(decodes_spinner_basic, "spinner_basic.json");
golden_decode_test!(decodes_hscroll_basic, "hscroll_basic.json");

#[test]
fn flex_row_distribution_renders_the_two_expected_rects() {
    // The only existing golden fixture whose commands are 100% covered by
    // raster.rs today (two plain rects, no text) — a real pixel test, not
    // just a decode check.
    let json = fixture("flex_row_distribution.json");
    let envelope = decode_envelope(&json).unwrap();
    let mut pixmap = Pixmap::new(100, 40).unwrap();
    render_commands(&mut pixmap, &envelope.commands, 0, &mut TextRenderer::new(), &InteractionState::default());

    let red_rect = pixmap.pixel(20, 20).unwrap();
    assert_eq!((red_rect.red(), red_rect.green(), red_rect.blue()), (239, 68, 68), "#EF4444");

    let blue_rect = pixmap.pixel(68, 20).unwrap();
    assert_eq!((blue_rect.red(), blue_rect.green(), blue_rect.blue()), (59, 130, 246), "#3B82F6");

    let gap = pixmap.pixel(44, 20).unwrap();
    assert_eq!(gap.alpha(), 0, "the 8px gap between the two rects must stay unpainted");
}

#[test]
fn circle_basic_renders_a_filled_circle() {
    use phpnitro_render::protocol::DrawCommand;

    let json = fixture("circle_basic.json");
    let envelope = decode_envelope(&json).unwrap();
    assert!(matches!(envelope.commands[0], DrawCommand::Circle(_)));

    let mut pixmap = Pixmap::new(60, 60).unwrap();
    render_commands(&mut pixmap, &envelope.commands, 0, &mut TextRenderer::new(), &InteractionState::default());
    let center = pixmap.pixel(30, 30).unwrap();
    assert_eq!((center.red(), center.green(), center.blue(), center.alpha()), (34, 197, 94, 255), "#22C55E");
    let corner = pixmap.pixel(0, 0).unwrap();
    assert_eq!(corner.alpha(), 0, "outside the 24px radius must stay unpainted");
}

#[test]
fn image_network_and_data_uri_decodes_both_url_forms() {
    use phpnitro_render::protocol::DrawCommand;

    let json = fixture("image_network_and_data_uri.json");
    let envelope = decode_envelope(&json).unwrap();
    assert_eq!(envelope.commands.len(), 2);
    let urls: Vec<&str> = envelope
        .commands
        .iter()
        .map(|c| match c {
            DrawCommand::Image(image) => image.url.as_str(),
            other => panic!("expected Image, got {other:?}"),
        })
        .collect();
    assert!(urls[0].starts_with("https://"));
    assert!(urls[1].starts_with("data:image/png;base64,"));
}

#[test]
fn spinner_basic_decodes_with_no_rotation_field_on_the_wire() {
    use phpnitro_render::protocol::DrawCommand;

    let json = fixture("spinner_basic.json");
    let envelope = decode_envelope(&json).unwrap();
    match &envelope.commands[0] {
        DrawCommand::Spinner(spinner) => {
            assert_eq!(spinner.size, 32.0);
            assert!(spinner.stroke_width > 0.0);
        }
        other => panic!("expected Spinner, got {other:?}"),
    }
}

#[test]
fn hscroll_basic_nested_commands_are_panel_relative_not_absolute() {
    use phpnitro_render::protocol::DrawCommand;

    // Confirms, from real PHP output, what HorizontalScroll::paint()'s own
    // source already implies: children are painted into a fresh nested
    // Canvas starting at their own local offset, never shifted by the
    // panel's own (x, y) — the first child sits at x=0 even though the
    // panel itself could be placed anywhere on screen.
    let json = fixture("hscroll_basic.json");
    let envelope = decode_envelope(&json).unwrap();
    match &envelope.commands[0] {
        DrawCommand::HScroll(scroll) => {
            assert_eq!(scroll.commands.len(), 3);
            match &scroll.commands[0] {
                DrawCommand::Rect(rect) => assert_eq!(rect.x, 0.0, "first child stays panel-relative"),
                other => panic!("expected Rect, got {other:?}"),
            }
        }
        other => panic!("expected HScroll, got {other:?}"),
    }
}

#[test]
fn button_with_icon_has_exactly_the_expected_command_shape() {
    let json = fixture("button_with_icon.json");
    let envelope = decode_envelope(&json).unwrap();
    assert_eq!(envelope.commands.len(), 3, "rect + icon + text");
    assert_eq!(envelope.hit_regions.len(), 1);
    assert_eq!(envelope.hit_regions[0].action, "submit:demo");
}

#[test]
fn screen_widgets_forms_decodes_every_command_type_it_claims_to_cover() {
    // The richest fixture (170KB) — per the project's own research this is
    // documented as covering rect/text/icon/line/arc/skeleton/slider/
    // clientPanel/vScroll/custom:barChart/custom:pieChart/custom:sparkline.
    // Walking it recursively (clientPanel/vScroll nest their own commands)
    // and asserting zero DrawCommand::Unknown values is a strong signal
    // that this crate's decoder has no real gap against actual PHP output.
    use phpnitro_render::protocol::DrawCommand;

    fn walk(commands: &[DrawCommand], unknown_types: &mut Vec<String>) {
        for command in commands {
            match command {
                DrawCommand::ClientPanel(panel) => walk(&panel.commands, unknown_types),
                DrawCommand::HScroll(scroll) => walk(&scroll.commands, unknown_types),
                DrawCommand::VScroll(scroll) => walk(&scroll.commands, unknown_types),
                DrawCommand::Unknown(value) => unknown_types.push(
                    value
                        .get("type")
                        .and_then(|t| t.as_str())
                        .unwrap_or("<no type field>")
                        .to_string(),
                ),
                _ => {}
            }
        }
    }

    let json = fixture("screen_widgets_forms.json");
    let envelope = decode_envelope(&json).unwrap();
    let mut unknown_types = Vec::new();
    walk(&envelope.commands, &mut unknown_types);
    assert!(
        unknown_types.is_empty(),
        "unrecognized command types in screen_widgets_forms.json: {unknown_types:?}"
    );
}
