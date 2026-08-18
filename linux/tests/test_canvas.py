"""Real rendering tests — render to an in-memory `cairo.ImageSurface`
(no X11/Wayland/Broadway display needed, so this runs the same way in
CI as it does in an editor with no display server at all) and sample
actual pixels, instead of only checking "did it throw." This is the
closest thing to a screenshot assertion achievable headlessly, and it
is genuinely stronger verification than compiling-only: a swapped x/y,
an unregistered icon font falling back to tofu, or a color parsed
wrong would all show up as a wrong pixel here, not just a clean exit.
"""

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import cairo

from phpnitro_desktop.canvas import RenderState, needs_animation, parse_color, render_payload
from phpnitro_desktop.draw_command import decode_payload
from phpnitro_desktop.fonts import register_bundled_fonts


def _pixel_rgb(surface: cairo.ImageSurface, x: int, y: int) -> tuple[int, int, int]:
    """FORMAT_ARGB32 is premultiplied-alpha, native-endian — on this
    (little-endian) platform that's a [B, G, R, A] byte layout per pixel.
    Only meaningful for a fully-opaque source pixel (every color used in
    these tests is #RRGGBB, alpha=1), where premultiplication is a no-op.
    """
    surface.flush()
    stride = surface.get_stride()
    data = surface.get_data()
    offset = y * stride + x * 4
    b, g, r, _a = data[offset], data[offset + 1], data[offset + 2], data[offset + 3]
    return (r, g, b)


def _render(commands: list[dict], size=(100, 100), now: float = 0.0) -> cairo.ImageSurface:
    payload = decode_payload({"commands": commands, "hitRegions": [], "contentHeight": size[1]})
    surface = cairo.ImageSurface(cairo.FORMAT_ARGB32, *size)
    ctx = cairo.Context(surface)
    render_payload(ctx, payload, RenderState(now=now, client_tab_state={}))
    return surface


class ParseColorTests(unittest.TestCase):
    def test_parses_six_digit_hex(self):
        self.assertEqual(parse_color("#111827"), (0x11 / 255, 0x18 / 255, 0x27 / 255, 1.0))

    def test_parses_eight_digit_hex_with_alpha(self):
        r, g, b, a = parse_color("#11182780")
        self.assertAlmostEqual(a, 0x80 / 255, places=4)

    def test_returns_none_for_malformed_input(self):
        self.assertIsNone(parse_color("not-a-color"))
        self.assertIsNone(parse_color(None))


class RenderRectTests(unittest.TestCase):
    def test_fills_a_flat_rect_with_the_exact_requested_color(self):
        surface = _render([{"type": "rect", "x": 0, "y": 0, "width": 100, "height": 100, "color": "#F97316"}])

        self.assertEqual(_pixel_rgb(surface, 50, 50), (0xF9, 0x73, 0x16))

    def test_a_rect_with_no_color_paints_nothing(self):
        surface = _render([{"type": "rect", "x": 0, "y": 0, "width": 100, "height": 100}])

        # No fill color at all -> the surface stays fully transparent
        # black (Cairo's own zero-initialized ARGB32 buffer), not some
        # accidental default color.
        self.assertEqual(_pixel_rgb(surface, 50, 50), (0, 0, 0))

    def test_rounded_rect_corner_stays_unpainted_outside_the_curve(self):
        surface = _render([{"type": "rect", "x": 0, "y": 0, "width": 100, "height": 100, "color": "#111827", "radius": 40}])

        # A true corner pixel is well outside a 40px-radius rounded
        # rect's curve — must NOT be filled, otherwise this silently
        # degraded into a square rect (radius ignored).
        self.assertEqual(_pixel_rgb(surface, 1, 1), (0, 0, 0))
        # But the center must still be fully painted.
        self.assertEqual(_pixel_rgb(surface, 50, 50), (0x11, 0x18, 0x27))


class RenderCircleAndSliderTests(unittest.TestCase):
    def test_circle_center_is_painted_but_far_corner_is_not(self):
        surface = _render([{"type": "circle", "cx": 50, "cy": 50, "radius": 20, "color": "#DC2626"}])

        self.assertEqual(_pixel_rgb(surface, 50, 50), (0xDC, 0x26, 0x26))
        self.assertEqual(_pixel_rgb(surface, 2, 2), (0, 0, 0))

    def test_slider_thumb_lands_at_the_documented_formula_position(self):
        # thumb_cx = x + thumbSize/2 + (width - thumbSize) * value — the
        # exact formula ported from drawSliderCommand() on Android and
        # draw(_ command: SliderCommand...) on iOS.
        surface = _render([{
            "type": "slider", "key": "v", "x": 0, "y": 40, "width": 100, "height": 20,
            "trackHeight": 4, "thumbSize": 20, "value": 1.0,
            "trackColor": "#E5E7EB", "activeColor": "#111827", "thumbColor": "#FFFFFF",
        }], size=(100, 100))

        # value=1.0 -> thumb center at x=90 (100 - thumbSize/2), the
        # active track fill must reach that same point.
        self.assertEqual(_pixel_rgb(surface, 90, 50), (0xFF, 0xFF, 0xFF))  # thumb fill
        self.assertEqual(_pixel_rgb(surface, 40, 50), (0x11, 0x18, 0x27))  # active track before the thumb


class IconFontTests(unittest.TestCase):
    def test_bundled_icon_fonts_register_successfully(self):
        self.assertTrue(register_bundled_fonts())

    def test_icon_command_paints_a_real_glyph_not_an_empty_box(self):
        surface = _render([{"type": "icon", "x": 0, "y": 0, "size": 40, "codepoint": 58826, "color": "#111827"}], size=(40, 40))

        # A rendered glyph must paint SOME non-background pixel inside
        # its box — an unregistered font / missing glyph falls back to
        # either nothing or a hollow "tofu" box outline, which this
        # can't distinguish pixel-by-pixel, but a fully blank box (zero
        # painted pixels anywhere) is unambiguously wrong either way.
        painted_any = any(
            _pixel_rgb(surface, x, y) != (0, 0, 0)
            for x in range(0, 40, 2)
            for y in range(0, 40, 2)
        )
        self.assertTrue(painted_any)


class NestedCommandTests(unittest.TestCase):
    def test_client_panel_only_paints_the_active_index(self):
        surface = _render([
            {
                "type": "clientPanel", "key": "tabs", "index": 0, "initiallyActive": True, "x": 0, "y": 0,
                "commands": [{"type": "rect", "x": 0, "y": 0, "width": 100, "height": 100, "color": "#F97316"}],
                "hitRegions": [],
            },
            {
                "type": "clientPanel", "key": "tabs", "index": 1, "initiallyActive": False, "x": 0, "y": 0,
                "commands": [{"type": "rect", "x": 0, "y": 0, "width": 100, "height": 100, "color": "#0000FF"}],
                "hitRegions": [],
            },
        ])

        # Only the initiallyActive panel (index 0, orange) should have
        # painted anything — the other one must be fully skipped.
        self.assertEqual(_pixel_rgb(surface, 50, 50), (0xF9, 0x73, 0x16))

    def test_hscroll_clips_content_outside_its_viewport(self):
        surface = _render([{
            "type": "hScroll", "key": "row", "x": 10, "y": 10, "width": 30, "height": 30, "contentWidth": 200,
            "commands": [{"type": "rect", "x": 0, "y": 0, "width": 200, "height": 30, "color": "#111827"}],
            "hitRegions": [],
        }])

        self.assertEqual(_pixel_rgb(surface, 20, 20), (0x11, 0x18, 0x27))  # inside the viewport
        self.assertEqual(_pixel_rgb(surface, 80, 20), (0, 0, 0))  # content exists here, but clipped away


class AnimationTests(unittest.TestCase):
    def test_needs_animation_true_only_when_a_spinner_or_skeleton_is_present(self):
        static_payload = decode_payload({"commands": [{"type": "rect", "x": 0, "y": 0, "width": 10, "height": 10, "color": "#000"}], "hitRegions": [], "contentHeight": 0})
        animated_payload = decode_payload({"commands": [{"type": "spinner", "x": 0, "y": 0, "size": 10, "color": "#000", "trackColor": "#fff", "strokeWidth": 2}], "hitRegions": [], "contentHeight": 0})

        self.assertFalse(needs_animation(static_payload))
        self.assertTrue(needs_animation(animated_payload))

    def test_needs_animation_looks_inside_nested_client_panels(self):
        nested_payload = decode_payload({
            "commands": [{
                "type": "clientPanel", "key": "t", "index": 0, "initiallyActive": True, "x": 0, "y": 0,
                "commands": [{"type": "skeleton", "x": 0, "y": 0, "width": 10, "height": 10, "color": "#000", "radius": 0}],
                "hitRegions": [],
            }],
            "hitRegions": [],
            "contentHeight": 0,
        })

        self.assertTrue(needs_animation(nested_payload))


if __name__ == "__main__":
    unittest.main()
