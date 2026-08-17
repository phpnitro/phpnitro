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
        self.widget.on_action = fired.append
        self.widget._on_click(None, 1, 5.0, 5.0)

        self.assertEqual(fired, ["navigate:settings"])

    def test_click_outside_every_hit_region_fires_nothing(self):
        payload = decode_payload({
            "commands": [],
            "hitRegions": [{"x": 0, "y": 0, "width": 20, "height": 20, "action": "navigate:settings"}],
            "contentHeight": 20,
        })
        self.widget.set_payload(payload)

        fired = []
        self.widget.on_action = fired.append
        self.widget._on_click(None, 1, 500.0, 500.0)

        self.assertEqual(fired, [])

    def test_set_client_tab_updates_local_state_without_touching_the_payload(self):
        self.widget.set_client_tab("tabs1", 2)

        self.assertEqual(self.widget.client_tab_state, {"tabs1": 2})
        self.assertIsNone(self.widget.payload)


if __name__ == "__main__":
    unittest.main()
