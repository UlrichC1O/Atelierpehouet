/* Ateliers Pehouet — gallery.js: "shuffle" the artworks (filters & lightbox come from effects.js). */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    AP.ready(function () {
        var grid = AP.qs('[data-gallery-grid]');
        var button = AP.qs('[data-gallery-shuffle]');
        if (!grid || !button) { return; }
        button.hidden = false;
        button.addEventListener('click', function () {
            var items = AP.qsa('.gallery__item', grid);
            for (var i = items.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var tmp = items[i]; items[i] = items[j]; items[j] = tmp;
            }
            items.forEach(function (item, index) {
                item.style.setProperty('--i', index);
                grid.appendChild(item);
            });
            grid.classList.remove('is-shuffling');
            void grid.offsetWidth;
            if (AP.motion()) { grid.classList.add('is-shuffling'); }
        });
    });
})();
