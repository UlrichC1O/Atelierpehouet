# Ateliers Pehouet — project guide

Website for **Ateliers Pehouet** (*L'art au service de la communauté*): Laravel 13 + hand-written
HTML/CSS/vanilla JS (no build step) + a Python generative-art engine.

**Read `docs/ARCHITECTURE.md` before changing anything** — it is the binding contract (palette, file
ownership, routes & view data, CSS/JS/animation conventions, component APIs, Python engine API).

Key rules:
- Logo colours only (tokens in `public/css/01-tokens.css`); never raw colours elsewhere.
- Every `@keyframes` is prefixed `ap-` and preceded by an `/** @anim … */` doc comment; keep ≥ 100.
- No inline `<script>` or `on*=` attributes (CSP). JS goes in `public/js/*.js` (deferred IIFEs).
- All copy through `__()`; `lang/fr` and `lang/en` must have identical keys.
- Each service = `resources/content/services/{slug}.php` + `services/scenes/{slug}.blade.php` + `public/css/scenes/{slug}.css`.

Checks: `php bin/blade-lint.php resources/views`, `php artisan test`,
`cd python && python3 -m unittest discover -s tests -t .`, `python3 python/tools/animations.py --check`,
`python3 python/tools/palette_audit.py`. Prefix PHP/Composer commands with `XDEBUG_MODE=off` in this
Codespace for speed.
