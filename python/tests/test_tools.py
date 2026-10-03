import importlib.util
import io
import sys
import json
import tempfile
import unittest
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

TOOLS = Path(__file__).resolve().parents[1] / "tools"


def load(name):
    spec = importlib.util.spec_from_file_location(name, TOOLS / f"{name}.py")
    module = importlib.util.module_from_spec(spec)
    sys.modules[name] = module
    spec.loader.exec_module(module)
    return module


animations = load("animations")
palette_audit = load("palette_audit")

CONFIG = """<?php return ['palette' => ['black' => '#000000', 'white' => '#fafcfd', 'red' => '#b32c2b', 'yellow' => '#f8d449'], 'x' => []];"""


def tree(tmp: Path, files: dict) -> Path:
    for rel, text in files.items():
        p = tmp / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text, encoding="utf-8")
    (tmp / "config").mkdir(exist_ok=True)
    (tmp / "config" / "atelier.php").write_text(CONFIG)
    (tmp / "resources" / "views").mkdir(parents=True, exist_ok=True)
    (tmp / "public" / "js").mkdir(parents=True, exist_ok=True)
    return tmp


GOOD = """
.a { animation: ap-one 1s; }
/** @anim group=brand demo=shape fr="Un" en="One" */
@keyframes ap-one { to { opacity: 1; } }
@media (min-width: 10px) {
  /** @anim group=ui demo=bar dur=3s fr="Deux" en="Two" */
  @keyframes ap-two { to { opacity: 1; } }
}
.b { animation-name: ap-two; }
"""


class AnimationsToolTest(unittest.TestCase):
    def run_tool(self, root, *args):
        out, err = io.StringIO(), io.StringIO()
        with redirect_stdout(out), redirect_stderr(err):
            code = animations.main(["--root", str(root), *args])
        return code, out.getvalue(), err.getvalue()

    def test_builds_catalogue(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = tree(Path(tmp), {"public/css/05-x.css": GOOD})
            code, out, err = self.run_tool(root, "--min", "2")
            self.assertEqual(code, 0, err)
            data = json.loads((root / "resources/content/animations.json").read_text())
            self.assertEqual(data["total"], 2)
            self.assertEqual([a["name"] for a in data["animations"]], ["ap-one", "ap-two"])
            self.assertEqual(data["animations"][1]["dur"], "3s")
            self.assertEqual(data["animations"][0]["label"], {"fr": "Un", "en": "One"})
            code, out, err = self.run_tool(root, "--min", "2", "--check")
            self.assertEqual(code, 0, err)

    def test_detects_problems(self):
        bad = GOOD + """
@keyframes ap-undocumented { to { opacity: 1 } }
/** @anim group=nope demo=shape fr="x" en="x" */
@keyframes ap-badgroup { to { opacity: 1 } }
/** @anim group=ui demo=shape fr="x" en="x" */
@keyframes ap-unused { to { opacity: 1 } }
/** @anim group=ui demo=shape fr="x" en="x" */
@keyframes noprefix { to { opacity: 1 } }
.c { animation: ap-undocumented 1s, ap-badgroup 1s, noprefix 1s; }
"""
        with tempfile.TemporaryDirectory() as tmp:
            root = tree(Path(tmp), {"public/css/05-x.css": bad})
            code, out, err = self.run_tool(root, "--min", "1")
            self.assertEqual(code, 1)
            self.assertIn("ap-undocumented", err)
            self.assertIn("unknown group", err)
            self.assertIn("ap-unused' is never used", err)
            self.assertIn("must start with 'ap-'", err)

    def test_minimum_and_stale(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = tree(Path(tmp), {"public/css/05-x.css": GOOD})
            code, out, err = self.run_tool(root, "--check")
            self.assertEqual(code, 1)
            self.assertIn("at least 100", err)
            self.assertIn("stale", err)

    def test_usage_in_blade_counts(self):
        css = '/** @anim group=page demo=shape fr="x" en="x" */\n@keyframes ap-blade { to { opacity: 1 } }\n'
        with tempfile.TemporaryDirectory() as tmp:
            root = tree(Path(tmp), {"public/css/pages/p.css": css, "resources/views/x.blade.php": '<i style="animation: ap-blade 1s"></i>'})
            code, out, err = self.run_tool(root, "--min", "1")
            self.assertEqual(code, 0, err)


class PaletteAuditTest(unittest.TestCase):
    def test_flags_foreign_colours(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = tree(Path(tmp), {
                "public/css/02-a.css": ".a{color:#123456;background:rgb(10 20 30 / .5);border-color:hotpink}\n#main{color:#fafcfd}\n/* #abcdef in a comment */",
                "public/css/01-tokens.css": ":root{--x:#123456}",
                "resources/views/v.blade.php": '<svg><path fill="#00ff00"/><path stroke="red"/><a href="#top">x</a></svg>',
                "public/js/a.js": "ctx.fillStyle = 'teal'; // #ff0000 comment",
            })
            problems = palette_audit.audit(root)
            colours = sorted(p["colour"] for p in problems)
            self.assertEqual(colours, sorted(["#123456", "rgb(10 20 30 / .5)", "hotpink", "#00ff00", "red", "teal"]))

    def test_scans_scripts_in_subdirectories(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = tree(Path(tmp), {
                "public/js/admin/uploader.js": "ctx.fillStyle = '#123456'; ctx.strokeStyle = '#fafcfd';",
            })
            problems = palette_audit.audit(root)
            self.assertEqual([(p["file"], p["colour"]) for p in problems], [("public/js/admin/uploader.js", "#123456")])

    def test_accepts_palette(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = tree(Path(tmp), {
                "public/css/02-a.css": ".a{color:#FAFCFD;background:rgb(179 44 43 / 0.4);fill:white;stroke:currentColor;border:1px solid black}",
                "resources/views/v.blade.php": '<path fill="#f8d449" stroke="#fff"/>',
            })
            self.assertEqual(palette_audit.audit(root), [])


if __name__ == "__main__":
    unittest.main()
