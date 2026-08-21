"""ctypes bindings for librlottie's C ABI (`rlottie_capi.h`) — the one
overlay this Linux port didn't have yet (see `linux/README.md`'s own
"pas encore" note before this file existed). Unlike `rust_render.py`,
this binds a system library installed via the distro's package manager
(`librlottie-dev`/`librlottie0-1`, added to `ci.yml`'s apt-get line),
not something this repo builds itself — so there's no `target/{release,
debug}` search, just the handful of paths `ctypes.util.find_library()`
and a normal Debian/Ubuntu multiarch install would use.

Deliberately independent of canvas.py/draw_command.py/app.py, same
"pure binding, no GTK dependency" separation rust_render.py already
keeps.

# Honesty

Written and reviewed against `rlottie_capi.h`'s documented signatures
from memory, not against a locally installed copy of the header —
`librlottie-dev` was confirmed available via `apt-cache show` but never
installed in the sandbox this was written in (same "no local apt
installs during autonomous work" boundary that already applied to
`gir1.2-shumate-1.0` earlier). The specific claim most likely to be
wrong if this doesn't work: `lottie_animation_render()`'s buffer format
is documented as "ARGB32 Premultiplied", the same native/host-endian
premultiplied-ARGB32 convention Skia uses on little-endian systems —
which is also exactly Cairo's own `FORMAT_ARGB32` on this platform (see
`rust_render.to_cairo_bgra()`'s own doc comment), so `_render_frame()`
below hands the raw buffer to Cairo with no channel swap, unlike the
Rust render path. If a real animation shows with red/blue swapped, this
is the first place to look — add a `buffer[0::4], buffer[2::4] =
buffer[2::4], buffer[0::4]` swap here, mirroring `to_cairo_bgra()`
exactly, and this comment was wrong.

Confirmed correct via CI once `librlottie-dev`/`librlottie0-1` are
installed there — see `linux/tests/test_lottie_render.py`, which loads
a real embedded fixture animation and asserts on its actual decoded
size/frame count/rendered pixels, not just that this module imports.
"""

from __future__ import annotations

import ctypes
import ctypes.util
from typing import Optional


class LottieUnavailable(RuntimeError):
    """Raised when librlottie can't be found/loaded at all — distinct
    from a normal load/render failure (malformed JSON, etc.), which
    returns None instead of raising, same contract rust_render.py's own
    RustRenderUnavailable keeps relative to a plain None return.
    """


def _load_library() -> ctypes.CDLL:
    name = ctypes.util.find_library("rlottie")
    candidates = [name] if name else []
    # find_library() can come back empty in a minimal container even
    # when the package is installed (it shells out to `ldconfig`,
    # which isn't always populated) — these are the real paths Debian/
    # Ubuntu's multiarch layout installs librlottie0-1 to.
    candidates += [
        "librlottie.so.1",
        "librlottie.so",
        "/usr/lib/x86_64-linux-gnu/librlottie.so.1",
        "/usr/lib/aarch64-linux-gnu/librlottie.so.1",
    ]
    errors = []
    for candidate in candidates:
        if not candidate:
            continue
        try:
            return ctypes.CDLL(candidate)
        except OSError as exc:
            errors.append(f"{candidate}: {exc}")
    raise LottieUnavailable(
        "could not locate/load librlottie — tried:\n" + "\n".join(errors)
        + "\nInstall it with `apt-get install librlottie-dev` (Debian/Ubuntu) "
        "or your distro's equivalent."
    )


_lib: Optional[ctypes.CDLL] = None


def _lib_handle() -> ctypes.CDLL:
    global _lib
    if _lib is None:
        lib = _load_library()
        lib.lottie_animation_from_data.restype = ctypes.c_void_p
        lib.lottie_animation_from_data.argtypes = [ctypes.c_char_p, ctypes.c_char_p, ctypes.c_char_p]
        lib.lottie_animation_from_file.restype = ctypes.c_void_p
        lib.lottie_animation_from_file.argtypes = [ctypes.c_char_p]
        lib.lottie_animation_destroy.argtypes = [ctypes.c_void_p]

        lib.lottie_animation_get_size.argtypes = [ctypes.c_void_p, ctypes.POINTER(ctypes.c_size_t), ctypes.POINTER(ctypes.c_size_t)]
        lib.lottie_animation_get_duration.restype = ctypes.c_double
        lib.lottie_animation_get_duration.argtypes = [ctypes.c_void_p]
        lib.lottie_animation_get_totalframe.restype = ctypes.c_size_t
        lib.lottie_animation_get_totalframe.argtypes = [ctypes.c_void_p]
        lib.lottie_animation_get_framerate.restype = ctypes.c_double
        lib.lottie_animation_get_framerate.argtypes = [ctypes.c_void_p]

        lib.lottie_animation_render.argtypes = [
            ctypes.c_void_p, ctypes.c_size_t, ctypes.POINTER(ctypes.c_uint32),
            ctypes.c_size_t, ctypes.c_size_t, ctypes.c_size_t,
        ]
        _lib = lib
    return _lib


class LottieAnimation:
    """Owns one loaded animation handle — create ONE per distinct
    `LottieRegion.key`, reused across every render (loading/parsing the
    JSON is the expensive part; rendering a given frame into a caller-
    owned buffer is cheap and meant to be called every tick), never
    reloaded just because a fetch happened. See
    `PhpNitroCanvasWidget._reconcile_lottie_overlays()` in app.py.
    """

    def __init__(self, handle: ctypes.c_void_p) -> None:
        self._lib = _lib_handle()
        self._handle = handle
        width = ctypes.c_size_t()
        height = ctypes.c_size_t()
        self._lib.lottie_animation_get_size(self._handle, ctypes.byref(width), ctypes.byref(height))
        self.width = int(width.value)
        self.height = int(height.value)
        self.duration_seconds = float(self._lib.lottie_animation_get_duration(self._handle))
        self.total_frames = int(self._lib.lottie_animation_get_totalframe(self._handle))
        self.framerate = float(self._lib.lottie_animation_get_framerate(self._handle))

    def frame_at(self, elapsed_seconds: float, loop: bool) -> int:
        """Which frame index to render for a given point in the
        timeline — computed from wall-clock elapsed time rather than a
        per-widget counter incremented once per tick, so a slow/late
        tick (a busy main loop) never desyncs the animation's actual
        speed from its authored duration, the same "elapsed time, not
        tick count, drives the frame" principle
        Animated.php/`drawInterpolated()` already use for Hero/implicit
        animations.
        """
        if self.total_frames <= 0 or self.duration_seconds <= 0:
            return 0
        progress = elapsed_seconds / self.duration_seconds
        if loop:
            progress %= 1.0
        else:
            progress = min(progress, 1.0)
        frame = int(progress * self.total_frames)
        return min(frame, self.total_frames - 1)

    def render(self, frame_num: int, width: int, height: int) -> bytes:
        """Renders one frame into a freshly allocated premultiplied
        ARGB32 buffer, `width * height * 4` bytes, stride == width * 4
        (no row padding — same "4 always divides evenly" reasoning
        RustRenderer.kt's own stride comment already makes). Returns
        plain `bytes` (a copy) rather than handing back the ctypes
        buffer object, since the caller (a `Cairo.ImageSurface`) needs
        a stable, unshared buffer of its own to draw from.
        """
        stride = width * 4
        buffer = (ctypes.c_uint32 * (width * height))()
        self._lib.lottie_animation_render(self._handle, frame_num, buffer, width, height, stride)
        return bytes(buffer)

    def close(self) -> None:
        if self._handle is not None:
            self._lib.lottie_animation_destroy(self._handle)
            self._handle = None

    def __del__(self) -> None:
        self.close()


def load_from_data(json_data: str, cache_key: str) -> Optional[LottieAnimation]:
    """`json_data` is the raw Lottie JSON, already fetched — this
    module never does its own networking (see `lottie_loader.py`,
    which mirrors `image_loader.py`'s async-fetch-then-cache shape).
    Returns None on a parse failure rather than raising, same
    "malformed input degrades to nothing shown" contract every other
    overlay in this file follows.
    """
    lib = _lib_handle()
    handle = lib.lottie_animation_from_data(json_data.encode("utf-8"), cache_key.encode("utf-8"), b"")
    if not handle:
        return None
    return LottieAnimation(ctypes.c_void_p(handle))


def load_from_file(path: str) -> Optional[LottieAnimation]:
    lib = _lib_handle()
    handle = lib.lottie_animation_from_file(path.encode("utf-8"))
    if not handle:
        return None
    return LottieAnimation(ctypes.c_void_p(handle))
