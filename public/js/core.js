/* Ateliers Pehouet — core.js: the shared window.AP toolkit (docs/ARCHITECTURE.md §9).
   Also pauses .ap-anim-scope regions off-screen and wires [data-motion-toggle]. */
(function () {
    'use strict';

    var root = document.documentElement;
    var reducedQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var fineQuery = window.matchMedia ? window.matchMedia('(hover: hover) and (pointer: fine)') : null;

    var palette = {
        black: '#000000', white: '#fafcfd', blue: '#265fa5', yellow: '#f8d449', red: '#b32c2b',
        amber: '#e3a94e', orange: '#c96338', smoke: '#898789', blueBright: '#4f8edc', redBright: '#e8433b'
    };

    var AP = {
        version: '1.0',
        root: root,
        palette: palette,
        colors: [palette.blue, palette.yellow, palette.red, palette.white, palette.amber, palette.orange],

        qs: function (selector, scope) { return (scope || document).querySelector(selector); },
        qsa: function (selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); },

        ready: function (fn) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fn, { once: true });
            } else {
                fn();
            }
        },

        motion: function () {
            if (root.classList.contains('motion-off')) { return false; }
            if (root.classList.contains('motion-on')) { return true; }
            return !(reducedQuery && reducedQuery.matches);
        },

        setMotion: function (enabled) {
            enabled = !!enabled;
            root.classList.toggle('motion-off', !enabled);
            // "on" must beat prefers-reduced-motion in CSS, so it is explicit only when needed.
            root.classList.toggle('motion-on', enabled && !!(reducedQuery && reducedQuery.matches));
            try { window.localStorage.setItem('ap-motion', enabled ? 'on' : 'off'); } catch (e) { /* ignore */ }
            syncToggles();
            AP.emit('motion', { enabled: enabled });
        },

        onMotionChange: function (fn) {
            AP.on('motion', function (event) { fn(event.detail.enabled); });
        },

        inView: function (el, cb, options) {
            options = options || {};
            if (!('IntersectionObserver' in window)) {
                cb(true, null);
                return function () {};
            }
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    cb(entry.isIntersecting, entry);
                    if (entry.isIntersecting && options.once) { observer.disconnect(); }
                });
            }, {
                threshold: options.threshold == null ? 0.15 : options.threshold,
                rootMargin: options.rootMargin || '0px 0px -8% 0px'
            });
            observer.observe(el);
            return function () { observer.disconnect(); };
        },

        throttle: function (fn, ms) {
            var last = 0, timer = null;
            return function () {
                var now = Date.now(), args = arguments, self = this;
                var wait = ms - (now - last);
                if (wait <= 0) {
                    last = now;
                    fn.apply(self, args);
                } else if (!timer) {
                    timer = setTimeout(function () { last = Date.now(); timer = null; fn.apply(self, args); }, wait);
                }
            };
        },

        debounce: function (fn, ms) {
            var timer = null;
            return function () {
                var args = arguments, self = this;
                clearTimeout(timer);
                timer = setTimeout(function () { fn.apply(self, args); }, ms);
            };
        },

        clamp: function (v, min, max) { return Math.min(max, Math.max(min, v)); },
        lerp: function (a, b, t) { return a + (b - a) * t; },
        rand: function (min, max) { return min + Math.random() * (max - min); },
        pick: function (arr) { return arr[Math.floor(Math.random() * arr.length)]; },
        finePointer: function () { return !!(fineQuery && fineQuery.matches); },

        emit: function (name, detail) {
            document.dispatchEvent(new CustomEvent('ap:' + name, { detail: detail || {} }));
        },
        on: function (name, fn) {
            document.addEventListener('ap:' + name, fn);
        }
    };

    window.AP = AP;

    /* --- Motion toggles ---------------------------------------------------- */
    function syncToggles() {
        var on = AP.motion();
        AP.qsa('[data-motion-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            var label = btn.querySelector('[data-motion-label]');
            if (label) { label.textContent = on ? btn.getAttribute('data-label-on') : btn.getAttribute('data-label-off'); }
        });
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest && event.target.closest('[data-motion-toggle]');
        if (btn) { AP.setMotion(!AP.motion()); }
    });

    if (reducedQuery && reducedQuery.addEventListener) {
        reducedQuery.addEventListener('change', function () {
            var stored = null;
            try { stored = window.localStorage.getItem('ap-motion'); } catch (e) { /* ignore */ }
            if (!stored) {
                root.classList.toggle('motion-off', reducedQuery.matches);
                syncToggles();
                AP.emit('motion', { enabled: AP.motion() });
            }
        });
    }

    /* --- Pause animation scopes off-screen or in hidden tabs --------------- */
    function watchScopes() {
        var scopes = AP.qsa('.ap-anim-scope');
        if (!('IntersectionObserver' in window) || !scopes.length) { return; }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                entry.target.classList.toggle('is-paused', !entry.isIntersecting || document.hidden);
                entry.target.__apVisible = entry.isIntersecting;
            });
        }, { rootMargin: '150px 0px' });
        scopes.forEach(function (scope) { observer.observe(scope); });
        AP.observeScope = function (el) { observer.observe(el); };

        document.addEventListener('visibilitychange', function () {
            scopes.forEach(function (scope) {
                scope.classList.toggle('is-paused', document.hidden || scope.__apVisible === false);
            });
        });
    }

    AP.ready(function () {
        syncToggles();
        watchScopes();
    });
})();
