"""eclats — signature slashes and thin shards bursting around a neon core."""

from __future__ import annotations

import math

from ..palette import AMBER, BLUE_BRIGHT, GRADIENT, RED_BRIGHT, WHITE, YELLOW, gradient_at
from ..rng import Rng
from ..svg import glow_filter, line, linear_gradient, polygon
from ._common import Art, anim_css, delay, triangle


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    art.defs += [glow_filter("glow", RED_BRIGHT, round(s * 0.014, 2)), glow_filter("soft", WHITE, round(s * 0.004, 2)),
                 linear_gradient("tg", GRADIENT)]
    fx, fy = w * rng.uniform(0.38, 0.62), h * rng.uniform(0.4, 0.62)
    diag = math.hypot(w, h)

    # Shards radiating from the focus.
    for i in range(rng.randint(14, 24)):
        ang = rng.uniform(0, math.tau)
        r1 = s * rng.uniform(0.06, 0.16)
        r2 = s * rng.uniform(0.3, 0.75)
        spread = rng.uniform(0.015, 0.05)
        pts = [(fx + math.cos(ang) * r1, fy + math.sin(ang) * r1),
               (fx + math.cos(ang - spread) * r2, fy + math.sin(ang - spread) * r2),
               (fx + math.cos(ang + spread) * r2, fy + math.sin(ang + spread) * r2)]
        color = rng.choice([gradient_at(rng.random()), BLUE_BRIGHT, WHITE, YELLOW])
        art.body.append(polygon(pts, fill=color, opacity=round(rng.uniform(0.45, 0.95), 2),
                                cls="pop" if animate else None, style=delay(0.2 + i * 0.05) if animate else None))

    # Long slashes across the canvas at about −20°, and steep strokes at about 65°.
    n = rng.randint(14, 26)
    for i in range(n):
        steep = rng.chance(0.25)
        ang = math.radians(-rng.uniform(58, 70) if steep else -rng.uniform(15, 25))
        off = rng.uniform(-0.55, 0.55) * diag
        cx, cy = fx + math.cos(ang + math.pi / 2) * off * 0.6, fy + math.sin(ang + math.pi / 2) * off * 0.6
        length = diag * (rng.uniform(0.25, 0.5) if steep else rng.uniform(0.5, 1.1))
        dx, dy = math.cos(ang) * length / 2, math.sin(ang) * length / 2
        color = WHITE if rng.chance(0.72) else rng.choice([YELLOW, AMBER, RED_BRIGHT])
        art.body.append(line(cx - dx, cy - dy, cx + dx, cy + dy, stroke=color, stroke_width=round(s * rng.uniform(0.0012, 0.0055), 2),
                             stroke_linecap="round", opacity=round(rng.uniform(0.35, 0.95), 2), pathLength=1,
                             filter="url(#soft)" if rng.chance(0.25) else None,
                             cls="draw" if animate else None, style=delay(0.6 + i * 0.08) if animate else None))

    # The neon core: a glowing TELIERS triangle.
    core = triangle(fx, fy, s * rng.uniform(0.12, 0.2), rng.uniform(-20, 20))
    art.body.append(polygon(core, fill="url(#tg)", filter="url(#glow)", cls="pop breathe" if animate else None,
                            style=delay(0.1) if animate else None))
    art.body.append(polygon(core, fill="none", stroke=WHITE, stroke_width=round(s * 0.004, 2), stroke_linejoin="round"))
    if animate:
        art.css = anim_css()
    return art
