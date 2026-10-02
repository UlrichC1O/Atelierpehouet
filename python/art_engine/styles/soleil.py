"""soleil — a sunburst of triangular rays around an off-centre focus, with triangle rings."""

from __future__ import annotations

import math

from ..palette import AMBER, BLACK, BLUE, GRADIENT, INK, ORANGE, RED, RED_BRIGHT, WHITE, YELLOW
from ..rng import Rng
from ..svg import el, glow_filter, linear_gradient, polygon
from ._common import Art, anim_css, delay, triangle


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    art.defs += [linear_gradient("tg", GRADIENT, x2=0.0, y2=1.0), glow_filter("glow", RED_BRIGHT, round(s * 0.016, 2))]
    fx, fy = w * rng.uniform(0.3, 0.7), h * rng.uniform(0.32, 0.68)
    far = math.hypot(w, h) * 1.2
    n = rng.choice([18, 24, 30, 36])
    start = rng.uniform(0, math.tau)
    sequence = rng.choice([[YELLOW, INK, RED, BLACK, BLUE, INK], [AMBER, BLACK, ORANGE, INK, RED, BLACK, WHITE, INK],
                           [BLUE, BLACK, YELLOW, INK, RED, BLACK]])
    rays = []
    for i in range(n):
        a1 = start + math.tau * i / n
        a2 = start + math.tau * (i + 1) / n
        rays.append(polygon([(fx, fy), (fx + math.cos(a1) * far, fy + math.sin(a1) * far), (fx + math.cos(a2) * far, fy + math.sin(a2) * far)],
                            fill=sequence[i % len(sequence)], cls="fade" if animate else None, style=delay(i * 0.03) if animate else None))
    spin = None
    if animate:
        spin = f"transform-origin:{fx:.1f}px {fy:.1f}px"
    art.body.append(el("g", rays, cls="spin" if animate else None, style=spin))

    # Concentric triangle rings.
    rot = rng.uniform(-15, 15)
    for i in range(rng.randint(4, 7)):
        side = s * (0.14 + i * rng.uniform(0.11, 0.15))
        color = [WHITE, YELLOW, WHITE, RED_BRIGHT][i % 4]
        art.body.append(polygon(triangle(fx, fy, side, rot + i * rng.uniform(4, 9)), fill="none", stroke=color,
                                stroke_width=round(s * (0.006 - i * 0.0006), 2), stroke_linejoin="round", opacity=round(0.95 - i * 0.1, 2),
                                pathLength=1, cls="draw" if animate else None, style=delay(0.5 + i * 0.2) if animate else None))
    core = triangle(fx, fy, s * 0.12, rot)
    art.body.append(polygon(core, fill="url(#tg)", filter="url(#glow)", cls="breathe" if animate else None))
    if animate:
        art.css = anim_css(".spin{animation:spin 90s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}")
    return art
