"""The Linux counterpart of ScreenClient.swift (iOS) — deliberately the
MINIMAL slice of fetchDrawCommands()'s (NativeRenderPocActivity.kt)
contract: fetch one screen, optionally with a tap action, get back a
DrawCommandPayload. Everything else fetchDrawCommands() also does
(screen-stack push/pop already lives in navigation.py, not here;
lastHash short-circuiting, dark/locale/online params, scroll-position
prefetch hints, polling for Async/Canvas::pollAgain(), confetti/
snackbar/redirect side-channels) is real, separate follow-up work — see
this project's own ios/README.md for the same scoping on the other
platform this was ported from.
"""

from __future__ import annotations

import json
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Optional, Union

from .draw_command import DrawCommandPayload, decode_payload


@dataclass(frozen=True)
class FetchError:
    kind: str  # "network" | "server" | "decoding"
    message: str
    status: Optional[int] = None


@dataclass(frozen=True)
class FetchSuccess:
    payload: DrawCommandPayload
    # The exact bytes the server returned, decoded as text — kept
    # alongside the already-decoded `payload` so a Rust-render path
    # (see rust_render.py) can hand this straight to
    # phpnitro_render_frame() without re-serializing `payload` back into
    # JSON (DrawCommandPayload has no such method, and reconstructing
    # JSON from already-decoded dataclasses would risk subtly diverging
    # from what the server actually sent).
    raw_json: str


FetchResult = Union[FetchSuccess, FetchError]


def build_url(
    host: str,
    port: int,
    screen: str,
    action: Optional[str] = None,
    width: float = 390,
    height: float = 844,
    field_values: Optional[dict[str, str]] = None,
) -> str:
    """Mirrors fetchDrawCommands()'s own "/native/layout-demo?width=...
    &height=...&screen=...&action=...&<field>=<value>..." URL. Every
    param this minimal client doesn't send yet has a server-side default
    (see public/index.php's own `$_GET[...] ?? ...` fallbacks), so
    omitting them is a real degradation (no dark mode, no i18n, always
    the full 'fr'/light-mode render) rather than a broken request.
    `field_values` is always sent when non-empty — unlike Android's
    explicit `includeFields` toggle, sending it costs nothing when
    there's nothing to send.
    """
    params: list[tuple[str, str]] = [
        ("screen", screen),
        ("width", str(width)),
        ("height", str(height)),
    ]
    if action is not None:
        params.append(("action", action))
    # Sorted by name — an arbitrary but STABLE order, so the same
    # field_values always produce the same URL (matters for tests and
    # for anything that might cache/log/compare these URLs later).
    for name in sorted(field_values or {}):
        params.append((name, field_values[name]))

    query = urllib.parse.urlencode(params)
    return f"http://{host}:{port}/native/layout-demo?{query}"


def fetch_screen(
    host: str,
    port: int,
    screen: str,
    action: Optional[str] = None,
    width: float = 390,
    height: float = 844,
    field_values: Optional[dict[str, str]] = None,
    timeout: float = 8,
) -> FetchResult:
    url = build_url(host, port, screen, action, width, height, field_values)

    try:
        with urllib.request.urlopen(url, timeout=timeout) as response:
            body = response.read()
            status = response.status
    except urllib.error.HTTPError as error:
        status = error.code
        body = error.read()
        message = _server_error_message(body) or f"HTTP {status}"
        return FetchError(kind="server", message=message, status=status)
    except urllib.error.URLError as error:
        return FetchError(kind="network", message=str(error.reason))
    except OSError as error:
        return FetchError(kind="network", message=str(error))

    if not (200 <= status < 300):
        message = _server_error_message(body) or f"HTTP {status}"
        return FetchError(kind="server", message=message, status=status)

    try:
        data = json.loads(body)
        payload = decode_payload(data)
    except (json.JSONDecodeError, KeyError, TypeError) as error:
        return FetchError(kind="decoding", message=str(error))

    return FetchSuccess(payload=payload, raw_json=body.decode("utf-8"))


def _server_error_message(body: bytes) -> Optional[str]:
    """Body shape of public/index.php's own `{"error":{"class":...,
    "message":...}}` — set_exception_handler()'s payload for the
    `/native/layout-demo` route.
    """
    try:
        data = json.loads(body)
        return data["error"]["message"]
    except (json.JSONDecodeError, KeyError, TypeError):
        return None
