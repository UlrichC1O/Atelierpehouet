/* Ateliers Pehouet — artists.js: the artist screens of the admin (docs/ARTISTS.md §6.3).
   Pushed by the admin artist views (admin.artists.partials.assets), after core.js and admin.js. Every
   screen works without it; this only enhances:
     [data-artist-slug]                   the page address: a slug of [data-artist-slug-source] (the name)
                                          while it was not typed by hand (creation form); always cleaned on
                                          change; [data-artist-slug-preview] shows the address live
     [data-media-field]                   photo fields: the preview follows the <select data-media-select>
                                          (each <option> carries data-src, data-ratio, data-pos, data-meta). When the CMS
                                          picker (js/admin/media-picker.js) is on the page, the select becomes
                                          <input type="hidden" id="{data-media-input-id}"> and the
                                          [data-media-picker] / [data-media-clear] buttons appear; the preview
                                          follows that input's "change" event (the picker sets its value)
     [data-exhibition-start]              exhibition form: the start date's year goes into
                                          [data-exhibition-year]; the end date cannot precede it
   Untrusted strings are written with textContent / setAttribute only. */
(function () {
    'use strict';

    var AP = window.AP || {};
    var doc = document;

    function qsa(selector, scope) {
        return Array.prototype.slice.call((scope || doc).querySelectorAll(selector));
    }

    function qs(selector, scope) {
        return (scope || doc).querySelector(selector);
    }

    /** A bubbling event as if the user had typed (counters, dirty tracking of admin.js). */
    function fire(el, type) {
        var event;
        try {
            event = new Event(type, { bubbles: true });
        } catch (e) {
            event = doc.createEvent('Event');
            event.initEvent(type, true, false);
        }
        el.dispatchEvent(event);
    }

    /* --- 1. Page address (slug) -------------------------------------------------------- */

    var LETTERS = { 'œ': 'oe', 'Œ': 'oe', 'æ': 'ae', 'Æ': 'ae', 'ß': 'ss', 'ø': 'o', 'Ø': 'o', 'đ': 'd', 'Đ': 'd', 'ł': 'l', 'Ł': 'l', 'þ': 'th', 'Þ': 'th', '&': ' ', '@': ' at ' };
    var SLUG_MAX = 80;

    /** "Camille Durand-Œuvre" ⇒ "camille-durand-oeuvre" (as Str::slug on the server). */
    function slugify(value) {
        var text = String(value || '').replace(/[œŒæÆßøØđĐłŁþÞ&@]/g, function (ch) { return LETTERS[ch] || ''; });
        if (text.normalize) { text = text.normalize('NFD').replace(/[̀-ͯ]/g, ''); }
        text = text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return text.slice(0, SLUG_MAX).replace(/-+$/, '');
    }

    function initSlug(slug) {
        if (slug.__apSlug) { return; }
        slug.__apSlug = true;

        var form = slug.form || doc;
        var source = qs('[data-artist-slug-source]', form);
        var previews = qsa('[data-artist-slug-preview]', form);
        // Typed by hand when it holds something else than the slug of the name.
        var manual = slug.value.trim() !== '' && (!source || slug.value !== slugify(source.value));

        function show() {
            var value = slugify(slug.value);
            previews.forEach(function (preview) {
                preview.textContent = value || preview.getAttribute('data-placeholder') || '';
            });
        }

        if (source) {
            source.addEventListener('input', function () {
                if (manual) { return; }
                var value = slugify(source.value);
                if (slug.value !== value) {
                    slug.value = value;
                    fire(slug, 'input');
                }
                show();
            });
        }

        slug.addEventListener('input', function (event) {
            if (event.isTrusted !== false) { manual = slug.value.trim() !== ''; }
            show();
        });

        slug.addEventListener('change', function () {
            var value = slugify(slug.value);
            if (value !== slug.value) {
                slug.value = value;
                fire(slug, 'input');
            }
            if (value === '' && source) {
                manual = false;
                slug.value = slugify(source.value);
                fire(slug, 'input');
            }
            show();
        });

        show();
    }

    /* --- 2. Photo fields ---------------------------------------------------------------- */

    function pickerPresent() {
        return !!qs('script[src*="js/admin/media-picker.js"]');
    }

    function optionFor(select, value) {
        if (!select || !value) { return null; }
        var options = select.options || [];
        for (var i = 0; i < options.length; i++) {
            if (options[i].value === String(value)) { return options[i]; }
        }
        return null;
    }

    function setHidden(el, hidden) {
        if (el) { el.hidden = !!hidden; }
    }

    /** Shows the photo of id `value` in the field (or the empty state). */
    function syncPreview(field, select, value) {
        var frame = qs('[data-media-preview]', field);
        var img = frame ? qs('img', frame) : null;
        var empty = qs('[data-media-empty]', field);
        var meta = qs('[data-media-meta]', field);
        var edit = qs('[data-media-edit]', field);
        var natural = field.hasAttribute('data-media-natural');
        var option = optionFor(select, value);
        var src = option ? option.getAttribute('data-src') : null;
        var currentSrc = img ? img.getAttribute('src') || '' : '';

        if (!value) {
            if (img) {
                img.hidden = true;
                img.setAttribute('alt', '');
            }
            setHidden(empty, false);
            if (meta) { meta.textContent = ''; meta.hidden = true; }
            setHidden(edit, true);
            if (frame && natural) { frame.style.aspectRatio = '4 / 3'; }
            return;
        }

        if (img && src) {
            if (currentSrc !== src) {
                img.removeAttribute('srcset');
                img.removeAttribute('sizes');
                img.setAttribute('src', src);
                img.classList.remove('is-updated');
                void img.offsetWidth; // restart the fade-in
                img.classList.add('is-updated');
            }
            img.style.objectPosition = option.getAttribute('data-pos') || '';
            if (frame && natural && option.getAttribute('data-ratio')) {
                frame.style.aspectRatio = option.getAttribute('data-ratio');
            }
        }

        // A photo the picker just uploaded is not in the list: keep the image it set, if any.
        var shows = !!(img && (src || (currentSrc && currentSrc.indexOf('data:') !== 0)));
        if (img) {
            img.hidden = !shows;
            img.setAttribute('alt', '');
        }
        setHidden(empty, shows);

        if (meta) {
            var described = option ? option.getAttribute('data-meta') : null;
            meta.textContent = described || (field.getAttribute('data-media-selected-label') || '#__ID__').replace('__ID__', String(value));
            meta.hidden = false;
        }

        var template = field.getAttribute('data-media-edit-template');
        if (edit && template && /^\d+$/.test(String(value))) {
            edit.setAttribute('href', template.replace('__ID__', String(value)));
            edit.hidden = false;
        }
    }

    function initMediaField(field) {
        if (field.__apMediaField) { return; }
        field.__apMediaField = true;

        var select = qs('select[data-media-select]', field);
        if (!select) { return; }
        var frame = qs('[data-media-preview]', field);
        var img = frame ? qs('img', frame) : null;

        // Natural-ratio fields follow the real size of whatever image ends up in the frame.
        if (img && frame && field.hasAttribute('data-media-natural')) {
            img.addEventListener('load', function () {
                if (!img.hidden && img.naturalWidth > 1 && img.naturalHeight > 1) {
                    frame.style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight;
                }
            });
        }

        if (!pickerPresent() || !select.name) {
            // No CMS picker: the <select> stays; the preview follows it.
            select.addEventListener('change', function () { syncPreview(field, select, select.value); });
            return;
        }

        // CMS picker present (docs/CMS.md §7.6, field mode): hidden input + picker buttons.
        var input = doc.createElement('input');
        input.type = 'hidden';
        input.id = select.getAttribute('data-media-input-id') || '';
        input.name = select.name;
        input.value = select.value;
        select.parentNode.insertBefore(input, select);
        select.removeAttribute('name');
        select.hidden = true;
        setHidden(qs('[data-media-fallback]', field), true);
        setHidden(qs('[data-media-library-hint]', field), true);

        qsa('[data-media-picker], [data-media-clear]', field).forEach(function (button) { button.hidden = false; });

        var last = input.value;
        function onChange() {
            if (input.value === last) { return; }
            last = input.value;
            syncPreview(field, select, input.value);
        }
        input.addEventListener('change', onChange);
        input.addEventListener('input', onChange);

        // "Retirer" (the picker may handle it too: setting the same empty value twice is harmless).
        qsa('[data-media-clear]', field).forEach(function (button) {
            button.addEventListener('click', function () {
                if (input.value === '') { return; }
                input.value = '';
                fire(input, 'change');
            });
        });
    }

    /* --- 3. Exhibition dates -------------------------------------------------------------- */

    function initExhibitionDates(start) {
        if (start.__apDates) { return; }
        start.__apDates = true;

        var form = start.form || doc;
        var year = qs('[data-exhibition-year]', form);
        var end = qs('[data-exhibition-end]', form);

        function apply() {
            var match = /^(\d{4})-\d{2}-\d{2}$/.exec(start.value || '');
            if (match && year && year.value !== match[1]) {
                year.value = match[1];
                fire(year, 'input');
            }
            if (end) {
                end.min = match ? start.value : '1900-01-01';
            }
        }

        start.addEventListener('change', apply);
        start.addEventListener('input', apply);
    }

    /* --- Init ------------------------------------------------------------------------------ */

    function init(scope) {
        qsa('[data-artist-slug]', scope).forEach(initSlug);
        qsa('[data-media-field]', scope).forEach(initMediaField);
        qsa('[data-exhibition-start]', scope).forEach(initExhibitionDates);
    }

    AP.artists = { init: init, slugify: slugify };

    // Deferred: the document is parsed. Run now (before media-picker.js, loaded after this file).
    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', function () { init(doc); }, { once: true });
    } else {
        init(doc);
    }
})();
