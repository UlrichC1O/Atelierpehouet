/* Ateliers Pehouet — focal-point.js: the point of a photo kept visible when it is cropped
   (docs/CMS.md §7.6). Needs core.js (window.AP).

   <div class="adm-focal" data-focal tabindex="0" role="slider" data-focal-text="Horizontal :x %, vertical :y %"
        style="--x: 30; --y: 60"><img …><span class="adm-focal__dot"></span></div>
   + in the same form: input[data-focal-input="x"], input[data-focal-input="y"] (0–100, the values
   posted; they work alone without JavaScript), optional [data-focal-reset] (back to 50 / 50, shown by
   the script) and img[data-focal-preview] (crop previews: their object-position follows).

   Pointer (mouse, touch, pen): press or drag on the photo. Keyboard on the photo: arrows move by 1 %
   (Shift: 10 %), Home / End: 0 / 100 horizontally. Each change fires "input" on the numeric fields,
   so the form's unsaved-changes tracking (admin.js) sees it. */
(function () {
    'use strict';

    var AP = window.AP;
    if (!AP || AP.focalPoint) { return; }

    function clamp(value) {
        var number = Math.round(Number(value));
        return isNaN(number) ? 50 : Math.max(0, Math.min(100, number));
    }

    function fire(input, name) {
        var event;
        try {
            event = new Event(name, { bubbles: true });
        } catch (e) {
            event = document.createEvent('Event');
            event.initEvent(name, true, false);
        }
        input.dispatchEvent(event);
    }

    function init(picker) {
        if (picker.__apFocal) { return; }
        picker.__apFocal = true;

        var scope = picker.closest('form') || document;
        var inputX = AP.qs('[data-focal-input="x"]', scope);
        var inputY = AP.qs('[data-focal-input="y"]', scope);
        var previews = AP.qsa('[data-focal-preview]', scope);
        var reset = AP.qs('[data-focal-reset]', scope);
        var template = picker.getAttribute('data-focal-text') || ':x / :y';
        var x = clamp(inputX ? inputX.value : 50);
        var y = clamp(inputY ? inputY.value : 50);
        var dragging = null;

        function render() {
            picker.style.setProperty('--x', String(x));
            picker.style.setProperty('--y', String(y));
            picker.setAttribute('aria-valuenow', String(x));
            picker.setAttribute('aria-valuetext', template.split(':x').join(String(x)).split(':y').join(String(y)));
            previews.forEach(function (img) { img.style.objectPosition = x + '% ' + y + '%'; });
            if (reset) { reset.disabled = x === 50 && y === 50; }
        }

        function set(nextX, nextY) {
            nextX = clamp(nextX);
            nextY = clamp(nextY);
            if (nextX === x && nextY === y) { return; }
            x = nextX;
            y = nextY;
            render();
            [[inputX, x], [inputY, y]].forEach(function (pair) {
                if (pair[0] && String(pair[0].value) !== String(pair[1])) {
                    pair[0].value = String(pair[1]);
                    fire(pair[0], 'input');
                }
            });
        }

        function fromPointer(event) {
            var img = AP.qs('img', picker);
            var box = (img || picker).getBoundingClientRect();
            if (!box.width || !box.height) { return; }
            set((event.clientX - box.left) / box.width * 100, (event.clientY - box.top) / box.height * 100);
        }

        picker.addEventListener('pointerdown', function (event) {
            if (event.button > 0) { return; }
            event.preventDefault();
            dragging = event.pointerId;
            if (picker.setPointerCapture) {
                try { picker.setPointerCapture(event.pointerId); } catch (e) { /* not capturable */ }
            }
            picker.focus({ preventScroll: true });
            picker.classList.add('is-dragging');
            fromPointer(event);
        });
        picker.addEventListener('pointermove', function (event) {
            if (dragging !== event.pointerId) { return; }
            fromPointer(event);
        });
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (name) {
            picker.addEventListener(name, function () {
                dragging = null;
                picker.classList.remove('is-dragging');
            });
        });

        picker.addEventListener('keydown', function (event) {
            var step = event.shiftKey ? 10 : 1;
            var moves = {
                ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step],
                Home: [-100, 0], End: [100, 0], PageUp: [0, -10], PageDown: [0, 10]
            };
            var move = moves[event.key];
            if (!move) { return; }
            event.preventDefault();
            set(x + move[0], y + move[1]);
        });

        [inputX, inputY].forEach(function (input) {
            if (!input) { return; }
            input.addEventListener('input', function () {
                if (input.value === '') { return; }
                var value = clamp(input.value);
                if (input === inputX) { x = value; } else { y = value; }
                render();
            });
        });

        if (reset) {
            reset.hidden = false;
            reset.addEventListener('click', function () { set(50, 50); });
        }

        render();
    }

    AP.focalPoint = { init: init };

    AP.ready(function () {
        AP.qsa('[data-focal]').forEach(init);
    });
})();
