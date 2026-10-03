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

    /* --- Photo uploads while the CMS uploader is not installed ------------------------------- */
    // Stop-gap for the CMS drop zones of the artist screens (admin.media.partials.uploader): until
    // public/js/admin/uploader.js exists, photos are resized in the browser (the CMS settings in the
    // form's data-* attributes), sent one by one as JSON requests (photo + variants[w] + original_name,
    // docs/CMS.md §7.6) and the page is reloaded. Without JavaScript the plain form still works.

    function uploaderPresent() {
        return !!qs('script[src*="js/admin/uploader.js"]');
    }

    function uploadText(key, replace) {
        var holder = qs('[data-artist-upload-i18n]');
        var text = holder ? holder.getAttribute('data-' + key) || '' : '';
        Object.keys(replace || {}).forEach(function (name) { text = text.split(':' + name).join(String(replace[name])); });
        return text;
    }

    function decode(file) {
        if (window.createImageBitmap) {
            return window.createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return decodeWithImage(file); });
        }
        return decodeWithImage(file);
    }

    function decodeWithImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('unreadable')); };
            img.src = url;
        });
    }

    function encode(source, width, height, type, quality) {
        return new Promise(function (resolve, reject) {
            var canvas = doc.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            var context = canvas.getContext('2d');
            context.imageSmoothingQuality = 'high';
            context.drawImage(source, 0, 0, width, height);
            canvas.toBlob(function (blob) { return blob ? resolve(blob) : reject(new Error('encode')); }, type, quality);
        });
    }

    /** The photo to send: resized to the CMS settings, with its narrower variants. GIFs are sent as they are. */
    function prepare(file, settings) {
        if (file.type === 'image/gif') {
            return Promise.resolve({ photo: file, name: file.name, variants: [] });
        }

        return decode(file).then(function (image) {
            var width = image.width || image.naturalWidth;
            var height = image.height || image.naturalHeight;
            var scale = Math.min(1, settings.maxEdge / Math.max(width, height));
            var mainWidth = Math.max(1, Math.round(width * scale));
            var mainHeight = Math.max(1, Math.round(height * scale));

            return encode(image, mainWidth, mainHeight, 'image/webp', settings.quality).then(function (blob) {
                // Browsers that cannot encode WebP answer with a PNG: use JPEG then.
                return blob.type === 'image/webp' ? blob : encode(image, mainWidth, mainHeight, 'image/jpeg', 0.86);
            }).then(function (main) {
                var type = main.type;
                var extension = type === 'image/webp' ? '.webp' : '.jpg';
                var base = file.name.replace(/\.[^.]+$/, '') || 'photo';
                var widths = settings.widths.filter(function (w) { return w < mainWidth; });

                return Promise.all(widths.map(function (w) {
                    return encode(image, w, Math.max(1, Math.round(mainHeight * w / mainWidth)), type, type === 'image/webp' ? settings.quality : 0.86)
                        .then(function (blob) { return { width: w, blob: blob, name: base + '-' + w + extension }; });
                })).then(function (variants) {
                    if (image.close) {
                        image.close();
                    }
                    return { photo: main, name: base + extension, variants: variants };
                });
            });
        });
    }

    function send(form, prepared, original, onProgress) {
        return new Promise(function (resolve, reject) {
            var data = new FormData(form);
            data.delete('photo');
            data.append('photo', prepared.photo, prepared.name);
            prepared.variants.forEach(function (variant) { data.append('variants[' + variant.width + ']', variant.blob, variant.name); });
            data.append('original_name', original.name);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 120000;
            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    onProgress(Math.round(event.loaded / event.total * 100));
                }
            });
            xhr.onload = function () {
                var body = null;
                try { body = JSON.parse(xhr.responseText); } catch (e) { body = null; }
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(body);
                } else if (xhr.status === 413) {
                    reject(new Error(uploadText('too-large')));
                } else if (xhr.status === 419) {
                    reject(new Error(uploadText('expired')));
                } else {
                    reject(new Error((body && body.message) || uploadText('failed')));
                }
            };
            xhr.onerror = xhr.ontimeout = function () { reject(new Error(uploadText('network'))); };
            xhr.send(data);
        });
    }

    function initUpload(form) {
        var input = qs('input[type="file"][data-uploader-input]', form);
        var list = qs('[data-uploader-list]', form);
        var drop = qs('[data-uploader-drop]', form);
        var widths = [];

        if (!input || !window.FormData || !window.XMLHttpRequest || !window.Promise || form.hasAttribute('data-artist-upload')) {
            return;
        }

        form.setAttribute('data-artist-upload', '');
        try { widths = JSON.parse(form.getAttribute('data-widths') || '[]'); } catch (e) { widths = []; }

        var settings = {
            maxEdge: parseInt(form.getAttribute('data-max-edge'), 10) || 1920,
            quality: parseFloat(form.getAttribute('data-quality')) || 0.82,
            widths: widths.map(Number).filter(function (w) { return w > 0; }),
            maxRequest: parseInt(form.getAttribute('data-max-request'), 10) || 4000000
        };
        var busy = false;

        if (form.getAttribute('data-multiple') === '1') {
            input.multiple = true;
        }

        function row(file) {
            var item = doc.createElement('li');
            var name = doc.createElement('span');
            var state = doc.createElement('span');
            item.className = 'adm-uploads__item';
            name.className = 'adm-uploads__name';
            state.className = 'adm-uploads__status';
            name.textContent = file.name;
            item.appendChild(name);
            item.appendChild(state);
            if (list) {
                list.hidden = false;
                list.appendChild(item);
            }
            return {
                set: function (text, status) {
                    state.textContent = text;
                    item.setAttribute('data-status', status);
                    item.classList.toggle('is-done', status === 'done');
                    item.classList.toggle('is-error', status === 'failed');
                }
            };
        }

        function run(files) {
            if (busy || !files.length) {
                return;
            }
            busy = true;
            form.classList.add('is-busy');
            var sent = 0;
            var rows = files.map(function (file) {
                var r = row(file);
                r.set(uploadText('queued'), 'queued');
                return r;
            });

            files.reduce(function (chain, file, index) {
                return chain.then(function () {
                    rows[index].set(uploadText('preparing'), 'processing');
                    return prepare(file, settings).then(function (prepared) {
                        var total = prepared.photo.size + prepared.variants.reduce(function (sum, v) { return sum + v.blob.size; }, 0);
                        if (total > settings.maxRequest) {
                            prepared.variants = []; // the main photo alone, the server needs no variant
                        }
                        if (prepared.photo.size > settings.maxRequest) {
                            throw new Error(uploadText('too-large'));
                        }
                        return send(form, prepared, file, function (percent) {
                            rows[index].set(uploadText('uploading', { percent: percent }), 'uploading');
                        });
                    }, function () {
                        throw new Error(uploadText('unreadable'));
                    }).then(function () {
                        sent++;
                        rows[index].set(uploadText('done'), 'done');
                    }).catch(function (error) {
                        rows[index].set(uploadText('failed') + ' — ' + error.message, 'failed');
                    });
                });
            }, Promise.resolve()).then(function () {
                busy = false;
                form.classList.remove('is-busy');
                input.value = '';
                if (sent > 0) {
                    var notice = row({ name: '' });
                    notice.set(uploadText('reloading'), 'done');
                    window.location.reload();
                }
            });
        }

        form.addEventListener('submit', function (event) {
            if (input.files && input.files.length) {
                event.preventDefault();
                run(Array.prototype.slice.call(input.files));
            }
        });
        input.addEventListener('change', function () {
            run(Array.prototype.slice.call(input.files || []));
        });

        if (drop) {
            drop.addEventListener('dragover', function (event) {
                event.preventDefault();
                form.classList.add('is-dragover');
            });
            drop.addEventListener('dragleave', function () { form.classList.remove('is-dragover'); });
            drop.addEventListener('drop', function (event) {
                event.preventDefault();
                form.classList.remove('is-dragover');
                var files = Array.prototype.slice.call((event.dataTransfer && event.dataTransfer.files) || []);
                run(input.multiple ? files : files.slice(0, 1));
            });
        }
    }

    /* --- Init ------------------------------------------------------------------------------ */

    function init(scope) {
        qsa('[data-artist-slug]', scope).forEach(initSlug);
        qsa('[data-media-field]', scope).forEach(initMediaField);
        qsa('[data-exhibition-start]', scope).forEach(initExhibitionDates);
        if (!uploaderPresent()) {
            qsa('form[data-uploader]', scope).forEach(initUpload);
        }
    }

    AP.artists = { init: init, slugify: slugify };

    // Deferred: the document is parsed. Run now (before media-picker.js, loaded after this file).
    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', function () { init(doc); }, { once: true });
    } else {
        init(doc);
    }
})();
