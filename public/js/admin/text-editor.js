/* Ateliers Pehouet — text-editor.js: the content editors of the admin (docs/CMS.md §7.3, §7.4).
   Needs core.js (window.AP); admin.js gives the live filter, dirty tracking and toasts. Every screen
   works without it.

   « Textes des pages » — form[data-text-editor]:
     [data-text-row]          one text: .is-modified + its [data-text-badge] follow the fields
     [data-text-cell]         one language: [data-text-input] (data-placeholders = JSON list of tokens,
                              data-pipes = number of "|"), [data-text-default] (shown while the text
                              differs from [data-text-default-value]), [data-text-reset] ("Rétablir
                              l'original": the field shows the original, read-only), [data-text-keep]
                              (.is-missing while a placeholder or a "|" is gone)
     [data-text-counter]      "N textes modifiés" (data-zero / data-one / data-many with :count)
     [data-text-only]         "Seulement les textes modifiés" (form.is-only-modified)

   « Pages libres » — form[data-page-form]:
     [data-slug-source] ⇒ [data-slug-target]   the address follows the French title while
                              data-slug-auto is set and the address was not typed; [data-slug-preview]
     [data-page-previewed]    after "Mettre à jour l'aperçu" the previewed values count as unsaved
     [data-page-insert]       "Insérer une photo": picker field mode (js/admin/media-picker.js) on
                              #page-insert-media ⇒ ![alt](/media/{key}) at the cursor of the last
                              focused [data-page-body] (the visible one by default)
     [data-page-cover]        cover photo: with the picker, the <select> becomes a hidden input
                              #page-cover-input; the preview follows the value either way
   Untrusted strings are only written with textContent / setAttribute. */
(function () {
    'use strict';

    var AP = window.AP;
    if (!AP) { return; }

    var doc = document;

    function qs(selector, scope) { return (scope || doc).querySelector(selector); }
    function qsa(selector, scope) { return Array.prototype.slice.call((scope || doc).querySelectorAll(selector)); }

    function fire(el, type) {
        el.dispatchEvent(new Event(type, { bubbles: true }));
    }

    function clean(text) {
        return String(text || '').replace(/\r\n?/g, '\n').trim();
    }

    function toast(message, type) {
        if (message && AP.admin && typeof AP.admin.toast === 'function') {
            AP.admin.toast(message, type || 'success');
        }
    }

    /* --- 1. Texts editor ----------------------------------------------------------------- */

    function parseList(value) {
        try {
            var list = JSON.parse(value || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function initTextEditor(form) {
        if (form.__apTextEditor) { return; }
        form.__apTextEditor = true;

        var rows = qsa('[data-text-row]', form);
        var counter = qs('[data-text-counter]', form);
        var counterText = counter ? qs('[data-text-counter-text]', counter) : null;
        var only = qs('[data-text-only]', form);
        var onlyBox = only ? qs('input', only) : null;
        var lastCount = null;

        function cellState(cell) {
            var input = qs('[data-text-input]', cell);
            var reset = qs('[data-text-reset]', cell);
            var valueEl = qs('[data-text-default-value]', cell);
            var original = clean(valueEl ? valueEl.textContent : '');
            var value = clean(input ? input.value : '');
            var effective = reset && reset.checked ? original : (value === '' ? original : value);
            return { input: input, original: original, value: value, modified: effective !== original };
        }

        function checkPlaceholders(cell, state) {
            var keep = qs('[data-text-keep]', cell);
            if (!keep || !state.input) { return; }
            var tokens = parseList(state.input.getAttribute('data-placeholders'));
            var pipes = state.input.getAttribute('data-pipes');
            var lower = state.value.toLowerCase();
            var missing = state.value !== '' && tokens.some(function (token) { return lower.indexOf(String(token).toLowerCase()) === -1; });
            if (state.value !== '' && pipes !== null && (state.value.split('|').length - 1) !== parseInt(pipes, 10)) {
                missing = true;
            }
            keep.classList.toggle('is-missing', missing);
        }

        function updateRow(row) {
            var modified = false;
            qsa('[data-text-cell]', row).forEach(function (cell) {
                var state = cellState(cell);
                var original = qs('[data-text-default]', cell);
                var overridden = !!qs('[data-text-reset]', cell);
                if (original) { original.hidden = !(state.modified || overridden); }
                checkPlaceholders(cell, state);
                modified = modified || state.modified;
            });
            row.classList.toggle('is-modified', modified);
            var badge = qs('[data-text-badge]', row);
            if (badge) { badge.hidden = !modified; }
            return modified;
        }

        function updateCounter() {
            var count = rows.filter(function (row) { return row.classList.contains('is-modified'); }).length;
            if (counter && counterText) {
                var key = count === 0 ? 'data-zero' : (count === 1 ? 'data-one' : 'data-many');
                counterText.textContent = String(counter.getAttribute(key) || count).split(':count').join(String(count));
                counter.classList.toggle('is-zero', count === 0);
                if (lastCount !== null && lastCount !== count && AP.motion()) {
                    counter.classList.remove('is-bumped');
                    void counter.offsetWidth; // restart the animation
                    counter.classList.add('is-bumped');
                }
            }
            lastCount = count;
            qsa('[data-text-section]', form).forEach(function (section) {
                section.classList.toggle('is-unmodified', !qs('[data-text-row].is-modified', section));
            });
        }

        rows.forEach(updateRow);
        updateCounter();

        form.addEventListener('input', function (event) {
            var row = event.target.closest ? event.target.closest('[data-text-row]') : null;
            if (row) {
                updateRow(row);
                updateCounter();
            }
        });

        // "Rétablir l'original": the field shows the original (read-only) until unticked.
        form.addEventListener('change', function (event) {
            var box = event.target;
            if (!box.matches || !box.matches('[data-text-reset]')) { return; }
            var cell = box.closest('[data-text-cell]');
            var input = cell ? qs('[data-text-input]', cell) : null;
            var valueEl = cell ? qs('[data-text-default-value]', cell) : null;
            if (!input) { return; }
            if (box.checked) {
                input.__apBeforeReset = input.value;
                input.value = valueEl ? valueEl.textContent : '';
                input.readOnly = true;
            } else {
                if (typeof input.__apBeforeReset === 'string') { input.value = input.__apBeforeReset; }
                input.readOnly = false;
            }
            cell.classList.toggle('is-reset', box.checked);
            if (input.matches('textarea[data-autosize]') && AP.admin && AP.admin.autosize) { AP.admin.autosize(input); }
            updateRow(cell.closest('[data-text-row]'));
            updateCounter();
        });

        // Enter in the filter box filters; it never saves the form.
        qsa('[data-adm-filter]', form).forEach(function (search) {
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); }
            });
        });

        if (only && onlyBox) {
            only.hidden = false;
            onlyBox.addEventListener('change', function () {
                form.classList.toggle('is-only-modified', onlyBox.checked);
            });
        }
    }

    /* --- 2. Free pages ------------------------------------------------------------------- */

    var SLUG_MAX = 80;

    function slugify(text) {
        var value = String(text || '').toLowerCase()
            .replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/ß/g, 'ss');
        if (value.normalize) { value = value.normalize('NFD').replace(/[̀-ͯ]/g, ''); }
        value = value.replace(/['’]/g, '-').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return value.slice(0, SLUG_MAX).replace(/-+$/g, '');
    }

    function initSlug(form) {
        var source = qs('[data-slug-source]', form);
        var target = qs('[data-slug-target]', form);
        var preview = qs('[data-slug-preview]', form);
        if (!target) { return; }
        var auto = target.hasAttribute('data-slug-auto') && target.value === '';

        function show() {
            if (preview) { preview.textContent = target.value || (source && auto ? slugify(source.value) : ''); }
        }

        if (source) {
            source.addEventListener('input', function () {
                if (!auto) { return; }
                target.value = slugify(source.value);
                fire(target, 'input');
                show();
            });
        }

        target.addEventListener('input', function (event) {
            if (event.isTrusted) { auto = target.value === ''; }
            show();
        });

        target.addEventListener('blur', function () {
            var tidy = slugify(target.value);
            if (tidy !== target.value && target.value !== '') {
                target.value = tidy;
                fire(target, 'input');
            }
            show();
        });

        show();
    }

    function pickerPresent() {
        return !!qs('script[src*="js/admin/media-picker.js"]');
    }

    /** id ⇒ {key, alt: {fr, en}} from the cover <select> (every library photo is listed there). */
    function photoInfo(form, id) {
        var select = qs('[data-page-cover-select]', form);
        var options = select ? select.options : [];
        for (var i = 0; i < options.length; i++) {
            if (options[i].value === String(id) && options[i].getAttribute('data-key')) {
                return {
                    key: options[i].getAttribute('data-key'),
                    alt: { fr: options[i].getAttribute('data-alt-fr') || '', en: options[i].getAttribute('data-alt-en') || '' }
                };
            }
        }
        return null;
    }

    /** The key of a photo the picker just uploaded: from the URL it showed (/media/{ulid}[-{width}].{ext}). */
    function keyFromUrl(url) {
        var match = /\/media\/([0-9a-z]{26})(?:-[0-9]{2,4})?\.(jpg|png|webp|gif)(?:[?#].*)?$/.exec(String(url || ''));
        return match ? match[1] + '.' + match[2] : null;
    }

    function markdownAlt(text) {
        return String(text || '').replace(/[\r\n]+/g, ' ').replace(/[[\]\\]/g, '').trim();
    }

    function insertAt(textarea, snippet) {
        var value = textarea.value;
        var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : value.length;
        var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : value.length;
        var before = value.slice(0, start);
        var after = value.slice(end);
        // A photo is a paragraph of its own.
        var prefix = before === '' || /\n\n$/.test(before) ? '' : (/\n$/.test(before) ? '\n' : '\n\n');
        var suffix = after === '' ? '\n' : (/^\n\n/.test(after) ? '' : (/^\n/.test(after) ? '\n' : '\n\n'));
        var text = prefix + snippet + suffix;
        textarea.value = before + text + after;
        var caret = before.length + text.length;
        textarea.focus();
        try { textarea.setSelectionRange(caret, caret); } catch (e) { /* ignore */ }
        fire(textarea, 'input');
    }

    function initInsert(form) {
        var box = qs('[data-page-insert]', form);
        var input = box ? qs('[data-page-insert-input]', box) : null;
        var bodies = qsa('[data-page-body]', form).filter(function (el) { return el.tagName === 'TEXTAREA'; });
        if (!box || !input || !bodies.length || !pickerPresent()) { return; }
        box.hidden = false;

        var last = null;
        bodies.forEach(function (body) {
            body.addEventListener('focus', function () { last = body; });
        });

        function target() {
            if (last) { return last; }
            for (var i = 0; i < bodies.length; i++) {
                var panel = bodies[i].closest('[role="tabpanel"], .adm-tabs__panel');
                if (!panel || !panel.hidden) { return bodies[i]; }
            }
            return bodies[0];
        }

        // The picker sets the value (input + change), then shows the photo and focuses its button:
        // read everything once it is done.
        var pending = false;
        function onPick() {
            if (!input.value || pending) { return; }
            pending = true;
            setTimeout(function () {
                pending = false;
                insertPicked();
            }, 0);
        }

        function insertPicked() {
            var id = input.value;
            if (!id) { return; }
            var info = photoInfo(form, id);
            if (!info) {
                var img = qs('[data-media-preview] img', box);
                var key = keyFromUrl(img ? img.getAttribute('src') : '');
                info = key ? { key: key, alt: { fr: img.getAttribute('alt') || '', en: img.getAttribute('alt') || '' } } : null;
            }
            input.value = '';
            qsa('[data-media-preview]', box).forEach(function (el) { el.hidden = true; });
            if (!info) { return; }
            var textarea = target();
            var lang = textarea.getAttribute('lang') === 'en' ? 'en' : 'fr';
            var alt = markdownAlt(info.alt[lang] || info.alt.fr);
            insertAt(textarea, '![' + alt + '](/media/' + info.key + ')');
            toast(form.getAttribute('data-page-inserted'), 'success');
        }

        input.addEventListener('change', onPick);
        input.addEventListener('input', onPick);
    }

    function initCover(form) {
        var field = qs('[data-page-cover]', form);
        var select = field ? qs('[data-page-cover-select]', field) : null;
        if (!field || !select) { return; }

        var frame = qs('[data-media-preview]', field);
        var img = frame ? qs('img', frame) : null;
        var empty = qs('[data-media-empty]', field);
        var picker = qs('[data-media-picker]', field);
        var clear = qs('[data-media-clear]', field);

        function optionFor(value) {
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].value === String(value)) { return select.options[i]; }
            }
            return null;
        }

        function sync(value) {
            var option = value ? optionFor(value) : null;
            var src = option ? option.getAttribute('data-src') : null;
            if (img) {
                if (!value) {
                    img.hidden = true;
                } else if (src) {
                    img.removeAttribute('srcset');
                    img.removeAttribute('sizes');
                    img.setAttribute('src', src);
                    img.style.objectPosition = option.getAttribute('data-pos') || '';
                    img.hidden = false;
                } else {
                    // Uploaded in the picker: keep the image the picker put in the frame.
                    img.hidden = !(img.getAttribute('src') && img.getAttribute('src').indexOf('data:') !== 0);
                }
            }
            if (empty) { empty.hidden = !!(img && !img.hidden); }
            if (clear) { clear.hidden = !value; }
            if (picker) {
                var label = qs('.btn__label', picker);
                var text = picker.getAttribute(value ? 'data-label-change' : 'data-label-choose');
                if (label && text) { label.textContent = text; }
            }
        }

        if (!pickerPresent()) {
            select.addEventListener('change', function () { sync(select.value); });
            return;
        }

        // Picker field mode (docs/CMS.md §7.6): the form posts a hidden input the picker fills.
        var input = doc.createElement('input');
        input.type = 'hidden';
        input.id = 'page-cover-input';
        input.name = select.name || 'cover_media_id';
        input.value = select.value;
        field.insertBefore(input, field.firstChild);
        select.removeAttribute('name');
        var fallback = qs('[data-page-cover-fallback]', field);
        if (fallback) { fallback.hidden = true; }
        var library = qs('[data-page-cover-library]', field);
        if (library) { library.hidden = true; }
        var actions = qs('[data-page-cover-actions]', field);
        if (actions) { actions.hidden = false; }

        var last = input.value;
        function onChange() {
            if (input.value === last) { return; }
            last = input.value;
            sync(input.value);
        }
        input.addEventListener('change', onChange);
        input.addEventListener('input', onChange);

        if (clear) {
            clear.addEventListener('click', function () {
                if (input.value === '') { return; }
                input.value = '';
                fire(input, 'change');
            });
        }

        sync(input.value);
    }

    function initPageForm(form) {
        if (form.__apPageForm) { return; }
        form.__apPageForm = true;

        initSlug(form);
        initInsert(form);
        initCover(form);

        // After "Mettre à jour l'aperçu" the form shows values that are not saved yet: admin.js has
        // already read it as clean, so one hidden value changes to make it dirty (leave warning, save bar).
        var previewed = qs('[data-page-previewed]', form);
        if (previewed) {
            previewed.value = '0';
            fire(previewed, 'change');
        }
    }

    AP.ready(function () {
        qsa('form[data-text-editor]').forEach(initTextEditor);
        qsa('form[data-page-form]').forEach(initPageForm);
    });
})();
