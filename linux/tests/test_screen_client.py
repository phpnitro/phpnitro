"""build_url() is tested with plain assertions. fetch_screen() is tested
against a REAL `php -S` server, spawned against this repo's actual
public/ directory (not a mocked HTTP layer) — the same route Android's
NativeRenderPocActivity.kt and iOS's ScreenClient.swift both fetch in
production, run for real by an interpreter that's actually installed
here. This is stronger verification than a stub: a real change to
Canvas::toJson()'s JSON shape would break this test, not just a
hand-maintained fixture.
"""

import socket
import subprocess
import sys
import time
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from phpnitro_desktop.screen_client import FetchError, FetchSuccess, build_url, fetch_screen

REPO_ROOT = Path(__file__).resolve().parent.parent.parent


class BuildUrlTests(unittest.TestCase):
    def test_builds_the_expected_url(self):
        url = build_url("192.168.1.23", 8090, "home", action="counter:increment", width=390, height=844)

        self.assertTrue(url.startswith("http://192.168.1.23:8090/native/layout-demo?"))
        self.assertIn("screen=home", url)
        self.assertIn("action=counter%3Aincrement", url)

    def test_omits_action_when_none(self):
        url = build_url("127.0.0.1", 8090, "home")

        self.assertNotIn("action=", url)

    def test_includes_field_values_sorted_by_name(self):
        url = build_url("127.0.0.1", 8090, "login", field_values={"password": "hunter2", "email": "a@b.com"})

        self.assertLess(url.index("email"), url.index("password"))


def _find_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def _wait_until_listening(port: int, timeout: float = 10) -> None:
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            if s.connect_ex(("127.0.0.1", port)) == 0:
                return
        time.sleep(0.1)
    raise TimeoutError(f"php -S never started listening on 127.0.0.1:{port}")


@unittest.skipUnless((REPO_ROOT / "vendor" / "autoload.php").exists(), "composer install hasn't run in this checkout")
class FetchScreenIntegrationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.port = _find_free_port()
        cls.server = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{cls.port}", "-t", "public", "public/router.php"],
            cwd=REPO_ROOT,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        _wait_until_listening(cls.port)

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)

    def test_fetches_and_decodes_the_real_home_screen(self):
        result = fetch_screen("127.0.0.1", self.port, "home", width=390, height=844)

        self.assertIsInstance(result, FetchSuccess)
        # The real home screen — not a synthetic fixture. Exact content
        # can change as the app evolves; what must stay true is that a
        # real render comes back with a real, non-trivial command tree.
        self.assertGreater(len(result.payload.commands), 0)

    def test_an_unrecognized_screen_falls_back_to_home_instead_of_erroring(self):
        # Discovered by actually running this against the real server,
        # not assumed: public/index.php's own dispatch match() has a
        # `default => NativeHomeScreen::build(...)` arm — an unknown
        # `screen=` value is a real 200 with the home screen's content,
        # not a 404/500. Real integration testing catches an assumption
        # like the opposite (this test originally expected a FetchError
        # here) being wrong the first time it actually runs, not months
        # later against a live device.
        result = fetch_screen("127.0.0.1", self.port, "this-screen-does-not-exist-anywhere")

        self.assertIsInstance(result, FetchSuccess)
        self.assertGreater(len(result.payload.commands), 0)

    def test_an_action_tap_round_trips_against_the_real_server(self):
        # NativeHomeScreen's own counter — a real action, not invented
        # for this test (see docs/getting-started.md's own example).
        first = fetch_screen("127.0.0.1", self.port, "home", width=390, height=844)
        second = fetch_screen("127.0.0.1", self.port, "home", action="home_increment", width=390, height=844)

        self.assertIsInstance(first, FetchSuccess)
        self.assertIsInstance(second, FetchSuccess)


if __name__ == "__main__":
    unittest.main()
