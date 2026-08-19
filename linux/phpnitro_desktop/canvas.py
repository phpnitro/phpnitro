"""Replays a decoded DrawCommandPayload with Cairo/Pango — the Linux
counterpart of NativeCanvasView.kt (Android, android.graphics.Canvas)
and NativeCanvasView.swift (iOS, Core Graphics). Cairo is already the
real, OS-level 2D engine GTK itself draws with (GtkDrawingArea's own
"draw" signal hands you a live Cairo context) — same "the OS already
gives you a real native 2D engine, don't reinvent one" reasoning
documented in docs/proposals/moteur-rendu-natif.md for Skia/Core
Graphics, just for Linux's own native stack instead.

`render_payload(ctx, payload, ...)` is a PURE function of a
`cairo.Context` — it does not know or care whether that context came
from a live GtkDrawingArea or an offscreen `cairo.ImageSurface`, which
is what makes this independently testable without a display server
(see tests/test_canvas.py, which renders to a real PNG and inspects
pixels — the closest thing to a screenshot achievable in an environment
with no X11/Wayland/Broadway available at all).
"""

from __future__ import annotations

import math
import time
from dataclasses import dataclass
from typing import Optional

import cairo
import gi

gi.require_version("Pango", "1.0")
gi.require_version("PangoCairo", "1.0")
gi.require_version("Gdk", "4.0")
from gi.repository import Gdk, Pango, PangoCairo  # noqa: E402

from . import fonts, image_loader  # noqa: E402
from .draw_command import (  # noqa: E402
    ArcCommand, CircleCommand, ClientPanelCommand, DrawCommand, DrawCommandPayload,
    HScrollCommand, IconCommand, ImageCommand, LineCommand, RectCommand, SkeletonCommand,
    SliderCommand, SpinnerCommand, TextCommand, UnknownCommand, VScrollCommand,
)


def parse_color(hex_value: Optional[str]) -> Optional[tuple[float, float, float, float]]:
    """Parses "#RRGGBB" or "#RRGGBBAA" — the exact two shapes every
    Engine\\Color::toHex()/Tokens color constant on the PHP side
    produces. Returns None (never raises) on anything else — same
    "malformed input degrades gracefully" contract UIColor(hex:) (iOS)
    and Color.parseColor (Android, wrapped in a try/catch there) follow.
    """
    if hex_value is None:
        return None
    value = hex_value.lstrip("#")
    if len(value) not in (6, 8):
        return None
    try:
        parts = bytes.fromhex(value)
    except ValueError:
        return None

    r, g, b = parts[0] / 255, parts[1] / 255, parts[2] / 255
    a = parts[3] / 255 if len(parts) == 4 else 1.0
    return (r, g, b, a)


def _rounded_rect_path(ctx, x: float, y: float, w: float, h: float, r: float) -> None:
    r = max(0.0, min(r, w / 2, h / 2))
    if r == 0:
        ctx.rectangle(x, y, w, h)
        return

    ctx.new_sub_path()
    ctx.arc(x + w - r, y + r, r, -math.pi / 2, 0)
    ctx.arc(x + w - r, y + h - r, r, 0, math.pi / 2)
    ctx.arc(x + r, y + h - r, r, math.pi / 2, math.pi)
    ctx.arc(x + r, y + r, r, math.pi, 3 * math.pi / 2)
    ctx.close_path()


@dataclass
class RenderState:
    """Threads through a render pass — mirrors NativeCanvasView's own
    clientTabState/hScrollOffsets/vScrollOffsets, plus a monotonic clock
    sample for spinner/skeleton animation so every command drawn in the
    same frame agrees on "now" instead of drifting mid-frame.
    """

    now: float
    client_tab_state: dict[str, int]
    icon_cache_warmed: bool = False


def needs_animation(payload: DrawCommandPayload) -> bool:
    """Whether a redraw timer should be running at all — same
    started-only-when-needed idea as NativeCanvasView.kt's
    updateSpinnerAnimator()/updateSkeletonAnimator(), checked
    recursively since a spinner/skeleton could be nested inside a
    clientPanel/hScroll/vScroll.
    """

    def command_needs_it(command: DrawCommand) -> bool:
        if isinstance(command, (SpinnerCommand, SkeletonCommand)):
            return True
        if isinstance(command, (ClientPanelCommand, HScrollCommand, VScrollCommand)):
            return any(command_needs_it(c) for c in command.commands)
        return False

    return any(command_needs_it(c) for c in payload.commands)


def render_payload(ctx, payload: DrawCommandPayload, state: Optional[RenderState] = None) -> None:
    if state is None:
        state = RenderState(now=time.monotonic(), client_tab_state={})
    fonts.register_bundled_fonts()
    for command in payload.commands:
        _render_command(ctx, command, state)


def _render_command(ctx, command: DrawCommand, state: RenderState) -> None:
    if isinstance(command, RectCommand):
        _draw_rect(ctx, command)
    elif isinstance(command, TextCommand):
        _draw_text(ctx, command)
    elif isinstance(command, IconCommand):
        _draw_icon(ctx, command)
    elif isinstance(command, CircleCommand):
        _draw_circle(ctx, command)
    elif isinstance(command, LineCommand):
        _draw_line(ctx, command)
    elif isinstance(command, ArcCommand):
        _draw_arc(ctx, command)
    elif isinstance(command, ImageCommand):
        _draw_image(ctx, command)
    elif isinstance(command, SpinnerCommand):
        _draw_spinner(ctx, command, state.now)
    elif isinstance(command, SkeletonCommand):
        _draw_skeleton(ctx, command, state.now)
    elif isinstance(command, ClientPanelCommand):
        _draw_client_panel(ctx, command, state)
    elif isinstance(command, HScrollCommand):
        _draw_hscroll(ctx, command, state)
    elif isinstance(command, VScrollCommand):
        _draw_vscroll(ctx, command, state)
    elif isinstance(command, SliderCommand):
        _draw_slider(ctx, command)
    elif isinstance(command, UnknownCommand):
        pass  # Same "an unhandled command is a no-op, not a crash" contract every platform follows.


def _draw_rect(ctx, c: RectCommand) -> None:
    ctx.save()
    radius = c.radius or 0
    _rounded_rect_path(ctx, c.x, c.y, c.width, c.height, radius)
    fill = parse_color(c.color)
    if fill:
        ctx.set_source_rgba(*fill)
        ctx.fill_preserve()
    border = parse_color(c.border_color)
    if border and (c.border_width or 0) > 0:
        ctx.set_source_rgba(*border)
        ctx.set_line_width(c.border_width)
        ctx.stroke()
    else:
        ctx.new_path()
    ctx.restore()


def _pango_layout_for(ctx, family: str, size_px: float, bold: bool = False):
    layout = PangoCairo.create_layout(ctx)
    desc = Pango.FontDescription()
    desc.set_family(family)
    desc.set_absolute_size(size_px * Pango.SCALE)
    if bold:
        desc.set_weight(Pango.Weight.BOLD)
    layout.set_font_description(desc)
    return layout


def _draw_text(ctx, c: TextCommand) -> None:
    color = parse_color(c.color) or (0, 0, 0, 1)
    size = c.size or 16
    # Falls back to the bundled Roboto (see fonts.py), not the host's
    # generic "sans-serif" — packages/ui/src/Native/TextMetrics.php's
    # width table was measured against real Roboto, so painting body
    # text in a different font here would silently disagree with the
    # box sizes/wrap points PHP already computed.
    layout = _pango_layout_for(ctx, c.font_family or fonts.ROBOTO_FAMILY, size, bold=bool(c.bold))
    layout.set_text(c.text, -1)

    # Canvas::text()'s (x, y) is the drawText BASELINE (same convention
    # android.graphics.Canvas.drawText() uses) — Pango's own show_layout
    # anchors at the top-left of the layout box instead, so shift up by
    # the layout's own baseline offset to land on the same visual
    # baseline PHP assumed, same idea as NativeCanvasView.swift's own
    # `command.y - font.ascender`.
    baseline = layout.get_baseline() / Pango.SCALE
    ctx.save()
    ctx.set_source_rgba(*color)
    ctx.move_to(c.x, c.y - baseline)
    PangoCairo.show_layout(ctx, layout)
    ctx.restore()


def _draw_icon(ctx, c: IconCommand) -> None:
    family = fonts.FONT_AWESOME_FAMILY if c.font == "fontawesome" else fonts.MATERIAL_ICONS_FAMILY
    color = parse_color(c.color) or (0.07, 0.09, 0.15, 1)
    try:
        glyph = chr(c.codepoint)
    except (ValueError, OverflowError):
        return

    layout = _pango_layout_for(ctx, family, c.size)
    layout.set_text(glyph, -1)
    ink, logical = layout.get_pixel_extents()

    # Same "measure the real glyph and center it" approach
    # NativeCanvasView.swift's drawIconCommand() takes, rather than a
    # fixed baseline-offset percentage tuned for one specific renderer's
    # font metrics (NativeCanvasView.kt's own 86%-of-size magic number).
    origin_x = c.x + (c.size - logical.width) / 2 - logical.x
    origin_y = c.y + (c.size - logical.height) / 2 - logical.y

    ctx.save()
    ctx.set_source_rgba(*color)
    ctx.move_to(origin_x, origin_y)
    PangoCairo.show_layout(ctx, layout)
    ctx.restore()


def _draw_circle(ctx, c: CircleCommand) -> None:
    ctx.save()
    ctx.new_sub_path()
    ctx.arc(c.cx, c.cy, c.radius, 0, 2 * math.pi)
    fill = parse_color(c.color)
    if fill:
        ctx.set_source_rgba(*fill)
        ctx.fill_preserve()
    border = parse_color(c.border_color)
    if border and (c.border_width or 0) > 0:
        ctx.set_source_rgba(*border)
        ctx.set_line_width(c.border_width)
        ctx.stroke()
    else:
        ctx.new_path()
    ctx.restore()


def _draw_line(ctx, c: LineCommand) -> None:
    color = parse_color(c.color)
    if not color:
        return
    ctx.save()
    ctx.set_source_rgba(*color)
    ctx.set_line_width(c.width or 1)
    ctx.move_to(c.x1, c.y1)
    ctx.line_to(c.x2, c.y2)
    ctx.stroke()
    ctx.restore()


def _draw_arc(ctx, c: ArcCommand) -> None:
    color = parse_color(c.color)
    if not color:
        return
    # Canvas::arc()'s convention (documented on the PHP side, and
    # already ported this same way to NativeCanvasView.swift) is
    # Android's: 0deg = 3 o'clock, sweeping CLOCKWISE in a normal
    # (Y-down) screen coordinate space — which is exactly Cairo's own
    # `arc()` (as opposed to `arc_negative()`) sense too, since Cairo
    # also uses a Y-down surface by default. No sign-flip needed here,
    # unlike Core Graphics' own flipped default coordinate space on iOS.
    start = math.radians(c.start_degrees)
    end = math.radians(c.start_degrees + c.sweep_degrees)
    ctx.save()
    ctx.set_source_rgba(*color)
    ctx.set_line_width(c.stroke_width)
    ctx.new_sub_path()
    ctx.arc(c.cx, c.cy, c.radius, start, end)
    ctx.stroke()
    ctx.restore()


def _draw_spinner(ctx, c: SpinnerCommand, now: float) -> None:
    track = parse_color(c.track_color)
    color = parse_color(c.color)
    if not track or not color:
        return

    center = c.size / 2
    radius = center - c.stroke_width / 2
    cx, cy = c.x + center, c.y + center

    ctx.save()
    ctx.set_line_width(c.stroke_width)
    ctx.new_sub_path()
    ctx.arc(cx, cy, radius, 0, 2 * math.pi)
    ctx.set_source_rgba(*track)
    ctx.stroke()

    period_s = 1.1
    rotation = ((now % period_s) / period_s) * 2 * math.pi
    sweep = math.radians(110)
    ctx.set_line_cap(1)  # cairo.LINE_CAP_ROUND
    ctx.new_sub_path()
    ctx.arc(cx, cy, radius, rotation, rotation + sweep)
    ctx.set_source_rgba(*color)
    ctx.stroke()
    ctx.restore()


def _draw_skeleton(ctx, c: SkeletonCommand, now: float) -> None:
    base = parse_color(c.color)
    if not base:
        return

    ctx.save()
    _rounded_rect_path(ctx, c.x, c.y, c.width, c.height, c.radius)
    ctx.set_source_rgba(*base)
    ctx.fill_preserve()

    r, g, b, a = base
    highlight = (r + (1 - r) * 0.5, g + (1 - g) * 0.5, b + (1 - b) * 0.5)
    sweep_width = max(c.width * 0.6, 1)
    period_s = 1.3
    phase = (now % period_s) / period_s
    sweep_x = c.x - sweep_width + (c.width + sweep_width) * phase

    ctx.clip()
    gradient = cairo.LinearGradient(sweep_x, c.y, sweep_x + sweep_width, c.y)
    gradient.add_color_stop_rgba(0, *highlight, 0)
    gradient.add_color_stop_rgba(0.5, *highlight, 0.8)
    gradient.add_color_stop_rgba(1, *highlight, 0)
    ctx.set_source(gradient)
    ctx.rectangle(c.x, c.y, c.width, c.height)
    ctx.fill()
    ctx.restore()


def _draw_image(ctx, c: ImageCommand) -> None:
    pixbuf = image_loader.get(c.url)
    if pixbuf is None:
        image_loader.load(c.url)
        return

    ctx.save()
    radius = c.radius or 0
    if radius > 0:
        _rounded_rect_path(ctx, c.x, c.y, c.width, c.height, radius)
        ctx.clip()

    scale_x = c.width / pixbuf.get_width()
    scale_y = c.height / pixbuf.get_height()
    ctx.translate(c.x, c.y)
    ctx.scale(scale_x, scale_y)
    Gdk.cairo_set_source_pixbuf(ctx, pixbuf, 0, 0)
    ctx.paint()
    ctx.restore()


def _draw_client_panel(ctx, c: ClientPanelCommand, state: RenderState) -> None:
    if c.key not in state.client_tab_state and c.initially_active:
        state.client_tab_state[c.key] = c.index
    if state.client_tab_state.get(c.key) != c.index:
        return

    ctx.save()
    ctx.translate(c.x, c.y)
    for nested in c.commands:
        _render_command(ctx, nested, state)
    ctx.restore()


def _draw_hscroll(ctx, c: HScrollCommand, state: RenderState) -> None:
    ctx.save()
    ctx.rectangle(c.x, c.y, c.width, c.height)
    ctx.clip()
    ctx.translate(c.x, c.y)
    for nested in c.commands:
        _render_command(ctx, nested, state)
    ctx.restore()


def _draw_vscroll(ctx, c: VScrollCommand, state: RenderState) -> None:
    ctx.save()
    ctx.rectangle(c.x, c.y, c.width, c.height)
    ctx.clip()
    ctx.translate(c.x, c.y)
    for nested in c.commands:
        _render_command(ctx, nested, state)
    ctx.restore()


def _draw_slider(ctx, c: SliderCommand) -> None:
    track = parse_color(c.track_color)
    active = parse_color(c.active_color)
    thumb = parse_color(c.thumb_color)
    if not track or not active or not thumb:
        return

    track_y = c.y + (c.height - c.track_height) / 2
    thumb_cx = c.x + c.thumb_size / 2 + (c.width - c.thumb_size) * min(max(c.value, 0), 1)
    thumb_cy = c.y + c.height / 2

    ctx.save()
    _rounded_rect_path(ctx, c.x, track_y, c.width, c.track_height, c.track_height / 2)
    ctx.set_source_rgba(*track)
    ctx.fill()

    active_width = max(thumb_cx - c.x, 0)
    _rounded_rect_path(ctx, c.x, track_y, active_width, c.track_height, c.track_height / 2)
    ctx.set_source_rgba(*active)
    ctx.fill()

    ctx.new_sub_path()
    ctx.arc(thumb_cx, thumb_cy, c.thumb_size / 2, 0, 2 * math.pi)
    ctx.set_source_rgba(*thumb)
    ctx.fill_preserve()
    ctx.set_source_rgba(*active)
    ctx.set_line_width(1.5)
    ctx.stroke()
    ctx.restore()
