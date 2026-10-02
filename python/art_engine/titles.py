"""Deterministic, poetic bilingual titles for artworks ("Triangle du matin" / "Morning Triangle")."""

from __future__ import annotations

from .rng import Rng

NOUNS = [
    ("Triangle", "Triangle"), ("Lumière", "Light"), ("Éclat", "Shard"), ("Fresque", "Fresco"),
    ("Prisme", "Prism"), ("Rythme", "Rhythm"), ("Chant", "Song"), ("Passage", "Passage"),
    ("Seuil", "Threshold"), ("Horizon", "Horizon"), ("Constellation", "Constellation"),
    ("Mosaïque", "Mosaic"), ("Vitrail", "Window"), ("Soleil", "Sun"), ("Tissage", "Weave"),
    ("Signature", "Signature"), ("Aube", "Dawn"), ("Flamme", "Flame"), ("Ronde", "Round"),
    ("Fenêtre", "Window"), ("Mémoire", "Memory"), ("Danse", "Dance"),
]

COMPLEMENTS = [
    ("du matin", "Morning"), ("de minuit", "Midnight"), ("des rues", "Street"), ("du marché", "Market"),
    ("de la place", "Square"), ("des enfants", "Children's"), ("du fleuve", "River"), ("de l’atelier", "Studio"),
    ("d’été", "Summer"), ("de pluie", "Rain"), ("des voisins", "Neighbours'"), ("du dimanche", "Sunday"),
    ("du quartier", "Neighbourhood"), ("de fête", "Festival"), ("d’orage", "Storm"), ("du soir", "Evening"),
]


def title(style: str, seed: str) -> dict[str, str]:
    rng = Rng("title", style, seed)
    noun_fr, noun_en = rng.choice(NOUNS)
    comp_fr, comp_en = rng.choice(COMPLEMENTS)
    return {"fr": f"{noun_fr} {comp_fr}", "en": f"{comp_en} {noun_en}"}
