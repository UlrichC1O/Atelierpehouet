/* Ateliers Pehouet — nav.js: header states, Services mega menu, full-screen mobile menu. */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    AP.ready(function () {
        var root = document.documentElement;
        var header = AP.qs('[data-header]');

        /* Header: .is-scrolled after a few pixels, .is-hidden when scrolling down. */
        if (header) {
            var lastY = window.scrollY;
            var ticking = false;
            var update = function () {
                var y = window.scrollY;
                header.classList.toggle('is-scrolled', y > 24);
                var down = y > lastY + 4;
                var up = y < lastY - 4;
                if (root.classList.contains('menu-open') || header.contains(document.activeElement) || y < 160) {
                    header.classList.remove('is-hidden');
                } else if (down) {
                    header.classList.add('is-hidden');
                    closeMega();
                } else if (up) {
                    header.classList.remove('is-hidden');
                }
                lastY = y;
                ticking = false;
            };
            window.addEventListener('scroll', function () {
                if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
            }, { passive: true });
            update();
        }

        /* Mega menu: CSS opens it on hover/focus; the button makes it keyboard & touch friendly. */
        var megaItem = AP.qs('[data-mega-item]');
        var megaToggle = AP.qs('[data-mega-toggle]');
        var mega = AP.qs('[data-mega]');

        function openMega() {
            if (!mega) { return; }
            mega.classList.remove('is-dismissed');
            mega.classList.add('is-open');
            megaToggle.setAttribute('aria-expanded', 'true');
        }
        function closeMega(dismiss) {
            if (!mega) { return; }
            mega.classList.remove('is-open');
            if (dismiss) { mega.classList.add('is-dismissed'); }
            if (megaToggle) { megaToggle.setAttribute('aria-expanded', 'false'); }
        }

        if (megaItem && megaToggle && mega) {
            megaToggle.addEventListener('click', function () {
                if (mega.classList.contains('is-open')) { closeMega(true); } else { openMega(); }
            });
            megaItem.addEventListener('mouseenter', function () {
                mega.classList.remove('is-dismissed');
                megaToggle.setAttribute('aria-expanded', 'true');
            });
            megaItem.addEventListener('mouseleave', function () {
                if (!mega.classList.contains('is-open')) { megaToggle.setAttribute('aria-expanded', 'false'); }
                mega.classList.remove('is-dismissed');
            });
            megaItem.addEventListener('focusout', function (event) {
                if (!megaItem.contains(event.relatedTarget)) { closeMega(false); mega.classList.remove('is-dismissed'); }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && (mega.classList.contains('is-open') || megaItem.matches(':hover') || megaItem.contains(document.activeElement))) {
                    closeMega(true);
                    megaToggle.focus();
                }
            });
            document.addEventListener('click', function (event) {
                if (mega.classList.contains('is-open') && !megaItem.contains(event.target)) { closeMega(false); }
            });
        }

        /* Mobile menu: dialog with focus trap, Esc, scroll lock. */
        var toggle = AP.qs('[data-menu-toggle]');
        var menu = AP.qs('[data-mobile-menu]');
        if (!toggle || !menu) { return; }
        toggle.setAttribute('role', 'button');
        var label = toggle.querySelector('[data-menu-label]');
        var lastFocus = null;

        function focusables() {
            return AP.qsa('a[href], button:not([disabled]), summary, input, select, textarea', menu)
                .filter(function (el) { return el.offsetParent !== null; });
        }
        function setOpen(open) {
            root.classList.toggle('menu-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (label) { label.textContent = toggle.getAttribute(open ? 'data-label-close' : 'data-label-open'); }
            document.body.style.overflow = open ? 'hidden' : '';
            if (open) {
                lastFocus = document.activeElement;
                var first = focusables()[0];
                if (first) { window.setTimeout(function () { first.focus({ preventScroll: true }); }, 60); }
            } else if (lastFocus && lastFocus.focus) {
                lastFocus.focus({ preventScroll: true });
            }
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            setOpen(!root.classList.contains('menu-open'));
        });

        menu.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (link && link.getAttribute('href').charAt(0) !== '#') { setOpen(false); }
        });

        document.addEventListener('keydown', function (event) {
            if (!root.classList.contains('menu-open')) { return; }
            if (event.key === 'Escape') {
                setOpen(false);
                return;
            }
            if (event.key === 'Tab') {
                var items = focusables().concat([toggle]);
                var first = items[0], last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });

        window.matchMedia('(min-width: 68.75em)').addEventListener('change', function (mq) {
            if (mq.matches && root.classList.contains('menu-open')) { setOpen(false); }
        });

        // A no-JS #mobile-menu hash must not leave the menu stuck open.
        if (window.location.hash === '#mobile-menu') {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    });
})();
