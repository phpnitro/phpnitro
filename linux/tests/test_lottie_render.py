"""Real unit tests for lottie_render.py's ctypes bindings — no GTK/
display required, same "pure binding module" spirit as
test_rust_render_parity.py. Written against librlottie's C ABI from
memory (see lottie_render.py's own "# Honesty" docblock) — this is the
first thing that actually calls into the real, compiled library rather
than just reading the header, so a wrong function signature/argument
order shows up here as a real failure, not a silent success.

Run with: python3 -m unittest discover -s linux/tests -v
"""

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from phpnitro_desktop import lottie_render

# The simplest animation librlottie's Bodymovin/Lottie JSON schema
# allows: one shape layer (ty: 4), a rectangle (ty: "rc") covering the
# whole 50x50 canvas, filled solid red (ty: "fl", c: [1, 0, 0, 1]) —
# no keyframed properties at all ("a": 0 everywhere means "static
# value, not animated"), 10 frames at 10fps (1 second), so every frame
# renders identically and a single rendered pixel is enough to assert
# on, the same minimal-fixture spirit
# RustRendererDeviceTest.kt's own plain-red-rect test already uses.
_RED_RECT_LOTTIE_JSON = """
{
  "v": "5.5.2", "fr": 10, "ip": 0, "op": 10, "w": 50, "h": 50, "nm": "red_rect", "ddd": 0,
  "assets": [],
  "layers": [
    {
      "ddd": 0, "ind": 1, "ty": 4, "nm": "rect layer", "sr": 1,
      "ks": {
        "o": {"a": 0, "k": 100},
        "r": {"a": 0, "k": 0},
        "p": {"a": 0, "k": [25, 25, 0]},
        "a": {"a": 0, "k": [0, 0, 0]},
        "s": {"a": 0, "k": [100, 100, 100]}
      },
      "ao": 0,
      "shapes": [
        {"ty": "rc", "d": 1, "s": {"a": 0, "k": [50, 50]}, "p": {"a": 0, "k": [0, 0]}, "r": {"a": 0, "k": 0}, "nm": "rect"},
        {"ty": "fl", "c": {"a": 0, "k": [1, 0, 0, 1]}, "o": {"a": 0, "k": 100}, "nm": "fill"}
      ],
      "ip": 0, "op": 10, "st": 0, "bm": 0
    }
  ]
}
"""


class LottieRenderTests(unittest.TestCase):
    def _load_or_skip(self):
        try:
            animation = lottie_render.load_from_data(_RED_RECT_LOTTIE_JSON, "test-red-rect")
        except lottie_render.LottieUnavailable as exc:
            self.skipTest(f"librlottie not installed in this environment: {exc}")
        self.assertIsNotNone(animation, "load_from_data() returned None — the fixture JSON failed to parse")
        return animation

    def test_loads_a_real_fixture_and_reports_its_own_metadata(self):
        animation = self._load_or_skip()

        self.assertEqual(animation.width, 50)
        self.assertEqual(animation.height, 50)
        self.assertEqual(animation.total_frames, 10)
        self.assertEqual(animation.framerate, 10.0)
        # Confirmed via real CI execution, not the naive total_frames/
        # framerate this originally assumed: librlottie reports
        # duration as (total_frames - 1) / framerate — see
        # frame_at()'s own docblock on lottie_render.py for why frame
        # timing is derived from framerate directly instead, sidestepping
        # this quirk rather than depending on it.
        self.assertAlmostEqual(animation.duration_seconds, 0.9, places=2)

    def test_renders_the_expected_pixel_for_a_plain_red_rect(self):
        animation = self._load_or_skip()

        pixels = animation.render(0, 50, 50)

        self.assertEqual(len(pixels), 50 * 50 * 4)
        # (25, 25) sits well inside the 50x50 red rect covering the
        # whole canvas — premultiplied ARGB32, native/host byte order
        # (see lottie_render.py's own "# Honesty" docblock): opaque red
        # is [B=0, G=0, R=255, A=255] in memory on this little-endian
        # platform, the same byte order Cairo's own FORMAT_ARGB32 uses.
        offset = (25 * 50 + 25) * 4
        b, g, r, a = pixels[offset], pixels[offset + 1], pixels[offset + 2], pixels[offset + 3]
        self.assertEqual((b, g, r, a), (0, 0, 255, 255), f"got BGRA={(b, g, r, a)} — if this is (255, 0, 0, 255) instead, the channel order assumption in this module's docblock was wrong, add the R/B swap it describes")

    def test_frame_at_computes_the_expected_frame_index(self):
        animation = self._load_or_skip()

        self.assertEqual(animation.frame_at(0.0, loop=True), 0)
        self.assertEqual(animation.frame_at(0.95, loop=True), 9)
        self.assertEqual(animation.frame_at(1.5, loop=True), 5)  # wraps
        self.assertEqual(animation.frame_at(5.0, loop=False), 9)  # clamped to last

    def test_unavailable_library_raises_a_distinct_exception_type(self):
        # Patches ctypes.CDLL itself (not just find_library) to always
        # fail — CI actually has librlottie0-1 installed for the other
        # tests in this file, so patching only find_library() wouldn't
        # be enough: _load_library() also tries a few hardcoded
        # fallback paths (see its own docblock) that would still
        # succeed there. This has to fail regardless of what's really
        # on the machine running it, local dev box or CI alike — same
        # reasoning test_show_map_overlay_degrades_to_nothing... gives
        # for patching Shumate directly rather than relying on the
        # ambient environment.
        import ctypes

        from unittest import mock

        def always_fail(_name):
            raise OSError("patched: pretend the library can't be loaded")

        with mock.patch.object(ctypes, "CDLL", side_effect=always_fail):
            with self.assertRaises(lottie_render.LottieUnavailable):
                lottie_render._load_library()


if __name__ == "__main__":
    unittest.main()
