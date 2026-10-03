#!/usr/bin/env python3
"""Palette audit: the site may only use the colours of the Ateliers Pehouet logo.

Scans public/css/**/*.css (except 01-tokens.css, where the palette is declared),
resources/views/**/*.blade.php and public/js/**/*.js for colour literals — hex, rgb()/rgba(),
hsl()/hsla() and named colours in colour contexts — and reports every colour whose RGB value
is not in the palette of config/atelier.php (+ pure #fff / #000).

    python3 python/tools/palette_audit.py [--root DIR] [--json]
"""

from __future__ import annotations

import argparse
import colorsys
import json
import re
import sys
from pathlib import Path

NAMED = {
    "aliceblue", "antiquewhite", "aqua", "aquamarine", "azure", "beige", "bisque", "blanchedalmond", "blue",
    "blueviolet", "brown", "burlywood", "cadetblue", "chartreuse", "chocolate", "coral", "cornflowerblue",
    "cornsilk", "crimson", "cyan", "darkblue", "darkcyan", "darkgoldenrod", "darkgray", "darkgreen", "darkgrey",
    "darkkhaki", "darkmagenta", "darkolivegreen", "darkorange", "darkorchid", "darkred", "darksalmon",
    "darkseagreen", "darkslateblue", "darkslategray", "darkslategrey", "darkturquoise", "darkviolet", "deeppink",
    "deepskyblue", "dimgray", "dimgrey", "dodgerblue", "firebrick", "floralwhite", "forestgreen", "fuchsia",
    "gainsboro", "ghostwhite", "gold", "goldenrod", "gray", "green", "greenyellow", "grey", "honeydew", "hotpink",
    "indianred", "indigo", "ivory", "khaki", "lavender", "lavenderblush", "lawngreen", "lemonchiffon", "lightblue",
    "lightcoral", "lightcyan", "lightgoldenrodyellow", "lightgray", "lightgreen", "lightgrey", "lightpink",
    "lightsalmon", "lightseagreen", "lightskyblue", "lightslategray", "lightslategrey", "lightsteelblue",
    "lightyellow", "lime", "limegreen", "linen", "magenta", "maroon", "mediumaquamarine", "mediumblue",
    "mediumorchid", "mediumpurple", "mediumseagreen", "mediumslateblue", "mediumspringgreen", "mediumturquoise",
    "mediumvioletred", "midnightblue", "mintcream", "mistyrose", "moccasin", "navajowhite", "navy", "oldlace",
    "olive", "olivedrab", "orange", "orangered", "orchid", "palegoldenrod", "palegreen", "paleturquoise",
    "palevioletred", "papayawhip", "peachpuff", "peru", "pink", "plum", "powderblue", "purple", "rebeccapurple",
    "red", "rosybrown", "royalblue", "saddlebrown", "salmon", "sandybrown", "seagreen", "seashell", "sienna",
    "silver", "skyblue", "slateblue", "slategray", "slategrey", "snow", "springgreen", "steelblue", "tan", "teal",
    "thistle", "tomato", "turquoise", "violet", "wheat", "yellow", "yellowgreen",
}
ALLOWED_KEYWORDS = {"white", "black", "transparent", "currentcolor", "inherit", "none"}

HEX = re.compile(r"(?<![\w&#])#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})(?![0-9a-zA-Z_-])")
RGB = re.compile(r"\brgba?\(\s*([^)]*)\)", re.I)
HSL = re.compile(r"\bhsla?\(\s*([^)]*)\)", re.I)
# Named colours only where a colour is expected: CSS property values, SVG paint attributes, canvas styles.
CSS_DECL = re.compile(r"(?:^|[;{\s])(?:color|background(?:-color)?|border(?:-[a-z]+)*|outline(?:-color)?|fill|stroke|stop-color|flood-color|"
                      r"text-shadow|box-shadow|caret-color|accent-color|column-rule(?:-color)?|text-decoration(?:-color)?|"
                      r"-webkit-text-stroke(?:-color)?|-webkit-text-fill-color|scrollbar-color)\s*:\s*([^;}{]+)", re.I)
SVG_ATTR = re.compile(r"\b(?:fill|stroke|stop-color|flood-color|lighting-color|color)\s*=\s*\"([^\"]*)\"", re.I)
JS_STYLE = re.compile(r"\b(?:fillStyle|strokeStyle|shadowColor)\s*=\s*['\"]([^'\"]+)['\"]")


def palette(root: Path) -> set[tuple[int, int, int]]:
    config = (root / "config" / "atelier.php").read_text(encoding="utf-8")
    block = config.split("'palette'", 1)[1].split("]", 1)[0]
    values = {hex_rgb(h) for h in re.findall(r"#([0-9a-fA-F]{6})\b", block)}
    values |= {(255, 255, 255), (0, 0, 0)}
    return values


def hex_rgb(h: str) -> tuple[int, int, int]:
    h = h.lstrip("#")
    if len(h) in (3, 4):
        h = "".join(c * 2 for c in h[:3])
    return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)


def parse_rgb(args: str) -> tuple[int, int, int] | None:
    parts = re.split(r"[\s,/]+", args.strip())
    try:
        vals = []
        for p in parts[:3]:
            vals.append(round(float(p[:-1]) * 2.55) if p.endswith("%") else round(float(p)))
        return tuple(vals) if len(vals) == 3 else None  # type: ignore[return-value]
    except ValueError:
        return None  # var(), calc() … are not literals


def parse_hsl(args: str) -> tuple[int, int, int] | None:
    parts = re.split(r"[\s,/]+", args.strip())
    try:
        h = float(parts[0].replace("deg", "")) / 360
        s = float(parts[1].rstrip("%")) / 100
        l = float(parts[2].rstrip("%")) / 100
    except (ValueError, IndexError):
        return None
    r, g, b = colorsys.hls_to_rgb(h, l, s)
    return round(r * 255), round(g * 255), round(b * 255)


def strip_css_comments(text: str) -> str:
    return re.sub(r"/\*.*?\*/", lambda m: re.sub(r"[^\n]", " ", m.group(0)), text, flags=re.S)


def strip_blade_comments(text: str) -> str:
    text = re.sub(r"\{\{--.*?--\}\}", lambda m: re.sub(r"[^\n]", " ", m.group(0)), text, flags=re.S)
    return re.sub(r"<!--.*?-->", lambda m: re.sub(r"[^\n]", " ", m.group(0)), text, flags=re.S)


def strip_js_comments(text: str) -> str:
    text = re.sub(r"/\*.*?\*/", lambda m: re.sub(r"[^\n]", " ", m.group(0)), text, flags=re.S)
    return re.sub(r"(?<![:'\"\\])//[^\n]*", "", text)


def audit(root: Path) -> list[dict]:
    allowed = palette(root)
    files: list[tuple[Path, str]] = []
    for p in sorted((root / "public" / "css").rglob("*.css")):
        if p.name != "01-tokens.css":
            files.append((p, "css"))
    files += [(p, "blade") for p in sorted((root / "resources" / "views").rglob("*.blade.php"))]
    files += [(p, "js") for p in sorted((root / "public" / "js").rglob("*.js"))]

    problems = []
    for path, kind in files:
        raw = path.read_text(encoding="utf-8")
        text = strip_css_comments(raw) if kind == "css" else strip_blade_comments(raw) if kind == "blade" else strip_js_comments(raw)
        rel = path.relative_to(root).as_posix()

        def report(pos: int, literal: str, why: str) -> None:
            problems.append({"file": rel, "line": text.count("\n", 0, pos) + 1, "colour": literal, "reason": why})

        for m in HEX.finditer(text):
            # ignore URL fragments and HTML entities (&#123;) and ids in selectors (#main)
            before = text[max(0, m.start() - 6): m.start()]
            if kind == "css":
                line_start = text.rfind("\n", 0, m.start()) + 1
                prefix = text[line_start:m.start()]
                if ":" not in prefix and "(" not in prefix and "," not in prefix:
                    continue  # a selector like #main
            if kind in ("blade", "js") and ("href=" in before or "url(" in before):
                continue
            if hex_rgb(m.group(1)) not in allowed:
                report(m.start(), m.group(0), "hex colour outside the logo palette")
        for m in RGB.finditer(text):
            rgb = parse_rgb(m.group(1))
            if rgb is not None and rgb not in allowed:
                report(m.start(), m.group(0), "rgb() colour outside the logo palette")
        for m in HSL.finditer(text):
            rgb = parse_hsl(m.group(1))
            if rgb is not None and rgb not in allowed:
                report(m.start(), m.group(0), "hsl() colour outside the logo palette")
        contexts = []
        if kind in ("css", "blade"):
            contexts += [(m.start(1), m.group(1)) for m in CSS_DECL.finditer(text)]
        if kind == "blade":
            contexts += [(m.start(1), m.group(1)) for m in SVG_ATTR.finditer(text)]
        if kind == "js":
            contexts += [(m.start(1), m.group(1)) for m in JS_STYLE.finditer(text)]
        for pos, value in contexts:
            for word in re.findall(r"(?<![\w#(-])([a-zA-Z]+)(?![\w(-])", value):
                w = word.lower()
                if w in NAMED and w not in ALLOWED_KEYWORDS:
                    report(pos, word, "named colour outside the logo palette")
    return problems


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[2])
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv)
    problems = audit(args.root.resolve())
    if args.json:
        print(json.dumps({"problems": problems, "count": len(problems)}, ensure_ascii=False, indent=2))
    else:
        for p in problems:
            print(f"✗ {p['file']}:{p['line']}: {p['colour']} — {p['reason']}")
        print(f"{len(problems)} palette problem(s)" if problems else "✓ only logo colours are used")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
