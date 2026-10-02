/* Ateliers Pehouet — motion.js: search, pause/play, speed and click-to-replay on the animation catalogue. */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    AP.ready(function () {
        var grid = AP.qs('[data-motion-grid]');
        if (!grid) { return; }
        var tiles = AP.qsa('[data-motion-tile]', grid);
        var search = AP.qs('[data-motion-search]');
        var count = AP.qs('[data-motion-count]');
        var none = AP.qs('[data-motion-none]');
        var pause = AP.qs('[data-motion-pause]');

        function visibleCount() {
            return tiles.filter(function (t) { return !t.classList.contains('is-hidden') && !t.classList.contains('is-search-hidden'); }).length;
        }
        function announce() {
            var n = visibleCount();
            if (count) { count.textContent = (count.getAttribute('data-template') || '{count}').replace('{count}', n); }
            if (none) { none.hidden = n !== 0; }
        }

        if (search) {
            search.addEventListener('input', AP.debounce(function () {
                var q = search.value.trim().toLowerCase();
                tiles.forEach(function (t) {
                    t.classList.toggle('is-search-hidden', q !== '' && (t.getAttribute('data-name') || '').indexOf(q) === -1);
                });
                announce();
            }, 150));
        }
        AP.on('filter', function () { window.setTimeout(announce, 0); });

        AP.qsa('[data-motion-speed]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                grid.style.setProperty('--mspeed', btn.getAttribute('data-motion-speed'));
                AP.qsa('[data-motion-speed]').forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
            });
        });

        if (pause) {
            pause.addEventListener('click', function () {
                var frozen = grid.classList.toggle('is-frozen');
                pause.setAttribute('aria-pressed', frozen ? 'true' : 'false');
                var label = pause.querySelector('.btn__label');
                if (label) { label.textContent = pause.getAttribute(frozen ? 'data-label-play' : 'data-label-pause'); }
            });
        }

        function replay(tile) {
            AP.qsa('.mdemo__el', tile).forEach(function (el) {
                var name = el.style.animationName;
                el.style.animationName = 'none';
                void el.getBoundingClientRect();
                el.style.animationName = name;
            });
        }
        grid.addEventListener('click', function (e) {
            var tile = e.target.closest('[data-motion-tile]');
            if (tile) { replay(tile); }
        });
        grid.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-motion-tile]')) {
                e.preventDefault();
                replay(e.target);
            }
        });

        announce();
    });
})();
