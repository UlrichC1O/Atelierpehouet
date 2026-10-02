/* Ateliers Pehouet — services.js: scene-stage pointer tilt and ←/→ navigation between services. */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    AP.ready(function () {
        var stage = AP.qs('[data-stage]');
        if (stage && AP.finePointer()) {
            var raf = null;
            stage.addEventListener('pointermove', function (e) {
                if (!AP.motion()) { return; }
                var r = stage.getBoundingClientRect();
                var px = (e.clientX - r.left) / r.width - 0.5;
                var py = (e.clientY - r.top) / r.height - 0.5;
                if (raf) { cancelAnimationFrame(raf); }
                raf = requestAnimationFrame(function () {
                    stage.style.transform = 'perspective(1100px) rotateX(' + (-py * 6).toFixed(2) + 'deg) rotateY(' + (px * 8).toFixed(2) + 'deg)';
                });
            }, { passive: true });
            stage.addEventListener('pointerleave', function () { stage.style.transform = ''; });
        }

        var page = AP.qs('[data-service-page]');
        if (!page) { return; }
        document.addEventListener('keydown', function (e) {
            if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) { return; }
            var tag = (e.target && e.target.tagName) || '';
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag) || (e.target && e.target.isContentEditable)) { return; }
            if (document.documentElement.classList.contains('menu-open') || AP.qs('.lightbox:not([hidden])')) { return; }
            var url = e.key === 'ArrowLeft' ? page.getAttribute('data-prev') : e.key === 'ArrowRight' ? page.getAttribute('data-next') : null;
            if (url) { window.location.href = url; }
        });
    });
})();
