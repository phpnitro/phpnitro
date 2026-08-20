"""Real unit tests, no GTK/display required — run with:
    python3 -m unittest discover -s linux/tests -v
The same "decode real JSON shapes Canvas::toJson() actually produces"
spirit as DrawCommandTests.swift on iOS, including the golden-file
fixture shared across every platform.
"""

import json
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from phpnitro_desktop.draw_command import (
    ArcCommand, ClientPanelCommand, HScrollCommand, IconCommand, RectCommand,
    SkeletonCommand, SliderCommand, SpinnerCommand, TextCommand, UnknownCommand,
    VScrollCommand, decode_command, decode_payload,
)

GOLDEN_FIXTURE = Path(__file__).resolve().parent.parent.parent / "packages/ui/tests/Golden/__fixtures__/button_with_icon.json"


class DecodeCommandTests(unittest.TestCase):
    def test_decodes_rect_command(self):
        command = decode_command({"type": "rect", "x": 0, "y": 0, "width": 200, "height": 54, "color": "#111827", "radius": 999, "borderWidth": 0})

        self.assertIsInstance(command, RectCommand)
        self.assertEqual(command.width, 200)
        self.assertEqual(command.color, "#111827")
        self.assertEqual(command.radius, 999)

    def test_decodes_text_command(self):
        command = decode_command({"type": "text", "x": 89.1, "y": 29.6, "text": "Valider", "color": "#FFFFFF", "size": 15, "bold": True})

        self.assertIsInstance(command, TextCommand)
        self.assertEqual(command.text, "Valider")
        self.assertTrue(command.bold)
        self.assertIsNone(command.font_family)

    def test_decodes_icon_command_with_fontawesome_font(self):
        command = decode_command({"type": "icon", "x": 63.1, "y": 18, "size": 18, "codepoint": 58826, "color": "#FFFFFF", "font": "fontawesome"})

        self.assertIsInstance(command, IconCommand)
        self.assertEqual(command.codepoint, 58826)
        self.assertEqual(command.font, "fontawesome")

    def test_unrecognized_type_decodes_to_unknown_instead_of_raising(self):
        command = decode_command({"type": "custom:sparkline", "values": [1, 2, 3]})

        self.assertIsInstance(command, UnknownCommand)
        self.assertEqual(command.type, "custom:sparkline")

    def test_decodes_arc_command(self):
        command = decode_command({"type": "arc", "cx": 40, "cy": 40, "radius": 30, "startDegrees": 0, "sweepDegrees": 270, "color": "#F97316", "strokeWidth": 4})

        self.assertIsInstance(command, ArcCommand)
        self.assertEqual(command.sweep_degrees, 270)

    def test_decodes_spinner_and_skeleton_commands(self):
        spinner = decode_command({"type": "spinner", "x": 0, "y": 0, "size": 24, "color": "#111827", "trackColor": "#E5E7EB", "strokeWidth": 3})
        skeleton = decode_command({"type": "skeleton", "x": 0, "y": 0, "width": 200, "height": 16, "color": "#E5E7EB", "radius": 8})

        self.assertIsInstance(spinner, SpinnerCommand)
        self.assertIsInstance(skeleton, SkeletonCommand)
        self.assertEqual(skeleton.radius, 8)

    def test_decodes_slider_command(self):
        command = decode_command({
            "type": "slider", "key": "volume", "x": 0, "y": 0, "width": 260, "height": 32,
            "trackHeight": 4, "thumbSize": 20, "value": 0.4,
            "trackColor": "#E5E7EB", "activeColor": "#111827", "thumbColor": "#FFFFFF",
        })

        self.assertIsInstance(command, SliderCommand)
        self.assertEqual(command.value, 0.4)

    def test_decodes_client_panel_with_nested_commands(self):
        command = decode_command({
            "type": "clientPanel", "key": "tabs1", "index": 1, "initiallyActive": False, "x": 0, "y": 40,
            "commands": [{"type": "text", "x": 0, "y": 0, "text": "Onglet 2", "color": "#111827"}],
            "hitRegions": [],
        })

        self.assertIsInstance(command, ClientPanelCommand)
        self.assertEqual(len(command.commands), 1)
        self.assertIsInstance(command.commands[0], TextCommand)

    def test_decodes_hscroll_and_vscroll_commands(self):
        hscroll = decode_command({"type": "hScroll", "key": "carousel", "x": 0, "y": 0, "width": 300, "height": 120, "contentWidth": 900, "commands": [], "hitRegions": []})
        vscroll = decode_command({"type": "vScroll", "key": "comments", "x": 0, "y": 0, "width": 300, "height": 200, "contentHeight": 600, "commands": [], "hitRegions": []})

        self.assertIsInstance(hscroll, HScrollCommand)
        self.assertEqual(hscroll.content_width, 900)
        self.assertIsInstance(vscroll, VScrollCommand)
        self.assertEqual(vscroll.content_height, 600)


class DecodePayloadTests(unittest.TestCase):
    def test_decodes_a_full_real_button_payload_from_the_shared_golden_fixture(self):
        with open(GOLDEN_FIXTURE, encoding="utf-8") as f:
            raw = json.load(f)

        payload = decode_payload(raw)

        self.assertEqual(len(payload.commands), 3)
        self.assertEqual(payload.content_height, 0)
        self.assertEqual(len(payload.hit_regions), 1)
        self.assertEqual(payload.hit_regions[0].action, "submit:demo")

    def test_empty_hit_regions_array_decodes_without_raising(self):
        payload = decode_payload({"commands": [], "hitRegions": [], "contentHeight": 0})

        self.assertEqual(len(payload.hit_regions), 0)

    def test_action_at_hits_the_containing_region(self):
        payload = decode_payload({
            "commands": [],
            "hitRegions": [
                {"x": 0, "y": 0, "width": 100, "height": 50, "action": "navigate:home"},
                {"x": 100, "y": 0, "width": 100, "height": 50, "action": "navigate:settings"},
            ],
            "contentHeight": 0,
        })

        self.assertEqual(payload.action_at(50, 25), "navigate:home")
        self.assertEqual(payload.action_at(150, 25), "navigate:settings")
        self.assertIsNone(payload.action_at(500, 500))

    def test_region_at_returns_the_whole_region_not_just_the_action(self):
        payload = decode_payload({
            "commands": [],
            "hitRegions": [{"x": 10, "y": 20, "width": 100, "height": 50, "action": "focus:name"}],
            "contentHeight": 0,
        })

        region = payload.region_at(50, 40)
        self.assertIsNotNone(region)
        self.assertEqual(region.action, "focus:name")
        self.assertEqual((region.x, region.y, region.width, region.height), (10, 20, 100, 50))
        self.assertIsNone(payload.region_at(500, 500))

    def test_action_at_prefers_the_last_region_when_overlapping(self):
        payload = decode_payload({
            "commands": [],
            "hitRegions": [
                {"x": 0, "y": 0, "width": 200, "height": 200, "action": "background"},
                {"x": 50, "y": 50, "width": 50, "height": 50, "action": "foreground_button"},
            ],
            "contentHeight": 0,
        })

        self.assertEqual(payload.action_at(60, 60), "foreground_button")
        self.assertEqual(payload.action_at(10, 10), "background")


if __name__ == "__main__":
    unittest.main()
