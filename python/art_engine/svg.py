"""A tiny, byte-stable SVG builder (fixed number precision, strict escaping)."""

from __future__ import annotations

import re
from typing import Iterable

from .palette import BLACK

_INVALID_XML = re.compile("[\x00-\x08\x0b\x0c\x0e-\x1f￾￿]")


def fmt(value: object) -> str:
    """Format numbers with at most 2 decimals and no trailing zeros; strings pass through."""
    if isinstance(value, bool):
        return "true" if value else "false"
    if isinstance(value, int):
        return str(value)
    if isinstance(value, float):
        text = f"{value:.2f}".rstrip("0").rstrip(".")
        return "0" if text in ("", "-0") else text
    return str(value)


def text(value: str) -> str:
    """Escape character data (and drop characters XML 1.0 forbids)."""
    value = _INVALID_XML.sub("", value)
    return value.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def attr(value: object) -> str:
    return text(fmt(value)).replace('"', "&quot;")


def el(tag: str, children: Iterable[str] | str | None = None, **attrs: object) -> str:
    """el("rect", x=1, fill="#000") → <rect x="1" fill="#000"/>. Keyword `cls` → class, `_` → -."""
    parts = []
    for key, value in attrs.items():
        if value is None:
            continue
        name = "class" if key == "cls" else key.rstrip("_").replace("_", "-")
        parts.append(f'{name}="{attr(value)}"')
    head = f"<{tag}" + ("" if not parts else " " + " ".join(parts))
    if children is None:
        return head + "/>"
    body = children if isinstance(children, str) else "".join(children)
    return f"{head}>{body}</{tag}>"


def points(pts: Iterable[tuple[float, float]]) -> str:
    return " ".join(f"{fmt(float(x))},{fmt(float(y))}" for x, y in pts)


def polygon(pts: Iterable[tuple[float, float]], **attrs: object) -> str:
    return el("polygon", points=points(pts), **attrs)


def line(x1: float, y1: float, x2: float, y2: float, **attrs: object) -> str:
    return el("line", x1=float(x1), y1=float(y1), x2=float(x2), y2=float(y2), **attrs)


def rect(x: float, y: float, w: float, h: float, **attrs: object) -> str:
    return el("rect", x=float(x), y=float(y), width=float(w), height=float(h), **attrs)


def glow_filter(fid: str, color: str, blur: float) -> str:
    """Neon glow: a coloured blurred copy under the source graphic."""
    return el("filter", [
        el("feGaussianBlur", in_="SourceAlpha", stdDeviation=blur, result="b"),
        el("feFlood", flood_color=color, result="c"),
        el("feComposite", in_="c", in2="b", operator="in", result="g"),
        el("feMerge", [el("feMergeNode", in_="g"), el("feMergeNode", in_="g"), el("feMergeNode", in_="SourceGraphic")]),
    ], id=fid, x="-50%", y="-50%", width="200%", height="200%")


def linear_gradient(gid: str, stops: list[str], x2: float = 1.0, y2: float = 0.0) -> str:
    n = max(1, len(stops) - 1)
    return el("linearGradient", [
        el("stop", offset=f"{fmt(i / n * 100.0)}%", stop_color=c) for i, c in enumerate(stops)
    ], id=gid, x1="0", y1="0", x2=fmt(x2), y2=fmt(y2))


def document(width: int, height: int, title: str, body: list[str], defs: list[str] | None = None,
             css: str = "", background: str = BLACK) -> str:
    defs = defs or []
    head = [el("title", text(title))]
    if css:
        head.append(el("style", css))
    if defs:
        head.append(el("defs", defs))
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {height}" '
        f'width="{width}" height="{height}" role="img">'
        + "".join(head)
        + rect(0, 0, width, height, fill=background)
        + "".join(body)
        + "</svg>\n"
    )


REDUCED_MOTION = "@media (prefers-reduced-motion: reduce){*{animation:none!important}}"
