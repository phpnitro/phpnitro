"""Real integration test — actually spawns `php -S` against this repo
via PhpProcess, exactly the class the GTK app shell uses, not a
reimplementation of its logic for testing purposes.
"""

import sys
import unittest
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from phpnitro_desktop.php_process import PhpProcess

REPO_ROOT = Path(__file__).resolve().parent.parent.parent


@unittest.skipUnless((REPO_ROOT / "vendor" / "autoload.php").exists(), "composer install hasn't run in this checkout")
class PhpProcessTests(unittest.TestCase):
    def test_start_binds_a_real_port_that_answers_http_and_stop_tears_it_down(self):
        process = PhpProcess(REPO_ROOT)

        port = process.start()
        try:
            self.assertTrue(process.is_running)
            self.assertGreater(port, 0)

            with urllib.request.urlopen(f"http://127.0.0.1:{port}/native/layout-demo?screen=home", timeout=8) as response:
                self.assertEqual(response.status, 200)
        finally:
            process.stop()

        self.assertFalse(process.is_running)
        with self.assertRaises(OSError):
            urllib.request.urlopen(f"http://127.0.0.1:{port}/", timeout=2)

    def test_raises_a_clear_error_for_a_directory_with_no_public_folder(self):
        process = PhpProcess(Path("/tmp"))

        with self.assertRaises(FileNotFoundError):
            process.start()


if __name__ == "__main__":
    unittest.main()
