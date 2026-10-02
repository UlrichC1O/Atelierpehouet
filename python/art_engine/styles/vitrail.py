"""vitrail — stained glass: jittered triangulated panes, dark lead, light pouring through."""

from __future__ import annotations

import math

from ..palette import AMBER, BLUE, BLUE_BRIGHT, INK, ORANGE, RED, RED_BRIGHT, STEEL, WHITE, YELLOW
from ..rng import Rng
from ..svg import el, polygon
from ._common import Art, anim_css, delay


def _window(kind: str, x0: float, y0: float, x1: float, y1: float) -> str:
    w = x1 - x0
    if kind == "triangle":
        return f"M{x0 + w / 2:.2f} {y0:.2f} L{x1:.2f} {y1:.2f} L{x0:.2f} {y1:.2f} Z"
    if kind == "arch":
        r = w / 2
        return (f"M{x0:.2f} {y1:.2f} L{x0:.2f} {y0 + r:.2f} A{r:.2f} {r:.2f} 0 0 1 {x1:.2f} {y0 + r:.2f} "
                f"L{x1:.2f} {y1:.2f} Z")
    # lancet (pointed gothic arch)
    r = w * 0.85
    return (f"M{x0:.2f} {y1:.2f} L{x0:.2f} {y0 + w * 0.55:.2f} A{r:.2f} {r:.2f} 0 0 1 {x0 + w / 2:.2f} {y0:.2f} "
            f"A{r:.2f} {r:.2f} 0 0 1 {x1:.2f} {y0 + w * 0.55:.2f} L{x1:.2f} {y1:.2f} Z")


def render(rng: Rng, w: int, h: int, animate: bool) -> Art:
    s = min(w, h)
    art = Art()
    kind = rng.choice(["arch", "lancet", "triangle"])
    ww = s * rng.uniform(0.5, 0.62) if kind != "triangle" else s * 0.78
    x0 = w / 2 - ww / 2
    x1 = w / 2 + ww / 2
    y0 = h * 0.08
    y1 = h * rng.uniform(0.7, 0.78)
    shape = _window(kind, x0, y0, x1, y1)

    # Light source glowing behind the glass + rays falling on the floor.
    art.defs.append(el("radialGradient", [
        el("stop", offset="0%", stop_color=WHITE, stop_opacity=0.95),
        el("stop", offset="35%", stop_color=YELLOW, stop_opacity=0.55),
        el("stop", offset="100%", stop_color=YELLOW, stop_opacity=0),
    ], id="light", cx=f"{rng.uniform(35, 65):.0f}%", cy="30%", r="70%"))
    art.defs.append(el("linearGradient", [
        el("stop", offset="0%", stop_color=YELLOW, stop_opacity=0.32),
        el("stop", offset="100%", stop_color=YELLOW, stop_opacity=0),
    ], id="ray", x1="0", y1="0", x2="0", y2="1"))
    art.defs.append(el("clipPath", el("path", d=shape), id="win"))

    floor_y = y1
    for i in range(rng.randint(4, 7)):
        fx = x0 + ww * (i + 0.5) / 6 + rng.uniform(-0.03, 0.03) * s
        spread = s * rng.uniform(0.05, 0.12)
        art.body.append(polygon([(fx - ww * 0.06, floor_y), (fx + ww * 0.06, floor_y), (fx + spread + s * 0.18, h), (fx - spread + s * 0.08, h)],
                                fill="url(#ray)", cls="shimmer" if animate else None, style=delay(i * 0.7) if animate else None))
    for color in (BLUE, RED, YELLOW):
        art.body.append(el("ellipse", cx=round(w / 2 + rng.uniform(-0.2, 0.2) * w, 2), cy=round(h * 0.9, 2), rx=round(s * rng.uniform(0.08, 0.16), 2),
                           ry=round(s * 0.025, 2), fill=color, opacity=0.25, cls="shimmer" if animate else None))

    # Panes: a jittered grid split into triangles.
    cols, rows = rng.randint(4, 7), rng.randint(6, 9)
    gx = [x0 + (x1 - x0) * i / cols for i in range(cols + 1)]
    gy = [y0 + (y1 - y0) * j / rows for j in range(rows + 1)]
    pts = [[(gx[i] + (rng.uniform(-0.3, 0.3) * ww / cols if 0 < i < cols else 0),
             gy[j] + (rng.uniform(-0.3, 0.3) * (y1 - y0) / rows if 0 < j < rows else 0)) for i in range(cols + 1)] for j in range(rows + 1)]
    weights = [(BLUE, 3), (BLUE_BRIGHT, 1.2), (RED, 2.6), (RED_BRIGHT, 1), (YELLOW, 2.2), (AMBER, 1.2), (ORANGE, 1), (WHITE, 0.5)]
    panes = [el("rect", x=round(x0, 2), y=round(y0, 2), width=round(ww, 2), height=round(y1 - y0, 2), fill="url(#light)")]
    lead = round(s * 0.011, 2)
    k = 0
    for j in range(rows):
        for i in range(cols):
            a, b, c, d = pts[j][i], pts[j][i + 1], pts[j + 1][i + 1], pts[j + 1][i]
            tris = [(a, b, c), (a, c, d)] if rng.chance(0.5) else [(a, b, d), (b, c, d)]
            for t in tris:
                cyy = (t[0][1] + t[1][1] + t[2][1]) / 3
                panes.append(polygon(t, fill=rng.weighted(weights), fill_opacity=round(rng.uniform(0.72, 0.92), 2),
                                     stroke=INK, stroke_width=lead, stroke_linejoin="round",
                                     cls="fade" if animate else None, style=delay((cyy - y0) / (y1 - y0) * 1.8 + rng.uniform(0, 0.3)) if animate else None))
                k += 1
    art.body.append(el("g", panes, clip_path="url(#win)"))
    art.body.append(el("path", d=shape, fill="none", stroke=STEEL, stroke_width=round(s * 0.022, 2), stroke_linejoin="round"))
    art.body.append(el("path", d=shape, fill="none", stroke=WHITE, stroke_width=round(s * 0.004, 2), stroke_linejoin="round", opacity=0.85,
                       pathLength=1, cls="draw" if animate else None, style=delay(0.4) if animate else None))
    if animate:
        art.css = anim_css()
    return art
