"""Fetch/load + cache for `LottieRegion.url`, the same shape
`image_loader.py` already uses for `ImageCommand.url`: an in-memory
cache keyed by URL/path, one in-flight load per distinct key, the
actual fetch+parse happening on a background thread, completion
marshaled back to the GLib main loop via `GLib.idle_add` — ctypes calls
into librlottie only ever happen on the main thread this way, avoiding
any doubt about whether the library itself is safe to call
concurrently from Python threads.

`Lottie.php`'s own docblock: "$url can be a remote https:// URL... or
an asset path under assets/lottie/ bundled with the app" — mirrors that
exact split, resolved against `PROJECT_DIR` (set once by `app.py`'s
`run_local()`, the same module-level-global convention
`image_loader.on_image_loaded` already uses rather than threading a
path through every call site).
"""

from __future__ import annotations

import threading
import urllib.request
from pathlib import Path
from typing import Callable, Optional

import gi

gi.require_version("GLib", "2.0")
from gi.repository import GLib  # noqa: E402

from . import lottie_render

#: Set once by app.py's run_local() — resolves a non-"http" url
#: (Lottie.php's own "asset path under assets/lottie/" case) against
#: the actual scaffolded project directory, not this package's own
#: install location.
PROJECT_DIR: Optional[Path] = None

#: Mirrors image_loader.on_image_loaded exactly.
on_lottie_loaded: Optional[Callable[[], None]] = None

_cache: dict[str, lottie_render.LottieAnimation] = {}
_in_flight: set[str] = set()
_lock = threading.Lock()


def get(url: str) -> Optional[lottie_render.LottieAnimation]:
    return _cache.get(url)


def load(url: str) -> None:
    with _lock:
        if url in _cache or url in _in_flight:
            return
        _in_flight.add(url)

    threading.Thread(target=_fetch, args=(url,), daemon=True).start()


def _fetch(url: str) -> None:
    # Parsing (lottie_animation_from_data) happens right here, on this
    # background thread, same as image_loader.py's own
    # GdkPixbuf.PixbufLoader.write() — rlottie documents distinct
    # Animation objects as safe to create/use from different threads
    # concurrently, only a single shared object is thread-affine (never
    # the case here: each url gets its own object, and `finish()` below
    # is the only place a completed one gets published for the main
    # thread to touch).
    try:
        if url.startswith("http"):
            with urllib.request.urlopen(url, timeout=8) as response:
                json_data = response.read().decode("utf-8")
        else:
            asset_path = (PROJECT_DIR / "assets" / "lottie" / url) if PROJECT_DIR is not None else Path(url)
            json_data = asset_path.read_text(encoding="utf-8")
        animation = lottie_render.load_from_data(json_data, url)
    except Exception:
        animation = None

    def finish():
        with _lock:
            _in_flight.discard(url)
        if animation is not None:
            _cache[url] = animation
            if on_lottie_loaded is not None:
                on_lottie_loaded()
        return False

    GLib.idle_add(finish)
