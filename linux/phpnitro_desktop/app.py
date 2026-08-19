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

import os
import threading
import time
from pathlib import Path
from typing import Optional

import cairo
import gi

gi.require_version("Gtk", "4.0")
from gi.repository import GLib, Gtk  # noqa: E402

from . import image_loader, navigation, php_process, rust_render, screen_client  # noqa: E402
from .canvas import RenderState, needs_animation, render_payload  # noqa: E402
from .draw_command import DrawCommandPayload  # noqa: E402

APP_ID = "com.phpnitro.desktop"

# The shared Rust engine (see rust/phpnitro-render/README.md) is now the
# DEFAULT render path — real-machine-tested against a genuinely blank
# phpx-new project (Cairo and Rust confirmed pixel-identical). Set
# PHPNITRO_RUST_RENDER=0 to fall back to the original Cairo path (kept
# fully intact, never removed); either way, a Rust failure (library
# missing, a frame that fails to render) still falls back to Cairo
# automatically with a printed reason, never a hard crash.
_RUST_RENDER_ENABLED = os.environ.get("PHPNITRO_RUST_RENDER", "1") != "0"


class PhpNitroCanvasWidget(Gtk.DrawingArea):
    """The Linux counterpart of NativeCanvasView.kt/NativeCanvasView.swift
    — a widget that only knows how to replay whatever DrawCommandPayload
    it was last given, and report which hitRegion a click landed in. It
    fetches nothing itself, same separation of concerns the other two
    platforms' own canvas views keep from their respective fetch loops.
    """

    def __init__(self):
        super().__init__()
        self.payload: Optional[DrawCommandPayload] = None
        self.raw_json: Optional[str] = None
        self.client_tab_state: dict[str, int] = {}
        self.on_action = None  # Optional[Callable[[str], None]]
        self._timer_id: Optional[int] = None
        self._rust_renderer: Optional[rust_render.RustRenderer] = None
        self._rust_render_start = time.monotonic()

        self.set_draw_func(self._on_draw)

        click = Gtk.GestureClick.new()
        click.connect("pressed", self._on_click)
        self.add_controller(click)

        image_loader.on_image_loaded = self.queue_draw

    def set_payload(self, payload: DrawCommandPayload, raw_json: Optional[str] = None) -> None:
        self.payload = payload
        self.raw_json = raw_json
        self._update_animation_timer()
        self.queue_draw()

    def set_client_tab(self, key: str, index: int) -> None:
        self.client_tab_state[key] = index
        self.queue_draw()

    def _on_draw(self, _area, ctx, width, height) -> None:
        # Scaffold/Container only paint a background rect when one is
        # explicitly given (see Tokens::surface(), #FFFFFF by default) —
        # most screens omit it, relying on the host surface underneath.
        # Android gets this for free from its Activity theme; GTK4 has no
        # such default, so without this the user's dark system theme shows
        # through every unpainted pixel.
        ctx.set_source_rgb(1, 1, 1)
        ctx.paint()
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
        frame = self._rust_renderer.render_frame(self.raw_json, width, height, elapsed_ms)
        if frame is None:
            print(f"[phpnitro] Rust render_frame failed, using Cairo for this frame: {rust_render.last_error()}")
            return False

        bgra = rust_render.to_cairo_bgra(frame)
        surface = cairo.ImageSurface.create_for_data(bgra, cairo.FORMAT_ARGB32, frame.width, frame.height, frame.stride)
        ctx.set_source_surface(surface, 0, 0)
        ctx.paint()
        return True

    def _on_click(self, _gesture, _n_press, x: float, y: float) -> None:
        if self.payload is None:
            return
        if _RUST_RENDER_ENABLED and self.raw_json is not None:
            action = self._hit_test_via_rust(x, y)
        else:
            action = self.payload.action_at(x, y)
        if action is not None and self.on_action is not None:
            self.on_action(action)

    def _hit_test_via_rust(self, x: float, y: float) -> Optional[str]:
        """Mirrors `_draw_via_rust`'s fallback contract: only falls back
        to `action_at()` if Rust's hit-test call itself couldn't run at
        all (library missing/import error) — a clean "nothing hit" from
        Rust is trusted as-is, not silently re-tried against the Python
        path, which has real, documented behavior gaps of its own
        (reverse hit-region order instead of Android's real forward/
        first-match order, no scroll-offset/`fixed` handling, no nested
        clientPanel/hScroll/vScroll hit-testing at all) that this Rust
        path is specifically meant to fix, not paper back over.
        """
        try:
            import json

            state_json = json.dumps({"activePanel": self.client_tab_state})
            hit = rust_render.hit_test(self.raw_json, x, y, state_json)
        except rust_render.RustRenderUnavailable as exc:
            print(f"[phpnitro] PHPNITRO_RUST_RENDER=1 but hit-testing is unavailable, using Python: {exc}")
            return self.payload.action_at(x, y)
        return hit.action if hit is not None else None

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

        self.canvas = PhpNitroCanvasWidget()
        self.canvas.on_action = self._handle_action
        self.set_child(self.canvas)

        self._fetch(action=None)

    def set_field_value(self, name: str, value: str) -> None:
        """For a future TextField overlay (no native-View-overlay story
        exists yet on this target, same real gap iOS's own
        NativeScreenViewController.setFieldValue(_:forName:) docblock
        is upfront about) to call once one exists.
        """
        self.field_values[name] = value

    def _handle_action(self, action: str) -> None:
        result = navigation.reduce(action, self.stack)
        if isinstance(result, navigation.ClientTabOnly):
            self.canvas.set_client_tab(result.key, result.index)
            return

        self.stack = result.stack
        self._fetch(action=result.action)

    def _fetch(self, action: Optional[str]) -> None:
        screen = self.stack[-1] if self.stack else "home"
        width, height = self.get_default_size()

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
