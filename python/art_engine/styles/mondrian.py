"""mondrian — recursive rectangles with white seams on black, bold primary accents."""

from __future__ import annotations

from ..palette import BLUE, DARKS, GRADIENT, RED, WHITE, YELLOW
from ..rng import Rng
from ..svg import el, linear_gradient, polygon, rect
from ._common import Art, anim_css, delay, partition


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    art.defs.append(linear_gradient("tg", GRADIENT, x2=rng.choice([1.0, 0.0]), y2=rng.choice([0.0, 1.0])))
    seam = round(s * rng.uniform(0.009, 0.014), 2)
    cells = partition(rng, 0, 0, w, h, rng.randint(4, 6), s * rng.uniform(0.06, 0.1), stop=0.18)
    weights = [(BLUE, 1.4), (YELLOW, 1.2), (RED, 1.4), (WHITE, 1.6)] + [(d, 1.3) for d in DARKS]
    accent_done = False
    pieces = []
    for i, (x0, y0, x1, y1) in enumerate(cells):
        color = rng.weighted(weights)
        cw, ch = x1 - x0, y1 - y0
        attrs = {"cls": "pop" if animate else None, "style": delay(i * 0.05) if animate else None}
        if not accent_done and cw * ch > s * s * 0.03 and rng.chance(0.25):
            color, accent_done = "url(#tg)", True
        if rng.chance(0.12) and cw > s * 0.08 and ch > s * 0.08:
            # A triangle cut through the field: the logo's slope.
            a, b = rng.sample([BLUE, YELLOW, RED, WHITE, DARKS[0]], 2)
            flip = rng.chance(0.5)
            t1 = [(x0, y1), (x1, y0), (x1, y1)] if flip else [(x0, y0), (x1, y1), (x0, y1)]
            t2 = [(x0, y0), (x1, y0), (x0, y1)] if flip else [(x0, y0), (x1, y0), (x1, y1)]
            pieces.append(el("g", [polygon(t1, fill=a), polygon(t2, fill=b)], **attrs))
            pieces.append(rect(x0, y0, cw, ch, fill="none", stroke=WHITE, stroke_width=seam))
            continue
        pieces.append(rect(x0, y0, cw, ch, fill=color, stroke=WHITE, stroke_width=seam, **attrs))
    art.body += pieces
    art.body.append(rect(0, 0, w, h, fill="none", stroke=WHITE, stroke_width=seam * 2))
    if animate:
        art.css = anim_css()
    return art
