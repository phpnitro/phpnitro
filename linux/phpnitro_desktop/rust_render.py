"""ctypes bindings for rust/phpnitro-render's C ABI
(include/phpnitro_render.h) — the Python counterpart of what a Kotlin
JNI wrapper or a Swift C-interop layer would eventually be for
Android/iOS. Linux gets this first specifically because ctypes is the
lowest-ceremony FFI binding of the four platforms (no build-system
integration comparable to Gradle/Xcode/MSBuild needed).

This module is deliberately independent of canvas.py/draw_command.py —
it doesn't know or care about DrawCommandPayload dataclasses, only raw
envelope JSON strings in, raw pixel bytes out, mirroring how
draw_command.py itself is a "pure decoder, no GTK dependency" module.

Every function/type name below matches phpnitro_render.h one-for-one —
compare them side by side rather than trusting this docstring alone if
the two ever appear to disagree.
"""

from __future__ import annotations

import ctypes
import os
from dataclasses import dataclass
from pathlib import Path
from typing import Optional


class RustRenderUnavailable(RuntimeError):
    """Raised when the compiled library can't be found/loaded at all —
    distinct from a normal render/hit-test failure, which returns None
    instead of raising (same "malformed input degrades gracefully"
    contract every other module in this package follows).
    """


def _candidate_library_paths() -> list[Path]:
    here = Path(__file__).resolve()
    # here = .../linux/phpnitro_desktop/rust_render.py
    # repo root = here.parents[2]
    repo_root = here.parents[2]
    crate_dir = repo_root / "rust" / "phpnitro-render"
    candidates = [
        crate_dir / "target" / "release" / "libphpnitro_render.so",
        crate_dir / "target" / "debug" / "libphpnitro_render.so",
    ]
    override = os.environ.get("PHPNITRO_RUST_RENDER_LIB")
    if override:
        candidates.insert(0, Path(override))
    return candidates


def _load_library() -> ctypes.CDLL:
    errors = []
    for path in _candidate_library_paths():
        if not path.is_file():
            errors.append(f"{path}: not found")
            continue
        try:
            return ctypes.CDLL(str(path))
        except OSError as exc:
            errors.append(f"{path}: {exc}")
    raise RustRenderUnavailable(
        "could not locate/load libphpnitro_render.so — tried:\n" + "\n".join(errors)
        + "\nBuild it with `cargo build --release` in rust/phpnitro-render, "
        "or set PHPNITRO_RUST_RENDER_LIB to an explicit .so path."
    )


_lib: Optional[ctypes.CDLL] = None


def _lib_handle() -> ctypes.CDLL:
    global _lib
    if _lib is None:
        lib = _load_library()
        lib.phpnitro_render_version.restype = ctypes.c_char_p
        lib.phpnitro_render_last_error.restype = ctypes.c_char_p

        lib.phpnitro_render_new.restype = ctypes.c_void_p
        lib.phpnitro_render_free.argtypes = [ctypes.c_void_p]

        lib.phpnitro_render_frame.restype = ctypes.c_void_p
        lib.phpnitro_render_frame.argtypes = [
            ctypes.c_void_p, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_uint64,
            ctypes.c_uint32, ctypes.c_uint32, ctypes.c_uint64,
        ]
        lib.phpnitro_render_frame_pixels.restype = ctypes.c_void_p
        lib.phpnitro_render_frame_pixels.argtypes = [ctypes.c_void_p]
        lib.phpnitro_render_frame_stride.restype = ctypes.c_uint32
        lib.phpnitro_render_frame_stride.argtypes = [ctypes.c_void_p]
        lib.phpnitro_render_frame_width.restype = ctypes.c_uint32
        lib.phpnitro_render_frame_width.argtypes = [ctypes.c_void_p]
        lib.phpnitro_render_frame_height.restype = ctypes.c_uint32
        lib.phpnitro_render_frame_height.argtypes = [ctypes.c_void_p]
        lib.phpnitro_render_free_frame.argtypes = [ctypes.c_void_p]

        lib.phpnitro_render_hit_test.restype = ctypes.c_void_p
        lib.phpnitro_render_hit_test.argtypes = [
            ctypes.c_char_p, ctypes.c_float, ctypes.c_float, ctypes.c_char_p,
        ]
        lib.phpnitro_render_hit_action.restype = ctypes.c_char_p
        lib.phpnitro_render_hit_action.argtypes = [ctypes.c_void_p]
        lib.phpnitro_render_hit_meta_json.restype = ctypes.c_char_p
        lib.phpnitro_render_hit_meta_json.argtypes = [ctypes.c_void_p]
        lib.phpnitro_render_hit_rect.argtypes = [
            ctypes.c_void_p,
            ctypes.POINTER(ctypes.c_float), ctypes.POINTER(ctypes.c_float),
            ctypes.POINTER(ctypes.c_float), ctypes.POINTER(ctypes.c_float),
        ]
        lib.phpnitro_render_free_hit.argtypes = [ctypes.c_void_p]
        _lib = lib
    return _lib


def version() -> str:
    return _lib_handle().phpnitro_render_version().decode("utf-8")


def last_error() -> Optional[str]:
    raw = _lib_handle().phpnitro_render_last_error()
    return raw.decode("utf-8") if raw else None


@dataclass
class RenderedFrame:
    """RGBA8, premultiplied alpha — tiny-skia's native Pixmap layout,
    NOT Cairo's ARGB32/BGRA layout. A caller comparing this against a
    Cairo-rendered surface needs to swap the R/B channels, not assume
    the same byte order (see tests/test_rust_render_parity.py).
    """

    width: int
    height: int
    stride: int
    data: bytes


def to_cairo_bgra(frame: RenderedFrame) -> bytearray:
    """Swaps R/B per pixel so `frame.data` (RGBA8) can be painted through
    a `cairo.ImageSurface.create_for_data(..., cairo.FORMAT_ARGB32, ...)`
    (BGRA8 on this little-endian platform, per Cairo's own docs). Both
    are premultiplied alpha already, so no other conversion is needed —
    swap only, no channel math.
    """
    buffer = bytearray(frame.data)
    buffer[0::4], buffer[2::4] = buffer[2::4], buffer[0::4]
    return buffer


class RustRenderer:
    """Owns the loaded fonts (rust/phpnitro-render's FontSystem) — create
    ONE of these per app lifetime (or per screen at most), never one per
    frame, same guidance phpnitro_render.h gives every other consumer.
    """

    def __init__(self) -> None:
        self._lib = _lib_handle()
        self._handle = self._lib.phpnitro_render_new()
        if not self._handle:
            raise RustRenderUnavailable("phpnitro_render_new() returned NULL")

    def render_frame(
        self,
        envelope_json: str,
        width: int,
        height: int,
        elapsed_ms: int = 0,
        previous_envelope_json: Optional[str] = None,
        transition_elapsed_ms: int = 0,
    ) -> Optional[RenderedFrame]:
        """Returns None on failure (malformed JSON, zero width/height) —
        check last_error() for why, same "None on a normal-ish failure,
        exception only when the library itself is unusable" split
        RustRenderUnavailable already draws.

        previous_envelope_json/transition_elapsed_ms drive a crossfade/hero
        transition between it and envelope_json (see
        rust/phpnitro-render/src/transition.rs) — omit both (the defaults)
        for a plain, untransitioned render.
        """
        previous_bytes = previous_envelope_json.encode("utf-8") if previous_envelope_json is not None else None
        frame = self._lib.phpnitro_render_frame(
            self._handle,
            envelope_json.encode("utf-8"),
            previous_bytes,
            transition_elapsed_ms,
            width,
            height,
            elapsed_ms,
        )
        if not frame:
            return None
        try:
            stride = self._lib.phpnitro_render_frame_stride(frame)
            actual_width = self._lib.phpnitro_render_frame_width(frame)
            actual_height = self._lib.phpnitro_render_frame_height(frame)
            pixels_ptr = self._lib.phpnitro_render_frame_pixels(frame)
            byte_count = stride * actual_height
            data = ctypes.string_at(pixels_ptr, byte_count) if pixels_ptr else b""
            return RenderedFrame(width=actual_width, height=actual_height, stride=stride, data=data)
        finally:
            self._lib.phpnitro_render_free_frame(frame)

    def close(self) -> None:
        if self._handle:
            self._lib.phpnitro_render_free(self._handle)
            self._handle = None

    def __del__(self) -> None:
        try:
            self.close()
        except Exception:
            pass


@dataclass
class HitResult:
    action: str
    meta_json: str
    rect: tuple[float, float, float, float]


def hit_test(
    envelope_json: str, tap_x: float, tap_y: float, interaction_state_json: Optional[str] = None
) -> Optional[HitResult]:
    """Module-level (not a `RustRenderer` method) since hit-testing needs
    no loaded fonts at all — mirrors `phpnitro_render_hit_test` not
    taking a renderer handle either.
    """
    lib = _lib_handle()
    state_bytes = interaction_state_json.encode("utf-8") if interaction_state_json else None
    hit = lib.phpnitro_render_hit_test(envelope_json.encode("utf-8"), tap_x, tap_y, state_bytes)
    if not hit:
        return None
    try:
        action = lib.phpnitro_render_hit_action(hit)
        meta_json = lib.phpnitro_render_hit_meta_json(hit)
        left, top, right, bottom = (ctypes.c_float(), ctypes.c_float(), ctypes.c_float(), ctypes.c_float())
        lib.phpnitro_render_hit_rect(hit, left, top, right, bottom)
        return HitResult(
            action=action.decode("utf-8") if action else "",
            meta_json=meta_json.decode("utf-8") if meta_json else "null",
            rect=(left.value, top.value, right.value, bottom.value),
        )
    finally:
        lib.phpnitro_render_free_hit(hit)
