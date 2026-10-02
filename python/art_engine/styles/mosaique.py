"""mosaique — a lattice of equilateral tiles with dark grout and a great triangle emerging."""

from __future__ import annotations

import math

from ..palette import AMBER, BLUE, BLUE_DEEP, COAL, GRAPHITE, INK, ORANGE, RED, RED_DEEP, STEEL, WHITE, YELLOW
from ..rng import Rng
from ..svg import polygon
from ._common import Art, anim_css, delay, shrink, triangle


def _inside(p: tuple[float, float], tri: list[tuple[float, float]]) -> bool:
    (x1, y1), (x2, y2), (x3, y3) = tri
    x, y = p
    d1 = (x - x2) * (y1 - y2) - (x1 - x2) * (y - y2)
    d2 = (x - x3) * (y2 - y3) - (x2 - x3) * (y - y3)
    d3 = (x - x1) * (y3 - y1) - (x3 - x1) * (y - y1)
    neg = d1 < 0 or d2 < 0 or d3 < 0
    pos = d1 > 0 or d2 > 0 or d3 > 0
    return not (neg and pos)


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    side = s / rng.randint(10, 16)
    hgt = side * math.sqrt(3) / 2
    big = triangle(w / 2 + rng.uniform(-0.06, 0.06) * w, h / 2 + rng.uniform(-0.02, 0.05) * h, s * rng.uniform(0.7, 0.86))
    bright = [BLUE, YELLOW, RED, WHITE, AMBER, ORANGE]
    centers = [((rng.uniform(0.15, 0.85) * w, rng.uniform(0.2, 0.9) * h), rng.choice(bright)) for _ in range(rng.randint(6, 9))]
    for c in (BLUE, YELLOW, RED):
        centers[rng.randrange(len(centers))] = (centers[rng.randrange(len(centers))][0], c)
    darks = [INK, COAL, GRAPHITE, STEEL, BLUE_DEEP, RED_DEEP]
    rows = int(h / hgt) + 2
    cols = int(w / (side / 2)) + 3
    grout = rng.uniform(0.82, 0.9)
    for r in range(rows):
        for c in range(cols):
            x = c * side / 2 - side / 2
            y = r * hgt
            up = (r + c) % 2 == 0
            pts = [(x + side / 2, y), (x + side, y + hgt), (x, y + hgt)] if up else [(x, y), (x + side, y), (x + side / 2, y + hgt)]
            mx = sum(p[0] for p in pts) / 3
            my = sum(p[1] for p in pts) / 3
            if _inside((mx, my), big):
                nearest = min(centers, key=lambda cc: math.dist(cc[0], (mx, my)))[1]
                color = nearest if rng.chance(0.82) else rng.choice(bright)
            else:
                color = rng.choice(darks) if rng.chance(0.93) else rng.choice([BLUE_DEEP, RED_DEEP, AMBER])
            art.body.append(polygon(shrink(pts, grout), fill=color,
                                    cls="pop" if animate else None,
                                    style=delay((mx / w + my / h) * 1.2) if animate else None))
    art.body.append(polygon(big, fill="none", stroke=WHITE, stroke_width=round(s * 0.004, 2), opacity=0.7, stroke_linejoin="round",
                            pathLength=1, cls="draw" if animate else None, style=delay(1.8) if animate else None))
    if animate:
        art.css = anim_css()
    return art
