/* Ateliers Pehouet — hero.js (home only)
   1. A constellation of palette triangles drifting behind the logo, gently pushed by the pointer.
   2. The generator teaser cycling through the art styles. Static when animations are off. */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    AP.ready(function () {
        var canvas = AP.qs('[data-hero-canvas]');
        if (canvas && canvas.getContext) { constellation(canvas); }
        var cycler = AP.qs('[data-art-cycle]');
        if (cycler) { artCycle(cycler); }
    });

    function constellation(canvas) {
        var ctx = canvas.getContext('2d');
        var colors = [AP.palette.blueBright, AP.palette.yellow, AP.palette.redBright, AP.palette.white, AP.palette.amber, AP.palette.orange];
        var shapes = [];
        var w = 0, h = 0, dpr = 1;
        var pointer = { x: -9999, y: -9999 };
        var running = false, visible = true, raf = null;

        function resize() {
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            var r = canvas.getBoundingClientRect();
            w = r.width; h = r.height;
            canvas.width = Math.round(w * dpr);
            canvas.height = Math.round(h * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            var count = Math.round(AP.clamp(w * h / 16000, 24, 90));
            shapes = [];
            for (var i = 0; i < count; i++) {
                shapes.push({
                    x: Math.random() * w, y: Math.random() * h,
                    vx: AP.rand(-0.18, 0.18), vy: AP.rand(-0.14, 0.14),
                    s: AP.rand(3, 13), a: Math.random() * Math.PI * 2, va: AP.rand(-0.008, 0.008),
                    c: colors[i % colors.length], o: AP.rand(0.25, 0.8), outline: Math.random() < 0.35
                });
            }
            draw();
        }

        function tri(x, y, s, a) {
            ctx.beginPath();
            for (var k = 0; k < 3; k++) {
                var ang = a + k * (Math.PI * 2 / 3) - Math.PI / 2;
                var px = x + Math.cos(ang) * s, py = y + Math.sin(ang) * s;
                if (k === 0) { ctx.moveTo(px, py); } else { ctx.lineTo(px, py); }
            }
            ctx.closePath();
        }

        function draw() {
            ctx.clearRect(0, 0, w, h);
            // links between close triangles
            ctx.lineWidth = 1;
            for (var i = 0; i < shapes.length; i++) {
                for (var j = i + 1; j < shapes.length; j++) {
                    var dx = shapes[i].x - shapes[j].x, dy = shapes[i].y - shapes[j].y;
                    var d2 = dx * dx + dy * dy;
                    if (d2 < 120 * 120) {
                        ctx.globalAlpha = (1 - Math.sqrt(d2) / 120) * 0.22;
                        ctx.strokeStyle = AP.palette.white;
                        ctx.beginPath();
                        ctx.moveTo(shapes[i].x, shapes[i].y);
                        ctx.lineTo(shapes[j].x, shapes[j].y);
                        ctx.stroke();
                    }
                }
            }
            shapes.forEach(function (p) {
                ctx.globalAlpha = p.o;
                tri(p.x, p.y, p.s, p.a);
                if (p.outline) {
                    ctx.strokeStyle = p.c;
                    ctx.lineWidth = 1.4;
                    ctx.stroke();
                } else {
                    ctx.fillStyle = p.c;
                    ctx.fill();
                }
            });
            ctx.globalAlpha = 1;
        }

        function step() {
            shapes.forEach(function (p) {
                var dx = p.x - pointer.x, dy = p.y - pointer.y;
                var d2 = dx * dx + dy * dy;
                if (d2 < 140 * 140 && d2 > 1) {
                    var f = (1 - Math.sqrt(d2) / 140) * 0.6;
                    p.vx += (dx / Math.sqrt(d2)) * f * 0.12;
                    p.vy += (dy / Math.sqrt(d2)) * f * 0.12;
                }
                p.vx = AP.clamp(p.vx * 0.99, -1.2, 1.2);
                p.vy = AP.clamp(p.vy * 0.99, -1.2, 1.2);
                if (Math.abs(p.vx) < 0.05) { p.vx += AP.rand(-0.02, 0.02); }
                if (Math.abs(p.vy) < 0.05) { p.vy += AP.rand(-0.02, 0.02); }
                p.x += p.vx; p.y += p.vy; p.a += p.va;
                if (p.x < -20) { p.x = w + 20; } else if (p.x > w + 20) { p.x = -20; }
                if (p.y < -20) { p.y = h + 20; } else if (p.y > h + 20) { p.y = -20; }
            });
            draw();
            raf = window.requestAnimationFrame(step);
        }

        function start() {
            if (running || !visible || document.hidden || !AP.motion()) { return; }
            running = true;
            raf = window.requestAnimationFrame(step);
        }
        function stop() {
            running = false;
            if (raf) { window.cancelAnimationFrame(raf); raf = null; }
        }

        var host = canvas.parentElement;
        host.addEventListener('pointermove', function (e) {
            var r = canvas.getBoundingClientRect();
            pointer.x = e.clientX - r.left;
            pointer.y = e.clientY - r.top;
        }, { passive: true });
        host.addEventListener('pointerleave', function () { pointer.x = pointer.y = -9999; });

        AP.inView(canvas, function (isVisible) {
            visible = isVisible;
            if (isVisible) { start(); } else { stop(); }
        }, { threshold: 0, rootMargin: '0px' });
        document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else { start(); } });
        AP.onMotionChange(function (on) { if (on) { start(); } else { stop(); draw(); } });
        window.addEventListener('resize', AP.debounce(resize, 200));
        resize();
        start();
    }

    function artCycle(img) {
        var styles;
        try { styles = JSON.parse(img.getAttribute('data-styles') || '[]'); } catch (e) { styles = []; }
        if (styles.length < 2) { return; }
        var base = img.getAttribute('data-url');
        var seed = img.getAttribute('data-seed') || 'Pehouet';
        var altTemplate = img.getAttribute('data-alt') || '';
        var index = 0, timer = null, visible = false;

        function next() {
            if (!AP.motion() || !visible || document.hidden) { return; }
            index = (index + 1) % styles.length;
            var url = base + '?' + new URLSearchParams({ style: styles[index], seed: seed, size: 800 }).toString();
            var probe = new Image();
            probe.onload = function () {
                img.classList.add('is-swapping');
                window.setTimeout(function () {
                    img.src = url;
                    img.alt = altTemplate.replace('__STYLE__', styles[index]);
                    img.classList.remove('is-swapping');
                }, 450);
            };
            probe.src = url;
        }
        AP.inView(img, function (isVisible) {
            visible = isVisible;
            if (isVisible && !timer) { timer = window.setInterval(next, 4200); }
            if (!isVisible && timer) { window.clearInterval(timer); timer = null; }
        }, { threshold: 0.3 });
    }
})();
