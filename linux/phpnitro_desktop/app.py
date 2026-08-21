"""GTK4 application shell — the Linux counterpart of
NativeRenderPocActivity.kt (Android) / NativeScreenViewController.swift
(iOS). Deliberately thin: every piece of actual logic (draw-command
decoding, Cairo rendering, navigation-stack reduction, the HTTP fetch,
the PHP subprocess) already lives in its own independently-tested
module — this file is glue between those and real GTK widgets, the one
part of this target that cannot be verified without a live display
(no X11/Wayland/Broadway available in the environment this was
written in — see linux/README.md).
"""

from __future__ import annotations

import json
import os
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Optional

import cairo
import gi

gi.require_version("Gtk", "4.0")
gi.require_version("Gdk", "4.0")
from gi.repository import Gdk, Gio, GLib, Gtk  # noqa: E402

# libshumate (gir1.2-shumate-1.0) is a real GNOME map-widget library, not
# core GTK4 — unlike Gtk.Video (bundled with GTK4 itself since 4.6), it
# may genuinely not be installed on a given machine. Import failure is
# handled the same way a missing Rust .so already is elsewhere in this
# file: caught once here, checked before ever touching Shumate.* so a
# `map:open:` tap degrades to "nothing happens" instead of a crash, not
# the other way around.
try:
    gi.require_version("Shumate", "1.0")
    from gi.repository import Shumate
except (ValueError, ImportError):
    Shumate = None  # type: ignore[assignment]

from . import image_loader, navigation, php_process, rust_render, screen_client  # noqa: E402
from .canvas import RenderState, needs_animation, render_payload  # noqa: E402
from .draw_command import DrawCommandPayload, HScrollCommand, VScrollCommand  # noqa: E402

APP_ID = "com.phpnitro.desktop"

# Mirrors NativeCanvasView.kt's own touchSlop (ViewConfiguration's
# scaledTouchSlop, ~8dp) — a plain constant here since GTK has no
# per-device touch-slop concept of its own to query, same choice
# ScreenForm.cs (Windows)/RustScreenView.swift (macOS) already made.
_TOUCH_SLOP = 4.0


@dataclass(frozen=True)
class _ScrollTarget:
    key: str
    is_horizontal: bool
    rect: tuple[float, float, float, float]  # x, y, width, height
    content_extent: float


@dataclass(frozen=True)
class _SliderDrag:
    key: str
    action: str
    rect: tuple[float, float, float, float]  # x, y, width, height
    thumb_size: float


def _slider_value_for_touch(rect: tuple[float, float, float, float], thumb_size: float, touch_x: float) -> float:
    """Inverse of draw_slider()'s own thumbCx formula
    (rust/phpnitro-render/src/raster.rs) — mirrors
    ScreenForm.cs/RustScreenView.swift's own SliderValueForTouch exactly.
    """
    x, _y, width, _height = rect
    track_width = max(width - thumb_size, 1.0)
    value = (touch_x - x - thumb_size / 2.0) / track_width
    return min(max(value, 0.0), 1.0)


# The shared Rust engine (see rust/phpnitro-render/README.md) is now the
# DEFAULT render path — real-machine-tested against a genuinely blank
# phpx-new project (Cairo and Rust confirmed pixel-identical). Set
# PHPNITRO_RUST_RENDER=0 to fall back to the original Cairo path (kept
# fully intact, never removed); either way, a Rust failure (library
# missing, a frame that fails to render) still falls back to Cairo
# automatically with a printed reason, never a hard crash.
_RUST_RENDER_ENABLED = os.environ.get("PHPNITRO_RUST_RENDER", "1") != "0"


# TextField.php/PasswordField.php send no styling over the wire at all
# (same "hand-copy Tokens.php's own constants" convention every other
# platform's own text-input overlay already follows, e.g.
# NativeRenderPocActivity.kt's showTextInput()) — RADIUS_MD=14,
# ink()=#111827, border()=#E5E7EB, TEXT_BODY=15 (see Tokens.php), light
# mode only since no platform's shell implements dark mode yet either.
_TEXT_INPUT_CSS = """
.phpnitro-text-input {
    background-color: #FFFFFF;
    color: #111827;
    border: 1px solid #E5E7EB;
    border-radius: 14px;
    font-size: 15px;
    padding: 4px 8px;
}
"""


class PhpNitroCanvasWidget(Gtk.Overlay):
    """The Linux counterpart of NativeCanvasView.kt/NativeCanvasView.swift
    — a widget that only knows how to replay whatever DrawCommandPayload
    it was last given, report which hitRegion a click landed in, and host
    a real text-input overlay for a `focus:` action (see
    `show_text_input`'s own doc comment). It fetches nothing itself, same
    separation of concerns the other two platforms' own canvas views keep
    from their respective fetch loops.

    A `Gtk.Overlay` wrapping an internal `Gtk.DrawingArea` (`_drawing_area`,
    the actual paint surface) rather than a `Gtk.DrawingArea` itself —
    `Gtk.Overlay.add_overlay()` is GTK4's own mechanism for floating a real
    widget (the text input) on top of a custom-drawn one, the same
    "one main child + floating overlay children" idiom Android's own
    `FrameLayout`/iOS's subview-on-top approach both use for the exact
    same purpose. Every method this class already exposed keeps the same
    name/signature — only the base class and internal drawing-area
    indirection changed.
    """

    _css_provider: Optional[Gtk.CssProvider] = None

    def __init__(self):
        super().__init__()
        self.payload: Optional[DrawCommandPayload] = None
        self.raw_json: Optional[str] = None
        self.client_tab_state: dict[str, int] = {}
        # hScroll/vScroll.key -> local drag offset, mirrors
        # NativeCanvasView.kt's own hScrollOffsets/vScrollOffsets —
        # accumulated raw (raster.rs's own draw_hscroll/draw_vscroll
        # clamp to [0, contentExtent - viewportExtent] on every render),
        # so this side never needs to know contentWidth/contentHeight to
        # stay in range.
        self.axis_offset: dict[str, float] = {}
        # slider.key -> live drag value (0..1), mirrors
        # NativeCanvasView.kt's own sliderValues — overrides
        # SliderCommand.value while dragging; committed via on_action
        # only on drag-end.
        self.slider_value: dict[str, float] = {}
        self.on_action = None  # Optional[Callable[[str, Optional[str], tuple[float, float, float, float]], None]]
        # Called with (width, height) whenever _on_draw observes a size
        # different from the one the current payload was fetched for —
        # GTK4 hands this widget its real, live-allocated size on every
        # draw (including after a user resize), unlike ScreenWindow's own
        # get_default_size() (see that method's docblock for the real bug
        # this fixes: get_default_size() returns the fixed CONSTRUCTION
        # default, never the actual current window size, on this
        # platform specifically).
        self.on_resize = None  # Optional[Callable[[int, int], None]]
        # Fires on every keystroke in the active text-input overlay (see
        # show_text_input's own doc comment).
        self.on_field_value_changed = None  # Optional[Callable[[str, str], None]]
        self._last_fetched_size: tuple[int, int] = (0, 0)
        self._timer_id: Optional[int] = None
        self._rust_renderer: Optional[rust_render.RustRenderer] = None
        self._rust_render_start = time.monotonic()
        self._active_text_input: Optional[Gtk.Widget] = None
        self._active_video_overlay: Optional[Gtk.Widget] = None
        self._active_map_overlay: Optional[Gtk.Widget] = None

        # hScroll/vScroll's own "was this decisive move actually meant as
        # this axis's scroll" ambiguity — a candidate target found on
        # drag-begin, promoted to _active_scroll only once _TOUCH_SLOP is
        # cleared in the matching axis (see _on_drag_update). A slider
        # has no such ambiguity: its whole touch box IS the gesture, so
        # it commits directly to _active_slider on drag-begin.
        self._pending_scroll: Optional[_ScrollTarget] = None
        self._active_scroll: Optional[_ScrollTarget] = None
        self._active_slider: Optional[_SliderDrag] = None
        # The point drag-begin fired at — GestureDrag's own
        # drag-update/drag-end signals hand back an OFFSET relative to
        # this point, not absolute coordinates, so this is what recovers
        # the current absolute point (start + offset) each event.
        self._drag_start: tuple[float, float] = (0.0, 0.0)
        # GestureDrag's own signals hand back offsets RELATIVE TO THE
        # DRAG START, not deltas since the previous event — _last_offset
        # lets _on_drag_update recover a per-event delta the same way
        # ScreenForm.cs's own _lastDragPoint does (delta = last - current).
        self._last_offset: tuple[float, float] = (0.0, 0.0)

        self._drawing_area = Gtk.DrawingArea()
        self._drawing_area.set_draw_func(self._on_draw)
        self.set_child(self._drawing_area)

        # A single Gtk.GestureDrag handles both a plain tap (drag-end
        # fires with an ~(0, 0) offset) and a real hScroll/vScroll/slider
        # drag — same "one mouse-down/move/up trio for both" design
        # ScreenForm.cs (Windows)/RustScreenView.swift (macOS) already
        # use, rather than a separate click gesture alongside a drag one.
        drag = Gtk.GestureDrag.new()
        drag.connect("drag-begin", self._on_drag_begin)
        drag.connect("drag-update", self._on_drag_update)
        drag.connect("drag-end", self._on_drag_end)
        self._drawing_area.add_controller(drag)

        image_loader.on_image_loaded = self._drawing_area.queue_draw

    def queue_draw(self) -> None:
        self._drawing_area.queue_draw()

    def set_payload(self, payload: DrawCommandPayload, raw_json: Optional[str] = None) -> None:
        self.payload = payload
        self.raw_json = raw_json
        # A new payload just replaced whatever the current overlay (if
        # any) was positioned/typed against — NativeRenderPocActivity.kt
        # only tears its own overlay down on navigate:/tab:/back/submit:,
        # leaving it alone across other same-screen refetches (toggle:,
        # etc); this port simplifies to "any new payload ends the current
        # editing session", safer than repositioning a stale overlay
        # against content it was never laid out for.
        self.clear_text_input()
        self.clear_video_overlay()
        self.clear_map_overlay()
        self._update_animation_timer()
        self.queue_draw()

    def set_client_tab(self, key: str, index: int) -> None:
        self.client_tab_state[key] = index
        self.queue_draw()

    def set_fetched_size(self, width: int, height: int) -> None:
        """Called by `ScreenWindow` right after every fetch, real or
        bootstrap, so `_on_draw` below has something to compare its own
        live-allocated size against.
        """
        self._last_fetched_size = (width, height)

    def show_text_input(self, field_name: str, initial_value: str, rect: tuple[float, float, float, float], multiline: bool, secure: bool) -> None:
        """`focus:[multiline:][secure:]name` — ports
        `NativeRenderPocActivity.kt`'s `showTextInput()`: one real
        `Gtk.Entry`/`Gtk.TextView` positioned over the static rect+text
        `TextField.php` already painted underneath (which stays in the
        command list, just visually covered while focused). Only one at a
        time — a second `focus:` tap always replaces the first, mirroring
        `NativeRenderPocActivity.kt`'s own single-nullable-field
        `activeEditText` (never a map).
        """
        self.clear_text_input()

        left, top, right, bottom = rect
        width, height = right - left, bottom - top

        if multiline:
            buffer = Gtk.TextBuffer()
            buffer.set_text(initial_value, -1)
            widget: Gtk.Widget = Gtk.TextView(buffer=buffer)
            widget.set_wrap_mode(Gtk.WrapMode.WORD_CHAR)
            buffer.connect(
                "changed",
                lambda buf: self._emit_field_value_changed(field_name, buf.get_text(buf.get_start_iter(), buf.get_end_iter(), False)),
            )
        else:
            entry = Gtk.Entry()
            entry.set_text(initial_value)
            entry.set_visibility(not secure)
            entry.connect("changed", lambda e: self._emit_field_value_changed(field_name, e.get_text()))
            widget = entry

        widget.add_css_class("phpnitro-text-input")
        self._ensure_css_loaded()
        widget.set_size_request(max(int(width), 1), max(int(height), 1))
        widget.set_halign(Gtk.Align.START)
        widget.set_valign(Gtk.Align.START)
        widget.set_margin_start(int(left))
        widget.set_margin_top(int(top))

        self.add_overlay(widget)
        widget.grab_focus()
        self._active_text_input = widget

    def clear_text_input(self) -> None:
        if self._active_text_input is None:
            return
        self.remove_overlay(self._active_text_input)
        self._active_text_input = None

    def show_video_overlay(self, url: str, rect: tuple[float, float, float, float]) -> None:
        """`video:play:<url>` (VideoPlayer.php) — ports
        `NativeRenderPocActivity.kt`'s `showVideoOverlay()`: a real
        `Gtk.Video` positioned over the static "play" box already painted
        underneath, autoplaying immediately (mirrors `VideoView`'s own
        `setOnPreparedListener { it.start() }`). `Gio.File.new_for_uri()`
        accepts both a remote `https://` URL and a local `file://` one
        uniformly, unlike Android's own manual asset/URL distinction. No
        transport bar (unlike Android's system-provided
        `MediaController`) — a real, larger undertaking of its own, not
        attempted here.
        """
        self.clear_video_overlay()

        left, top, right, bottom = rect
        width, height = right - left, bottom - top

        video = Gtk.Video()
        video.set_autoplay(True)
        video.set_file(Gio.File.new_for_uri(url))
        video.set_size_request(max(int(width), 1), max(int(height), 1))
        video.set_halign(Gtk.Align.START)
        video.set_valign(Gtk.Align.START)
        video.set_margin_start(int(left))
        video.set_margin_top(int(top))

        self.add_overlay(video)
        self._active_video_overlay = video

    def clear_video_overlay(self) -> None:
        if self._active_video_overlay is None:
            return
        self.remove_overlay(self._active_video_overlay)
        self._active_video_overlay = None

    def show_map_overlay(self, latitude: float, longitude: float, zoom: int, rect: tuple[float, float, float, float]) -> None:
        """`map:open:<lat>:<lon>:<zoom>` (MapView.php) — ports
        `NativeRenderPocActivity.kt`'s `showMapOverlay()`: a real,
        pannable/zoomable map centered at (latitude, longitude), no API
        key needed (OpenStreetMap tiles via libshumate, same "no API key"
        property osmdroid/MapKit already have on Android/iOS/macOS).

        Unlike every other overlay in this file, `Shumate` isn't GTK4
        core (unlike `Gtk.Video`) and isn't installed in the environment
        this was written in (confirmed via `apt-cache policy`), so this
        was written and reviewed by hand rather than exercised locally —
        confirmed correct since via CI (`gir1.2-shumate-1.0` added to
        ci.yml; `test_show_map_overlay_constructs_a_real_widget_when_shumate_is_available`
        runs for real there and passes). `Shumate is None` (import
        genuinely unavailable) and any exception during construction
        still degrade to "no overlay shown, printed to stderr" rather
        than a crash — the same fail-soft contract `_draw_via_rust`
        already has for a missing Rust library.
        """
        self.clear_map_overlay()

        if Shumate is None:
            print("[phpnitro] map:open: tapped but libshumate (gir1.2-shumate-1.0) isn't available — no map overlay shown.")
            return

        try:
            left, top, right, bottom = rect
            width, height = right - left, bottom - top

            simple_map = Shumate.SimpleMap()
            registry = Shumate.MapSourceRegistry.new_with_defaults()
            osm_source = registry.get_by_id(Shumate.MAP_SOURCE_OSM_MAPNIK)
            simple_map.set_map_source(osm_source)

            map_widget = simple_map.get_map()
            map_widget.get_viewport().set_zoom_level(zoom)
            map_widget.center_on(latitude, longitude)

            simple_map.set_size_request(max(int(width), 1), max(int(height), 1))
            simple_map.set_halign(Gtk.Align.START)
            simple_map.set_valign(Gtk.Align.START)
            simple_map.set_margin_start(int(left))
            simple_map.set_margin_top(int(top))

            self.add_overlay(simple_map)
            self._active_map_overlay = simple_map
        except Exception as exc:  # noqa: BLE001 — see docstring: never let this crash the app.
            print(f"[phpnitro] failed to show map overlay: {exc}")

    def clear_map_overlay(self) -> None:
        if self._active_map_overlay is None:
            return
        self.remove_overlay(self._active_map_overlay)
        self._active_map_overlay = None

    def _emit_field_value_changed(self, field_name: str, value: str) -> None:
        # Every keystroke, not just on blur/submit — mirrors
        # NativeRenderPocActivity.kt's TextWatcher.afterTextChanged()
        # exactly; every platform here already sends field_values on
        # EVERY fetch regardless of what triggered it (unlike Android's
        # own selective includeFields flag), so there's no separate
        # "commit" step to wire beyond keeping the window's dictionary
        # current.
        if self.on_field_value_changed is not None:
            self.on_field_value_changed(field_name, value)

    @classmethod
    def _ensure_css_loaded(cls) -> None:
        if cls._css_provider is not None:
            return
        provider = Gtk.CssProvider()
        provider.load_from_string(_TEXT_INPUT_CSS)
        Gtk.StyleContext.add_provider_for_display(
            Gdk.Display.get_default(), provider, Gtk.STYLE_PROVIDER_PRIORITY_APPLICATION,
        )
        cls._css_provider = provider

    def _on_draw(self, _area, ctx, width, height) -> None:
        # Scaffold/Container only paint a background rect when one is
        # explicitly given (see Tokens::surface(), #FFFFFF by default) —
        # most screens omit it, relying on the host surface underneath.
        # Android gets this for free from its Activity theme; GTK4 has no
        # such default, so without this the user's dark system theme shows
        # through every unpainted pixel.
        ctx.set_source_rgb(1, 1, 1)
        ctx.paint()

        # GTK hands this callback the widget's real, live-allocated size
        # on every draw — including right after the window is first shown
        # (which can genuinely differ from ScreenWindow's own construction-
        # time default) and after every live resize. A mismatch against
        # what the CURRENT payload was actually fetched for means PHP
        # laid out for the wrong size — ask for a fresh one; this frame
        # still paints whatever's cached in the meantime; the next draw
        # (once the new payload lands) will match and stop re-firing.
        if (width, height) != self._last_fetched_size and self.on_resize is not None:
            self.on_resize(width, height)

        if self.payload is None:
            return
        if _RUST_RENDER_ENABLED and self.raw_json is not None and self._draw_via_rust(ctx, width, height):
            return
        state = RenderState(now=time.monotonic(), client_tab_state=self.client_tab_state)
        render_payload(ctx, self.payload, state)

    def _draw_via_rust(self, ctx, width: int, height: int) -> bool:
        """Returns False on any failure (library missing, malformed
        response, etc.) so `_on_draw` falls back to the Cairo path above
        — this proof-of-concept switch must never be the reason a screen
        fails to render at all.
        """
        if self._rust_renderer is None:
            try:
                self._rust_renderer = rust_render.RustRenderer()
            except rust_render.RustRenderUnavailable as exc:
                print(f"[phpnitro] PHPNITRO_RUST_RENDER=1 but the library isn't available, using Cairo: {exc}")
                return False

        elapsed_ms = int((time.monotonic() - self._rust_render_start) * 1000)
        frame = self._rust_renderer.render_frame(
            self.raw_json, width, height, elapsed_ms, interaction_state_json=self._interaction_state_json(),
        )
        if frame is None:
            print(f"[phpnitro] Rust render_frame failed, using Cairo for this frame: {rust_render.last_error()}")
            return False

        bgra = rust_render.to_cairo_bgra(frame)
        surface = cairo.ImageSurface.create_for_data(bgra, cairo.FORMAT_ARGB32, frame.width, frame.height, frame.stride)
        ctx.set_source_surface(surface, 0, 0)
        ctx.paint()
        return True

    def _interaction_state_json(self) -> str:
        """{"activePanel":{...},"axisOffset":{...},"sliderValue":{...}} —
        the same shape rust/phpnitro-render/src/hittest.rs's
        InteractionState decodes, and ScreenForm.cs/RustScreenView.swift's
        own BuildInteractionStateJson()/buildInteractionStateJSON()
        build. Rebuilt fresh each call rather than cached — this state
        changes far less often than frames render, but this stays
        correct without a dirty flag to remember to clear.
        """
        return json.dumps({
            "activePanel": self.client_tab_state,
            "axisOffset": self.axis_offset,
            "sliderValue": self.slider_value,
        })

    def _on_drag_begin(self, _gesture, start_x: float, start_y: float) -> None:
        self._pending_scroll = None
        self._active_scroll = None
        self._active_slider = None
        self._drag_start = (start_x, start_y)
        self._last_offset = (0.0, 0.0)

        if self.payload is None:
            return

        # Slider commits immediately on down, not after a decisive-move
        # threshold like hScroll/vScroll — a slider's whole touch box IS
        # the gesture, there's no "was this actually meant as a page
        # scroll" ambiguity to resolve (see NativeCanvasView.kt's own
        # comment on this exact point).
        for region in self.payload.slider_regions:
            rect = (region.x, region.y, region.width, region.height)
            if region.x <= start_x <= region.x + region.width and region.y <= start_y <= region.y + region.height:
                self._active_slider = _SliderDrag(region.key, region.action, rect, region.thumb_size)
                self.slider_value[region.key] = _slider_value_for_touch(rect, region.thumb_size, start_x)
                self.queue_draw()
                return

        # Only TOP-LEVEL hScroll/vScroll commands are considered — same
        # limitation NativeCanvasView.kt's own parseHScrollRegions()/
        # parseVScrollRegions() have (a flat scan over `commands`, no
        # recursion into a nested clientPanel), not a gap introduced here.
        for command in self.payload.commands:
            if isinstance(command, HScrollCommand):
                if command.x <= start_x <= command.x + command.width and command.y <= start_y <= command.y + command.height:
                    rect = (command.x, command.y, command.width, command.height)
                    self._pending_scroll = _ScrollTarget(command.key, True, rect, command.content_width)
                    return
            elif isinstance(command, VScrollCommand):
                if command.x <= start_x <= command.x + command.width and command.y <= start_y <= command.y + command.height:
                    rect = (command.x, command.y, command.width, command.height)
                    self._pending_scroll = _ScrollTarget(command.key, False, rect, command.content_height)
                    return

    def _on_drag_update(self, _gesture, offset_x: float, offset_y: float) -> None:
        if self.payload is None:
            return

        if self._active_slider is not None:
            current_x = self._drag_start[0] + offset_x
            self.slider_value[self._active_slider.key] = _slider_value_for_touch(
                self._active_slider.rect, self._active_slider.thumb_size, current_x,
            )
            self.queue_draw()
            return

        if self._pending_scroll is not None and self._active_scroll is None:
            target = self._pending_scroll
            if target.is_horizontal and abs(offset_x) > _TOUCH_SLOP and abs(offset_x) > abs(offset_y):
                self._active_scroll = target
                self._pending_scroll = None
            elif not target.is_horizontal and abs(offset_y) > _TOUCH_SLOP:
                self._active_scroll = target
                self._pending_scroll = None
            elif target.is_horizontal and abs(offset_y) > _TOUCH_SLOP and abs(offset_y) > abs(offset_x):
                # Decisive move was vertical over a pending HORIZONTAL
                # target — this was never an hScroll gesture.
                self._pending_scroll = None

        if self._active_scroll is not None:
            rect = self._active_scroll.rect
            viewport_extent = rect[2] if self._active_scroll.is_horizontal else rect[3]
            max_offset = max(self._active_scroll.content_extent - viewport_extent, 0.0)
            key = self._active_scroll.key
            current = self.axis_offset.get(key, 0.0)
            last_offset_x, last_offset_y = self._last_offset
            delta = (last_offset_x - offset_x) if self._active_scroll.is_horizontal else (last_offset_y - offset_y)
            self.axis_offset[key] = min(max(current + delta, 0.0), max_offset)
            self._last_offset = (offset_x, offset_y)
            self.queue_draw()

    def _on_drag_end(self, _gesture, offset_x: float, offset_y: float) -> None:
        if self.payload is None:
            return

        if self._active_slider is not None:
            slider = self._active_slider
            self._active_slider = None
            value = self.slider_value.get(slider.key, 0.0)
            # Locale-independent formatting (Python's ".3f" mini-language
            # always uses a period, never the current locale's decimal
            # separator) — a French/Belgian/etc. locale's decimal COMMA
            # sent as a literal query-string value would have PHP's
            # (float) cast stop parsing at the first non-digit character,
            # silently truncating every dragged value to its integer part
            # (same bug NativeCanvasView.kt's own Locale.US comment warns
            # about).
            meta_json = json.dumps({"next": f"{value:.3f}"})
            if self.on_action is not None:
                self.on_action(slider.action, meta_json, slider.rect)
            return

        if self._active_scroll is not None:
            # A real scroll drag happened this gesture — no tap fires,
            # matching NativeCanvasView.kt's own ACTION_UP handling
            # (activeHScroll/activeVScroll just get cleared, nothing
            # else).
            self._active_scroll = None
            self._pending_scroll = None
            return
        self._pending_scroll = None

        # Never became a drag — a plain tap, hit-tested at the RELEASE
        # position (mirrors NativeCanvasView.kt's own handleTap(event),
        # called with the ACTION_UP event's coordinates).
        tap_x, tap_y = self._drag_start[0] + offset_x, self._drag_start[1] + offset_y
        if _RUST_RENDER_ENABLED and self.raw_json is not None:
            hit = self._hit_test_via_rust(tap_x, tap_y)
        else:
            region = self.payload.region_at(tap_x, tap_y)
            hit = (region.action, None, (region.x, region.y, region.x + region.width, region.y + region.height)) if region is not None else None
        if hit is not None and self.on_action is not None:
            self.on_action(hit[0], hit[1], hit[2])

    def _hit_test_via_rust(self, x: float, y: float) -> Optional[tuple[str, Optional[str], tuple[float, float, float, float]]]:
        """Mirrors `_draw_via_rust`'s fallback contract: only falls back
        to `region_at()` if Rust's hit-test call itself couldn't run at
        all (library missing/import error) — a clean "nothing hit" from
        Rust is trusted as-is, not silently re-tried against the Python
        path, which has real, documented behavior gaps of its own
        (reverse hit-region order instead of Android's real forward/
        first-match order, no scroll-offset/`fixed` handling, no nested
        clientPanel/hScroll/vScroll hit-testing at all) that this Rust
        path is specifically meant to fix, not paper back over. Returns
        (action, meta_json, (left, top, right, bottom)) — meta_json is
        the tapped region's own "meta" object (needed for a `toggle:`
        Checkbox/Toggle commit), None on the Python fallback path since
        HitRegion there carries no meta of its own; the rect is what a
        `focus:` action needs to position its text-input overlay.
        """
        try:
            state_json = self._interaction_state_json()
            hit = rust_render.hit_test(self.raw_json, x, y, state_json)
        except rust_render.RustRenderUnavailable as exc:
            print(f"[phpnitro] PHPNITRO_RUST_RENDER=1 but hit-testing is unavailable, using Python: {exc}")
            region = self.payload.region_at(x, y)
            return (region.action, None, (region.x, region.y, region.x + region.width, region.y + region.height)) if region is not None else None
        return (hit.action, hit.meta_json, hit.rect) if hit is not None else None

    def _update_animation_timer(self) -> None:
        """Started/stopped on demand — same idea as
        updateSpinnerAnimator()/updateSkeletonAnimator() on Android, a
        GLib.timeout_add standing in for a ValueAnimator/CADisplayLink.
        ~60fps (16ms); GLib itself coalesces against the real display
        refresh, this is just the upper bound.
        """
        should_animate = self.payload is not None and needs_animation(self.payload)
        if should_animate and self._timer_id is None:
            self._timer_id = GLib.timeout_add(16, self._tick)
        elif not should_animate and self._timer_id is not None:
            GLib.source_remove(self._timer_id)
            self._timer_id = None

    def _tick(self) -> bool:
        self.queue_draw()
        return True  # GLib.SOURCE_CONTINUE — keep the timer running.


class ScreenWindow(Gtk.ApplicationWindow):
    """Hosts one PhpNitroCanvasWidget, fetches one screen on load, and
    runs navigation.reduce(_:_:) against its own screen stack on every
    tap — the Linux counterpart of NativeScreenViewController.swift.
    `host`/`port` may point at a PhpProcess this same app started (the
    ":app"-equivalent local mode) or at a remote `phpx serve` (the
    PhpNitro Go-equivalent remote mode) — this class has no idea which,
    same as NativeRenderPocActivity.kt not caring whether its
    `serverHost` extra was "127.0.0.1" or a LAN IP.
    """

    def __init__(self, app: Gtk.Application, host: str, port: int, screen: str = "home"):
        super().__init__(application=app, title="PhpNitro")
        self.set_default_size(390, 844)

        self.host = host
        self.port = port
        self.stack: tuple[str, ...] = (screen,)
        self.field_values: dict[str, str] = {}
        # The last width/height an actual fetch went out for — None
        # before the very first fetch (the only case get_default_size()'s
        # fixed 390x844 CONSTRUCTION default is the right fallback; see
        # _fetch's own docblock). Without this, every navigate:/tab:/
        # back/toggle: fetch after a real resize would silently revert
        # to that fixed default instead of the size the window actually
        # is now — a real bug, not a hypothetical one.
        self._last_known_size: Optional[tuple[int, int]] = None

        self.canvas = PhpNitroCanvasWidget()
        self.canvas.on_action = self._handle_action
        self.canvas.on_resize = self._handle_resize
        self.canvas.on_field_value_changed = self.set_field_value
        self.set_child(self.canvas)

        self._fetch(action=None)

    def set_field_value(self, name: str, value: str) -> None:
        """Called on every keystroke in the active text-input overlay
        (see `PhpNitroCanvasWidget.show_text_input`'s own doc comment) —
        `field_values` is sent on every fetch already (see `_fetch`),
        never conditionally, so there's no separate "commit" step needed
        beyond keeping this dict current.
        """
        self.field_values[name] = value

    def _handle_action(self, action: str, meta_json: Optional[str], rect: tuple[float, float, float, float]) -> None:
        # focus: never reaches navigation.reduce (no fetch at all,
        # entirely client-side — same "not funneled through the generic
        # reducer" treatment clientTab: gets) — matches
        # NativeRenderPocActivity.kt's own onTap(), which branches on
        # "focus:" before any of the actions that DO end in a refetch.
        if action.startswith("focus:"):
            rest = action[len("focus:"):]
            multiline = rest.startswith("multiline:")
            if multiline:
                rest = rest[len("multiline:"):]
            secure = rest.startswith("secure:")
            if secure:
                rest = rest[len("secure:"):]
            field_name = rest
            self.canvas.show_text_input(field_name, self.field_values.get(field_name, ""), rect, multiline, secure)
            return

        # video:play:<url> (VideoPlayer.php) — same "entirely client-side,
        # no fetch at all" treatment as focus: above.
        if action.startswith("video:play:"):
            self.canvas.show_video_overlay(action[len("video:play:"):], rect)
            return

        # map:open:<lat>:<lon>:<zoom> (MapView.php) — same "entirely
        # client-side, no fetch at all" treatment as focus: above.
        # Fallback values mirror NativeRenderPocActivity.kt's own
        # showMapOverlay dispatch exactly (Paris, zoom 14).
        if action.startswith("map:open:"):
            parts = action[len("map:open:"):].split(":")

            def _part(index: int) -> Optional[str]:
                return parts[index] if index < len(parts) else None

            try:
                latitude = float(_part(0)) if _part(0) else 48.8566
            except ValueError:
                latitude = 48.8566
            try:
                longitude = float(_part(1)) if _part(1) else 2.3522
            except ValueError:
                longitude = 2.3522
            try:
                zoom = int(_part(2)) if _part(2) else 14
            except ValueError:
                zoom = 14
            self.canvas.show_map_overlay(latitude, longitude, zoom, rect)
            return

        result = navigation.reduce(action, self.stack, meta_json)
        if isinstance(result, navigation.ClientTabOnly):
            self.canvas.set_client_tab(result.key, result.index)
            return
        if isinstance(result, navigation.FieldUpdate):
            # Checkbox/Toggle/Slider's shared "toggle:" commit
            # destination — a local field_values[name] = value update
            # followed by a same-screen refetch, mirroring
            # NativeRenderPocActivity.kt's own generic "toggle:" handler
            # exactly. field_values is sent on EVERY fetch already (see
            # _fetch), so there's no separate "commit" step beyond
            # keeping this dict current.
            self.field_values[result.key] = result.value
            self._fetch(action=None)
            return

        self.stack = result.stack
        self._fetch(action=result.action)

    def _handle_resize(self, width: int, height: int) -> None:
        """`PhpNitroCanvasWidget.on_resize` — fired whenever a real draw
        reports a live-allocated size the current payload wasn't fetched
        for (first real show, or the user dragging the window's edge).
        Same current screen, no action — just re-lays-out for the new size.
        """
        self._fetch(action=None, width=width, height=height)

    def _fetch(self, action: Optional[str], width: Optional[int] = None, height: Optional[int] = None) -> None:
        screen = self.stack[-1] if self.stack else "home"
        # get_default_size() only ever returns the fixed CONSTRUCTION
        # default (390x844) on this platform, never the window's actual
        # current size — only used as a one-time bootstrap value before
        # ANY real size is known yet (self._last_known_size is still
        # None); every fetch after that (a real resize, or a plain
        # navigate:/tab:/back/toggle: with no explicit size of its own)
        # reuses the last size a fetch actually went out for instead. See
        # PhpNitroCanvasWidget.on_resize's own docblock.
        if width is None or height is None:
            width, height = self._last_known_size or self.get_default_size()
        self._last_known_size = (width, height)
        # Marked BEFORE the async result lands, not after — otherwise
        # every draw frame while this fetch is still in flight would see
        # the same size mismatch and fire another one.
        self.canvas.set_fetched_size(width, height)

        def worker() -> None:
            result = screen_client.fetch_screen(
                self.host, self.port, screen, action=action,
                width=width, height=height, field_values=self.field_values,
            )
            GLib.idle_add(self._apply_result, result)

        threading.Thread(target=worker, daemon=True).start()

    def _apply_result(self, result: screen_client.FetchResult) -> bool:
        if isinstance(result, screen_client.FetchSuccess):
            self.canvas.set_payload(result.payload, raw_json=result.raw_json)
        else:
            # A real error card (see NativeRenderPocActivity.kt's
            # showConnectionError()/showScreenErrorOverlay(), or
            # NativeScreenViewController.swift's own ScreenErrorView) is
            # real, separate follow-up work — same "don't crash on a
            # bad response" contract the rest of this pipeline follows,
            # just not surfaced to the user visually yet on this
            # platform.
            print(f"[phpnitro] fetch failed: {result.kind} — {result.message}")
        return GLib.SOURCE_REMOVE


def run_local(project_dir: Path, screen: str = "home") -> int:
    """The ":app"-equivalent entry point — starts a real PHP subprocess
    against `project_dir` and opens a window pointed at it.
    """
    process = php_process.PhpProcess(project_dir)
    port = process.start()

    app = Gtk.Application(application_id=APP_ID)

    def on_activate(app: Gtk.Application) -> None:
        window = ScreenWindow(app, "127.0.0.1", port, screen=screen)
        window.present()

    def on_shutdown(_app: Gtk.Application) -> None:
        process.stop()

    app.connect("activate", on_activate)
    app.connect("shutdown", on_shutdown)
    return app.run(None)


def run_remote(host: str, port: int, screen: str = "home") -> int:
    """The PhpNitro-Go-equivalent entry point — no local PHP process at
    all, just a client for whatever `phpx serve` is already running at
    host:port. Never bundles a line of any project's PHP, same as
    android/go/ConnectActivity's own renderIntent() and
    ios/Sources/PhpNitroGo's NativeScreenViewController push.
    """
    app = Gtk.Application(application_id=APP_ID)

    def on_activate(app: Gtk.Application) -> None:
        window = ScreenWindow(app, host, port, screen=screen)
        window.present()

    app.connect("activate", on_activate)
    return app.run(None)
