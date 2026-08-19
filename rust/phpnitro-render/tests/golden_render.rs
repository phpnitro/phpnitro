//! Decodes the golden fixtures that `packages/ui/tests/Golden/` already
//! generates from real `Canvas::toJson()` output (and that iOS's
//! `DrawCommandTests.swift` and Linux's `test_canvas.py` already consume
//! too) — referenced directly by relative path, never copied by hand, so
//! there is exactly one file per fixture across the whole repo.
//!
//! Decode-only for now: pixel-rendering assertions land once `raster.rs`
//! exists (Phase 1, commit 5+). This still proves the protocol module
//! handles every shape real PHP output actually produces, not just the
//! hand-built JSON in `protocol.rs`'s own unit tests.

use phpnitro_render::protocol::decode_envelope;
use phpnitro_render::raster::render_commands;
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

#[test]
fn flex_row_distribution_renders_the_two_expected_rects() {
    // The only existing golden fixture whose commands are 100% covered by
    // raster.rs today (two plain rects, no text) — a real pixel test, not
    // just a decode check.
    let json = fixture("flex_row_distribution.json");
    let envelope = decode_envelope(&json).unwrap();
    let mut pixmap = Pixmap::new(100, 40).unwrap();
    render_commands(&mut pixmap, &envelope.commands);

    let red_rect = pixmap.pixel(20, 20).unwrap();
    assert_eq!((red_rect.red(), red_rect.green(), red_rect.blue()), (239, 68, 68), "#EF4444");

    let blue_rect = pixmap.pixel(68, 20).unwrap();
    assert_eq!((blue_rect.red(), blue_rect.green(), blue_rect.blue()), (59, 130, 246), "#3B82F6");

    let gap = pixmap.pixel(44, 20).unwrap();
    assert_eq!(gap.alpha(), 0, "the 8px gap between the two rects must stay unpainted");
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
