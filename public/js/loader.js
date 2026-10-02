/* Ateliers Pehouet — loader.js: dismiss the intro loader (on load, at most 2.2 s), once per session. */
(function () {
    'use strict';
    var root = document.documentElement;
    if (!root.classList.contains('is-loading')) { return; }

    var done = false;
    function finish() {
        if (done) { return; }
        done = true;
        try { window.sessionStorage.setItem('ap-intro', '1'); } catch (e) { /* ignore */ }
        root.classList.add('is-loaded');
        root.classList.remove('is-loading');
        window.setTimeout(function () { root.classList.remove('is-loaded'); }, 900);
    }

    var start = (window.performance && performance.now()) || 0;
    function onLoad() {
        // Let the triangle finish assembling (≈ 1.4 s) before the curtain rises.
        var elapsed = ((window.performance && performance.now()) || 0) - start;
        window.setTimeout(finish, Math.max(0, 1400 - elapsed));
    }

    if (document.readyState === 'complete') { onLoad(); } else { window.addEventListener('load', onLoad, { once: true }); }
    window.setTimeout(finish, 2200);
    // Back/forward cache: never show a stale loader.
    window.addEventListener('pageshow', function (event) { if (event.persisted) { finish(); } });
})();
