"""The Linux counterpart of ImageLoader.kt (Android) / ImageLoader.swift
(iOS) — no external HTTP/image library dependency, `urllib.request` +
GdkPixbuf (already a GTK dependency) cover it. An in-memory cache keyed
by URL, one in-flight fetch per distinct URL (deduped so a fast
re-render doesn't refetch), decoding happens on a background thread,
completion marshaled back to the GLib main loop — same shape as both
other platforms, `threading.Thread` standing in for `kotlin.concurrent.
thread`/a background `DispatchQueue`, `GLib.idle_add` standing in for
`Handler(mainLooper).post{}`/`DispatchQueue.main.async`.
"""

from __future__ import annotations

import base64
import threading
import urllib.request
from typing import Callable, Optional

import gi

gi.require_version("GdkPixbuf", "2.0")
gi.require_version("GLib", "2.0")
from gi.repository import GdkPixbuf, GLib  # noqa: E402

_cache: dict[str, GdkPixbuf.Pixbuf] = {}
_in_flight: set[str] = set()
_lock = threading.Lock()

#: Set once by the GTK app shell (see app.py) — called on the main loop
#: whenever any image finishes loading, so the caller can queue_draw()
#: without every call site here needing its own callback plumbed
#: through render_payload()'s otherwise-pure signature.
on_image_loaded: Optional[Callable[[], None]] = None


def get(url: str) -> Optional[GdkPixbuf.Pixbuf]:
    return _cache.get(url)


def load(url: str) -> None:
    with _lock:
        if url in _cache or url in _in_flight:
            return
        _in_flight.add(url)

    threading.Thread(target=_fetch_and_decode, args=(url,), daemon=True).start()


def _fetch_and_decode(url: str) -> None:
    try:
        # A camera-captured or gallery-picked image comes back as a
        # base64 `data:` URI, not a real network location — decode
        # directly instead of a doomed HTTP fetch, same special-case
        # ImageLoader.kt/ImageLoader.swift both carve out.
        if url.startswith("data:"):
            _, _, payload = url.partition(",")
            data = base64.b64decode(payload)
        else:
            with urllib.request.urlopen(url, timeout=8) as response:
                data = response.read()

        loader = GdkPixbuf.PixbufLoader()
        loader.write(data)
        loader.close()
        pixbuf = loader.get_pixbuf()
    except Exception:
        pixbuf = None

    def finish():
        with _lock:
            _in_flight.discard(url)
        if pixbuf is not None:
            _cache[url] = pixbuf
            if on_image_loaded is not None:
                on_image_loaded()
        return False

    GLib.idle_add(finish)
