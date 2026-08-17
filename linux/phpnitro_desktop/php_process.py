"""Spawns the project's own PHP server as a real child process — the
Linux counterpart of PhpServer.kt (Android). Dramatically simpler than
that Kotlin original for one structural reason worth calling out: a
desktop OS doesn't sandbox a process away from its own filesystem the
way Android does an APK's assets, so there is no cross-compiled
`libphp.so` to ship and no assets/ -> filesDir copy step at all — this
just runs the SYSTEM `php` binary (whatever `php -v` on this machine
already resolves to) straight against the project directory's own
`public/`, exactly the same invocation `bin/phpx serve` itself already
uses for local dev.

Known, deliberate scoping gap: this means PHP itself must already be
installed on the machine running this desktop shell — unlike Android/
iOS, which embed a real interpreter so the end user never needs one
installed. Bundling a portable per-OS PHP binary the same way is real,
separate follow-up work for a shippable end-user desktop app; for a
developer running their own project locally, requiring `php` on PATH
is a reasonable, honest starting point, not an oversight.
"""

from __future__ import annotations

import os
import socket
import subprocess
import time
from pathlib import Path
from typing import Optional


def find_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def _is_listening(port: int) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.settimeout(0.2)
        return s.connect_ex(("127.0.0.1", port)) == 0


def wait_until_listening(port: int, timeout: float = 12) -> None:
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        if _is_listening(port):
            return
        time.sleep(0.15)
    raise TimeoutError(f"php -S never started listening on 127.0.0.1:{port}")


class PhpProcess:
    """Owns exactly one `php -S` child process for one project directory.
    `start()`/`stop()` mirror PhpServer.kt's own lifecycle, called from
    the GTK app shell's activate/shutdown handlers the same way
    MainActivity.kt calls PhpServer.start()/stop() from onCreate()/
    onDestroy().
    """

    def __init__(self, project_dir: Path):
        self.project_dir = Path(project_dir)
        self.port: int = 0
        self._process: Optional[subprocess.Popen] = None

    def start(self) -> int:
        public_dir = self.project_dir / "public"
        router = public_dir / "router.php"
        if not public_dir.is_dir():
            raise FileNotFoundError(f"no public/ directory found at {public_dir}")

        self.port = find_free_port()

        command = ["php", "-S", f"127.0.0.1:{self.port}", "-t", str(public_dir)]
        if router.exists():
            command.append(str(router))

        # No PHPNITRO_ACCESS_TOKEN here — deliberately, matching
        # `phpx serve`'s own dev server (bin/phpx's cmdServe(), see its
        # own comment on this exact choice), not PhpServer.kt's mobile
        # embed. That token exists to stop a DIFFERENT, LOWER-trust app
        # on the same device from reaching this one's PHP over
        # loopback — a real boundary on Android/iOS, where the OS
        # sandboxes apps from each other's files but not from each
        # other's localhost sockets. A desktop OS has no such sandbox
        # between ordinary processes owned by the same user to route
        # around in the first place: anything that could reach this
        # port already has direct filesystem access to the project
        # too. Revisit if this shell ever grows a phpx-serve-style
        # LAN-exposed mode (0.0.0.0, not 127.0.0.1) — that changes the
        # threat model back to needing one.
        self._process = subprocess.Popen(
            command,
            cwd=self.project_dir,
            env=os.environ.copy(),
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        wait_until_listening(self.port)
        return self.port

    def stop(self) -> None:
        if self._process is None:
            return
        self._process.terminate()
        try:
            self._process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            self._process.kill()
            self._process.wait(timeout=5)
        self._process = None

    @property
    def is_running(self) -> bool:
        return self._process is not None and self._process.poll() is None
