"""pehouet — the logo's triangle "A" re-partitioned into new Mondrian cuts."""

from __future__ import annotations

from ..palette import BLACK, BLUE, GRADIENT, RED, RED_BRIGHT, WHITE, YELLOW
from ..rng import Rng
from ..svg import el, glow_filter, linear_gradient, polygon, rect
from ._common import Art, anim_css, delay, field_color, partition, signature_slashes, triangle


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    art.defs += [linear_gradient("tg", GRADIENT), glow_filter("glow", RED_BRIGHT, round(s * 0.012, 2))]

    side = s * rng.uniform(0.64, 0.82)
    cx = w / 2 + rng.uniform(-0.07, 0.07) * w
    cy = h / 2 + rng.uniform(-0.03, 0.06) * h
    rot = rng.uniform(-8, 8) if rng.chance(0.6) else 0.0
    tri = triangle(cx, cy, side, rot)
    th = side * 0.866

    # Echo triangles: a TELIERS-gradient neon ghost and a thin white offset outline.
    ghost = triangle(cx + rng.uniform(-0.05, 0.05) * s, cy + rng.uniform(-0.04, 0.02) * s, side * rng.uniform(1.12, 1.3), rot + rng.uniform(-10, 10))
    art.body.append(polygon(ghost, fill="none", stroke="url(#tg)", stroke_width=round(s * 0.006, 2), opacity=0.55,
                            filter="url(#glow)", stroke_linejoin="round", pathLength=1,
                            cls="draw" if animate else None, style=delay(0.2) if animate else None))
    offset = triangle(cx + s * 0.035, cy + s * 0.03, side * 0.9, rot - 4)
    art.body.append(polygon(offset, fill="none", stroke=WHITE, stroke_width=round(s * 0.0025, 2), opacity=0.35))

    # Mondrian partition of the triangle's bounding box, clipped to the triangle.
    art.defs.append(el("clipPath", polygon(tri), id="a"))
    pad = side * 0.12  # covers the corners of a rotated triangle
    cells = partition(rng, cx - side / 2 - pad, cy - th / 2 - pad, cx + side / 2 + pad, cy + th / 2 + pad, rng.randint(3, 5), s * 0.05)
    cells.sort(key=lambda r: -(r[2] - r[0]) * (r[3] - r[1]))
    forced = [BLUE, YELLOW, RED]
    rng.shuffle(forced)
    seam = round(s * 0.0065, 2)
    pieces = []
    for i, (x0, y0, x1, y1) in enumerate(cells):
        color = forced[i] if i < 3 else field_color(rng)
        if i == 3 and rng.chance(0.35):
            color = "url(#tg)"
        pieces.append(rect(x0, y0, x1 - x0, y1 - y0, fill=color, stroke=WHITE, stroke_width=seam,
                           cls="pop" if animate else None, style=delay(0.1 + i * 0.07) if animate else None))
    # A black base bar like the logo's, sometimes (inside the triangle).
    if rng.chance(0.55):
        pieces.append(rect(tri[2][0] + side * 0.1, cy + th * 0.27, side * rng.uniform(0.34, 0.48), th * 0.2, fill=BLACK, stroke=WHITE,
                           stroke_width=seam, transform=f"rotate({round(rot, 2)} {round(cx, 2)} {round(cy, 2)})" if rot else None,
                           cls="fade" if animate else None, style=delay(1.0) if animate else None))
    art.body.append(el("g", pieces, clip_path="url(#a)"))

    # The white outline of the "A", with a red neon halo.
    art.body.append(polygon(tri, fill="none", stroke=WHITE, stroke_width=round(s * 0.011, 2), stroke_linejoin="round",
                            filter="url(#glow)", pathLength=1, cls="draw" if animate else None, style=delay(0.9) if animate else None))

    art.body += signature_slashes(rng, w, h, rng.randint(2, 4), rng.randint(2, 3), animate)
    if animate:
        art.css = anim_css()
    return art
