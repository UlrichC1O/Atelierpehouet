/* Ateliers Pehouet — fx.js: triangle cursor + trail (fine pointers), scroll progress, back-to-top. */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    AP.ready(function () {
        var root = document.documentElement;

        /* Scroll progress (TELIERS gradient bar). */
        var progress = AP.qs('[data-scroll-progress]');
        var backTop = AP.qs('[data-back-to-top]');
        var ticking = false;
        function onScroll() {
            var max = document.documentElement.scrollHeight - window.innerHeight;
            var ratio = max > 0 ? AP.clamp(window.scrollY / max, 0, 1) : 0;
            if (progress) { progress.style.setProperty('--progress', ratio.toFixed(4)); }
            if (backTop) { backTop.classList.toggle('is-visible', window.scrollY > window.innerHeight * 0.9); }
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; window.requestAnimationFrame(onScroll); }
        }, { passive: true });
        window.addEventListener('resize', AP.debounce(onScroll, 150));
        onScroll();

        if (backTop) {
            backTop.addEventListener('click', function () {
                backTop.classList.add('is-launching');
                window.setTimeout(function () { backTop.classList.remove('is-launching'); }, 750);
                window.scrollTo({ top: 0, behavior: AP.motion() ? 'smooth' : 'auto' });
                var main = document.getElementById('main');
                if (main) { main.focus({ preventScroll: true }); }
            });
        }

        /* Cursor follower + triangle trail: decorative, fine pointers only, motion on only. */
        var cursor = AP.qs('[data-cursor]');
        if (!cursor || !AP.finePointer()) { return; }

        var x = -100, y = -100, cx = -100, cy = -100;
        var raf = null;
        var lastTrail = 0;
        var trailCount = 0;
        var colors = [AP.palette.blueBright, AP.palette.yellow, AP.palette.redBright, AP.palette.white, AP.palette.amber];

        function loop() {
            cx = AP.lerp(cx, x, 0.22);
            cy = AP.lerp(cy, y, 0.22);
            cursor.style.transform = 'translate3d(' + cx.toFixed(1) + 'px,' + cy.toFixed(1) + 'px,0)';
            if (Math.abs(cx - x) > 0.2 || Math.abs(cy - y) > 0.2) {
                raf = window.requestAnimationFrame(loop);
            } else {
                raf = null;
            }
        }

        function spawnTrail(px, py) {
            if (trailCount > 18) { return; }
            var t = document.createElement('span');
            t.className = 'fx-trail';
            t.setAttribute('aria-hidden', 'true');
            t.style.left = px + 'px';
            t.style.top = py + 'px';
            t.style.setProperty('--c', AP.pick(colors));
            t.style.setProperty('--s', Math.round(AP.rand(5, 11)) + 'px');
            t.style.setProperty('--rot', Math.round(AP.rand(-200, 200)) + 'deg');
            t.style.setProperty('--tx', Math.round(AP.rand(-8, 8)) + 'px');
            trailCount++;
            t.addEventListener('animationend', function () { t.remove(); trailCount--; });
            document.body.appendChild(t);
            // Safety net if animationend never fires (e.g. animations collapsed).
            window.setTimeout(function () { if (t.isConnected) { t.remove(); trailCount--; } }, 1500);
        }

        function onMove(event) {
            if (!AP.motion()) { return; }
            x = event.clientX;
            y = event.clientY;
            root.classList.add('has-cursor');
            if (!raf) { raf = window.requestAnimationFrame(loop); }
            var now = performance.now();
            if (now - lastTrail > 45) {
                lastTrail = now;
                spawnTrail(x, y);
            }
            var target = event.target.closest && event.target.closest('a, button, [role="button"], label, summary, input, select, textarea');
            root.classList.toggle('cursor-active', !!target);
        }

        document.addEventListener('pointermove', onMove, { passive: true });
        document.addEventListener('pointerleave', function () { root.classList.remove('has-cursor'); });
        window.addEventListener('blur', function () { root.classList.remove('has-cursor'); });
        AP.onMotionChange(function (enabled) {
            if (!enabled) { root.classList.remove('has-cursor', 'cursor-active'); }
        });
    });
})();
