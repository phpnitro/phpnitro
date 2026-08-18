"""The Linux counterpart of ScreenNavigation.swift (iOS) — deliberately
the MINIMAL slice of the action-dispatch `when`/`match` block every
platform's tap handler has: `navigate:`/`tab:`/`back`/`clientTab:`, and
the plain fallback (any other action refetches the current screen with
it). A pure function of (action, stack) -> result, not a method on the
GTK app shell, for the exact same reason it's a free function on iOS:
the actual decision is fully unit-testable without a window, a network
call, or a display server.
"""

from __future__ import annotations

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


NavigationResult = Union[ClientTabOnly, Fetch]


def reduce(action: str, stack: tuple[str, ...]) -> NavigationResult:
    if action.startswith("clientTab:"):
        rest = action[len("clientTab:"):]
        key, sep, index_str = rest.partition(":")
        if sep and index_str.lstrip("-").isdigit():
            return ClientTabOnly(key=key, index=int(index_str))
        return Fetch(stack=stack, action=None)

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
