"""tissage — woven diagonal bands passing over and under each other, with white seams."""

from __future__ import annotations

import math

from ..palette import AMBER, BLACK, BLUE, BLUE_DEEP, GRAPHITE, ORANGE, RED, RED_DEEP, WHITE, YELLOW
from ..rng import Rng
from ..svg import el, rect
from ._common import Art, anim_css, delay


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    cell = s / rng.randint(7, 12)
    band = cell * rng.uniform(0.62, 0.82)
    angle = rng.choice([45, -45, 30, -30])
    diag = math.hypot(w, h)
    n = int(diag / cell) + 4
    warp_colors = rng.choice([[BLUE, YELLOW, RED, WHITE], [RED, AMBER, BLUE, YELLOW], [YELLOW, BLUE, ORANGE, RED]])
    weft_colors = rng.choice([[BLACK, GRAPHITE, WHITE, BLUE_DEEP], [GRAPHITE, RED_DEEP, BLACK, WHITE], [BLUE_DEEP, BLACK, GRAPHITE, AMBER]])
    seam = round(s * 0.004, 2)
    start = -diag / 2 - cell
    warp = [warp_colors[(i + rng.randint(0, 1)) % len(warp_colors)] for i in range(n)]
    weft = [weft_colors[i % len(weft_colors)] for i in range(n)]

    layer_a, layer_b, over = [], [], []
    for i in range(n):
        x = start + i * cell
        layer_a.append(rect(x + (cell - band) / 2, -diag / 2, band, diag, fill=warp[i], stroke=WHITE, stroke_width=seam,
                            cls="rise" if animate else None, style=delay(i * 0.05) if animate else None))
        layer_b.append(rect(-diag / 2, x + (cell - band) / 2, diag, band, fill=weft[i], stroke=WHITE, stroke_width=seam,
                            cls="fade" if animate else None, style=delay(0.6 + i * 0.05) if animate else None))
    # Over/under: at every other crossing the warp band comes back on top.
    for i in range(n):
        for j in range(n):
            if (i + j) % 2 == 0:
                x = start + i * cell + (cell - band) / 2
                y = start + j * cell + (cell - band) / 2
                over.append(rect(x, y - seam, band, band + seam * 2, fill=warp[i], stroke=WHITE, stroke_width=seam))
    transform = f"translate({w / 2:.2f} {h / 2:.2f}) rotate({angle})"
    art.body.append(el("g", layer_a + layer_b + [el("g", over, cls="fade" if animate else None, style=delay(1.2) if animate else None)],
                       transform=transform))
    # A single triangle window cut into the weave, outlined in white.
    if rng.chance(0.6):
        cx, cy, side = w * rng.uniform(0.35, 0.65), h * rng.uniform(0.35, 0.65), s * rng.uniform(0.25, 0.4)
        hh = side * 0.866
        pts = f"{cx:.2f},{cy - hh / 2:.2f} {cx + side / 2:.2f},{cy + hh / 2:.2f} {cx - side / 2:.2f},{cy + hh / 2:.2f}"
        art.body.append(el("polygon", points=pts, fill=BLACK, stroke=WHITE, stroke_width=round(s * 0.008, 2), stroke_linejoin="round",
                           cls="pop" if animate else None, style=delay(1.6) if animate else None))
        art.body.append(el("polygon", points=pts, fill=rng.choice([YELLOW, RED, BLUE]), opacity=0.85,
                           transform=f"translate({cx:.2f} {cy:.2f}) scale(0.55) translate({-cx:.2f} {-cy:.2f})"))
    if animate:
        art.css = anim_css()
    return art
