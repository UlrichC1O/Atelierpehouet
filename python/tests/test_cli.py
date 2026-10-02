import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

PYTHON_DIR = Path(__file__).resolve().parents[1]


def run(*args, check=True):
    env = dict(os.environ, PYTHONPATH=str(PYTHON_DIR), PYTHONIOENCODING="utf-8")
    proc = subprocess.run([sys.executable, "-m", "art_engine", *args], capture_output=True, text=True, env=env, cwd=PYTHON_DIR)
    if check and proc.returncode != 0:
        raise AssertionError(proc.stderr)
    return proc


class CliTest(unittest.TestCase):
    def test_render_prints_svg(self):
        out = run("render", "--style", "vitrail", "--seed", "Quartier", "--width", "300").stdout
        self.assertTrue(out.startswith("<svg"))
        self.assertIn("</svg>", out)

    def test_render_animate(self):
        self.assertIn("@keyframes", run("render", "--style", "eclats", "--animate").stdout)

    def test_styles_json(self):
        styles = json.loads(run("styles", "--json").stdout)
        self.assertEqual(len(styles), 8)
        self.assertIn("fr", styles[0]["name"])

    def test_bad_arguments_exit_2(self):
        self.assertEqual(run("render", "--style", "nope", check=False).returncode, 2)
        self.assertEqual(run("render", "--width", "9", check=False).returncode, 2)

    def test_batch_writes_files(self):
        with tempfile.TemporaryDirectory() as tmp:
            spec = Path(tmp) / "spec.json"
            spec.write_text(json.dumps({"items": [
                {"file": "services/a-1.svg", "style": "mondrian", "seed": "a-1", "width": 400, "height": 400},
                {"file": "b.svg", "style": "soleil", "seed": "b"},
            ]}))
            result = json.loads(run("batch", "--spec", str(spec), "--out", str(Path(tmp) / "out")).stdout)
            self.assertEqual(result["written"], 2)
            self.assertTrue((Path(tmp) / "out" / "services" / "a-1.svg").is_file())

    def test_batch_rejects_path_traversal(self):
        with tempfile.TemporaryDirectory() as tmp:
            spec = Path(tmp) / "spec.json"
            spec.write_text(json.dumps({"items": [{"file": "../evil.svg", "style": "mondrian", "seed": "x"}]}))
            proc = run("batch", "--spec", str(spec), "--out", str(Path(tmp) / "out"), check=False)
            self.assertEqual(proc.returncode, 2)
            self.assertFalse((Path(tmp) / "evil.svg").exists())

    def test_gallery_manifest(self):
        with tempfile.TemporaryDirectory() as tmp:
            run("gallery", "--out", tmp, "--per-style", "2", "--size", "200")
            manifest = json.loads((Path(tmp) / "gallery" / "manifest.json").read_text())
            self.assertEqual(manifest["version"], 1)
            self.assertEqual(len(manifest["items"]), 16)
            item = manifest["items"][0]
            self.assertEqual(set(item), {"file", "style", "seed", "title", "width", "height"})
            self.assertEqual(set(item["title"]), {"fr", "en"})
            self.assertTrue((Path(tmp) / item["file"]).is_file())


if __name__ == "__main__":
    unittest.main()
