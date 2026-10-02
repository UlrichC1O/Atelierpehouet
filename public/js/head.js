/* Ateliers Pehouet — head.js (blocking, tiny): set state classes before first paint.
   html.js / html.motion-off / html.motion-on / html.is-loading (first page of the session). */
(function () {
    'use strict';
    var root = document.documentElement;
    root.classList.remove('no-js');
    root.classList.add('js');

    var pref = null;
    try { pref = window.localStorage.getItem('ap-motion'); } catch (e) { /* storage blocked */ }
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (pref === 'off') {
        root.classList.add('motion-off');
    } else if (pref === 'on') {
        root.classList.add('motion-on');
    } else if (reduced) {
        root.classList.add('motion-off');
    }

    var motion = !root.classList.contains('motion-off');
    var seen = null;
    try { seen = window.sessionStorage.getItem('ap-intro'); } catch (e) { seen = '1'; }
    if (motion && seen !== '1') {
        root.classList.add('is-loading');
    }
})();
