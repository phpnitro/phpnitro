"""Registers MaterialIcons-Regular.ttf/FontAwesome-Solid.ttf (bundled
alongside this module — verbatim copies of the same two files
android/engine/src/main/assets/fonts/ already ships, same font IconFont.swift
loads on iOS) with fontconfig at process start, so Pango can resolve
them by family name without installing anything system-wide.

Body text (the "text" draw command) deliberately does NOT get a bundled
font here — it uses whatever sans-serif Pango/fontconfig already
resolves on this system, the same "system font, not a custom one"
choice IconFont.font(forKey:) makes on iOS (UIFont.systemFont) and
NativeCanvasView.kt's own drawTextCommand() makes on Android (Paint's
default typeface). Only icon glyphs need a specific bundled font, since
Material Icons/Font Awesome codepoints don't mean anything in a system
font.

fontconfig has no GObject-Introspection binding (Pango/Cairo just use
it internally) — FcConfigAppFontAddFile is the standard C API for
loading a font from a file at runtime without installing it, called
here via ctypes since that's the only way to reach it from Python.
"""

from __future__ import annotations

import ctypes
from pathlib import Path

FONTS_DIR = Path(__file__).resolve().parent / "fonts"

MATERIAL_ICONS_FAMILY = "Material Icons"
# Confirmed via `fc-query --format='family: %{family}\n'` against the
# actual bundled file — this font's own embedded name is "Font Awesome
# 6 Free Solid", not "5" (the codepoints in Icon.php's own FontAwesome
# map still come from the same "Solid" style either way).
FONT_AWESOME_FAMILY = "Font Awesome 6 Free Solid"

_fontconfig = ctypes.CDLL("libfontconfig.so.1")
_fontconfig.FcConfigGetCurrent.restype = ctypes.c_void_p
_fontconfig.FcConfigAppFontAddFile.restype = ctypes.c_int
_fontconfig.FcConfigAppFontAddFile.argtypes = [ctypes.c_void_p, ctypes.c_char_p]

_registered = False


def register_bundled_fonts() -> bool:
    """Idempotent — safe to call more than once (a second call is a
    cheap no-op), since both the GTK app entry point and any headless
    rendering test need this done before drawing an "icon" command.
    Returns False (never raises) if fontconfig is unavailable or a font
    file failed to load — same "an unrenderable glyph is a no-op, not a
    crash" contract IconFont.swift's own register(resource:) follows.
    """
    global _registered
    if _registered:
        return True

    config = _fontconfig.FcConfigGetCurrent()
    if not config:
        return False

    ok = True
    for filename in ("MaterialIcons-Regular.ttf", "FontAwesome-Solid.ttf"):
        path = FONTS_DIR / filename
        added = _fontconfig.FcConfigAppFontAddFile(config, str(path).encode("utf-8"))
        ok = ok and bool(added)

    _registered = ok
    return ok
