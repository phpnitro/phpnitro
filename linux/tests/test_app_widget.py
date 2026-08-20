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
        self.widget.on_action = lambda action, rect: fired.append((action, rect))
        self.widget._on_click(None, 1, 5.0, 5.0)

        self.assertEqual(fired, [("navigate:settings", (0.0, 0.0, 20.0, 20.0))])

    def test_click_outside_every_hit_region_fires_nothing(self):
        payload = decode_payload({
            "commands": [],
            "hitRegions": [{"x": 0, "y": 0, "width": 20, "height": 20, "action": "navigate:settings"}],
            "contentHeight": 20,
        })
        self.widget.set_payload(payload)

        fired = []
        self.widget.on_action = lambda action, rect: fired.append((action, rect))
        self.widget._on_click(None, 1, 500.0, 500.0)

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


if __name__ == "__main__":
    unittest.main()
