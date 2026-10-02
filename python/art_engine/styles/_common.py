"""Shared building blocks for the art styles."""

from __future__ import annotations

import math
from dataclasses import dataclass, field

from ..palette import FIELDS, WHITE, YELLOW
from ..rng import Rng
from ..svg import REDUCED_MOTION, fmt, line

# Base state of every animated element is its final, visible state; keyframes only describe
# where it comes FROM (fill-mode: backwards), so prefers-reduced-motion leaves a complete picture.
ANIM_CSS = (
    ".pop{transform-box:fill-box;transform-origin:center;animation:pop 1.1s cubic-bezier(.34,1.56,.64,1) backwards}"
    "@keyframes pop{from{opacity:0;transform:scale(.15) rotate(-12deg)}}"
    ".fade{animation:fade 1.6s ease backwards}"
    "@keyframes fade{from{opacity:0}}"
    ".draw{animation:draw 1.8s cubic-bezier(.65,0,.35,1) backwards}"
    "@keyframes draw{from{stroke-dasharray:1;stroke-dashoffset:1}to{stroke-dasharray:1;stroke-dashoffset:0}}"
    ".rise{transform-box:fill-box;transform-origin:50% 100%;animation:rise 1.2s cubic-bezier(.16,1,.3,1) backwards}"
    "@keyframes rise{from{opacity:0;transform:translateY(12%) scaleY(.6)}}"
    ".shimmer{animation:shimmer 5s ease-in-out infinite}"
    "@keyframes shimmer{50%{opacity:.55}}"
    ".breathe{transform-box:fill-box;transform-origin:center;animation:breathe 6s ease-in-out infinite}"
    "@keyframes breathe{50%{transform:scale(1.04)}}"
)


@dataclass
class Art:
    defs: list[str] = field(default_factory=list)
    body: list[str] = field(default_factory=list)
    css: str = ""


def anim_css(extra: str = "") -> str:
    return ANIM_CSS + extra + REDUCED_MOTION


def delay(seconds: float) -> str:
    return f"animation-delay:{fmt(round(seconds, 2))}s"


def field_color(rng: Rng, weights: list[tuple[str, float]] | None = None) -> str:
    return rng.weighted(weights or FIELDS)


def partition(rng: Rng, x0: float, y0: float, x1: float, y1: float, depth: int, min_size: float,
              stop: float = 0.12) -> list[tuple[float, float, float, float]]:
    """Recursive Mondrian cuts of a rectangle into smaller rectangles."""
    w, h = x1 - x0, y1 - y0
    if depth <= 0 or (w < 2 * min_size and h < 2 * min_size) or (depth < 3 and rng.chance(stop)):
        return [(x0, y0, x1, y1)]
    vertical = w > h if rng.chance(0.72) else rng.chance(0.5)
    if vertical and w < 2 * min_size:
        vertical = False
    if not vertical and h < 2 * min_size:
        vertical = True
    if vertical:
        cut = rng.uniform(x0 + min_size, x1 - min_size)
        return partition(rng, x0, y0, cut, y1, depth - 1, min_size, stop) + partition(rng, cut, y0, x1, y1, depth - 1, min_size, stop)
    cut = rng.uniform(y0 + min_size, y1 - min_size)
    return partition(rng, x0, y0, x1, cut, depth - 1, min_size, stop) + partition(rng, x0, cut, x1, y1, depth - 1, min_size, stop)


def signature_slashes(rng: Rng, w: float, h: float, count: int, steep: int, animate: bool,
                      start: float = 1.2, color: str = WHITE) -> list[str]:
    """Long thin strokes at ≈ −20° and steep strokes at ≈ 65°, like the logo's signature."""
    s = min(w, h)
    out = []
    ox, oy = w * rng.uniform(0.55, 0.78), h * rng.uniform(0.62, 0.82)
    for i in range(count):
        ang = math.radians(-rng.uniform(16, 25))
        length = s * rng.uniform(0.45, 0.95)
        cx, cy = ox + rng.uniform(-0.12, 0.12) * w, oy + rng.uniform(-0.08, 0.08) * h
        dx, dy = math.cos(ang) * length / 2, math.sin(ang) * length / 2
        out.append(line(cx - dx, cy - dy, cx + dx, cy + dy, stroke=color if i % 4 else YELLOW if rng.chance(0.3) else color,
                        stroke_width=round(s * rng.uniform(0.0018, 0.0042), 2), stroke_linecap="round",
                        opacity=round(rng.uniform(0.55, 0.95), 2), pathLength=1,
                        cls="draw" if animate else None, style=delay(start + i * 0.18) if animate else None))
    for i in range(steep):
        ang = math.radians(-rng.uniform(58, 70))
        length = s * rng.uniform(0.25, 0.45)
        cx, cy = ox + rng.uniform(-0.1, 0.14) * w, oy + rng.uniform(-0.06, 0.06) * h
        dx, dy = math.cos(ang) * length / 2, math.sin(ang) * length / 2
        out.append(line(cx - dx, cy - dy, cx + dx, cy + dy, stroke=color, stroke_width=round(s * rng.uniform(0.002, 0.0035), 2),
                        stroke_linecap="round", opacity=round(rng.uniform(0.6, 0.9), 2), pathLength=1,
                        cls="draw" if animate else None, style=delay(start + (count + i) * 0.18) if animate else None))
    return out


def triangle(cx: float, cy: float, side: float, rotation: float = 0.0, point_up: bool = True) -> list[tuple[float, float]]:
    """Equilateral triangle centred on its bounding box centre."""
    h = side * math.sqrt(3) / 2
    if point_up:
        pts = [(cx, cy - h / 2), (cx + side / 2, cy + h / 2), (cx - side / 2, cy + h / 2)]
    else:
        pts = [(cx - side / 2, cy - h / 2), (cx + side / 2, cy - h / 2), (cx, cy + h / 2)]
    if rotation:
        a = math.radians(rotation)
        pts = [(cx + (x - cx) * math.cos(a) - (y - cy) * math.sin(a), cy + (x - cx) * math.sin(a) + (y - cy) * math.cos(a)) for x, y in pts]
    return pts


def shrink(pts: list[tuple[float, float]], factor: float) -> list[tuple[float, float]]:
    cx = sum(p[0] for p in pts) / len(pts)
    cy = sum(p[1] for p in pts) / len(pts)
    return [(cx + (x - cx) * factor, cy + (y - cy) * factor) for x, y in pts]
