"""The Linux counterpart of ScreenNavigation.swift (iOS) — deliberately
the MINIMAL slice of the action-dispatch `when`/`match` block every
platform's tap handler has: `navigate:`/`tab:`/`back`/`clientTab:`/
`toggle:`, and the plain fallback (any other action refetches the
current screen with it). A pure function of (action, stack, meta_json)
-> result, not a method on the GTK app shell, for the exact same reason
it's a free function on iOS: the actual decision is fully unit-testable
without a window, a network call, or a display server.
"""

from __future__ import annotations

import json
from dataclasses import dataclass
from typing import Optional, Union


@dataclass(frozen=True)
class ClientTabOnly:
    """`clientTab:key:index` — a ClientTabs tab switch, entirely local
    (see canvas.RenderState.client_tab_state), no fetch at all.
    """

    key: str
    index: int


@dataclass(frozen=True)
class FieldUpdate:
    """`toggle:name` (Checkbox/Toggle/Slider's shared commit action, see
    packages/ui/src/Native/Checkbox.php/Slider.php) — a local
    field_values[name] = value update followed by a same-screen refetch
    with no action param, mirroring NativeRenderPocActivity.kt's own
    generic "toggle:" handler exactly. Only ever produced when the
    caller passes a real meta_json to reduce(...) containing a "next"
    key — a caller that never passes one keeps falling through to the
    generic Fetch case below, unchanged. Mirrors FieldUpdate on
    iOS/Windows exactly.
    """

    key: str
    value: str


@dataclass(frozen=True)
class Fetch:
    """Everything else ends in a fetch — `stack` is what the screen
    stack should become BEFORE fetching (already pushed/popped/reset
    for navigate:/tab:/back), `action` is what to pass to
    ScreenClient.fetch_screen(...) (None for navigate:/tab:/back, which
    always fetch fresh; the original action string for the plain
    fallback case).
    """

    stack: tuple[str, ...]
    action: Optional[str]


NavigationResult = Union[ClientTabOnly, FieldUpdate, Fetch]


def _next_value(meta_json: str) -> Optional[str]:
    """Extracts meta.next from a hit region's meta JSON (e.g.
    {"next":"1"} — see Checkbox.php's own docblock) as a string, same
    loose tolerance NativeRenderPocActivity.kt's own reader has: a
    present-but-empty next still counts (an unchecked Checkbox's own
    next IS ""), only a missing/malformed meta blob returns None.
    """
    try:
        data = json.loads(meta_json)
    except (json.JSONDecodeError, TypeError):
        return None
    if not isinstance(data, dict) or "next" not in data:
        return None
    next_value = data["next"]
    return next_value if isinstance(next_value, str) else json.dumps(next_value)


def reduce(action: str, stack: tuple[str, ...], meta_json: Optional[str] = None) -> NavigationResult:
    if action.startswith("clientTab:"):
        rest = action[len("clientTab:"):]
        key, sep, index_str = rest.partition(":")
        if sep and index_str.lstrip("-").isdigit():
            return ClientTabOnly(key=key, index=int(index_str))
        return Fetch(stack=stack, action=None)

    if action.startswith("toggle:") and meta_json is not None:
        next_value = _next_value(meta_json)
        if next_value is not None:
            return FieldUpdate(key=action[len("toggle:"):], value=next_value)

    if action.startswith("navigate:"):
        return Fetch(stack=stack + (action[len("navigate:"):],), action=None)

    # A BottomNavigation tab switch — resets the whole stack to that one
    # screen instead of pushing, so hopping between tabs repeatedly
    # doesn't grow an ever-longer back stack the way drilling into a
    # real detail screen should.
    if action.startswith("tab:"):
        return Fetch(stack=(action[len("tab:"):],), action=None)

    if action == "back":
        new_stack = stack[:-1] if len(stack) > 1 else stack
        return Fetch(stack=new_stack, action=None)

    return Fetch(stack=stack, action=action)
