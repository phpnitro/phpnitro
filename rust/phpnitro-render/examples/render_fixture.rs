//! Manual visual sanity check — renders one draw-command JSON fixture to a
//! PNG for a human to actually look at. Not run by `cargo test` (examples
//! are opt-in), so it costs CI nothing; it exists because pixel-exact
//! assertions can pass while still looking visually wrong in a way only a
//! human glance catches (mirrors the one manual PNG export the Linux
//! renderer's own test suite already relies on).
//!
//! Usage: `cargo run --example render_fixture -- <fixture.json> [width] [height] [out.png]`

use phpnitro_render::protocol::{decode_envelope, DrawCommand};
use phpnitro_render::raster::render_commands;
use phpnitro_render::text::TextRenderer;
use std::fs;
use tiny_skia::Pixmap;

fn main() {
    let mut args = std::env::args().skip(1);
    let path = args.next().expect("usage: render_fixture <fixture.json> [width] [height] [out.png]");
    let width: u32 = args.next().and_then(|s| s.parse().ok()).unwrap_or(300);
    let height: u32 = args.next().and_then(|s| s.parse().ok()).unwrap_or(100);
    let out = args.next().unwrap_or_else(|| "render_check.png".to_string());

    let json = fs::read_to_string(&path).unwrap_or_else(|e| panic!("reading {path}: {e}"));
    let envelope = decode_envelope(&json).unwrap_or_else(|e| panic!("decoding {path}: {e}"));

    let mut pixmap = Pixmap::new(width, height).expect("non-zero width/height");
    render_commands(&mut pixmap, &envelope.commands, 0);

    let mut text_renderer = TextRenderer::new();
    for command in &envelope.commands {
        match command {
            DrawCommand::Text(t) => text_renderer.render_text(&mut pixmap, t),
            DrawCommand::Icon(i) => text_renderer.render_icon(&mut pixmap, i),
            _ => {}
        }
    }

    pixmap.save_png(&out).unwrap_or_else(|e| panic!("saving {out}: {e}"));
    println!("saved {out}");
}
