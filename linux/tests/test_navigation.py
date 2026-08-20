import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from phpnitro_desktop.navigation import ClientTabOnly, Fetch, FieldUpdate, reduce


class NavigationTests(unittest.TestCase):
    def test_navigate_pushes_onto_the_stack(self):
        result = reduce("navigate:product?id=42", ("home",))

        self.assertEqual(result, Fetch(stack=("home", "product?id=42"), action=None))

    def test_tab_resets_the_whole_stack(self):
        result = reduce("tab:profile", ("home", "product?id=42", "reviews"))

        self.assertEqual(result, Fetch(stack=("profile",), action=None))

    def test_back_pops_the_stack_when_more_than_one_screen(self):
        result = reduce("back", ("home", "product?id=42"))

        self.assertEqual(result, Fetch(stack=("home",), action=None))

    def test_back_is_a_no_op_on_the_root_screen(self):
        result = reduce("back", ("home",))

        self.assertEqual(result, Fetch(stack=("home",), action=None))

    def test_client_tab_is_fully_local_with_no_fetch(self):
        result = reduce("clientTab:tabs1:2", ("home",))

        self.assertEqual(result, ClientTabOnly(key="tabs1", index=2))

    def test_malformed_client_tab_falls_back_to_a_plain_fetch(self):
        result = reduce("clientTab:tabs1", ("home",))

        self.assertEqual(result, Fetch(stack=("home",), action=None))

    def test_a_plain_action_refetches_the_current_screen_with_it(self):
        result = reduce("counter:increment", ("home",))

        self.assertEqual(result, Fetch(stack=("home",), action="counter:increment"))

    def test_toggle_with_meta_extracts_the_next_value_as_a_local_field_update(self):
        result = reduce("toggle:agree", ("home",), '{"next":"1"}')

        self.assertEqual(result, FieldUpdate(key="agree", value="1"))

    def test_toggle_with_an_empty_next_is_still_a_field_update(self):
        result = reduce("toggle:agree", ("home",), '{"next":""}')

        self.assertEqual(result, FieldUpdate(key="agree", value=""))

    def test_toggle_with_no_meta_falls_back_to_a_plain_fetch(self):
        result = reduce("toggle:agree", ("home",))

        self.assertEqual(result, Fetch(stack=("home",), action="toggle:agree"))

    def test_toggle_with_malformed_meta_falls_back_to_a_plain_fetch(self):
        result = reduce("toggle:agree", ("home",), "not json")

        self.assertEqual(result, Fetch(stack=("home",), action="toggle:agree"))


if __name__ == "__main__":
    unittest.main()
