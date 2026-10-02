import json
import threading
import unittest
import urllib.error
import urllib.request

from art_engine.server import make_server


class ServerTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = make_server("127.0.0.1", 0, quiet=True)
        cls.base = f"http://127.0.0.1:{cls.server.server_address[1]}"
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()

    def get(self, path):
        try:
            with urllib.request.urlopen(self.base + path, timeout=10) as r:
                return r.status, r.headers, r.read()
        except urllib.error.HTTPError as e:
            with e:
                return e.code, e.headers, e.read()

    def test_health(self):
        status, headers, body = self.get("/health")
        self.assertEqual(status, 200)
        data = json.loads(body)
        self.assertEqual(data["status"], "ok")
        self.assertEqual(len(data["styles"]), 8)

    def test_styles_and_palette(self):
        self.assertEqual(len(json.loads(self.get("/styles")[2])["styles"]), 8)
        self.assertEqual(json.loads(self.get("/palette")[2])["palette"]["yellow"], "#f8d449")

    def test_art(self):
        status, headers, body = self.get("/art?style=vitrail&seed=Quartier&width=400&height=400&animate=1")
        self.assertEqual(status, 200)
        self.assertTrue(headers["Content-Type"].startswith("image/svg+xml"))
        self.assertTrue(body.startswith(b"<svg"))
        self.assertEqual(body, self.get("/art?style=vitrail&seed=Quartier&width=400&height=400&animate=1")[2])

    def test_bad_params(self):
        for path in ("/art?style=nope", "/art?width=abc", "/art?width=10", "/art?seed=" + "x" * 300):
            status, headers, body = self.get(path)
            self.assertEqual(status, 400, path)
            self.assertIn("error", json.loads(body))

    def test_unknown_path(self):
        self.assertEqual(self.get("/nope")[0], 404)


if __name__ == "__main__":
    unittest.main()
