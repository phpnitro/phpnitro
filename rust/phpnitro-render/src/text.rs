//! Glyph shaping and rasterization for `text`/`icon` commands, via
//! `cosmic-text` (shaping + font matching) and its bundled `swash`
//! rasterizer. Owns the 3 fonts every other platform already bundles
//! (`MaterialIcons-Regular.ttf`, `FontAwesome-Solid.ttf`,
//! `Roboto-Regular.ttf`, copied byte-for-byte from `android/engine`'s
//! assets — verified identical via md5sum before copying) as the *only*
//! fonts this crate ever loads: `FontSystem::new_with_fonts([...])` does
//! no system-font scanning at all, so rendering is fully self-contained
//! and doesn't depend on whatever fonts happen to exist on a given CI
//! image or dev machine.
//!
//! `text` commands already carry an explicit baseline `(x, y)` — PHP's
//! `TextMetrics::wrap()` has already computed line breaks and hands us
//! one already-positioned line per command, so this module deliberately
//! bypasses `cosmic-text`'s own multi-line paragraph layout (`line_y`
//! stacking) and treats `command.y` as the literal baseline row.

use crate::protocol::{IconCommand, TextCommand};
use crate::raster::parse_color;
use cosmic_text::{Attrs, Buffer, Family, FontSystem, Metrics, Shaping, SwashCache, Weight};
use fontdb::Source;
use std::sync::Arc;
use tiny_skia::{Color, Pixmap, PremultipliedColorU8};

const MATERIAL_ICONS_TTF: &[u8] = include_bytes!("../fonts/MaterialIcons-Regular.ttf");
const FONT_AWESOME_TTF: &[u8] = include_bytes!("../fonts/FontAwesome-Solid.ttf");
const ROBOTO_TTF: &[u8] = include_bytes!("../fonts/Roboto-Regular.ttf");

const MATERIAL_ICONS_FAMILY: &str = "Material Icons";
/// Confirmed via `fc-query --format='%{family}\n'` against the real
/// bundled file — its embedded family name really is "...6 Free Solid",
/// not "...5" as an earlier iOS/macOS pass had briefly assumed.
const FONT_AWESOME_FAMILY: &str = "Font Awesome 6 Free Solid";
const BODY_FAMILY: &str = "Roboto";

struct Baseline {
    font_size: f32,
    x: f32,
    y: f32,
    color: Color,
}

pub struct TextRenderer {
    font_system: FontSystem,
    swash_cache: SwashCache,
}

impl Default for TextRenderer {
    fn default() -> Self {
        Self::new()
    }
}

impl TextRenderer {
    pub fn new() -> Self {
        let fonts = [MATERIAL_ICONS_TTF, FONT_AWESOME_TTF, ROBOTO_TTF]
            .into_iter()
            .map(|bytes| Source::Binary(Arc::new(bytes.to_vec())));
        Self {
            font_system: FontSystem::new_with_fonts(fonts),
            swash_cache: SwashCache::new(),
        }
    }

    pub fn render_text(&mut self, pixmap: &mut Pixmap, command: &TextCommand) {
        let weight = if command.bold { Weight::BOLD } else { Weight::NORMAL };
        let attrs = Attrs::new().family(Family::Name(BODY_FAMILY)).weight(weight);
        self.draw_line(
            pixmap,
            &command.text,
            attrs,
            Baseline {
                font_size: command.size as f32,
                x: command.x as f32,
                y: command.y as f32,
                color: parse_color(&command.color),
            },
        );
    }

    /// `icon.x/y` is the box's top-left corner (unlike `text`, which gets
    /// an explicit baseline) — approximated here as
    /// `baseline_y = y + size * 0.88`, matching how square icon-font
    /// glyphs (their ink filling most of the em box, near-zero descent)
    /// are typically positioned. Not yet cross-checked against a real
    /// Android render, since there is no reference image to diff against
    /// in this environment — worth revisiting once Phase 2 can compare
    /// against a live screenshot.
    pub fn render_icon(&mut self, pixmap: &mut Pixmap, command: &IconCommand) {
        let Some(glyph) = char::from_u32(command.codepoint) else {
            return;
        };
        let family = if command.font == "fontawesome" {
            FONT_AWESOME_FAMILY
        } else {
            MATERIAL_ICONS_FAMILY
        };
        let attrs = Attrs::new().family(Family::Name(family));
        let baseline_y = command.y as f32 + command.size as f32 * 0.88;
        let mut buf = [0u8; 4];
        self.draw_line(
            pixmap,
            glyph.encode_utf8(&mut buf),
            attrs,
            Baseline {
                font_size: command.size as f32,
                x: command.x as f32,
                y: baseline_y,
                color: parse_color(&command.color),
            },
        );
    }

    fn draw_line(&mut self, pixmap: &mut Pixmap, text: &str, attrs: Attrs, at: Baseline) {
        if text.is_empty() || at.font_size <= 0.0 {
            return;
        }
        let metrics = Metrics::new(at.font_size, at.font_size * 1.2);
        let mut buffer = Buffer::new(&mut self.font_system, metrics);
        buffer.set_text(&mut self.font_system, text, attrs, Shaping::Advanced);

        let base_rgb = (at.color.red() * 255.0, at.color.green() * 255.0, at.color.blue() * 255.0);
        let base_rgb = (base_rgb.0 as u8, base_rgb.1 as u8, base_rgb.2 as u8);
        let base_color = cosmic_text::Color::rgba(base_rgb.0, base_rgb.1, base_rgb.2, 255);

        for run in buffer.layout_runs() {
            for glyph in run.glyphs.iter() {
                let physical_glyph = glyph.physical((0.0, 0.0), 1.0);
                let origin_x = at.x as i32 + physical_glyph.x;
                let origin_y = at.y as i32 + physical_glyph.y;
                self.swash_cache.with_pixels(
                    &mut self.font_system,
                    physical_glyph.cache_key,
                    base_color,
                    |dx, dy, pixel_color| {
                        blend_pixel(
                            pixmap,
                            origin_x + dx,
                            origin_y + dy,
                            (pixel_color.r(), pixel_color.g(), pixel_color.b()),
                            pixel_color.a(),
                        );
                    },
                );
            }
        }
    }
}

/// Standard "source over" alpha compositing in premultiplied space —
/// `Pixmap` already stores every pixel premultiplied, and `swash`'s glyph
/// coverage arrives as a straight (unpremultiplied) color plus an 8-bit
/// coverage alpha, so the source side gets premultiplied here before the
/// blend.
fn blend_pixel(pixmap: &mut Pixmap, x: i32, y: i32, straight_rgb: (u8, u8, u8), coverage: u8) {
    if coverage == 0 || x < 0 || y < 0 {
        return;
    }
    let (x, y) = (x as u32, y as u32);
    if x >= pixmap.width() || y >= pixmap.height() {
        return;
    }
    let idx = (y * pixmap.width() + x) as usize;
    let dst = pixmap.pixels()[idx];

    let src_a = coverage as f32 / 255.0;
    let inv = 1.0 - src_a;
    let src_r = straight_rgb.0 as f32 * src_a;
    let src_g = straight_rgb.1 as f32 * src_a;
    let src_b = straight_rgb.2 as f32 * src_a;

    let out_a = coverage as f32 + dst.alpha() as f32 * inv;
    let out_r = src_r + dst.red() as f32 * inv;
    let out_g = src_g + dst.green() as f32 * inv;
    let out_b = src_b + dst.blue() as f32 * inv;

    let clamp = |v: f32| v.round().clamp(0.0, 255.0) as u8;
    if let Some(out) = PremultipliedColorU8::from_rgba(clamp(out_r), clamp(out_g), clamp(out_b), clamp(out_a)) {
        pixmap.pixels_mut()[idx] = out;
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::protocol::CommandTags;

    fn any_painted_pixel(pixmap: &Pixmap) -> bool {
        pixmap.pixels().iter().any(|p| p.alpha() > 0)
    }

    #[test]
    fn renders_body_text_and_paints_something() {
        let mut renderer = TextRenderer::new();
        let mut pixmap = Pixmap::new(200, 60).unwrap();
        let command = TextCommand {
            x: 4.0,
            y: 40.0,
            text: "Bonjour".to_string(),
            color: "#111827".to_string(),
            size: 24.0,
            bold: false,
            letter_spacing: None,
            font_family: None,
            tags: CommandTags::default(),
        };
        renderer.render_text(&mut pixmap, &command);
        assert!(any_painted_pixel(&pixmap), "expected Roboto to actually rasterize glyphs");
    }

    #[test]
    fn renders_a_material_icon_glyph() {
        let mut renderer = TextRenderer::new();
        let mut pixmap = Pixmap::new(48, 48).unwrap();
        // codepoint from button_with_icon.json's real icon command.
        let command = IconCommand {
            x: 0.0,
            y: 0.0,
            size: 24.0,
            codepoint: 58826,
            color: "#FFFFFF".to_string(),
            font: "material".to_string(),
            tags: CommandTags::default(),
        };
        renderer.render_icon(&mut pixmap, &command);
        assert!(any_painted_pixel(&pixmap), "expected the Material Icons font to actually load and rasterize");
    }

    #[test]
    fn renders_a_fontawesome_icon_glyph() {
        let mut renderer = TextRenderer::new();
        let mut pixmap = Pixmap::new(48, 48).unwrap();
        // codepoint + font from icon_fontawesome.json's real command.
        let command = IconCommand {
            x: 0.0,
            y: 0.0,
            size: 24.0,
            codepoint: 61444,
            color: "#DC2626".to_string(),
            font: "fontawesome".to_string(),
            tags: CommandTags::default(),
        };
        renderer.render_icon(&mut pixmap, &command);
        assert!(any_painted_pixel(&pixmap), "expected the Font Awesome font to actually load and rasterize");
    }

    #[test]
    fn empty_text_paints_nothing_and_does_not_panic() {
        let mut renderer = TextRenderer::new();
        let mut pixmap = Pixmap::new(20, 20).unwrap();
        let command = TextCommand {
            x: 0.0,
            y: 10.0,
            text: String::new(),
            color: "#000000".to_string(),
            size: 16.0,
            bold: false,
            letter_spacing: None,
            font_family: None,
            tags: CommandTags::default(),
        };
        renderer.render_text(&mut pixmap, &command);
        assert!(!any_painted_pixel(&pixmap));
    }
}
