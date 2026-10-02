"""prisme — recursive triangle subdivision, coloured along the TELIERS gradient."""

from __future__ import annotations

import math

from ..palette import BLACK, BLUE, BLUE_BRIGHT, DARKS, WHITE, YELLOW, gradient_at
from ..rng import Rng
from ..svg import polygon
from ._common import Art, anim_css, delay

Point = tuple[float, float]


def _subdivide(rng: Rng, tri: tuple[Point, Point, Point], depth: int, min_area: float, out: list) -> None:
    a, b, c = tri
    area = abs((b[0] - a[0]) * (c[1] - a[1]) - (c[0] - a[0]) * (b[1] - a[1])) / 2
    if depth <= 0 or area < min_area or (depth < 4 and rng.chance(0.14)):
        out.append(tri)
        return
    edges = [(a, b, c), (b, c, a), (c, a, b)]
    p, q, r = max(edges, key=lambda e: math.dist(e[0], e[1]))
    t = rng.uniform(0.36, 0.64)
    m = (p[0] + (q[0] - p[0]) * t, p[1] + (q[1] - p[1]) * t)
    _subdivide(rng, (p, m, r), depth - 1, min_area, out)
    _subdivide(rng, (m, q, r), depth - 1, min_area, out)


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    fx, fy = w * rng.uniform(0.3, 0.7), h * rng.uniform(0.3, 0.7)
    corners = [(0.0, 0.0), (float(w), 0.0), (float(w), float(h)), (0.0, float(h))]
    seeds = [((fx, fy), corners[i], corners[(i + 1) % 4]) for i in range(4)]
    tris: list = []
    for t in seeds:
        _subdivide(rng, t, rng.randint(5, 7), s * s * 0.0024, tris)

    direction = rng.choice(["x", "y", "d"])
    seam = round(s * 0.0028, 2)
    for i, (a, b, c) in enumerate(tris):
        mx, my = (a[0] + b[0] + c[0]) / 3, (a[1] + b[1] + c[1]) / 3
        t = mx / w if direction == "x" else my / h if direction == "y" else (mx / w + my / h) / 2
        roll = rng.random()
        if roll < 0.09:
            color = rng.choice([BLUE, BLUE_BRIGHT])
        elif roll < 0.13:
            color = YELLOW
        elif roll < 0.16:
            color = WHITE
        elif roll < 0.34:
            color = rng.choice(DARKS)
        else:
            color = gradient_at(rng.gauss_clamped(t, 0.12, 0, 1))
        dist = math.dist((mx, my), (fx, fy)) / s
        art.body.append(polygon([a, b, c], fill=color, stroke=BLACK, stroke_width=seam, stroke_linejoin="round",
                                cls="pop" if animate else None, style=delay(dist * 1.6) if animate else None))
    # A thin white prism outline catching the light.
    side = s * rng.uniform(0.35, 0.55)
    hgt = side * 0.866
    pts = [(fx, fy - hgt * 0.6), (fx + side / 2, fy + hgt * 0.4), (fx - side / 2, fy + hgt * 0.4)]
    art.body.append(polygon(pts, fill="none", stroke=WHITE, stroke_width=round(s * 0.005, 2), stroke_linejoin="round",
                            opacity=0.9, pathLength=1, cls="draw" if animate else None, style=delay(1.4) if animate else None))
    if animate:
        art.css = anim_css()
    return art
