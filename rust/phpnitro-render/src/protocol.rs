//! Mirrors the JSON envelope produced by `Engine\Native\Canvas::toJson()`
//! (`packages/ui/src/Native/Canvas.php`), the single source of truth for
//! this wire protocol. Field names use `#[serde(rename_all = "camelCase")]`
//! so the Rust struct fields stay idiomatic snake_case while matching the
//! PHP-emitted JSON keys exactly.
//!
//! `DrawCommand` needs a hand-written `Deserialize` impl rather than a
//! derived one because of `custom:$type` commands (e.g. `custom:sparkline`)
//! — the `type` field's value isn't one of a fixed set of strings, so
//! serde's usual internally-tagged-enum derive can't express it.

use serde::{Deserialize, Deserializer};
use serde_json::{Map, Value};

fn default_zero() -> f64 {
    0.0
}

fn default_one() -> f64 {
    1.0
}

fn default_text_color() -> String {
    "#000000".to_string()
}

fn default_text_size() -> f64 {
    16.0
}

fn default_icon_color() -> String {
    "#111827".to_string()
}

fn default_icon_font() -> String {
    "material".to_string()
}

/// `fixed`/`hero`/`dismiss`/`reorder` tagging applied by whichever
/// `beginFixed`/`beginHero`/`beginDismiss`/`beginReorder` scope was open
/// when a command was appended (`Canvas::tagFixed()`) — can appear on any
/// geometric command or `HitRegion`, so it's flattened into each of them
/// rather than duplicated field-by-field.
#[derive(Debug, Clone, PartialEq, Default, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct CommandTags {
    #[serde(default)]
    pub fixed: Option<bool>,
    #[serde(default)]
    pub hero: Option<String>,
    #[serde(default)]
    pub dismiss: Option<String>,
    #[serde(default)]
    pub reorder: Option<String>,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct RectCommand {
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    #[serde(default)]
    pub color: Option<String>,
    #[serde(default = "default_zero")]
    pub radius: f64,
    #[serde(default)]
    pub border_color: Option<String>,
    #[serde(default = "default_zero")]
    pub border_width: f64,
    #[serde(default)]
    pub elevation: Option<f64>,
    #[serde(default)]
    pub gradient_from: Option<String>,
    #[serde(default)]
    pub gradient_to: Option<String>,
    #[serde(flatten)]
    pub tags: CommandTags,
}

/// `(x, y)` is the text **baseline**, matching `Canvas.drawText()` — every
/// non-Android renderer shifts by the font's ascender to compensate.
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct TextCommand {
    pub x: f64,
    pub y: f64,
    pub text: String,
    #[serde(default = "default_text_color")]
    pub color: String,
    #[serde(default = "default_text_size")]
    pub size: f64,
    #[serde(default)]
    pub bold: bool,
    #[serde(default)]
    pub letter_spacing: Option<f64>,
    #[serde(default)]
    pub font_family: Option<String>,
    #[serde(flatten)]
    pub tags: CommandTags,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct IconCommand {
    pub x: f64,
    pub y: f64,
    pub size: f64,
    pub codepoint: u32,
    #[serde(default = "default_icon_color")]
    pub color: String,
    #[serde(default = "default_icon_font")]
    pub font: String,
    #[serde(flatten)]
    pub tags: CommandTags,
}

/// `url` is either a real HTTP(S) URL or a `data:` URI (camera/gallery
/// capture) — every existing renderer branches on `url.starts_with("data:")`
/// for a synchronous base64 decode instead of a network fetch.
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct ImageCommand {
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub url: String,
    #[serde(default = "default_zero")]
    pub radius: f64,
    #[serde(flatten)]
    pub tags: CommandTags,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct CircleCommand {
    pub cx: f64,
    pub cy: f64,
    pub radius: f64,
    #[serde(default)]
    pub color: Option<String>,
    #[serde(default)]
    pub border_color: Option<String>,
    #[serde(default)]
    pub border_width: Option<f64>,
    #[serde(flatten)]
    pub tags: CommandTags,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct LineCommand {
    pub x1: f64,
    pub y1: f64,
    pub x2: f64,
    pub y2: f64,
    pub color: String,
    #[serde(default = "default_one")]
    pub width: f64,
    #[serde(flatten)]
    pub tags: CommandTags,
}

/// Android `drawArc()` convention: 0° = 3 o'clock, sweeps clockwise.
/// Confirmed identical on Cairo (Linux); Core Graphics (iOS) needs a sign
/// flip locally because of its flipped default coordinate space — that
/// flip belongs in `raster.rs`, this struct just carries the wire values.
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct ArcCommand {
    pub cx: f64,
    pub cy: f64,
    pub radius: f64,
    pub start_degrees: f64,
    pub sweep_degrees: f64,
    pub color: String,
    pub stroke_width: f64,
    #[serde(flatten)]
    pub tags: CommandTags,
}

/// No rotation/phase field on the wire — every renderer independently
/// computes `rotation = (now_ms % periodMs) / periodMs * 360°` with a fixed
/// 110° sweep from wall-clock time (see `animate.rs`).
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct SpinnerCommand {
    pub x: f64,
    pub y: f64,
    pub size: f64,
    pub color: String,
    pub track_color: String,
    pub stroke_width: f64,
    #[serde(flatten)]
    pub tags: CommandTags,
}

/// No phase field on the wire either — shimmer position is likewise a pure
/// function of wall-clock time (see `animate.rs`).
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct SkeletonCommand {
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub color: String,
    #[serde(default = "default_zero")]
    pub radius: f64,
    #[serde(flatten)]
    pub tags: CommandTags,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct ClientPanelCommand {
    pub key: String,
    pub index: i64,
    pub initially_active: bool,
    pub x: f64,
    pub y: f64,
    pub commands: Vec<DrawCommand>,
    #[serde(default)]
    pub hit_regions: Vec<HitRegion>,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct HScrollCommand {
    pub key: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub content_width: f64,
    pub commands: Vec<DrawCommand>,
    #[serde(default)]
    pub hit_regions: Vec<HitRegion>,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct VScrollCommand {
    pub key: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub content_height: f64,
    pub commands: Vec<DrawCommand>,
    #[serde(default)]
    pub hit_regions: Vec<HitRegion>,
}

/// `value` is server-authored per frame but locally overridden while the
/// user is actively dragging the thumb — that override lives in a caller
/// -supplied interaction-state map, never in this struct.
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct SliderCommand {
    pub key: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub track_height: f64,
    pub thumb_size: f64,
    pub value: f64,
    pub track_color: String,
    pub active_color: String,
    pub thumb_color: String,
    #[serde(flatten)]
    pub tags: CommandTags,
}

/// `custom:$type` extension escape hatch — no engine-level schema, so the
/// remaining fields are kept as a raw JSON object rather than a typed
/// struct. Known production examples (`sparkline`/`barChart`/`pieChart`)
/// get typed handling in `charts.rs`, matched on `custom_type`.
#[derive(Debug, Clone, PartialEq)]
pub struct CustomCommand {
    pub custom_type: String,
    pub fields: Map<String, Value>,
}

#[derive(Debug, Clone, PartialEq)]
pub enum DrawCommand {
    Rect(RectCommand),
    Text(TextCommand),
    Icon(IconCommand),
    Image(ImageCommand),
    Circle(CircleCommand),
    Line(LineCommand),
    Arc(ArcCommand),
    Spinner(SpinnerCommand),
    Skeleton(SkeletonCommand),
    ClientPanel(ClientPanelCommand),
    HScroll(HScrollCommand),
    VScroll(VScrollCommand),
    Slider(SliderCommand),
    Custom(CustomCommand),
    /// A `type` this crate doesn't know yet — kept as raw JSON instead of
    /// failing the whole decode, so protocol drift degrades gracefully.
    Unknown(Value),
}

impl<'de> Deserialize<'de> for DrawCommand {
    fn deserialize<D>(deserializer: D) -> Result<Self, D::Error>
    where
        D: Deserializer<'de>,
    {
        let value = Value::deserialize(deserializer)?;
        let type_str = value
            .get("type")
            .and_then(Value::as_str)
            .unwrap_or_default()
            .to_string();

        macro_rules! decode {
            ($variant:ident, $ty:ty) => {
                serde_json::from_value::<$ty>(value.clone())
                    .map(DrawCommand::$variant)
                    .map_err(serde::de::Error::custom)
            };
        }

        match type_str.as_str() {
            "rect" => decode!(Rect, RectCommand),
            "text" => decode!(Text, TextCommand),
            "icon" => decode!(Icon, IconCommand),
            "image" => decode!(Image, ImageCommand),
            "circle" => decode!(Circle, CircleCommand),
            "line" => decode!(Line, LineCommand),
            "arc" => decode!(Arc, ArcCommand),
            "spinner" => decode!(Spinner, SpinnerCommand),
            "skeleton" => decode!(Skeleton, SkeletonCommand),
            "clientPanel" => decode!(ClientPanel, ClientPanelCommand),
            "hScroll" => decode!(HScroll, HScrollCommand),
            "vScroll" => decode!(VScroll, VScrollCommand),
            "slider" => decode!(Slider, SliderCommand),
            t if t.starts_with("custom:") => {
                let custom_type = t.trim_start_matches("custom:").to_string();
                let mut fields = value.as_object().cloned().unwrap_or_default();
                fields.remove("type");
                Ok(DrawCommand::Custom(CustomCommand { custom_type, fields }))
            }
            _ => Ok(DrawCommand::Unknown(value)),
        }
    }
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct HitRegion {
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub action: String,
    #[serde(default)]
    pub meta: Option<Value>,
    #[serde(flatten)]
    pub tags: CommandTags,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct HeroRegion {
    pub tag: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    #[serde(default)]
    pub curve: Option<String>,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct DismissRegion {
    pub key: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub action: String,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct ReorderRegion {
    pub group: String,
    pub key: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub action: String,
}

/// No Canvas-drawable equivalent at all — registers a real native Lottie
/// overlay view instead. Out of the rasterizer's scope by design; kept
/// here only so the envelope decodes fully.
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct LottieRegion {
    pub key: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub url: String,
    #[serde(rename = "loop")]
    pub is_loop: bool,
    pub autoplay: bool,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct SheetRegion {
    pub key: String,
    pub x: f64,
    pub y: f64,
    pub width: f64,
    pub height: f64,
    pub sheet_height: f64,
    pub close_action: String,
    #[serde(flatten)]
    pub tags: CommandTags,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct AutoNavigate {
    pub screen: String,
    pub after_ms: i64,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct Snackbar {
    pub message: String,
    pub duration_ms: i64,
}

#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub enum Transition {
    Fade,
    SlideLeft,
    SlideRight,
    SlideUp,
}

/// The full `Canvas::toJson()` envelope. Deliberately decodes everything,
/// not just `commands[]` — the FFI surface hands this whole struct across
/// the boundary as one JSON blob rather than picking it apart into several
/// separate parameters (per the project decision to keep the wire shape
/// future-proof for navigation/confetti/snackbar/etc. rather than a
/// draw-only cut).
#[derive(Debug, Clone, PartialEq, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct Envelope {
    pub commands: Vec<DrawCommand>,
    pub hit_regions: Vec<HitRegion>,
    #[serde(default)]
    pub hero_regions: Vec<HeroRegion>,
    #[serde(default)]
    pub dismiss_regions: Vec<DismissRegion>,
    #[serde(default)]
    pub reorder_regions: Vec<ReorderRegion>,
    #[serde(default)]
    pub lottie_regions: Vec<LottieRegion>,
    /// Exact field shape not yet confirmed against a real fixture (no
    /// golden fixture exercises `slider` yet in a way that captures this
    /// array) — kept as raw JSON rather than guessed at, revisit once a
    /// slider fixture exists.
    #[serde(default)]
    pub slider_regions: Vec<Value>,
    #[serde(default)]
    pub sheet_regions: Vec<SheetRegion>,
    #[serde(default)]
    pub auto_navigate: Option<AutoNavigate>,
    #[serde(default)]
    pub poll_again: Option<i64>,
    pub content_height: f64,
    #[serde(default)]
    pub render_time_ms: Option<f64>,
    #[serde(default)]
    pub redirect: Option<String>,
    #[serde(default)]
    pub scroll_follow: Option<bool>,
    #[serde(default)]
    pub pull_to_refresh: Option<String>,
    #[serde(default)]
    pub confetti: Option<bool>,
    #[serde(default)]
    pub snackbar: Option<Snackbar>,
    #[serde(default)]
    pub transition: Option<Transition>,
    pub hash: String,
}

/// Decodes one full `Canvas::toJson()` payload.
pub fn decode_envelope(json: &str) -> Result<Envelope, serde_json::Error> {
    serde_json::from_str(json)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn decodes_a_minimal_envelope() {
        let json = r##"{
            "commands": [{"type": "rect", "x": 0.0, "y": 0.0, "width": 10.0, "height": 10.0, "color": "#FF0000", "radius": 0.0}],
            "hitRegions": [],
            "contentHeight": 10.0,
            "hash": "abc123"
        }"##;
        let envelope = decode_envelope(json).unwrap();
        assert_eq!(envelope.commands.len(), 1);
        assert_eq!(envelope.content_height, 10.0);
        assert!(matches!(envelope.commands[0], DrawCommand::Rect(_)));
    }

    #[test]
    fn rect_defaults_radius_and_border_width_to_zero_when_absent() {
        let json = r#"{"type": "rect", "x": 0.0, "y": 0.0, "width": 1.0, "height": 1.0}"#;
        let command: DrawCommand = serde_json::from_str(json).unwrap();
        match command {
            DrawCommand::Rect(rect) => {
                assert_eq!(rect.radius, 0.0);
                assert_eq!(rect.border_width, 0.0);
                assert_eq!(rect.color, None);
            }
            other => panic!("expected Rect, got {other:?}"),
        }
    }

    #[test]
    fn text_defaults_color_to_black_and_size_to_16_when_absent() {
        let json = r#"{"type": "text", "x": 0.0, "y": 0.0, "text": "hi"}"#;
        let command: DrawCommand = serde_json::from_str(json).unwrap();
        match command {
            DrawCommand::Text(text) => {
                assert_eq!(text.color, "#000000");
                assert_eq!(text.size, 16.0);
                assert!(!text.bold);
            }
            other => panic!("expected Text, got {other:?}"),
        }
    }

    #[test]
    fn icon_defaults_to_material_font_and_dark_color() {
        let json = r#"{"type": "icon", "x": 0.0, "y": 0.0, "size": 24.0, "codepoint": 61761}"#;
        let command: DrawCommand = serde_json::from_str(json).unwrap();
        match command {
            DrawCommand::Icon(icon) => {
                assert_eq!(icon.font, "material");
                assert_eq!(icon.color, "#111827");
            }
            other => panic!("expected Icon, got {other:?}"),
        }
    }

    #[test]
    fn custom_command_captures_type_suffix_and_remaining_fields() {
        let json = r##"{"type": "custom:sparkline", "x": 0.0, "y": 0.0, "width": 100.0, "height": 40.0, "values": [1.0, 2.0, 3.0], "color": "#3366FF"}"##;
        let command: DrawCommand = serde_json::from_str(json).unwrap();
        match command {
            DrawCommand::Custom(custom) => {
                assert_eq!(custom.custom_type, "sparkline");
                assert!(!custom.fields.contains_key("type"));
                assert_eq!(custom.fields.get("color").unwrap(), "#3366FF");
            }
            other => panic!("expected Custom, got {other:?}"),
        }
    }

    #[test]
    fn unrecognized_type_decodes_as_unknown_instead_of_failing() {
        let json = r#"{"type": "somethingFromTheFuture", "foo": 1}"#;
        let command: DrawCommand = serde_json::from_str(json).unwrap();
        assert!(matches!(command, DrawCommand::Unknown(_)));
    }

    #[test]
    fn tagged_fixed_hero_dismiss_reorder_are_flattened_onto_the_command() {
        let json = r#"{"type": "rect", "x": 0.0, "y": 0.0, "width": 1.0, "height": 1.0, "fixed": true, "hero": "avatar", "dismiss": "item-1", "reorder": "item-1"}"#;
        let command: DrawCommand = serde_json::from_str(json).unwrap();
        match command {
            DrawCommand::Rect(rect) => {
                assert_eq!(rect.tags.fixed, Some(true));
                assert_eq!(rect.tags.hero.as_deref(), Some("avatar"));
                assert_eq!(rect.tags.dismiss.as_deref(), Some("item-1"));
                assert_eq!(rect.tags.reorder.as_deref(), Some("item-1"));
            }
            other => panic!("expected Rect, got {other:?}"),
        }
    }

    #[test]
    fn nested_client_panel_decodes_its_inner_commands_and_hit_regions() {
        let json = r#"{
            "type": "clientPanel", "key": "tabs", "index": 0, "initiallyActive": true,
            "x": 0.0, "y": 0.0,
            "commands": [{"type": "text", "x": 0.0, "y": 0.0, "text": "hi"}],
            "hitRegions": [{"x": 0.0, "y": 0.0, "width": 10.0, "height": 10.0, "action": "tap:me"}]
        }"#;
        let command: DrawCommand = serde_json::from_str(json).unwrap();
        match command {
            DrawCommand::ClientPanel(panel) => {
                assert_eq!(panel.commands.len(), 1);
                assert_eq!(panel.hit_regions.len(), 1);
                assert_eq!(panel.hit_regions[0].action, "tap:me");
            }
            other => panic!("expected ClientPanel, got {other:?}"),
        }
    }
}
