import re
import unittest
import xml.etree.ElementTree as ET

from art_engine import render, style_keys
from art_engine.palette import ALLOWED
from art_engine.titles import title

HEX = re.compile(r"#[0-9a-fA-F]{6}\b")


class EngineTest(unittest.TestCase):
    def test_every_style_renders_valid_svg_at_many_sizes(self):
        for style in style_keys():
            for w, h in ((64, 64), (800, 800), (1200, 630), (2400, 2400)):
                with self.subTest(style=style, size=(w, h)):
                    svg = render(style, "Communauté", w, h)
                    root = ET.fromstring(svg)
                    self.assertTrue(root.tag.endswith("svg"))
                    self.assertEqual(root.get("viewBox"), f"0 0 {w} {h}")
                    self.assertEqual(root.get("width"), str(w))

    def test_renders_are_deterministic(self):
        for style in style_keys():
            render.cache_clear()
            first = render(style, "seed-42", 800, 800, True)
            render.cache_clear()
            self.assertEqual(first, render(style, "seed-42", 800, 800, True), style)

    def test_different_seeds_and_styles_differ(self):
        self.assertNotEqual(render("pehouet", "a"), render("pehouet", "b"))
        self.assertNotEqual(render("pehouet", "a"), render("mondrian", "a"))

    def test_only_palette_colours(self):
        for style in style_keys():
            for animate in (False, True):
                colours = {c.lower() for c in HEX.findall(render(style, "palette", 800, 800, animate))}
                self.assertTrue(colours <= ALLOWED, f"{style}: {colours - ALLOWED}")

    def test_hostile_seeds_are_escaped(self):
        for seed in ('<script>alert(1)</script>', '"><img src=x onerror=alert(1)>', "a & b < c", "é漢字🎨", "x" * 200, "\x00\x1fctl"):
            for style in style_keys():
                svg = render(style, seed, 300, 300)
                ET.fromstring(svg)
                self.assertNotIn("<script", svg)
                self.assertNotIn("onerror", svg.split("<title>")[0])

    def test_no_external_references_or_scripts(self):
        for style in style_keys():
            svg = render(style, "refs", 400, 400, True)
            self.assertNotIn("<script", svg)
            self.assertNotIn("href=", svg)
            self.assertNotIn("foreignObject", svg)

    def test_animated_variant_respects_reduced_motion(self):
        for style in style_keys():
            svg = render(style, "motion", 400, 400, True)
            self.assertIn("prefers-reduced-motion", svg)
            self.assertIn("@keyframes", svg)
            self.assertNotIn("@keyframes", render(style, "motion", 400, 400, False))

    def test_files_stay_compact(self):
        for style in style_keys():
            for seed in ("one", "two", "three"):
                self.assertLess(len(render(style, seed, 800, 800).encode()), 90_000, f"{style}/{seed}")

    def test_invalid_arguments(self):
        with self.assertRaises(ValueError):
            render("nope", "x")
        with self.assertRaises(ValueError):
            render("pehouet", "x", 10, 10)
        with self.assertRaises(ValueError):
            render("pehouet", "x", 5000, 800)

    def test_empty_seed_defaults(self):
        self.assertEqual(render("soleil", ""), render("soleil", "pehouet"))

    def test_titles_are_bilingual_and_stable(self):
        t = title("vitrail", "aube-vitrail-1")
        self.assertEqual(set(t), {"fr", "en"})
        self.assertEqual(t, title("vitrail", "aube-vitrail-1"))
        self.assertTrue(all(t.values()))


if __name__ == "__main__":
    unittest.main()
