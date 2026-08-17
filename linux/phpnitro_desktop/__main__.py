"""Entry point:

    python3 -m phpnitro_desktop <project_dir>        # embeds/spawns php -S itself
    python3 -m phpnitro_desktop --connect HOST:PORT   # PhpNitro-Go-style remote client

Mirrors bin/phpx's own two related modes (`phpx serve`, a local dev
server; PhpNitro Go, a remote client to one) as a single CLI rather
than two separate entry points, since both ultimately just construct a
ScreenWindow pointed at a different host:port — see app.py.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from .app import run_local, run_remote


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(prog="phpnitro-desktop", description="PhpNitro desktop shell (Linux)")
    parser.add_argument("project_dir", nargs="?", help="path to a PhpNitro project (containing public/)")
    parser.add_argument("--connect", metavar="HOST:PORT", help="connect to a remote `phpx serve` instead of spawning one locally")
    parser.add_argument("--screen", default="home", help="initial screen token (default: home)")
    args = parser.parse_args(argv)

    if args.connect:
        host, _, port_str = args.connect.partition(":")
        if not host or not port_str.isdigit():
            parser.error("--connect expects HOST:PORT, e.g. 192.168.1.23:8090")
        return run_remote(host, int(port_str), screen=args.screen)

    if not args.project_dir:
        parser.error("pass a project directory, or --connect HOST:PORT")

    return run_local(Path(args.project_dir).resolve(), screen=args.screen)


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
