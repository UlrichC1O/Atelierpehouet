"""Deterministic randomness: the same (style, seed) always paints the same artwork."""

from __future__ import annotations

import hashlib
import math
import random
from typing import Sequence, TypeVar

T = TypeVar("T")


class Rng(random.Random):
    """random.Random seeded from SHA-256 (never Python's salted hash())."""

    def __init__(self, *parts: object) -> None:
        digest = hashlib.sha256("␟".join(str(p) for p in parts).encode("utf-8")).hexdigest()
        super().__init__(int(digest[:32], 16))

    def weighted(self, items: Sequence[tuple[T, float]]) -> T:
        total = sum(w for _, w in items)
        r = self.random() * total
        for item, weight in items:
            r -= weight
            if r <= 0:
                return item
        return items[-1][0]

    def jitter(self, value: float, amount: float) -> float:
        return value + self.uniform(-amount, amount)

    def gauss_clamped(self, mu: float, sigma: float, lo: float, hi: float) -> float:
        return min(hi, max(lo, self.gauss(mu, sigma)))

    def chance(self, p: float) -> bool:
        return self.random() < p


def rotate(x: float, y: float, cx: float, cy: float, degrees: float) -> tuple[float, float]:
    a = math.radians(degrees)
    dx, dy = x - cx, y - cy
    return cx + dx * math.cos(a) - dy * math.sin(a), cy + dx * math.sin(a) + dy * math.cos(a)
