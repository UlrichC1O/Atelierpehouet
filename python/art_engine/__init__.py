"""Ateliers Pehouet generative-art engine.

Paints SVG compositions in the visual language of the Ateliers Pehouet logo: a black
canvas, Mondrian colour fields with white seams, triangles, the TELIERS yellow-to-red
gradient with a red neon glow, and signature-like slashes. Standard library only.

    from art_engine import render
    svg = render("pehouet", "Communauté", 800, 800)
"""

from .registry import STYLES, render, style_keys

__all__ = ["STYLES", "render", "style_keys", "__version__"]
__version__ = "1.0.0"
