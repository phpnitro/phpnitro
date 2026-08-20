"""Phase 2 proof-of-concept: renders the SAME fixture through the
existing Cairo path (canvas.py) and the new Rust path
(rust_render.py -> rust/phpnitro-render's cdylib), then compares pixels.
This is the actual evidence this migration is building toward — not
"the Rust crate has its own tests" (already true, see
rust/phpnitro-render/README.md) but "Rust produces the same picture the
already-shipped Cairo renderer does, on a real fixture neither side was
tuned to make the other one pass."

Skipped (not failed) if the compiled library isn't found — this test
depends on `cargo build --release` having been run in
rust/phpnitro-render first, a genuinely separate build step from
`python3 -m unittest`, unrelated to whether phpnitro_desktop's own code
is correct.
"""

import json
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import cairo

from phpnitro_desktop.canvas import RenderState, render_payload
from phpnitro_desktop.draw_command import decode_payload
from phpnitro_desktop import rust_render

FIXTURES_DIR = Path(__file__).resolve().parent.parent.parent / "packages" / "ui" / "tests" / "Golden" / "__fixtures__"


def _cairo_pixel_rgba(surface: cairo.ImageSurface, x: int, y: int) -> tuple[int, int, int, int]:
    """FORMAT_ARGB32 is premultiplied-alpha, native-endian — on this
    (little-endian) platform that's byte order [B, G, R, A] per pixel,
    the OPPOSITE of tiny-skia's [R, G, B, A] — see RenderedFrame's own
    docstring in rust_render.py.
    """
    surface.flush()
    stride = surface.get_stride()
    data = surface.get_data()
    offset = y * stride + x * 4
    b, g, r, a = data[offset], data[offset + 1], data[offset + 2], data[offset + 3]
    return (r, g, b, a)


def _rust_pixel_rgba(frame: rust_render.RenderedFrame, x: int, y: int) -> tuple[int, int, int, int]:
    offset = y * frame.stride + x * 4
    r, g, b, a = frame.data[offset], frame.data[offset + 1], frame.data[offset + 2], frame.data[offset + 3]
    return (r, g, b, a)


def _render_cairo(fixture_json: str, size: tuple[int, int]) -> cairo.ImageSurface:
    payload = decode_payload(json.loads(fixture_json))
    surface = cairo.ImageSurface(cairo.FORMAT_ARGB32, *size)
    ctx = cairo.Context(surface)
    render_payload(ctx, payload, RenderState(now=0.0, client_tab_state={}))
    return surface


class RustCairoParityTests(unittest.TestCase):
    RUST_RENDERER: rust_render.RustRenderer

    @classmethod
    def setUpClass(cls):
        try:
            cls.RUST_RENDERER = rust_render.RustRenderer()
        except rust_render.RustRenderUnavailable as exc:
            raise unittest.SkipTest(f"rust/phpnitro-render not built: {exc}")

    @classmethod
    def tearDownClass(cls):
        if hasattr(cls, "RUST_RENDERER"):
            cls.RUST_RENDERER.close()

    def _assert_pixels_match(self, fixture_name: str, size: tuple[int, int], sample_points):
        fixture_json = (FIXTURES_DIR / fixture_name).read_text()

        cairo_surface = _render_cairo(fixture_json, size)
        rust_frame = self.RUST_RENDERER.render_frame(fixture_json, size[0], size[1])
        self.assertIsNotNone(rust_frame, f"Rust render_frame failed: {rust_render.last_error()}")

        for (x, y) in sample_points:
            cairo_rgba = _cairo_pixel_rgba(cairo_surface, x, y)
            rust_rgba = _rust_pixel_rgba(rust_frame, x, y)
            for channel_name, cairo_v, rust_v in zip("RGBA", cairo_rgba, rust_rgba):
                self.assertLessEqual(
                    abs(cairo_v - rust_v),
                    2,  # anti-aliasing rounding tolerance, not a real mismatch budget
                    f"{fixture_name} @ ({x},{y}) channel {channel_name}: "
                    f"cairo={cairo_rgba} rust={rust_rgba}",
                )

    def test_flex_row_distribution_matches(self):
        # Two plain, unrounded, unbordered rects — the simplest possible
        # case, and the one both canvas.py and raster.rs already have
        # their own dedicated pixel tests for individually.
        self._assert_pixels_match(
            "flex_row_distribution.json", size=(100, 60), sample_points=[(20, 20), (68, 20), (44, 20)],
        )

    def test_circle_basic_matches(self):
        # (6, 30) sits exactly ON the 24px-radius boundary (cx-radius=6),
        # a point deliberately avoided here — the two renderers' distinct
        # anti-aliasing algorithms legitimately disagree by a few units
        # right on an edge, which isn't a real mismatch. (2, 30) is
        # clearly outside instead.
        self._assert_pixels_match(
            "circle_basic.json", size=(60, 60), sample_points=[(30, 30), (0, 0), (2, 30)],
        )

    def test_container_with_padding_matches(self):
        # rect + text together — the first parity test that exercises
        # text/font rendering, not just flat-fill geometry.
        self._assert_pixels_match(
            "container_with_padding.json", size=(200, 80), sample_points=[(100, 40), (0, 0)],
        )

    def test_render_frame_crossfades_between_two_envelopes(self):
        # Not a Cairo/Rust parity check like the others above — Cairo has
        # no crossfade/hero transition path at all (Phase 2 scope), so this
        # exercises rust_render.py's own previous_envelope_json/
        # transition_elapsed_ms plumbing directly (see
        # rust/phpnitro-render/src/transition.rs), the same real FFI
        # round-trip as every other test in this file, just against a
        # hand-built envelope pair instead of a golden fixture.
        old_envelope = json.dumps({
            "commands": [{"type": "rect", "x": 0, "y": 0, "width": 20, "height": 20, "color": "#FF0000"}],
            "hitRegions": [], "contentHeight": 20,
        })
        new_envelope = json.dumps({
            "commands": [{"type": "rect", "x": 0, "y": 0, "width": 20, "height": 20, "color": "#0000FF"}],
            "hitRegions": [], "contentHeight": 20,
        })

        # transition_elapsed_ms=0 -> the eased crossfade progress is still
        # 0, i.e. only the OLD (red) envelope should be visible.
        at_start = self.RUST_RENDERER.render_frame(
            new_envelope, 20, 20, previous_envelope_json=old_envelope, transition_elapsed_ms=0,
        )
        self.assertIsNotNone(at_start)
        self.assertEqual(_rust_pixel_rgba(at_start, 10, 10), (255, 0, 0, 255))

        # Past the 220ms crossfade duration -> only the NEW (blue) envelope.
        at_end = self.RUST_RENDERER.render_frame(
            new_envelope, 20, 20, previous_envelope_json=old_envelope, transition_elapsed_ms=220,
        )
        self.assertIsNotNone(at_end)
        self.assertEqual(_rust_pixel_rgba(at_end, 10, 10), (0, 0, 255, 255))

        # No previous envelope at all -> same as every other call in this
        # file, the plain untransitioned path.
        plain = self.RUST_RENDERER.render_frame(new_envelope, 20, 20)
        self.assertIsNotNone(plain)
        self.assertEqual(_rust_pixel_rgba(plain, 10, 10), (0, 0, 255, 255))

    def test_render_frame_honors_a_live_interaction_state(self):
        # rust_render.py's own interaction_state_json plumbing (see
        # rust/phpnitro-render/src/raster.rs's clientPanel/hScroll/vScroll/
        # slider handling) — a real FFI round-trip proving a live tab
        # selection actually reaches the paint path, not just hit-testing.
        envelope = json.dumps({
            "commands": [
                {"type": "clientPanel", "key": "tabs1", "index": 0, "initiallyActive": True, "x": 0, "y": 0,
                 "commands": [{"type": "rect", "x": 0, "y": 0, "width": 10, "height": 10, "color": "#FF0000"}]},
                {"type": "clientPanel", "key": "tabs1", "index": 1, "initiallyActive": False, "x": 0, "y": 0,
                 "commands": [{"type": "rect", "x": 0, "y": 0, "width": 10, "height": 10, "color": "#0000FF"}]},
            ],
            "hitRegions": [], "contentHeight": 10,
        })

        resting = self.RUST_RENDERER.render_frame(envelope, 20, 20)
        self.assertIsNotNone(resting)
        self.assertEqual(_rust_pixel_rgba(resting, 5, 5), (255, 0, 0, 255), "with no live state, the initiallyActive panel should paint")

        live = self.RUST_RENDERER.render_frame(
            envelope, 20, 20, interaction_state_json=json.dumps({"activePanel": {"tabs1": 1}}),
        )
        self.assertIsNotNone(live)
        self.assertEqual(_rust_pixel_rgba(live, 5, 5), (0, 0, 255, 255), "live activePanel state should override initiallyActive")

    def test_hit_test_agrees_with_the_python_action_at(self):
        # app.py's PhpNitroCanvasWidget._hit_test_via_rust() trusts a
        # clean "nothing hit" from Rust as-is rather than silently
        # re-trying draw_command.py's own action_at() — this is the
        # automated version of the manual check that backed that design
        # decision: every real hitRegion in a real fixture, tapped dead
        # center, must resolve to the exact same action on both sides.
        fixture_json = (FIXTURES_DIR / "button_with_icon.json").read_text()
        payload = decode_payload(json.loads(fixture_json))
        self.assertGreater(len(payload.hit_regions), 0, "fixture must have at least one hit region to be a real test")

        for region in payload.hit_regions:
            cx = region.x + region.width / 2
            cy = region.y + region.height / 2
            python_action = payload.action_at(cx, cy)
            rust_hit = rust_render.hit_test(fixture_json, cx, cy, "{}")
            rust_action = rust_hit.action if rust_hit is not None else None
            self.assertEqual(python_action, rust_action, f"mismatch at ({cx}, {cy})")


if __name__ == "__main__":
    unittest.main()
