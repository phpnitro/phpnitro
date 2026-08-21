"""PhpNitroCanvasWidget (app.py) turns out to be constructible and
exercisable without any display server at all — GTK4 widget
construction, GLib timer registration, and Gtk.GestureClick dispatch
are all display-agnostic; only actually REALIZING a window on screen
needs one (confirmed the other way too: constructing a bare
Gtk.ApplicationWindow directly, outside this widget, does fail with
"Gtk couldn't be initialized" in this same environment). That gap is
exactly the boundary this test suite draws: everything up to and
including a real click producing a real callback is covered here;
ScreenWindow itself (app.py) is not, and stays unverified beyond
import/construction until run against a real display.
"""

import json
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import cairo
from gi.repository import GLib

from phpnitro_desktop.app import PhpNitroCanvasWidget
from phpnitro_desktop.draw_command import decode_payload


class PhpNitroCanvasWidgetTests(unittest.TestCase):
    def setUp(self):
        self.widget = PhpNitroCanvasWidget()

    def tearDown(self):
        if self.widget._timer_id is not None:
            GLib.source_remove(self.widget._timer_id)

    def test_constructs_without_a_display(self):
        self.assertIsNotNone(self.widget)
        self.assertIsNone(self.widget.payload)

    def test_set_payload_stores_it_and_queues_a_redraw(self):
        payload = decode_payload({"commands": [], "hitRegions": [], "contentHeight": 0})

        self.widget.set_payload(payload)

        self.assertIs(self.widget.payload, payload)

    def test_animation_timer_starts_only_when_a_spinner_or_skeleton_is_present(self):
        static_payload = decode_payload({"commands": [{"type": "rect", "x": 0, "y": 0, "width": 1, "height": 1, "color": "#000"}], "hitRegions": [], "contentHeight": 0})
        self.widget.set_payload(static_payload)
        self.assertIsNone(self.widget._timer_id)

        animated_payload = decode_payload({"commands": [{"type": "spinner", "x": 0, "y": 0, "size": 10, "color": "#000", "trackColor": "#fff", "strokeWidth": 2}], "hitRegions": [], "contentHeight": 0})
        self.widget.set_payload(animated_payload)
        self.assertIsNotNone(self.widget._timer_id)

        # Going back to a static payload must stop the timer, not leak it.
        self.widget.set_payload(static_payload)
        self.assertIsNone(self.widget._timer_id)

    def test_click_inside_a_hit_region_fires_on_action_with_the_right_string(self):
        payload = decode_payload({
            "commands": [],
            "hitRegions": [{"x": 0, "y": 0, "width": 20, "height": 20, "action": "navigate:settings"}],
            "contentHeight": 20,
        })
        self.widget.set_payload(payload)

        fired = []
        self.widget.on_action = lambda action, meta_json, rect: fired.append((action, meta_json, rect))
        # A tap is a drag whose end offset is (0, 0) — GestureDrag itself
        # fires both signals even for a click that never actually moved.
        self.widget._on_drag_begin(None, 5.0, 5.0)
        self.widget._on_drag_end(None, 0.0, 0.0)

        self.assertEqual(fired, [("navigate:settings", None, (0.0, 0.0, 20.0, 20.0))])

    def test_click_outside_every_hit_region_fires_nothing(self):
        payload = decode_payload({
            "commands": [],
            "hitRegions": [{"x": 0, "y": 0, "width": 20, "height": 20, "action": "navigate:settings"}],
            "contentHeight": 20,
        })
        self.widget.set_payload(payload)

        fired = []
        self.widget.on_action = lambda action, meta_json, rect: fired.append((action, meta_json, rect))
        self.widget._on_drag_begin(None, 500.0, 500.0)
        self.widget._on_drag_end(None, 0.0, 0.0)

        self.assertEqual(fired, [])

    def test_set_client_tab_updates_local_state_without_touching_the_payload(self):
        self.widget.set_client_tab("tabs1", 2)

        self.assertEqual(self.widget.client_tab_state, {"tabs1": 2})
        self.assertIsNone(self.widget.payload)

    def _draw(self, width: int, height: int) -> None:
        # A real in-memory Cairo surface, no display server needed —
        # same offscreen-rendering approach test_canvas.py's own pixel
        # tests already rely on.
        surface = cairo.ImageSurface(cairo.FORMAT_ARGB32, width, height)
        ctx = cairo.Context(surface)
        self.widget._on_draw(None, ctx, width, height)

    def test_on_draw_reports_a_resize_when_the_live_size_differs_from_the_fetched_one(self):
        self.widget.set_fetched_size(390, 844)
        resizes = []
        self.widget.on_resize = lambda w, h: resizes.append((w, h))

        self._draw(390, 844)
        self.assertEqual(resizes, [], "matching size must not fire a spurious resize")

        self._draw(500, 900)
        self.assertEqual(resizes, [(500, 900)])

    def test_on_draw_does_not_refire_for_the_same_new_size_while_a_fetch_is_in_flight(self):
        # set_fetched_size is called by ScreenWindow BEFORE the async
        # result lands (see app.py's own _fetch) — simulated here by
        # calling it directly, matching that real sequencing.
        self.widget.set_fetched_size(390, 844)
        resizes = []
        self.widget.on_resize = lambda w, h: resizes.append((w, h))

        self._draw(500, 900)
        self.widget.set_fetched_size(500, 900)
        self._draw(500, 900)

        self.assertEqual(resizes, [(500, 900)], "a second draw at the already-fetched size must not fire again")

    def test_show_text_input_creates_a_single_line_entry_seeded_with_the_initial_value(self):
        self.widget.show_text_input("email", "already@typed.com", (10.0, 20.0, 210.0, 60.0), multiline=False, secure=False)

        entry = self.widget._active_text_input
        self.assertIsNotNone(entry)
        self.assertEqual(entry.get_text(), "already@typed.com")
        self.assertTrue(entry.get_visibility(), "a non-secure field must show its typed text")

    def test_show_text_input_secure_field_hides_the_typed_text(self):
        self.widget.show_text_input("password", "", (0.0, 0.0, 100.0, 30.0), multiline=False, secure=True)

        entry = self.widget._active_text_input
        self.assertFalse(entry.get_visibility())

    def test_show_text_input_multiline_uses_a_text_view(self):
        from gi.repository import Gtk

        self.widget.show_text_input("bio", "hello", (0.0, 0.0, 200.0, 120.0), multiline=True, secure=False)

        self.assertIsInstance(self.widget._active_text_input, Gtk.TextView)

    def test_typing_in_a_single_line_entry_reports_every_keystroke(self):
        # Simulated via the entry's own buffer, character by character
        # (insert_text), rather than repeated set_text() calls — set_text()
        # is itself implemented as a clear-then-insert, so it fires
        # "changed" twice per call (once for the clear) — a real quirk of
        # that convenience method, not of the overlay logic being tested
        # here, and not representative of how a real keystroke arrives.
        changes = []
        self.widget.on_field_value_changed = lambda name, value: changes.append((name, value))
        self.widget.show_text_input("email", "", (0.0, 0.0, 200.0, 30.0), multiline=False, secure=False)

        entry = self.widget._active_text_input
        buffer = entry.get_buffer()
        buffer.insert_text(0, "a", -1)
        buffer.insert_text(1, "b", -1)

        self.assertEqual(changes, [("email", "a"), ("email", "ab")])

    def test_typing_in_a_multiline_field_reports_every_keystroke(self):
        changes = []
        self.widget.on_field_value_changed = lambda name, value: changes.append((name, value))
        self.widget.show_text_input("bio", "", (0.0, 0.0, 200.0, 120.0), multiline=True, secure=False)

        buffer = self.widget._active_text_input.get_buffer()
        buffer.set_text("hello", -1)

        self.assertEqual(changes, [("bio", "hello")])

    def test_a_second_focus_replaces_the_first_overlay(self):
        self.widget.show_text_input("first", "", (0.0, 0.0, 100.0, 30.0), multiline=False, secure=False)
        first_entry = self.widget._active_text_input

        self.widget.show_text_input("second", "", (0.0, 40.0, 100.0, 30.0), multiline=False, secure=False)
        second_entry = self.widget._active_text_input

        self.assertIsNot(first_entry, second_entry)
        self.assertIsNone(first_entry.get_parent(), "the first overlay must be removed, not left behind")

    def test_clear_text_input_removes_the_overlay(self):
        self.widget.show_text_input("first", "", (0.0, 0.0, 100.0, 30.0), multiline=False, secure=False)
        entry = self.widget._active_text_input

        self.widget.clear_text_input()

        self.assertIsNone(self.widget._active_text_input)
        self.assertIsNone(entry.get_parent())

    def test_set_payload_clears_any_active_text_input(self):
        self.widget.show_text_input("first", "", (0.0, 0.0, 100.0, 30.0), multiline=False, secure=False)
        payload = decode_payload({"commands": [], "hitRegions": [], "contentHeight": 0})

        self.widget.set_payload(payload)

        self.assertIsNone(self.widget._active_text_input)

    def test_interaction_state_json_includes_active_panel_axis_offset_and_slider_value(self):
        self.widget.set_client_tab("tabs1", 1)
        self.widget.axis_offset["chips"] = 42.0
        self.widget.slider_value["volume"] = 0.75

        state = json.loads(self.widget._interaction_state_json())

        self.assertEqual(state, {
            "activePanel": {"tabs1": 1},
            "axisOffset": {"chips": 42.0},
            "sliderValue": {"volume": 0.75},
        })

    def _slider_payload(self):
        return decode_payload({
            "commands": [], "hitRegions": [], "contentHeight": 0,
            "sliderRegions": [{
                "key": "volume", "x": 20, "y": 0, "width": 360, "height": 44,
                "trackHeight": 6, "thumbSize": 22, "value": 0.5, "action": "toggle:volume",
            }],
        })

    def test_dragging_inside_a_slider_region_updates_its_value_live(self):
        self.widget.set_payload(self._slider_payload())

        self.widget._on_drag_begin(None, 30.0, 20.0)
        self.assertIn("volume", self.widget.slider_value)

        self.widget._on_drag_update(None, 100.0, 0.0)

        # (touch_x - x - thumb_size/2) / (width - thumb_size), touch_x = 30 + 100
        self.assertAlmostEqual(self.widget.slider_value["volume"], (130 - 20 - 11) / (360 - 22), places=6)

    def test_releasing_a_slider_drag_commits_the_value_via_on_action(self):
        self.widget.set_payload(self._slider_payload())
        fired = []
        self.widget.on_action = lambda action, meta_json, rect: fired.append((action, meta_json, rect))

        self.widget._on_drag_begin(None, 30.0, 20.0)
        self.widget._on_drag_update(None, 100.0, 0.0)
        self.widget._on_drag_end(None, 100.0, 0.0)

        self.assertEqual(len(fired), 1)
        action, meta_json, rect = fired[0]
        self.assertEqual(action, "toggle:volume")
        self.assertEqual(json.loads(meta_json), {"next": "0.293"})

    def test_slider_drag_clamps_to_the_0_to_1_range(self):
        self.widget.set_payload(self._slider_payload())

        self.widget._on_drag_begin(None, 30.0, 20.0)
        self.widget._on_drag_update(None, -1000.0, 0.0)
        self.assertEqual(self.widget.slider_value["volume"], 0.0)

        self.widget._on_drag_update(None, 1000.0, 0.0)
        self.assertEqual(self.widget.slider_value["volume"], 1.0)

    def _hscroll_payload(self):
        return decode_payload({
            "commands": [{
                "type": "hScroll", "key": "chips", "x": 0, "y": 0, "width": 100, "height": 50,
                "contentWidth": 300, "commands": [], "hitRegions": [],
            }],
            "hitRegions": [], "contentHeight": 0,
        })

    def test_hscroll_drag_under_touch_slop_does_not_scroll_yet(self):
        self.widget.set_payload(self._hscroll_payload())

        self.widget._on_drag_begin(None, 50.0, 25.0)
        self.widget._on_drag_update(None, -2.0, 0.0)

        self.assertEqual(self.widget.axis_offset, {})

    def test_hscroll_drag_past_touch_slop_accumulates_axis_offset(self):
        self.widget.set_payload(self._hscroll_payload())

        self.widget._on_drag_begin(None, 50.0, 25.0)
        self.widget._on_drag_update(None, -10.0, 0.0)
        self.assertEqual(self.widget.axis_offset["chips"], 10.0)

        self.widget._on_drag_update(None, -20.0, 0.0)
        self.assertEqual(self.widget.axis_offset["chips"], 20.0)

    def test_hscroll_drag_clamps_to_the_max_content_offset(self):
        self.widget.set_payload(self._hscroll_payload())

        self.widget._on_drag_begin(None, 50.0, 25.0)
        self.widget._on_drag_update(None, -10000.0, 0.0)

        # content_width(300) - width(100)
        self.assertEqual(self.widget.axis_offset["chips"], 200.0)

    def test_releasing_an_hscroll_drag_fires_no_action(self):
        self.widget.set_payload(self._hscroll_payload())
        fired = []
        self.widget.on_action = lambda action, meta_json, rect: fired.append((action, meta_json, rect))

        self.widget._on_drag_begin(None, 50.0, 25.0)
        self.widget._on_drag_update(None, -10.0, 0.0)
        self.widget._on_drag_end(None, -10.0, 0.0)

        self.assertEqual(fired, [])

    def test_hscroll_drag_that_never_clears_touch_slop_falls_back_to_a_plain_tap(self):
        payload = decode_payload({
            "commands": [{
                "type": "hScroll", "key": "chips", "x": 0, "y": 0, "width": 100, "height": 50,
                "contentWidth": 300, "commands": [], "hitRegions": [],
            }],
            "hitRegions": [{"x": 0, "y": 0, "width": 100, "height": 50, "action": "navigate:chips"}],
            "contentHeight": 0,
        })
        self.widget.set_payload(payload)
        fired = []
        self.widget.on_action = lambda action, meta_json, rect: fired.append((action, meta_json, rect))

        self.widget._on_drag_begin(None, 50.0, 25.0)
        self.widget._on_drag_update(None, -1.0, 0.0)
        self.widget._on_drag_end(None, -1.0, 0.0)

        self.assertEqual(fired, [("navigate:chips", None, (0.0, 0.0, 100.0, 50.0))])

    def test_show_video_overlay_creates_an_autoplaying_video_widget(self):
        from gi.repository import Gtk

        self.widget.show_video_overlay("https://example.com/clip.mp4", (10.0, 20.0, 210.0, 140.0))

        video = self.widget._active_video_overlay
        self.assertIsInstance(video, Gtk.Video)
        self.assertTrue(video.get_autoplay())
        self.assertEqual(video.get_file().get_uri(), "https://example.com/clip.mp4")

    def test_a_second_video_overlay_replaces_the_first(self):
        self.widget.show_video_overlay("https://example.com/a.mp4", (0.0, 0.0, 100.0, 100.0))
        first = self.widget._active_video_overlay

        self.widget.show_video_overlay("https://example.com/b.mp4", (0.0, 0.0, 100.0, 100.0))
        second = self.widget._active_video_overlay

        self.assertIsNot(first, second)
        self.assertIsNone(first.get_parent(), "the first overlay must be removed, not left behind")

    def test_clear_video_overlay_removes_the_overlay(self):
        self.widget.show_video_overlay("https://example.com/a.mp4", (0.0, 0.0, 100.0, 100.0))
        video = self.widget._active_video_overlay

        self.widget.clear_video_overlay()

        self.assertIsNone(self.widget._active_video_overlay)
        self.assertIsNone(video.get_parent())

    def test_set_payload_clears_any_active_video_overlay(self):
        self.widget.show_video_overlay("https://example.com/a.mp4", (0.0, 0.0, 100.0, 100.0))
        payload = decode_payload({"commands": [], "hitRegions": [], "contentHeight": 0})

        self.widget.set_payload(payload)

        self.assertIsNone(self.widget._active_video_overlay)

    def test_show_map_overlay_degrades_to_nothing_without_crashing_when_shumate_is_unavailable(self):
        # Real state in this environment (confirmed: gir1.2-shumate-1.0
        # isn't installed) — this is the one branch of show_map_overlay
        # actually exercised anywhere so far; the Shumate-available branch
        # has never run, here or in CI, see its own docstring.
        from phpnitro_desktop import app as app_module

        self.assertIsNone(app_module.Shumate, "this test assumes libshumate genuinely isn't importable here")

        self.widget.show_map_overlay(48.8566, 2.3522, 14, (0.0, 0.0, 100.0, 100.0))

        self.assertIsNone(self.widget._active_map_overlay)

    def test_show_map_overlay_constructs_a_real_widget_when_shumate_is_available(self):
        from phpnitro_desktop import app as app_module

        if app_module.Shumate is None:
            self.skipTest("libshumate (gir1.2-shumate-1.0) not installed in this environment")

        # Genuinely never exercised anywhere until gir1.2-shumate-1.0 was
        # added to ci.yml's own apt-get line alongside this test — if the
        # Shumate.SimpleMap/MapSourceRegistry/Map API calls in
        # show_map_overlay() are wrong, THIS is what catches it, not a
        # local run (see the docstring on show_map_overlay itself).
        self.widget.show_map_overlay(48.8566, 2.3522, 14, (10.0, 20.0, 210.0, 260.0))

        self.assertIsNotNone(self.widget._active_map_overlay)

    def test_set_payload_clears_any_active_map_overlay_field(self):
        # Can't construct a real overlay without Shumate installed, but
        # clear_map_overlay()'s own no-op-when-empty path (called by
        # set_payload) is exercised regardless — confirms set_payload
        # doesn't raise even with no map overlay ever shown.
        payload = decode_payload({"commands": [], "hitRegions": [], "contentHeight": 0})

        self.widget.set_payload(payload)

        self.assertIsNone(self.widget._active_map_overlay)

    def test_a_decisive_vertical_move_cancels_a_pending_horizontal_scroll(self):
        self.widget.set_payload(self._hscroll_payload())

        self.widget._on_drag_begin(None, 50.0, 25.0)
        self.widget._on_drag_update(None, 1.0, 20.0)

        self.assertEqual(self.widget.axis_offset, {})
        self.assertIsNone(self.widget._active_scroll)
        self.assertIsNone(self.widget._pending_scroll)


if __name__ == "__main__":
    unittest.main()
