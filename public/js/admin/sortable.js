/* Ateliers Pehouet — sortable.js: reorderable lists and grids of the admin (docs/CMS.md §6).
   Load it after admin.js on the pages that need it:
     @once @push('scripts') <script src="{{ ap_asset('js/admin/sortable.js') }}" defer></script> @endpush @endonce

   <form method="POST" action="…"> @csrf
     <ol class="adm-sortable" data-sortable [data-sortable-autosubmit] [data-sortable-name="order[]"] [data-sortable-form="#id"]>
       <li class="adm-sortable__item" data-sortable-item data-sortable-value="peinture-murale">
         <button class="adm-handle" type="button" data-sortable-handle>…<span class="visually-hidden">Déplacer</span></button>
         <span class="adm-sortable__number" data-sortable-number>01</span> …
         <input type="hidden" name="order[]" value="peinture-murale">   (created from data-sortable-value when missing)
         <button type="button" data-move="up">…</button> <button type="button" data-move="down">…</button>
       </li>
     </ol>
   </form>

   - Pointer drag (mouse, pen, touch) by [data-sortable-handle], or by the item itself when it has none
     (never from its links, buttons or fields); works for vertical lists and wrapping grids; Esc cancels.
   - Keyboard: [data-move="up|down"] buttons, or the arrow keys on a focused handle; moves are announced.
   - The hidden order[] inputs live inside the items, so they always follow the DOM order.
   - After a change: [data-sortable-number] renumbered (01, 02…), edge buttons get aria-disabled, the list
     dispatches "ap:sortable-change" (detail {order, item}) and its form a "change" event (dirty tracking).
   - data-sortable-autosubmit: the form is sent with AP.admin.fetchJSON (Accept: application/json) 0.7 s after
     the last move; the endpoint answers JSON {message} (shown as a toast) — or a redirect without JS.
   API: AP.sortable = { init(list), refresh(scope), order(list) } */
(function () {
    'use strict';

    var AP = window.AP;
    if (!AP) { return; }

    var root = document.documentElement;
    var drag = null;
    var liveRegion = null;

    function t(key, replace) {
        return AP.admin && AP.admin.t ? AP.admin.t(key, replace) : key;
    }

    function announce(text) {
        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.className = 'visually-hidden';
            liveRegion.setAttribute('role', 'status');
            liveRegion.setAttribute('aria-live', 'polite');
            document.body.appendChild(liveRegion);
        }
        liveRegion.textContent = '';
        setTimeout(function () { liveRegion.textContent = text; }, 40);
    }

    function items(list) {
        var children = Array.prototype.slice.call(list.children);
        var marked = children.filter(function (el) { return el.hasAttribute('data-sortable-item'); });
        return marked.length ? marked : children.filter(function (el) { return el.tagName !== 'TEMPLATE' && el.tagName !== 'SCRIPT'; });
    }

    function ownList(list, el) {
        return el && el.closest && el.closest('[data-sortable]') === list;
    }

    function itemOf(list, el) {
        var node = el;
        while (node && node.parentElement !== list) { node = node.parentElement; }
        return node && items(list).indexOf(node) !== -1 ? node : null;
    }

    function inputName(list) {
        return list.getAttribute('data-sortable-name') || 'order[]';
    }

    function ensureInputs(list) {
        var name = inputName(list);
        items(list).forEach(function (item) {
            var value = item.getAttribute('data-sortable-value');
            if (value === null) { return; }
            var present = AP.qsa('input[type="hidden"]', item).some(function (input) { return input.name === name; });
            if (!present) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                item.appendChild(input);
            }
        });
    }

    function order(list) {
        var name = inputName(list);
        return items(list).map(function (item) {
            var input = AP.qsa('input[type="hidden"]', item).filter(function (el) { return el.name === name; })[0];
            return input ? input.value : (item.getAttribute('data-sortable-value') || '');
        });
    }

    function sync(list) {
        var all = items(list);
        all.forEach(function (item, i) {
            AP.qsa('[data-sortable-number]', item).forEach(function (number) {
                if (ownList(list, number)) { number.textContent = (i < 9 ? '0' : '') + (i + 1); }
            });
            AP.qsa('[data-move="up"]', item).forEach(function (button) {
                if (ownList(list, button)) { button.setAttribute('aria-disabled', i === 0 ? 'true' : 'false'); }
            });
            AP.qsa('[data-move="down"]', item).forEach(function (button) {
                if (ownList(list, button)) { button.setAttribute('aria-disabled', i === all.length - 1 ? 'true' : 'false'); }
            });
        });
    }

    function formOf(list) {
        var selector = list.getAttribute('data-sortable-form');
        return (selector && AP.qs(selector)) || list.closest('form');
    }

    function highlight(item) {
        item.classList.add('is-moved');
        clearTimeout(item.__apMovedTimer);
        item.__apMovedTimer = setTimeout(function () { item.classList.remove('is-moved'); }, 900);
    }

    function submit(list, form) {
        clearTimeout(list.__apSubmitTimer);
        list.__apSubmitTimer = setTimeout(function () {
            if (!(AP.admin && AP.admin.fetchJSON) || typeof FormData === 'undefined') {
                form.__apSubmitting = true;
                form.submit();
                return;
            }
            list.setAttribute('aria-busy', 'true');
            AP.admin.fetchJSON(form.action, { method: form.getAttribute('method') || 'POST', body: new FormData(form) })
                .then(function (data) {
                    if (AP.admin.markClean) { AP.admin.markClean(form); }
                    AP.admin.toast(data && data.message ? data.message : t('saved'), 'success');
                })
                .catch(function (error) {
                    AP.admin.toast(error && error.message ? error.message : t('error'), 'error');
                })
                .then(function () { list.removeAttribute('aria-busy'); });
        }, 700);
    }

    function changed(list, item) {
        sync(list);
        var form = formOf(list);
        list.dispatchEvent(new CustomEvent('ap:sortable-change', { bubbles: true, detail: { order: order(list), item: item } }));
        if (form) {
            form.dispatchEvent(new Event('change', { bubbles: true }));
            if (list.hasAttribute('data-sortable-autosubmit')) { submit(list, form); }
        }
    }

    /* --- Keyboard ------------------------------------------------------------------ */

    function move(list, item, direction, focusTarget) {
        var all = items(list);
        var index = all.indexOf(item);
        if (direction === 'up' && index > 0) {
            list.insertBefore(item, all[index - 1]);
        } else if (direction === 'down' && index > -1 && index < all.length - 1) {
            list.insertBefore(item, all[index + 1].nextSibling);
        } else {
            return;
        }
        if (focusTarget && typeof focusTarget.focus === 'function') { focusTarget.focus(); }
        highlight(item);
        changed(list, item);
        announce(t('moved', { position: items(list).indexOf(item) + 1, total: all.length }));
    }

    function onClick(event) {
        var list = event.currentTarget;
        var button = event.target.closest('[data-move]');
        if (!button || !ownList(list, button)) { return; }
        var item = itemOf(list, button);
        if (!item) { return; }
        event.preventDefault();
        if (button.getAttribute('aria-disabled') === 'true') { return; }
        move(list, item, button.getAttribute('data-move') === 'up' ? 'up' : 'down', button);
    }

    function onKeyDown(event) {
        var list = event.currentTarget;
        var handle = event.target.closest('[data-sortable-handle]');
        if (!handle || !ownList(list, handle)) { return; }
        var up = event.key === 'ArrowUp' || event.key === 'ArrowLeft';
        var down = event.key === 'ArrowDown' || event.key === 'ArrowRight';
        if (!up && !down) { return; }
        var item = itemOf(list, handle);
        if (!item) { return; }
        event.preventDefault();
        move(list, item, up ? 'up' : 'down', handle);
    }

    /* --- Pointer drag ---------------------------------------------------------------- */

    function startDrag() {
        var rect = drag.item.getBoundingClientRect();
        var ghost = drag.item.cloneNode(true);
        ghost.removeAttribute('id');
        AP.qsa('[id]', ghost).forEach(function (el) { el.removeAttribute('id'); });
        AP.qsa('[name]', ghost).forEach(function (el) { el.removeAttribute('name'); });
        ghost.classList.add('adm-sortable__ghost');
        ghost.setAttribute('aria-hidden', 'true');
        ghost.style.width = rect.width + 'px';
        ghost.style.height = rect.height + 'px';
        ghost.style.left = rect.left + 'px';
        ghost.style.top = rect.top + 'px';
        document.body.appendChild(ghost);

        drag.ghost = ghost;
        drag.started = true;
        drag.before = order(drag.list).join('\u0000');
        drag.item.classList.add('is-dragging');
        root.classList.add('adm-sorting');
    }

    function reorderAt(x, y) {
        var under = document.elementFromPoint(x, y);
        var target = under && ownList(drag.list, under) ? itemOf(drag.list, under) : null;
        if (!target || target === drag.item) { return; }
        var all = items(drag.list);
        var from = all.indexOf(drag.item);
        var to = all.indexOf(target);
        if (from === -1 || to === -1) { return; }

        var box = target.getBoundingClientRect();
        var own = drag.item.getBoundingClientRect();
        var sameRow = Math.abs(box.top - own.top) < Math.min(box.height, own.height) / 2;
        var past = sameRow
            ? (from < to ? x > box.left + box.width / 2 : x < box.left + box.width / 2)
            : (from < to ? y > box.top + box.height / 2 : y < box.top + box.height / 2);
        if (!past) { return; }

        drag.list.insertBefore(drag.item, from < to ? target.nextSibling : target);
    }

    function autoScroll() {
        if (!drag || !drag.started) { return; }
        cancelAnimationFrame(drag.frame);
        var edge = 70;
        var speed = 0;
        if (drag.y < edge) { speed = -Math.ceil((edge - drag.y) / 5); }
        else if (drag.y > window.innerHeight - edge) { speed = Math.ceil((drag.y - window.innerHeight + edge) / 5); }
        if (!speed) { return; }
        drag.frame = requestAnimationFrame(function () {
            if (!drag) { return; }
            window.scrollBy(0, speed);
            reorderAt(drag.x, drag.y);
            autoScroll();
        });
    }

    function onPointerMove(event) {
        if (!drag || event.pointerId !== drag.pointerId) { return; }
        drag.x = event.clientX;
        drag.y = event.clientY;
        if (!drag.started) {
            if (Math.abs(drag.x - drag.startX) + Math.abs(drag.y - drag.startY) < 6) { return; }
            startDrag();
        }
        event.preventDefault();
        drag.ghost.style.transform = 'translate(' + (drag.x - drag.startX) + 'px, ' + (drag.y - drag.startY) + 'px)';
        reorderAt(drag.x, drag.y);
        autoScroll();
    }

    function stopListening() {
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        document.removeEventListener('pointercancel', onPointerCancel);
        document.removeEventListener('keydown', onDragKey, true);
    }

    function finishDrag(cancelled) {
        if (!drag) { return; }
        var state = drag;
        drag = null;
        cancelAnimationFrame(state.frame);
        stopListening();
        try { state.capture.releasePointerCapture(state.pointerId); } catch (e) { /* already released */ }
        if (!state.started) { return; }

        if (cancelled) {
            state.list.insertBefore(state.item, state.origin && state.origin.parentNode === state.list ? state.origin : null);
        }
        if (state.ghost && state.ghost.parentNode) { state.ghost.parentNode.removeChild(state.ghost); }
        state.item.classList.remove('is-dragging');
        root.classList.remove('adm-sorting');

        if (order(state.list).join('\u0000') !== state.before) {
            highlight(state.item);
            changed(state.list, state.item);
            announce(t('moved', { position: items(state.list).indexOf(state.item) + 1, total: items(state.list).length }));
        }
    }

    function onPointerUp(event) {
        if (drag && event.pointerId === drag.pointerId) { finishDrag(false); }
    }

    function onPointerCancel(event) {
        if (drag && event.pointerId === drag.pointerId) { finishDrag(true); }
    }

    function onDragKey(event) {
        if (event.key === 'Escape' && drag) {
            event.preventDefault();
            event.stopPropagation();
            finishDrag(true);
        }
    }

    function onPointerDown(event) {
        if (drag || !event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)) { return; }
        var list = event.currentTarget;
        if (!ownList(list, event.target)) { return; }
        var item = itemOf(list, event.target);
        if (!item) { return; }

        var handle = event.target.closest('[data-sortable-handle]');
        var hasHandle = AP.qsa('[data-sortable-handle]', item).some(function (el) { return ownList(list, el); });
        if (hasHandle ? !handle : event.target.closest('a, button, input, select, textarea, label, [contenteditable="true"]')) { return; }

        var capture = handle || item;
        drag = {
            list: list,
            item: item,
            capture: capture,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            x: event.clientX,
            y: event.clientY,
            origin: item.nextSibling,
            started: false,
            ghost: null,
            frame: 0,
            before: ''
        };
        if (capture.setPointerCapture) {
            try { capture.setPointerCapture(event.pointerId); } catch (e) { /* ignore */ }
        }
        if (handle) { event.preventDefault(); }
        document.addEventListener('pointermove', onPointerMove, { passive: false });
        document.addEventListener('pointerup', onPointerUp);
        document.addEventListener('pointercancel', onPointerCancel);
        document.addEventListener('keydown', onDragKey, true);
    }

    /* --- Init ------------------------------------------------------------------------ */

    function init(list) {
        if (!list || list.__apSortable) {
            if (list) { sync(list); }
            return;
        }
        list.__apSortable = true;
        ensureInputs(list);
        sync(list);
        list.addEventListener('pointerdown', onPointerDown);
        list.addEventListener('click', onClick);
        list.addEventListener('keydown', onKeyDown);
        // Native drag & drop of images and links would fight the pointer drag.
        list.addEventListener('dragstart', function (event) {
            if (ownList(list, event.target)) { event.preventDefault(); }
        });
    }

    function refresh(scope) {
        AP.qsa('[data-sortable]', scope || document).forEach(init);
    }

    AP.sortable = { init: init, refresh: refresh, order: order };

    AP.ready(function () {
        refresh(document);
        // Content inserted later (AP.admin.refresh) gets sortable lists too.
        if (AP.admin && typeof AP.admin.refresh === 'function' && !AP.admin.refresh.__apSortable) {
            var base = AP.admin.refresh;
            AP.admin.refresh = function (scope) {
                base(scope);
                refresh(scope);
            };
            AP.admin.refresh.__apSortable = true;
        }
    });
})();
