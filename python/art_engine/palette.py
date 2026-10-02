"""The logo palette (mirrors config/atelier.php and public/css/01-tokens.css)."""

BLACK = "#000000"
INK = "#08080b"
COAL = "#121216"
GRAPHITE = "#1d1d23"
STEEL = "#2c2c34"
SMOKE = "#898789"
MIST = "#c4c2c5"
WHITE = "#fafcfd"
BLUE = "#265fa5"
BLUE_BRIGHT = "#4f8edc"
BLUE_DEEP = "#173d6b"
YELLOW = "#f8d449"
AMBER = "#e3a94e"
ORANGE = "#c96338"
RED = "#b32c2b"
RED_BRIGHT = "#e8433b"
RED_DEEP = "#6e1a1a"

PALETTE = {
    "black": BLACK, "ink": INK, "coal": COAL, "graphite": GRAPHITE, "steel": STEEL,
    "smoke": SMOKE, "mist": MIST, "white": WHITE,
    "blue": BLUE, "blue_bright": BLUE_BRIGHT, "blue_deep": BLUE_DEEP,
    "yellow": YELLOW, "amber": AMBER, "orange": ORANGE,
    "red": RED, "red_bright": RED_BRIGHT, "red_deep": RED_DEEP,
}

# The four colour fields of the triangle "A" (+ black), weighted roughly like the logo.
FIELDS = [(BLUE, 3), (YELLOW, 3), (RED, 3), (WHITE, 2), (BLACK, 3)]

# Dark fields used to keep compositions mostly in shadow, like the logo's black ground.
DARKS = [BLACK, INK, COAL, GRAPHITE, BLUE_DEEP, RED_DEEP]

# The TELIERS lettering: yellow "T" → amber → orange "E" → red "RS".
GRADIENT = [YELLOW, AMBER, ORANGE, RED_BRIGHT, RED]

# Every colour an artwork may contain.
ALLOWED = frozenset(PALETTE.values())


def gradient_at(t: float) -> str:
    """Nearest TELIERS gradient stop for t in [0, 1] (stays on-palette)."""
    t = min(1.0, max(0.0, t))
    return GRADIENT[min(len(GRADIENT) - 1, int(round(t * (len(GRADIENT) - 1))))]
