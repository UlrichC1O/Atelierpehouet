"""HTTP micro-service: GET /health, /styles, /palette, /art (stdlib ThreadingHTTPServer)."""

from __future__ import annotations

import json
import logging
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

from . import __version__
from .palette import PALETTE
from .registry import MAX_SEED, STYLES, render, style_keys

log = logging.getLogger("art_engine")


def _int(params: dict, name: str, default: int) -> int:
    raw = params.get(name, [str(default)])[-1]
    try:
        return int(raw)
    except ValueError as exc:
        raise ValueError(f"{name} must be an integer") from exc


class Handler(BaseHTTPRequestHandler):
    server_version = "PehouetArtEngine/" + __version__
    quiet = False

    def log_message(self, fmt: str, *args) -> None:  # noqa: D401 - stdlib signature
        if not self.quiet:
            log.info("%s - %s", self.address_string(), fmt % args)

    def _send(self, status: int, body: bytes, content_type: str, cache: bool = False) -> None:
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("Cache-Control", "public, max-age=604800" if cache else "no-store")
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(body)

    def _json(self, status: int, payload: dict) -> None:
        self._send(status, json.dumps(payload, ensure_ascii=False).encode("utf-8"), "application/json; charset=utf-8")

    def do_HEAD(self) -> None:  # noqa: N802
        self.do_GET()

    def do_GET(self) -> None:  # noqa: N802
        url = urlparse(self.path)
        params = parse_qs(url.query, keep_blank_values=True)
        if url.path == "/health":
            return self._json(200, {"status": "ok", "engine": "art_engine", "version": __version__, "styles": style_keys()})
        if url.path == "/styles":
            return self._json(200, {"styles": [{"key": k, "name": v["name"], "description": v["description"]} for k, v in STYLES.items()]})
        if url.path == "/palette":
            return self._json(200, {"palette": PALETTE})
        if url.path == "/art":
            try:
                style = params.get("style", ["pehouet"])[-1]
                seed = params.get("seed", ["pehouet"])[-1]
                if len(seed) > MAX_SEED:
                    raise ValueError(f"seed must be at most {MAX_SEED} characters")
                width = _int(params, "width", 800)
                height = _int(params, "height", width)
                animate = params.get("animate", ["0"])[-1] in ("1", "true", "yes")
                svg = render(style, seed, width, height, animate)
            except ValueError as exc:
                return self._json(HTTPStatus.BAD_REQUEST, {"error": str(exc)})
            return self._send(200, svg.encode("utf-8"), "image/svg+xml; charset=utf-8", cache=True)
        return self._json(HTTPStatus.NOT_FOUND, {"error": "not found"})


def make_server(host: str = "127.0.0.1", port: int = 8765, quiet: bool = False) -> ThreadingHTTPServer:
    handler = type("QuietHandler" if quiet else "Handler", (Handler,), {"quiet": quiet})
    server = ThreadingHTTPServer((host, port), handler)
    server.daemon_threads = True
    return server


def serve(host: str = "127.0.0.1", port: int = 8765, quiet: bool = False) -> None:
    logging.basicConfig(level=logging.INFO, format="[art] %(message)s")
    server = make_server(host, port, quiet)
    log.info("Pehouet art engine %s listening on http://%s:%s", __version__, host, server.server_address[1])
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
