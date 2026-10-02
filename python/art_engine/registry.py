"""Style registry and the top-level render() entry point."""

from __future__ import annotations

from functools import lru_cache
from types import ModuleType

from .rng import Rng
from .styles import eclats, mondrian, mosaique, pehouet, prisme, soleil, tissage, vitrail
from .svg import document

STYLES: dict[str, dict] = {
    "pehouet": {"module": pehouet, "name": {"fr": "Pehouet", "en": "Pehouet"},
                "description": {"fr": "Le triangle du logo, redécoupé en nouveaux champs Mondrian.",
                                "en": "The logo's triangle, re-cut into new Mondrian fields."}},
    "mondrian": {"module": mondrian, "name": {"fr": "Mondrian", "en": "Mondrian"},
                 "description": {"fr": "Rectangles imbriqués, joints blancs et couleurs franches sur le noir.",
                                 "en": "Nested rectangles, white seams and bold colours on black."}},
    "prisme": {"module": prisme, "name": {"fr": "Prisme", "en": "Prism"},
               "description": {"fr": "Des triangles qui se divisent à l'infini, dans le dégradé TELIERS.",
                               "en": "Endlessly dividing triangles in the TELIERS gradient."}},
    "mosaique": {"module": mosaique, "name": {"fr": "Mosaïque", "en": "Mosaic"},
                 "description": {"fr": "Un pavage de tesselles triangulaires d'où surgit un grand triangle.",
                                 "en": "Triangular tesserae from which a great triangle emerges."}},
    "vitrail": {"module": vitrail, "name": {"fr": "Vitrail", "en": "Stained glass"},
                "description": {"fr": "Verre coloré, plomb sombre et lumière qui traverse.",
                                "en": "Coloured glass, dark lead and light pouring through."}},
    "eclats": {"module": eclats, "name": {"fr": "Éclats", "en": "Shards"},
               "description": {"fr": "Les traits de la signature et des éclats autour d'un cœur néon.",
                               "en": "Signature strokes and shards around a neon core."}},
    "soleil": {"module": soleil, "name": {"fr": "Soleil", "en": "Sun"},
               "description": {"fr": "Des rayons triangulaires et des anneaux autour d'un foyer.",
                               "en": "Triangular rays and rings around a burning focus."}},
    "tissage": {"module": tissage, "name": {"fr": "Tissage", "en": "Weave"},
                "description": {"fr": "Des bandes de couleur tissées dessus-dessous, cousues de blanc.",
                                "en": "Colour bands woven over and under, stitched in white."}},
}

MIN_SIZE = 64
MAX_SIZE = 2400
MAX_SEED = 200


def style_keys() -> list[str]:
    return list(STYLES)


def normalize_seed(seed: str | None) -> str:
    seed = (seed or "").strip()
    return seed[:MAX_SEED] if seed else "pehouet"


def validate(style: str, width: int, height: int) -> None:
    if style not in STYLES:
        raise ValueError(f"unknown style {style!r}; expected one of: {', '.join(STYLES)}")
    for name, value in (("width", width), ("height", height)):
        if not isinstance(value, int) or not MIN_SIZE <= value <= MAX_SIZE:
            raise ValueError(f"{name} must be an integer between {MIN_SIZE} and {MAX_SIZE}")


@lru_cache(maxsize=256)
def render(style: str, seed: str | None = None, width: int = 800, height: int = 800, animate: bool = False) -> str:
    """Paint one artwork. Deterministic: same arguments → byte-identical SVG."""
    validate(style, width, height)
    seed = normalize_seed(seed)
    module: ModuleType = STYLES[style]["module"]
    art = module.render(Rng(style, seed, width, height), width, height, animate)
    title = f"Ateliers Pehouet · {STYLES[style]['name']['fr']} · {seed}"
    return document(width, height, title, art.body, art.defs, art.css)
