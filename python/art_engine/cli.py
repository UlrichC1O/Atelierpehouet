"""Command line: render · styles · serve · batch · gallery (python3 -m art_engine --help)."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from . import __version__
from .registry import MAX_SIZE, MIN_SIZE, STYLES, render, style_keys
from .titles import title


def _size(value: str) -> int:
    try:
        n = int(value)
    except ValueError as exc:
        raise argparse.ArgumentTypeError("must be an integer") from exc
    if not MIN_SIZE <= n <= MAX_SIZE:
        raise argparse.ArgumentTypeError(f"must be between {MIN_SIZE} and {MAX_SIZE}")
    return n


def _safe_path(root: Path, relative: str) -> Path:
    target = (root / relative).resolve()
    if root.resolve() not in target.parents and target != root.resolve():
        raise ValueError(f"path escapes the output directory: {relative}")
    if target.suffix != ".svg":
        raise ValueError(f"only .svg files may be written: {relative}")
    return target


def cmd_render(args: argparse.Namespace) -> int:
    sys.stdout.write(render(args.style, args.seed, args.width, args.height or args.width, args.animate))
    return 0


def cmd_styles(args: argparse.Namespace) -> int:
    if args.json:
        print(json.dumps([{"key": k, "name": v["name"], "description": v["description"]} for k, v in STYLES.items()], ensure_ascii=False, indent=2))
    else:
        for key, meta in STYLES.items():
            print(f"{key:10s} {meta['description']['fr']}")
    return 0


def cmd_serve(args: argparse.Namespace) -> int:
    from .server import serve
    serve(args.host, args.port, args.quiet)
    return 0


def cmd_batch(args: argparse.Namespace) -> int:
    spec = json.loads(Path(args.spec).read_text(encoding="utf-8"))
    out = Path(args.out)
    written = []
    for item in spec.get("items", []):
        target = _safe_path(out, str(item["file"]))
        width = int(item.get("width", 800))
        height = int(item.get("height", width))
        svg = render(str(item["style"]), str(item.get("seed", "pehouet")), width, height, bool(item.get("animate", False)))
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(svg, encoding="utf-8")
        written.append(str(item["file"]))
    print(json.dumps({"written": len(written), "files": written}, ensure_ascii=False))
    return 0


GALLERY_SEEDS = ["atelier", "communaute", "lumiere", "quartier", "fresque", "aube", "marche", "fete"]


def cmd_gallery(args: argparse.Namespace) -> int:
    out = Path(args.out)
    folder = out / "gallery"
    folder.mkdir(parents=True, exist_ok=True)
    items = []
    for style in style_keys():
        for n in range(1, args.per_style + 1):
            seed = f"{GALLERY_SEEDS[(n - 1) % len(GALLERY_SEEDS)]}-{style}-{n}"
            rel = f"gallery/{style}-{n}.svg"
            _safe_path(out, rel).write_text(render(style, seed, args.size, args.size, False), encoding="utf-8")
            items.append({"file": rel, "style": style, "seed": seed, "title": title(style, seed), "width": args.size, "height": args.size})
    manifest = {"version": 1, "generated_with": f"art_engine {__version__}", "items": items}
    (folder / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"written": len(items), "manifest": "gallery/manifest.json"}, ensure_ascii=False))
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="art_engine", description="Ateliers Pehouet generative-art engine")
    parser.add_argument("--version", action="version", version=f"art_engine {__version__}")
    sub = parser.add_subparsers(dest="command", required=True)

    p = sub.add_parser("render", help="print one SVG artwork to stdout")
    p.add_argument("--style", choices=style_keys(), default="pehouet")
    p.add_argument("--seed", default="pehouet")
    p.add_argument("--width", type=_size, default=800)
    p.add_argument("--height", type=_size, default=None)
    p.add_argument("--animate", action="store_true")
    p.set_defaults(func=cmd_render)

    p = sub.add_parser("styles", help="list the styles")
    p.add_argument("--json", action="store_true")
    p.set_defaults(func=cmd_styles)

    p = sub.add_parser("serve", help="run the HTTP service")
    p.add_argument("--host", default="127.0.0.1")
    p.add_argument("--port", type=int, default=8765)
    p.add_argument("--quiet", action="store_true")
    p.set_defaults(func=cmd_serve)

    p = sub.add_parser("batch", help="render the artworks listed in a JSON spec")
    p.add_argument("--spec", required=True)
    p.add_argument("--out", required=True)
    p.set_defaults(func=cmd_batch)

    p = sub.add_parser("gallery", help="render the gallery + manifest.json")
    p.add_argument("--out", required=True)
    p.add_argument("--per-style", type=int, default=4)
    p.add_argument("--size", type=_size, default=800)
    p.set_defaults(func=cmd_gallery)
    return parser


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    try:
        return args.func(args)
    except (ValueError, KeyError, OSError, json.JSONDecodeError) as exc:
        print(f"art_engine: error: {exc}", file=sys.stderr)
        return 2
