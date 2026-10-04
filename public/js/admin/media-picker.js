/* Ateliers Pehouet — media-picker.js: choose a library photo in a dialog (docs/CMS.md §7.6).
   Needs core.js (window.AP) and admin.js (AP.admin). Loads GET admin.media.index?picker=1 (the
   server-rendered partial admin.media.picker) into a <dialog class="adm-dialog">: filters, search and
   pages load inside it, and a photo uploaded there (uploader.js, added when missing) is chosen at once.

   Slot mode   a[data-media-picker][data-slot] inside form[data-slot-form] (admin.media.partials.slot):
               the chosen id goes into that form's media_id, then the form is submitted. Without
               JavaScript the link opens the library (?slot=…&redirect=…, "Utiliser ici").
   Field mode  [data-media-picker][data-media-target="#input-id"], usually inside [data-media-field]:
               the hidden input (or <select>) gets the id and a "change" event; the <img> inside
               [data-media-preview] shows the photo ([data-media-empty] hides, [data-media-name] gets
               its name); nothing is submitted. [data-media-clear] in the same [data-media-field]
               empties it. Optional on the trigger: data-media-picker-url (library URL, default
               /admin/photos), data-picker-i18n (JSON {title, loading, failed, retry}: the dialog's
               strings before its content loads — admin_media.picker.*).
   Also: select[data-media-autosubmit] submits its form on change (library filters).
   API: AP.mediaPicker = { open(trigger), choose(media), clear(field) } */
(function () {
    'use strict';

    var AP = window.AP;
    if (!AP || AP.mediaPicker) { return; }

    var script = document.currentScript;
    var scriptSrc = script && script.src ? script.src : '';
    var dialog = null;
    var body = null;
    var title = null;
    var current = null; // {mode: 'slot'|'field', trigger, form?, input?, field?}
    var texts = {};
    var loading = null;

    function el(tag, className) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        return node;
    }

    function t(key) {
        return AP.admin && AP.admin.t ? AP.admin.t(key) : key;
    }

    /** URL of a sibling asset (css/admin/media.css, js/admin/uploader.js), with this script's version query. */
    function asset(path) {
        if (!scriptSrc) { return '/' + path; }
        var url = new URL(scriptSrc, window.location.href);
        url.pathname = url.pathname.replace(/js\/admin\/media-picker\.js$/, path);
        return url.href;
    }

    function ensureStyles() {
        if (AP.qs('link[href*="css/admin/media.css"]')) { return; }
        var link = el('link');
        link.rel = 'stylesheet';
        link.href = asset('css/admin/media.css');
        document.head.appendChild(link);
    }

    function ensureUploader() {
        if (AP.uploader) { return Promise.resolve(); }
        if (loading) { return loading; }
        loading = new Promise(function (resolve) {
            var tag = el('script');
            tag.src = asset('js/admin/uploader.js');
            tag.defer = true;
            tag.onload = function () { resolve(); };
            tag.onerror = function () { resolve(); };
            document.head.appendChild(tag);
        });
        return loading;
    }

    function fire(target, name) {
        var event;
        try {
            event = new Event(name, { bubbles: true });
        } catch (e) {
            event = document.createEvent('Event');
            event.initEvent(name, true, false);
        }
        target.dispatchEvent(event);
    }

    /* --- Dialog ----------------------------------------------------------------------------- */

    function build() {
        if (dialog) { return; }
        dialog = el('dialog', 'adm-dialog adm-dialog--wide adm-picker-dialog');
        dialog.id = 'adm-media-picker';
        dialog.setAttribute('aria-labelledby', 'adm-media-picker-title');

        var head = el('div', 'adm-dialog__head');
        title = el('h2', 'adm-dialog__title');
        title.id = 'adm-media-picker-title';
        var close = el('button', 'adm-icon-btn');
        close.type = 'button';
        close.setAttribute('aria-label', t('close'));
        close.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/></svg>';
        head.appendChild(title);
        head.appendChild(close);

        body = el('div', 'adm-dialog__body');
        body.setAttribute('data-picker-body', '');

        dialog.appendChild(head);
        dialog.appendChild(body);
        document.body.appendChild(dialog);

        close.addEventListener('click', function () { AP.admin.closeDialog(dialog); });
        body.addEventListener('click', onBodyClick);
        body.addEventListener('submit', onBodySubmit);
        body.addEventListener('ap:media-uploaded', function (event) {
            event.preventDefault(); // chosen here: no page reload
            if (event.detail && event.detail.media) { choose(event.detail.media); }
        });
        dialog.addEventListener('close', function () {
            current = null;
        });
    }

    function state(kind, message, retryUrl) {
        body.textContent = '';
        body.setAttribute('aria-busy', kind === 'loading' ? 'true' : 'false');
        var box = el('div', 'adm-picker-state');
        box.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        if (kind === 'loading') {
            var bar = el('progress', 'adm-progress');
            box.appendChild(bar);
        }
        var text = el('p');
        text.textContent = message;
        box.appendChild(text);
        if (retryUrl) {
            var retry = el('button', 'btn btn--sm btn--secondary');
            retry.type = 'button';
            retry.textContent = texts.retry || (current && current.trigger ? (current.trigger.textContent || '').trim() : '') || t('close');
            retry.addEventListener('click', function () { load(retryUrl); });
            box.appendChild(retry);
        }
        body.appendChild(box);
    }

    function load(url, keepContent) {
        if (!keepContent || !AP.qs('[data-picker-root]', body)) {
            state('loading', texts.loading || '');
        } else {
            body.setAttribute('aria-busy', 'true');
        }

        return fetch(url, {
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            return response.text().then(function (html) {
                if (!response.ok || html.indexOf('data-picker-root') === -1) {
                    throw new Error(response.status === 401 || /\/connexion/.test(response.url || '') ? 'expired' : 'failed');
                }
                return html;
            });
        }).then(function (html) {
            // The server-rendered picker partial (escaped by Blade): the only HTML inserted this way.
            body.innerHTML = html;
            body.setAttribute('aria-busy', 'false');
            var root = AP.qs('[data-picker-root]', body);
            if (root) {
                texts.title = root.getAttribute('data-picker-title') || texts.title;
                texts.chosen = root.getAttribute('data-picker-chosen') || texts.chosen;
                texts.cleared = root.getAttribute('data-picker-cleared') || texts.cleared;
                if (texts.title) { title.textContent = texts.title; }
                root.setAttribute('data-picker-url', url);
            }
            if (AP.admin.refresh) { AP.admin.refresh(body); }
            return ensureUploader().then(function () {
                if (AP.uploader) { AP.uploader.init(body); }
                if (!keepContent) {
                    // The search box on computers; on phones a focused field would open the keyboard.
                    var fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
                    var focus = fine ? AP.qs('[data-adm-dialog-focus]', body) : AP.qs('.adm-media-filters__chip', body);
                    if (focus) { focus.focus({ preventScroll: true }); }
                } else {
                    body.scrollTop = 0;
                }
            });
        }).catch(function (error) {
            state('error', error && error.message === 'expired' ? t('expired') : (texts.failed || t('network')), url);
        });
    }

    function pickerUrl(trigger) {
        var base = trigger.getAttribute('data-media-picker-url')
            || (trigger.tagName === 'A' && trigger.getAttribute('href') ? trigger.getAttribute('href') : '/admin/photos');
        var url = new URL(base, window.location.href);
        url.search = '';
        url.hash = '';
        url.searchParams.set('picker', '1');
        var selected = selectedId();
        if (selected) { url.searchParams.set('selected', selected); }
        return url.href;
    }

    function selectedId() {
        if (!current) { return ''; }
        var input = current.mode === 'slot' ? AP.qs('input[name="media_id"]', current.form) : current.input;
        var value = input ? String(input.value || '') : '';
        return /^\d+$/.test(value) ? value : '';
    }

    function targetInput(trigger) {
        var selector = trigger.getAttribute('data-media-target');
        if (!selector) { return null; }
        try { return document.querySelector(selector); } catch (e) { return null; }
    }

    function open(trigger) {
        if (!AP.admin || !AP.admin.openDialog || typeof fetch !== 'function') { return false; }
        var slotForm = trigger.hasAttribute('data-slot') ? trigger.closest('form[data-slot-form]') : null;
        var input = slotForm ? null : targetInput(trigger);
        if (!slotForm && !input) { return false; }

        ensureStyles();
        build();
        // Strings of the dialog before its content arrives (the slot partial renders them).
        try {
            var given = JSON.parse(trigger.getAttribute('data-picker-i18n') || '{}') || {};
            Object.keys(given).forEach(function (key) { if (typeof given[key] === 'string') { texts[key] = given[key]; } });
        } catch (e) { /* defaults */ }
        current = slotForm
            ? { mode: 'slot', trigger: trigger, form: slotForm }
            : { mode: 'field', trigger: trigger, input: input, field: trigger.closest('[data-media-field]') };

        title.textContent = texts.title || (trigger.textContent || '').trim();
        AP.admin.openDialog(dialog);
        load(pickerUrl(trigger));
        return true;
    }

    /* --- Inside the dialog -------------------------------------------------------------------- */

    function mediaOf(button) {
        try { return JSON.parse(button.getAttribute('data-media') || 'null'); } catch (e) { return null; }
    }

    function onBodyClick(event) {
        var chooser = event.target.closest('[data-media-choose]');
        if (chooser && body.contains(chooser)) {
            event.preventDefault();
            var media = mediaOf(chooser);
            if (media) { choose(media); }
            return;
        }

        var link = event.target.closest('a[data-picker-link], .adm-pagination a[href]');
        if (link && body.contains(link) && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault();
            load(link.href, true);
        }
    }

    function onBodySubmit(event) {
        var form = event.target;
        if (!form || !form.hasAttribute('data-picker-form')) { return; }
        event.preventDefault();
        var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        var params = new URLSearchParams();
        new FormData(form).forEach(function (value, name) {
            if (typeof value === 'string' && value !== '') { params.append(name, value); }
        });
        params.set('picker', '1');
        url.search = params.toString();
        load(url.href, true);
    }

    /* --- Choosing --------------------------------------------------------------------------- */

    function preview(field, media) {
        if (!field) { return; }
        var frame = AP.qs('[data-media-preview]', field);
        if (frame) {
            var img = AP.qs('img', frame);
            if (!img) {
                img = el('img');
                img.decoding = 'async';
                frame.appendChild(img);
            }
            img.setAttribute('src', media.thumb || media.url || '');
            if (media.srcset) {
                img.setAttribute('srcset', media.srcset);
                if (!img.getAttribute('sizes')) { img.setAttribute('sizes', '(min-width: 64em) 24rem, 90vw'); }
            } else {
                img.removeAttribute('srcset');
            }
            img.setAttribute('alt', '');
            img.style.objectPosition = media.object_position || '';
            img.hidden = false;
        }
        AP.qsa('[data-media-empty]', field).forEach(function (empty) { empty.hidden = true; });
        AP.qsa('[data-media-name]', field).forEach(function (name) { name.textContent = media.name || media.original_name || ''; });
        field.classList.add('has-media');
    }

    function setValue(input, value, media) {
        if (input.tagName === 'SELECT' && value !== '' && !Array.prototype.some.call(input.options, function (o) { return o.value === value; })) {
            var option = el('option');
            option.value = value;
            option.textContent = media && (media.name || media.original_name) ? (media.name || media.original_name) : '#' + value;
            input.appendChild(option);
        }
        input.value = value;
        fire(input, 'input');
        fire(input, 'change');
    }

    /** Uses a photo ({id, url, thumb, srcset, name…}, MediaController::json) for the current trigger. */
    function choose(media) {
        if (!current || !media || !media.id) { return; }
        var picked = current;
        var id = String(media.id);
        AP.admin.closeDialog(dialog);

        if (picked.mode === 'slot') {
            var input = AP.qs('input[name="media_id"]', picked.form);
            if (!input) { return; }
            input.value = id;
            picked.trigger.classList.add('is-busy');
            if (typeof picked.form.requestSubmit === 'function') {
                picked.form.requestSubmit();
            } else {
                picked.form.submit();
            }
            return;
        }

        setValue(picked.input, id, media);
        preview(picked.field, media);
        if (texts.chosen && AP.admin.toast) { AP.admin.toast(texts.chosen, 'success'); }
        if (picked.trigger && picked.trigger.focus) { picked.trigger.focus(); }
    }

    /** Empties a [data-media-field] (its picker's target input) and shows its empty state. */
    function clear(field) {
        if (!field) { return; }
        var trigger = AP.qs('[data-media-picker][data-media-target]', field);
        var input = trigger ? targetInput(trigger) : AP.qs('input[type="hidden"]', field);
        if (input && input.value !== '') { setValue(input, '', null); }
        var frame = AP.qs('[data-media-preview]', field);
        var img = frame ? AP.qs('img', frame) : null;
        if (img) {
            img.hidden = true;
            img.setAttribute('alt', '');
        }
        AP.qsa('[data-media-empty]', field).forEach(function (empty) { empty.hidden = false; });
        AP.qsa('[data-media-name]', field).forEach(function (name) { name.textContent = ''; });
        field.classList.remove('has-media');
    }

    /* --- Wiring ------------------------------------------------------------------------------- */

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey) { return; }

        var trigger = event.target.closest('[data-media-picker]');
        if (trigger && !(dialog && dialog.contains(trigger))) {
            if (open(trigger)) { event.preventDefault(); }
            return;
        }

        var clearButton = event.target.closest('[data-media-clear]');
        if (clearButton) {
            event.preventDefault();
            clear(clearButton.closest('[data-media-field]'));
        }
    });

    document.addEventListener('change', function (event) {
        var select = event.target;
        if (!select || select.tagName !== 'SELECT' || !select.hasAttribute('data-media-autosubmit') || !select.form) { return; }
        if (typeof select.form.requestSubmit === 'function') {
            select.form.requestSubmit();
        } else {
            select.form.submit();
        }
    });

    AP.mediaPicker = { open: open, choose: choose, clear: clear };
})();
