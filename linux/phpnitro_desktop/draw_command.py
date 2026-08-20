"""Decodes the same JSON `Engine\\Native\\Canvas::toJson()` already
produces for Android (`NativeCanvasView.kt`) and iOS (`DrawCommand.swift`)
— this is the THIRD independent consumer of the identical wire protocol,
proving it was never platform-specific. Only the "phase 0" primitives
plus the composite commands iOS also decodes (image/spinner/skeleton/
clientPanel/hScroll/vScroll/slider) are modeled here; an unrecognized
`type` becomes an :class:`Unknown` instead of raising, the same
"PHP decides, an unhandled command is a silent no-op" contract every
other renderer in this codebase already follows.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Optional, Union


@dataclass(frozen=True)
class RectCommand:
    x: float
    y: float
    width: float
    height: float
    color: Optional[str] = None
    radius: Optional[float] = None
    border_color: Optional[str] = None
    border_width: Optional[float] = None


@dataclass(frozen=True)
class TextCommand:
    x: float
    y: float
    text: str
    color: Optional[str] = None
    size: Optional[float] = None
    bold: Optional[bool] = None
    letter_spacing: Optional[float] = None
    font_family: Optional[str] = None


@dataclass(frozen=True)
class IconCommand:
    x: float
    y: float
    size: float
    codepoint: int
    color: Optional[str] = None
    font: Optional[str] = None


@dataclass(frozen=True)
class CircleCommand:
    cx: float
    cy: float
    radius: float
    color: Optional[str] = None
    border_color: Optional[str] = None
    border_width: Optional[float] = None


@dataclass(frozen=True)
class LineCommand:
    x1: float
    y1: float
    x2: float
    y2: float
    color: str
    width: Optional[float] = None


@dataclass(frozen=True)
class ArcCommand:
    cx: float
    cy: float
    radius: float
    start_degrees: float
    sweep_degrees: float
    color: str
    stroke_width: float


@dataclass(frozen=True)
class ImageCommand:
    x: float
    y: float
    width: float
    height: float
    url: str
    radius: Optional[float] = None


@dataclass(frozen=True)
class SpinnerCommand:
    x: float
    y: float
    size: float
    color: str
    track_color: str
    stroke_width: float


@dataclass(frozen=True)
class SkeletonCommand:
    x: float
    y: float
    width: float
    height: float
    color: str
    radius: float


@dataclass(frozen=True)
class HitRegion:
    x: float
    y: float
    width: float
    height: float
    action: str


@dataclass(frozen=True)
class ClientPanelCommand:
    key: str
    index: int
    initially_active: bool
    x: float
    y: float
    commands: tuple["DrawCommand", ...]
    hit_regions: tuple[HitRegion, ...]


@dataclass(frozen=True)
class HScrollCommand:
    key: str
    x: float
    y: float
    width: float
    height: float
    content_width: float
    commands: tuple["DrawCommand", ...]
    hit_regions: tuple[HitRegion, ...]


@dataclass(frozen=True)
class VScrollCommand:
    key: str
    x: float
    y: float
    width: float
    height: float
    content_height: float
    commands: tuple["DrawCommand", ...]
    hit_regions: tuple[HitRegion, ...]


@dataclass(frozen=True)
class SliderCommand:
    key: str
    x: float
    y: float
    width: float
    height: float
    track_height: float
    thumb_size: float
    value: float
    track_color: str
    active_color: str
    thumb_color: str


@dataclass(frozen=True)
class UnknownCommand:
    type: str


DrawCommand = Union[
    RectCommand, TextCommand, IconCommand, CircleCommand, LineCommand, ArcCommand,
    ImageCommand, SpinnerCommand, SkeletonCommand, ClientPanelCommand,
    HScrollCommand, VScrollCommand, SliderCommand, UnknownCommand,
]


def _hit_region(data: dict) -> HitRegion:
    return HitRegion(x=data["x"], y=data["y"], width=data["width"], height=data["height"], action=data["action"])


def decode_command(data: dict) -> DrawCommand:
    kind = data.get("type")

    if kind == "rect":
        return RectCommand(
            x=data["x"], y=data["y"], width=data["width"], height=data["height"],
            color=data.get("color"), radius=data.get("radius"),
            border_color=data.get("borderColor"), border_width=data.get("borderWidth"),
        )
    if kind == "text":
        return TextCommand(
            x=data["x"], y=data["y"], text=data["text"], color=data.get("color"),
            size=data.get("size"), bold=data.get("bold"),
            letter_spacing=data.get("letterSpacing"), font_family=data.get("fontFamily"),
        )
    if kind == "icon":
        return IconCommand(
            x=data["x"], y=data["y"], size=data["size"], codepoint=data["codepoint"],
            color=data.get("color"), font=data.get("font"),
        )
    if kind == "circle":
        return CircleCommand(
            cx=data["cx"], cy=data["cy"], radius=data["radius"], color=data.get("color"),
            border_color=data.get("borderColor"), border_width=data.get("borderWidth"),
        )
    if kind == "line":
        return LineCommand(x1=data["x1"], y1=data["y1"], x2=data["x2"], y2=data["y2"], color=data["color"], width=data.get("width"))
    if kind == "arc":
        return ArcCommand(
            cx=data["cx"], cy=data["cy"], radius=data["radius"],
            start_degrees=data["startDegrees"], sweep_degrees=data["sweepDegrees"],
            color=data["color"], stroke_width=data["strokeWidth"],
        )
    if kind == "image":
        return ImageCommand(x=data["x"], y=data["y"], width=data["width"], height=data["height"], url=data["url"], radius=data.get("radius"))
    if kind == "spinner":
        return SpinnerCommand(x=data["x"], y=data["y"], size=data["size"], color=data["color"], track_color=data["trackColor"], stroke_width=data["strokeWidth"])
    if kind == "skeleton":
        return SkeletonCommand(x=data["x"], y=data["y"], width=data["width"], height=data["height"], color=data["color"], radius=data["radius"])
    if kind == "clientPanel":
        return ClientPanelCommand(
            key=data["key"], index=data["index"], initially_active=data["initiallyActive"],
            x=data["x"], y=data["y"],
            commands=tuple(decode_command(c) for c in data["commands"]),
            hit_regions=tuple(_hit_region(h) for h in data["hitRegions"]),
        )
    if kind == "hScroll":
        return HScrollCommand(
            key=data["key"], x=data["x"], y=data["y"], width=data["width"], height=data["height"],
            content_width=data["contentWidth"],
            commands=tuple(decode_command(c) for c in data["commands"]),
            hit_regions=tuple(_hit_region(h) for h in data["hitRegions"]),
        )
    if kind == "vScroll":
        return VScrollCommand(
            key=data["key"], x=data["x"], y=data["y"], width=data["width"], height=data["height"],
            content_height=data["contentHeight"],
            commands=tuple(decode_command(c) for c in data["commands"]),
            hit_regions=tuple(_hit_region(h) for h in data["hitRegions"]),
        )
    if kind == "slider":
        return SliderCommand(
            key=data["key"], x=data["x"], y=data["y"], width=data["width"], height=data["height"],
            track_height=data["trackHeight"], thumb_size=data["thumbSize"], value=data["value"],
            track_color=data["trackColor"], active_color=data["activeColor"], thumb_color=data["thumbColor"],
        )

    return UnknownCommand(type=str(kind))


@dataclass(frozen=True)
class DrawCommandPayload:
    """The envelope `Canvas::toJson()` wraps every render in. `hit_regions`
    is always present (possibly empty), never omitted — mirrors
    DrawCommandPayload on iOS / the JSON parsing in NativeCanvasView.kt.
    """

    commands: tuple[DrawCommand, ...]
    hit_regions: tuple[HitRegion, ...]
    content_height: float

    def action_at(self, x: float, y: float) -> Optional[str]:
        """Which hitRegion (if any) a click at (x, y) should fire —
        checked in REVERSE declaration order, since a later region was
        painted later (visually on top). Mirrors
        DrawCommandPayload.action(at:) on iOS exactly.
        """
        region = self.region_at(x, y)
        return region.action if region is not None else None

    def region_at(self, x: float, y: float) -> Optional[HitRegion]:
        """Same matching order/logic as `action_at` above, but returns the
        whole matched `HitRegion` — a `focus:` action needs its rect to
        position a text-input overlay (see `app.py`'s own `show_text_input`).
        """
        for region in reversed(self.hit_regions):
            if region.x <= x <= region.x + region.width and region.y <= y <= region.y + region.height:
                return region
        return None


def decode_payload(data: dict) -> DrawCommandPayload:
    return DrawCommandPayload(
        commands=tuple(decode_command(c) for c in data["commands"]),
        hit_regions=tuple(_hit_region(h) for h in data["hitRegions"]),
        content_height=data["contentHeight"],
    )
