/* Ateliers Pehouet — effects.js
   Declarative behaviours driven by data-* attributes (docs/ARCHITECTURE.md §9):
   data-reveal · data-reveal-stagger · data-inview · data-split · data-count-to · data-tilt ·
   data-spotlight · data-magnetic · data-ripple · data-parallax · data-marquee · data-typewriter ·
   data-burst · data-glitch · data-lightbox · data-filter-group. Everything degrades without JS
   and stays still when the visitor turns animations off. */
(function () {
    'use strict';

    var AP = window.AP;
    if (!AP) {
        if (window.console) { console.warn('effects.js: window.AP (core.js) is missing'); }
        document.documentElement.classList.add('reveal-ready');
        return;
    }

    var root = document.documentElement;
    var lang = (root.lang || 'fr').slice(0, 2);
    var T = {
        fr: { dialog: 'Visionneuse d’œuvres', close: 'Fermer', prev: 'Œuvre précédente', next: 'Œuvre suivante' },
        en: { dialog: 'Artwork viewer', close: 'Close', prev: 'Previous artwork', next: 'Next artwork' }
    }[lang] || null;
    T = T || { dialog: 'Visionneuse d’œuvres', close: 'Fermer', prev: 'Œuvre précédente', next: 'Œuvre suivante' };

    function svgIcon(d) {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + d + '"/></svg>';
    }

    /* ------------------------------------------------------------------ reveal */
    function initReveals() {
        AP.qsa('[data-reveal-stagger]').forEach(function (parent) {
            var step = parseInt(parent.getAttribute('data-reveal-stagger'), 10) || 80;
            var kids = AP.qsa('[data-reveal]', parent).filter(function (el) { return !el.hasAttribute('data-reveal-delay'); });
            kids.forEach(function (el, i) { el.style.setProperty('--reveal-delay', (i * step) + 'ms'); });
        });
        AP.qsa('[data-reveal-delay]').forEach(function (el) {
            el.style.setProperty('--reveal-delay', (parseInt(el.getAttribute('data-reveal-delay'), 10) || 0) + 'ms');
        });

        var targets = AP.qsa('[data-reveal], [data-inview]');
        if (!AP.motion() || !('IntersectionObserver' in window)) {
            targets.forEach(function (el) { el.classList.add('is-revealed'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
        targets.forEach(function (el) { io.observe(el); });
        AP.observeReveal = function (el) { io.observe(el); };
    }

    /* --------------------------------------------------------------- split text */
    function splitElement(el) {
        if (el.__apSplit) { return; }
        el.__apSplit = true;
        var mode = el.getAttribute('data-split') === 'chars' ? 'chars' : 'words';
        var original = el.textContent.replace(/\s+/g, ' ').trim();
        var index = 0;

        function processNode(node) {
            if (node.nodeType === 3) {
                var parts = node.textContent.split(/(\s+)/);
                var frag = document.createDocumentFragment();
                parts.forEach(function (part) {
                    if (part === '') { return; }
                    if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
                    if (mode === 'words') {
                        var w = document.createElement('span');
                        w.className = 'split__unit';
                        w.style.setProperty('--i', index++);
                        w.textContent = part;
                        frag.appendChild(w);
                    } else {
                        var word = document.createElement('span');
                        word.className = 'split__word';
                        Array.from(part).forEach(function (ch) {
                            var c = document.createElement('span');
                            c.className = 'split__unit';
                            c.style.setProperty('--i', index++);
                            c.textContent = ch;
                            word.appendChild(c);
                        });
                        frag.appendChild(word);
                    }
                });
                node.parentNode.replaceChild(frag, node);
            } else if (node.nodeType === 1 && !node.classList.contains('visually-hidden')) {
                Array.from(node.childNodes).forEach(processNode);
            }
        }

        var visual = document.createElement('span');
        visual.setAttribute('aria-hidden', 'true');
        while (el.firstChild) { visual.appendChild(el.firstChild); }
        Array.from(visual.childNodes).forEach(processNode);
        var label = document.createElement('span');
        label.className = 'visually-hidden';
        label.textContent = original;
        el.appendChild(label);
        el.appendChild(visual);
    }

    function initSplits() {
        var els = AP.qsa('[data-split]');
        els.forEach(splitElement);
        if (!AP.motion()) {
            els.forEach(function (el) { el.classList.add('is-split-in'); });
            return;
        }
        els.forEach(function (el) {
            AP.inView(el, function (visible) {
                if (visible) { el.classList.add('is-split-in'); }
            }, { once: true, threshold: 0.2, rootMargin: '0px 0px -5% 0px' });
        });
    }

    /* ----------------------------------------------------------------- counters */
    function initCounters() {
        AP.qsa('[data-count-to]').forEach(function (el) {
            var to = parseFloat(el.getAttribute('data-count-to')) || 0;
            var from = parseFloat(el.getAttribute('data-count-from')) || 0;
            var dur = parseInt(el.getAttribute('data-count-duration'), 10) || 1600;
            var prefix = el.getAttribute('data-count-prefix') || '';
            var suffix = el.getAttribute('data-count-suffix') || '';
            var decimals = (String(to).split('.')[1] || '').length;
            var render = function (v) { el.textContent = prefix + v.toFixed(decimals) + suffix; };
            if (!AP.motion()) { render(to); return; }
            render(from);
            AP.inView(el, function (visible) {
                if (!visible) { return; }
                var start = null;
                var host = el.parentElement;
                if (host) { host.classList.add('is-counting'); }
                var tick = function (now) {
                    if (start === null) { start = now; }
                    var t = AP.clamp((now - start) / dur, 0, 1);
                    var eased = 1 - Math.pow(1 - t, 3);
                    render(from + (to - from) * eased);
                    if (t < 1 && AP.motion()) {
                        window.requestAnimationFrame(tick);
                    } else {
                        render(to);
                        if (host) { host.classList.remove('is-counting'); }
                    }
                };
                window.requestAnimationFrame(tick);
            }, { once: true, threshold: 0.4 });
        });
    }

    /* ------------------------------------------- pointer: tilt, spotlight, magnetic */
    function initPointer() {
        var fine = AP.finePointer();

        AP.qsa('[data-spotlight]').forEach(function (el) {
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect();
                el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
                el.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
            }, { passive: true });
        });

        if (!fine) { return; }

        AP.qsa('[data-tilt]').forEach(function (el) {
            var max = parseFloat(el.getAttribute('data-tilt-max')) || 8;
            var raf = null;
            el.addEventListener('pointermove', function (e) {
                if (!AP.motion()) { return; }
                var r = el.getBoundingClientRect();
                var px = (e.clientX - r.left) / r.width - 0.5;
                var py = (e.clientY - r.top) / r.height - 0.5;
                if (raf) { cancelAnimationFrame(raf); }
                raf = requestAnimationFrame(function () {
                    el.style.transform = 'perspective(900px) rotateX(' + (-py * max).toFixed(2) + 'deg) rotateY(' + (px * max).toFixed(2) + 'deg)';
                });
            }, { passive: true });
            el.addEventListener('pointerleave', function () {
                if (raf) { cancelAnimationFrame(raf); }
                el.style.transform = '';
            });
        });

        AP.qsa('[data-magnetic]').forEach(function (el) {
            var strength = parseFloat(el.getAttribute('data-magnetic-strength')) || 0.28;
            el.addEventListener('pointermove', function (e) {
                if (!AP.motion()) { return; }
                var r = el.getBoundingClientRect();
                var dx = (e.clientX - (r.left + r.width / 2)) * strength;
                var dy = (e.clientY - (r.top + r.height / 2)) * strength;
                el.style.translate = dx.toFixed(1) + 'px ' + dy.toFixed(1) + 'px';
            }, { passive: true });
            el.addEventListener('pointerleave', function () { el.style.translate = ''; });
        });
    }

    /* -------------------------------------------------------------- click: ripple, burst */
    function initClicks() {
        document.addEventListener('pointerdown', function (e) {
            var el = e.target.closest && e.target.closest('[data-ripple]');
            if (!el || !AP.motion()) { return; }
            var r = el.getBoundingClientRect();
            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.setAttribute('aria-hidden', 'true');
            ripple.style.setProperty('--rx', (e.clientX - r.left) + 'px');
            ripple.style.setProperty('--ry', (e.clientY - r.top) + 'px');
            ripple.style.setProperty('--ripple-scale', Math.ceil(Math.max(r.width, r.height) / 5));
            el.appendChild(ripple);
            window.setTimeout(function () { ripple.remove(); }, 750);
        }, { passive: true });

        document.addEventListener('click', function (e) {
            var el = e.target.closest && e.target.closest('[data-burst]');
            if (!el || !AP.motion()) { return; }
            var x = e.clientX, y = e.clientY;
            if (!x && !y) {
                var r = el.getBoundingClientRect();
                x = r.left + r.width / 2;
                y = r.top + r.height / 2;
            }
            for (var i = 0; i < 16; i++) {
                var p = document.createElement('span');
                p.className = 'burst-particle';
                p.setAttribute('aria-hidden', 'true');
                var angle = (Math.PI * 2 * i) / 16 + AP.rand(-0.2, 0.2);
                var dist = AP.rand(40, 110);
                p.style.left = x + 'px';
                p.style.top = y + 'px';
                p.style.setProperty('--dx', (Math.cos(angle) * dist).toFixed(1) + 'px');
                p.style.setProperty('--dy', (Math.sin(angle) * dist).toFixed(1) + 'px');
                p.style.setProperty('--rot', Math.round(AP.rand(-360, 360)) + 'deg');
                p.style.setProperty('--s', Math.round(AP.rand(7, 14)) + 'px');
                p.style.setProperty('--c', AP.pick(AP.colors));
                document.body.appendChild(p);
                window.setTimeout(p.remove.bind(p), 1000);
            }
        });
    }

    /* ------------------------------------------------------------------- parallax */
    function initParallax() {
        var els = AP.qsa('[data-parallax]');
        if (!els.length) { return; }
        var ticking = false;
        function update() {
            ticking = false;
            var vh = window.innerHeight;
            els.forEach(function (el) {
                if (!AP.motion()) { el.style.translate = ''; return; }
                var factor = parseFloat(el.getAttribute('data-parallax')) || 0.15;
                var r = el.getBoundingClientRect();
                if (r.bottom < -200 || r.top > vh + 200) { return; }
                var offset = (r.top + r.height / 2 - vh / 2) * -factor;
                el.style.translate = '0 ' + offset.toFixed(1) + 'px';
            });
        }
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }, { passive: true });
        window.addEventListener('resize', AP.debounce(update, 150));
        AP.onMotionChange(update);
        update();
    }

    /* -------------------------------------------------------------------- marquee */
    function initMarquees() {
        AP.qsa('[data-marquee]').forEach(function (el) {
            var group = el.querySelector('.marquee__group');
            if (!group) { return; }
            var speed = el.classList.contains('marquee--slow') ? 35 : el.classList.contains('marquee--fast') ? 110 : 60;
            var width = group.getBoundingClientRect().width;
            if (width > 0) { el.style.setProperty('--marquee-dur', (width / speed).toFixed(1) + 's'); }
        });
    }

    /* ----------------------------------------------------------------- typewriter */
    function initTypewriters() {
        AP.qsa('[data-typewriter]').forEach(function (el) {
            var words;
            try { words = JSON.parse(el.getAttribute('data-typewriter')); } catch (e) { words = null; }
            if (!words || !words.length) { return; }
            el.setAttribute('aria-hidden', 'true');
            var sr = document.createElement('span');
            sr.className = 'visually-hidden';
            sr.textContent = words.join(', ');
            el.parentNode.insertBefore(sr, el.nextSibling);

            var w = 0, i = 0, deleting = false, timer = null;
            el.textContent = words[0];
            if (!AP.motion()) { return; }
            i = words[0].length;

            function step() {
                var word = words[w];
                if (!AP.motion()) { el.textContent = words[0]; return; }
                if (!deleting) {
                    i++;
                    el.textContent = word.slice(0, i);
                    if (i >= word.length) { deleting = true; timer = setTimeout(step, 1700); return; }
                    timer = setTimeout(step, 70 + Math.random() * 60);
                } else {
                    i--;
                    el.textContent = word.slice(0, Math.max(0, i));
                    if (i <= 0) { deleting = false; w = (w + 1) % words.length; timer = setTimeout(step, 320); return; }
                    timer = setTimeout(step, 38);
                }
            }
            deleting = true;
            timer = setTimeout(step, 2600);
            AP.onMotionChange(function (on) {
                clearTimeout(timer);
                if (on) { deleting = true; i = el.textContent.length; timer = setTimeout(step, 1200); } else { el.textContent = words[0]; }
            });
        });
    }

    /* --------------------------------------------------------------------- glitch */
    function initGlitch() {
        AP.qsa('[data-glitch]').forEach(function (el) {
            el.setAttribute('data-text', el.textContent.trim());
        });
    }

    /* ------------------------------------------------------------------- lightbox */
    function initLightbox() {
        var links = AP.qsa('a[data-lightbox]');
        if (!links.length) { return; }

        var box = document.createElement('div');
        box.className = 'lightbox';
        box.hidden = true;
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-label', T.dialog);
        box.innerHTML =
            '<div class="lightbox__bar"><span class="lightbox__counter" aria-live="polite"></span>' +
            '<button type="button" class="lightbox__btn lightbox__btn--close" aria-label="' + T.close + '">' + svgIcon('M5.5 5.5l13 13M18.5 5.5l-13 13') + '</button></div>' +
            '<div class="lightbox__stage">' +
            '<button type="button" class="lightbox__btn lightbox__btn--prev" aria-label="' + T.prev + '">' + svgIcon('M20 12H5M11 6l-6 6 6 6') + '</button>' +
            '<figure class="lightbox__figure"><img class="lightbox__img" alt=""><figcaption class="lightbox__caption"></figcaption></figure>' +
            '<button type="button" class="lightbox__btn lightbox__btn--next" aria-label="' + T.next + '">' + svgIcon('M4 12h15M13 6l6 6-6 6') + '</button>' +
            '</div>';
        document.body.appendChild(box);

        var img = box.querySelector('.lightbox__img');
        var caption = box.querySelector('.lightbox__caption');
        var counter = box.querySelector('.lightbox__counter');
        var btnClose = box.querySelector('.lightbox__btn--close');
        var btnPrev = box.querySelector('.lightbox__btn--prev');
        var btnNext = box.querySelector('.lightbox__btn--next');
        var items = [], index = 0, opener = null;

        function groupItems(name) {
            return AP.qsa('a[data-lightbox="' + name + '"]').filter(function (a) {
                var item = a.closest('[data-filter-item]');
                return !(item && item.classList.contains('is-hidden'));
            });
        }
        function show(i) {
            index = (i + items.length) % items.length;
            var a = items[index];
            var thumb = a.querySelector('img');
            img.style.animation = 'none';
            void img.offsetWidth;
            img.style.animation = '';
            img.src = a.getAttribute('href');
            img.alt = thumb ? thumb.alt : '';
            caption.textContent = a.getAttribute('data-caption') || (thumb ? thumb.alt : '');
            counter.textContent = (index + 1) + ' / ' + items.length;
            var multi = items.length > 1;
            btnPrev.hidden = !multi;
            btnNext.hidden = !multi;
        }
        function open(link) {
            items = groupItems(link.getAttribute('data-lightbox'));
            opener = link;
            box.hidden = false;
            box.classList.remove('is-closing');
            document.body.style.overflow = 'hidden';
            show(Math.max(0, items.indexOf(link)));
            btnClose.focus();
        }
        function close() {
            if (box.hidden) { return; }
            var finish = function () {
                box.hidden = true;
                box.classList.remove('is-closing');
                document.body.style.overflow = '';
                if (opener) { opener.focus(); }
            };
            if (AP.motion()) {
                box.classList.add('is-closing');
                window.setTimeout(finish, 280);
            } else {
                finish();
            }
        }

        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('a[data-lightbox]');
            if (!link || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
            e.preventDefault();
            open(link);
        });
        btnClose.addEventListener('click', close);
        btnPrev.addEventListener('click', function () { show(index - 1); });
        btnNext.addEventListener('click', function () { show(index + 1); });
        box.addEventListener('click', function (e) {
            if (e.target === box || e.target.classList.contains('lightbox__stage')) { close(); }
        });
        document.addEventListener('keydown', function (e) {
            if (box.hidden) { return; }
            if (e.key === 'Escape') { close(); }
            else if (e.key === 'ArrowLeft') { show(index - 1); }
            else if (e.key === 'ArrowRight') { show(index + 1); }
            else if (e.key === 'Tab') {
                var f = [btnClose, btnPrev, btnNext].filter(function (b) { return !b.hidden; });
                var pos = f.indexOf(document.activeElement);
                e.preventDefault();
                f[(pos + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
            }
        });
        var startX = null;
        box.addEventListener('pointerdown', function (e) { startX = e.clientX; }, { passive: true });
        box.addEventListener('pointerup', function (e) {
            if (startX === null) { return; }
            var dx = e.clientX - startX;
            startX = null;
            if (Math.abs(dx) > 50 && items.length > 1) { show(index + (dx < 0 ? 1 : -1)); }
        }, { passive: true });
    }

    /* -------------------------------------------------------------------- filters */
    function initFilters() {
        AP.qsa('[data-filter-group]').forEach(function (group) {
            var buttons = AP.qsa('[data-filter]', group);
            var items = AP.qsa('[data-filter-item]', group);
            if (!buttons.length || !items.length) { return; }
            var live = document.createElement('p');
            live.className = 'visually-hidden';
            live.setAttribute('aria-live', 'polite');
            group.appendChild(live);
            var template = group.getAttribute('data-filter-announce') || '{count}';

            function apply(value) {
                var count = 0;
                buttons.forEach(function (b) {
                    var on = b.getAttribute('data-filter') === value;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                items.forEach(function (item, i) {
                    var cats = (item.getAttribute('data-category') || '').split(/\s+/);
                    var match = value === 'all' || value === '*' || cats.indexOf(value) !== -1;
                    var wasHidden = item.classList.contains('is-hidden');
                    item.classList.toggle('is-hidden', !match);
                    if (match) {
                        count++;
                        item.classList.add('is-revealed');
                        if (wasHidden && AP.motion()) {
                            item.style.animationDelay = (Math.min(count, 12) * 40) + 'ms';
                            item.classList.remove('is-entering');
                            void item.offsetWidth;
                            item.classList.add('is-entering');
                        }
                    }
                });
                live.textContent = template.replace('{count}', count);
                AP.emit('filter', { group: group, value: value, count: count });
            }

            group.addEventListener('click', function (e) {
                var b = e.target.closest('[data-filter]');
                if (!b || !group.contains(b)) { return; }
                apply(b.getAttribute('data-filter'));
            });
            group.addEventListener('animationend', function (e) {
                if (e.target.classList && e.target.classList.contains('is-entering')) {
                    e.target.classList.remove('is-entering');
                    e.target.style.animationDelay = '';
                }
            });
            var active = buttons.filter(function (b) { return b.classList.contains('is-active') || b.getAttribute('aria-pressed') === 'true'; })[0];
            // ?famille=<key> opens the group already filtered (links from the home page's families).
            var wanted = new URLSearchParams(window.location.search).get('famille');
            var linked = wanted && buttons.filter(function (b) { return b.getAttribute('data-filter') === wanted; })[0];
            if (linked) { apply(wanted); } else if (active && active.getAttribute('data-filter') !== 'all') { apply(active.getAttribute('data-filter')); }
        });
    }

    /* ---------------------------------------------------------------------- start */
    function safe(fn, name) {
        try { fn(); } catch (err) {
            if (window.console) { console.error('effects.js: ' + name + ' failed', err); }
        }
    }

    AP.ready(function () {
        safe(initSplits, 'split');
        safe(initReveals, 'reveal');
        safe(initCounters, 'counters');
        safe(initPointer, 'pointer');
        safe(initClicks, 'clicks');
        safe(initParallax, 'parallax');
        safe(initMarquees, 'marquee');
        safe(initTypewriters, 'typewriter');
        safe(initGlitch, 'glitch');
        safe(initLightbox, 'lightbox');
        safe(initFilters, 'filters');
        root.classList.add('reveal-ready');

        AP.onMotionChange(function (on) {
            if (!on) {
                AP.qsa('[data-reveal], [data-inview]').forEach(function (el) { el.classList.add('is-revealed'); });
                AP.qsa('[data-split]').forEach(function (el) { el.classList.add('is-split-in'); });
                AP.qsa('[data-tilt]').forEach(function (el) { el.style.transform = ''; });
                AP.qsa('[data-magnetic]').forEach(function (el) { el.style.translate = ''; });
            }
        });
    });
})();
