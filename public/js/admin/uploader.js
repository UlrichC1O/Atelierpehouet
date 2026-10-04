/* Ateliers Pehouet — uploader.js: photo uploads of the admin (docs/CMS.md §7.6, §13 C10/C13/C14).
   Needs core.js (window.AP) and admin.js (AP.admin). Enhances every form[data-uploader]
   (admin.media.partials.uploader); without this script the form posts one file the classic way.

   For each file (file picker with `multiple`, drag & drop, paste, phone camera), one at a time:
     - GIF: sent untouched (an animation), refused before sending when over data-max-request;
     - HEIC/HEIF the browser cannot decode: a French help message, never uploaded as is;
     - other images: decoded (createImageBitmap with the EXIF orientation, else <img>), resized to
       data-max-edge on the longest side, encoded as WebP at data-quality only when the browser really
       produces WebP (blob.type, Safari answers PNG), else JPEG 0.86 — variants too, for every width of
       data-widths narrower than the photo; each ImageBitmap is closed after use;
     - sent with XMLHttpRequest (progress, timeout) to the form's own action with new FormData(form):
       every hidden field is kept, the file input is replaced by photo + variants[w] + original_name.
   Any 2xx JSON answer holding a `media` object is a success: the form dispatches "ap:media-uploaded"
   (bubbles, cancelable, detail {media, response}). A page that handles it calls preventDefault();
   otherwise, once the batch is over, the page reloads and shows the result (message + "Voir sur le
   site" when the answer gives view_url) in [data-uploader-notice]. At the end of every batch the form
   dispatches "ap:uploader-done" (detail {uploaded: [responses], failed: count}).
   Failures stay listed with their reason; network, timeout, session and server errors get "Réessayer".
   Leaving the page while photos are being sent asks for confirmation (beforeunload).
   API: AP.uploader = { init(scope), enhance(form), add(form, files) }
   Strings: data-uploader-i18n (lang/{fr,en}/admin_media.php "uploader"). */
(function () {
    'use strict';

    var AP = window.AP;
    if (!AP || AP.uploader) { return; }

    var HEIC_TYPES = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];
    var IMAGE_NAME = /\.(jpe?g|jfif|png|webp|gif|heic|heif|avif|bmp|tiff?)$/i;
    var JPEG_QUALITY = 0.86;
    var LOWER_QUALITIES = [0.7, 0.55];
    var TIMEOUT = 120000;
    var OVERHEAD = 64 * 1024; // the form's other fields and the multipart headers
    var NOTICE_KEY = 'adm-upload-notice';
    var NOTICE_TTL = 60000;
    var ICON_DONE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.8 2.8L16.2 9"/></svg>';
    var ICON_ERROR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3.5 21.5 20H2.5Z"/><path d="M12 9.5V14M12 17h.01"/></svg>';
    var ICON_CLOSE = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/></svg>';

    var webp = null; // can this browser encode WebP? null until the first photo tells
    var leaving = false;

    /* --- Strings & settings --------------------------------------------------------------- */

    function strings(form) {
        if (!form.__apUploaderI18n) {
            try { form.__apUploaderI18n = JSON.parse(form.getAttribute('data-uploader-i18n') || '{}') || {}; } catch (e) { form.__apUploaderI18n = {}; }
        }
        return form.__apUploaderI18n;
    }

    function fill(text, replace) {
        Object.keys(replace || {}).sort(function (a, b) { return b.length - a.length; }).forEach(function (name) {
            text = text.split(':' + name).join(String(replace[name]));
        });
        return text;
    }

    function t(form, key, replace) {
        var all = strings(form);
        var text = typeof all[key] === 'string' ? all[key] : (AP.admin && AP.admin.t ? AP.admin.t(key) : key);
        return fill(text, replace);
    }

    function json(value, fallback) {
        try {
            var parsed = JSON.parse(value);
            return parsed == null ? fallback : parsed;
        } catch (e) {
            return fallback;
        }
    }

    function settings(form) {
        var widths = json(form.getAttribute('data-widths') || '[]', []);
        return {
            maxEdge: parseInt(form.getAttribute('data-max-edge'), 10) || 1920,
            quality: parseFloat(form.getAttribute('data-quality')) || 0.82,
            widths: Array.isArray(widths) ? widths.map(function (w) { return parseInt(w, 10); }).filter(function (w) { return w > 0; }) : [],
            maxRequest: parseInt(form.getAttribute('data-max-request'), 10) || 4000000,
            maxFile: parseInt(form.getAttribute('data-max-file'), 10) || 3500000,
            multiple: form.getAttribute('data-multiple') === '1',
            replace: form.getAttribute('data-uploader-mode') === 'replace'
        };
    }

    function el(tag, className) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        return node;
    }

    function sameOrigin(url) {
        try { return new URL(url, window.location.href).origin === window.location.origin; } catch (e) { return false; }
    }

    function actionOf(form) {
        return new URL(form.getAttribute('action') || window.location.href, window.location.href).href;
    }

    /* --- Image processing --------------------------------------------------------------------- */

    function problem(key, retryable, message, status) {
        var error = new Error(key);
        error.key = key;
        error.retryable = !!retryable;
        error.text = message || '';
        error.status = status || 0;
        return error;
    }

    function decodeWithImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.decoding = 'async';
            img.onload = function () {
                var width = img.naturalWidth;
                var height = img.naturalHeight;
                if (!width || !height) {
                    URL.revokeObjectURL(url);
                    reject(new Error('empty'));
                    return;
                }
                resolve({ source: img, width: width, height: height, close: function () { URL.revokeObjectURL(url); img.removeAttribute('src'); } });
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('decode'));
            };
            img.src = url;
        });
    }

    /** Decodes a file with its EXIF orientation applied: {source, width, height, close()}. */
    function decode(file) {
        if (typeof window.createImageBitmap !== 'function') { return decodeWithImage(file); }
        var attempt;
        try {
            attempt = window.createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch (e) {
            attempt = Promise.reject(e);
        }
        return attempt.then(function (bitmap) {
            return { source: bitmap, width: bitmap.width, height: bitmap.height, close: function () { if (bitmap.close) { bitmap.close(); } } };
        }).catch(function () { return decodeWithImage(file); });
    }

    function draw(source, width, height) {
        var canvas = el('canvas');
        canvas.width = width;
        canvas.height = height;
        var context = canvas.getContext('2d');
        if (!context) { throw problem('unreadable'); }
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(source, 0, 0, width, height);
        return canvas;
    }

    function release(canvas) {
        if (canvas && canvas.tagName === 'CANVAS') {
            canvas.width = 0;
            canvas.height = 0;
        }
    }

    /** Resizes by halves first (sharper than one big step), then to the exact size. */
    function resize(source, width, height, targetWidth, targetHeight) {
        var current = source;
        var w = width;
        var h = height;
        while (w / 2 >= targetWidth * 1.05 && h / 2 >= targetHeight * 1.05) {
            var next = draw(current, Math.round(w / 2), Math.round(h / 2));
            if (current !== source) { release(current); }
            current = next;
            w = next.width;
            h = next.height;
        }
        var result = draw(current, targetWidth, targetHeight);
        if (current !== source) { release(current); }
        return result;
    }

    function toBlob(canvas, type, quality) {
        return new Promise(function (resolve) {
            if (typeof canvas.toBlob !== 'function') { resolve(null); return; }
            canvas.toBlob(function (blob) { resolve(blob); }, type, quality);
        });
    }

    function required(blob) {
        if (!blob || !blob.size) { throw problem('unreadable'); }
        return blob;
    }

    /** WebP only when the browser really writes WebP (Safari answers PNG), else JPEG. */
    function encode(canvas, quality, jpegQuality) {
        if (webp === false) { return toBlob(canvas, 'image/jpeg', jpegQuality).then(required); }
        return toBlob(canvas, 'image/webp', quality).then(function (blob) {
            if (blob && blob.type === 'image/webp' && blob.size) {
                webp = true;
                return blob;
            }
            webp = false;
            return toBlob(canvas, 'image/jpeg', jpegQuality).then(required);
        });
    }

    function baseName(name) {
        var base = String(name || '').replace(/\.[^.]*$/, '').normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
        return base || 'photo';
    }

    function extensionOf(blob) {
        return blob.type === 'image/webp' ? 'webp' : (blob.type === 'image/png' ? 'png' : 'jpg');
    }

    /**
     * The files to send for one photo: {photo, filename, variants: [{width, blob, filename}]}.
     * Rejects with a problem (not_image, heic, unreadable, too_big, gif_too_big).
     */
    function prepare(file, config) {
        var type = String(file.type || '').toLowerCase();
        var name = String(file.name || '');
        var limit = Math.min(config.maxRequest - OVERHEAD, config.maxFile);

        if (type === 'image/gif' || (!type && /\.gif$/i.test(name))) {
            if (file.size > limit) { return Promise.reject(problem('gif_too_big')); }
            return Promise.resolve({ photo: file, filename: baseName(name) + '.gif', variants: [] });
        }

        if (type === 'image/svg+xml' || /\.svgz?$/i.test(name) || (type.indexOf('image/') !== 0 && !IMAGE_NAME.test(name))) {
            return Promise.reject(problem('not_image'));
        }

        var heic = HEIC_TYPES.indexOf(type) !== -1 || /\.(heic|heif)$/i.test(name);

        return decode(file).then(function (decoded) {
            var scale = Math.min(1, config.maxEdge / Math.max(decoded.width, decoded.height));
            var width = Math.max(1, Math.round(decoded.width * scale));
            var height = Math.max(1, Math.round(decoded.height * scale));
            var main;

            try {
                main = resize(decoded.source, decoded.width, decoded.height, width, height);
            } finally {
                decoded.close(); // one decoded photo in memory at a time
            }

            return encodeAll(main, width, height, config, name, limit);
        }, function () {
            throw problem(heic ? 'heic' : 'unreadable');
        });
    }

    function encodeAll(main, width, height, config, name, limit) {
        var base = baseName(name);
        var widths = config.widths.filter(function (w) { return w < width; }).sort(function (a, b) { return b - a; });
        var variants = [];
        var current = main;

        function smallerCopies(index) {
            if (index >= widths.length) { return Promise.resolve(); }
            var w = widths[index];
            var copy = resize(current, current.width, current.height, w, Math.max(1, Math.round(height * w / width)));
            return encode(copy, config.quality, JPEG_QUALITY).then(function (blob) {
                variants.unshift({ width: w, blob: blob, filename: base + '-' + w + '.' + extensionOf(blob) });
                if (current !== main) { release(current); }
                current = copy;
                return smallerCopies(index + 1);
            });
        }

        function lighter(blob, step) {
            if (blob.size <= limit) { return Promise.resolve(blob); }
            if (step >= LOWER_QUALITIES.length) { throw problem('too_big'); }
            var quality = LOWER_QUALITIES[step];
            return encode(main, quality, quality).then(function (next) { return lighter(next, step + 1); });
        }

        return encode(main, config.quality, JPEG_QUALITY)
            .then(function (blob) { return lighter(blob, 0); })
            .then(function (photo) {
                return smallerCopies(0).then(function () {
                    if (current !== main) { release(current); }
                    release(main);
                    var total = photo.size + OVERHEAD;
                    variants.forEach(function (variant) { total += variant.blob.size; });
                    // Over the request limit: the variants go (the server serves the photo itself).
                    return { photo: photo, filename: base + '.' + extensionOf(photo), variants: total > config.maxRequest ? [] : variants };
                });
            }, function (error) {
                release(main);
                throw error;
            });
    }

    /* --- Sending ---------------------------------------------------------------------------- */

    function httpProblem(status, body) {
        var message = body && typeof body.message === 'string' ? body.message : '';
        if (status === 0) { return problem('network', true); }
        if (status === 413) { return problem('server_too_big', false, '', status); }
        if (status === 419 || status === 401) { return problem('expired', true, '', status); }
        if (status === 422) {
            var reason = body && typeof body.reason === 'string' ? body.reason : '';
            return problem('error', reason === 'interrupted' || reason === 'storage', message, status);
        }
        return problem('error', status >= 500 || status === 429, status >= 500 ? message : (message || ''), status);
    }

    function send(form, task, progress) {
        var prepared = task.prepared;
        var data = new FormData(form);
        data.delete('photo');
        data.set('photo', prepared.photo, prepared.filename);
        prepared.variants.forEach(function (variant) {
            data.set('variants[' + variant.width + ']', variant.blob, variant.filename);
        });
        data.set('original_name', task.originalName);

        var token = AP.admin && AP.admin.csrf ? AP.admin.csrf() : '';
        if (token) { data.set('_token', token); }

        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', actionOf(form), true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (token) { xhr.setRequestHeader('X-CSRF-TOKEN', token); }
            xhr.timeout = TIMEOUT;
            if (xhr.upload) {
                xhr.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable && event.total) { progress(Math.min(99, Math.round(event.loaded / event.total * 100))); }
                });
            }
            xhr.onload = function () {
                var body = null;
                try { body = JSON.parse(xhr.responseText); } catch (e) { body = null; }
                if (xhr.status >= 200 && xhr.status < 300 && body && body.media && typeof body.media === 'object') {
                    resolve(body);
                    return;
                }
                reject(httpProblem(xhr.status, body));
            };
            xhr.onerror = function () { reject(problem('network', true)); };
            xhr.ontimeout = function () { reject(problem('timeout', true)); };
            xhr.onabort = function () { reject(problem('network', true)); };
            xhr.send(data);
        });
    }

    /** Sends a prepared photo; an expired CSRF token is refreshed and the photo sent once more. */
    function upload(form, task, progress) {
        return send(form, task, progress).catch(function (error) {
            if (error.status === 419 && !task.tokenRetried && AP.admin && AP.admin.refreshToken) {
                task.tokenRetried = true;
                return AP.admin.refreshToken().then(function (fresh) {
                    if (!fresh) { throw error; }
                    return send(form, task, progress);
                });
            }
            throw error;
        });
    }

    /* --- Rows of the status list ----------------------------------------------------------- */

    function makeRow(form, task) {
        var list = AP.qs('[data-uploader-list]', form);
        if (!list) { return null; }
        list.hidden = false;

        var item = el('li', 'adm-uploads__item is-queued');
        var thumb = el('img', 'adm-uploads__thumb');
        thumb.alt = '';
        thumb.hidden = true;
        var name = el('span', 'adm-uploads__name');
        name.textContent = task.originalName;
        var status = el('span', 'adm-uploads__status');
        var bar = el('progress', 'adm-progress');
        bar.max = 100;
        bar.value = 0;
        bar.hidden = true;
        var error = el('span', 'adm-uploads__error');

        item.appendChild(thumb);
        item.appendChild(name);
        item.appendChild(status);
        item.appendChild(bar);
        item.appendChild(error);
        list.appendChild(item);

        return { item: item, thumb: thumb, name: name, status: status, bar: bar, error: error, retry: null };
    }

    function setState(task, state, text) {
        var row = task.row;
        if (!row) { return; }
        ['is-queued', 'is-processing', 'is-uploading', 'is-done', 'is-error'].forEach(function (name) {
            row.item.classList.toggle(name, name === 'is-' + state);
        });
        row.status.textContent = text;
        row.bar.hidden = state !== 'uploading' && state !== 'processing';
        if (state === 'processing') { row.bar.removeAttribute('value'); }
        if (state !== 'error') { row.error.textContent = ''; }
        if (row.retry && state !== 'error') {
            row.retry.remove();
            row.retry = null;
        }
    }

    function showThumb(task, blob) {
        var row = task.row;
        if (!row || !blob || !window.URL || !URL.createObjectURL) { return; }
        if (task.thumbUrl) { URL.revokeObjectURL(task.thumbUrl); }
        task.thumbUrl = URL.createObjectURL(blob);
        row.thumb.src = task.thumbUrl;
        row.thumb.hidden = false;
    }

    function errorText(form, error) {
        if (error && error.text) { return error.text; }
        var key = error && error.key ? error.key : 'error';
        return t(form, key);
    }

    /* --- Queue ------------------------------------------------------------------------------- */

    function stateOf(form) {
        if (!form.__apUploads) {
            form.__apUploads = { queue: [], running: false, results: [], failed: [] };
        }
        return form.__apUploads;
    }

    function renamePasted(form, file) {
        if (file.name && !/^image\.(png|jpe?g|gif|webp)$/i.test(file.name)) { return file; }
        var now = new Date();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var stamp = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + '-' + pad(now.getHours()) + pad(now.getMinutes()) + pad(now.getSeconds());
        var extension = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
        try {
            return new File([file], t(form, 'pasted') + '-' + stamp + '.' + extension, { type: file.type });
        } catch (e) {
            return file;
        }
    }

    /** The destination choice wants a service: the select becomes required (the browser says so). */
    function formReady(form) {
        var select = AP.qs('[data-uploader-service]', form);
        var service = AP.qs('input[name="destination"][value="service"]', form);
        if (select) { select.required = !!(service && service.checked); }
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            if (typeof form.reportValidity === 'function') { form.reportValidity(); }
            return false;
        }
        return true;
    }

    function add(form, files) {
        if (!form || !files || !files.length) { return; }
        enhance(form);
        var config = settings(form);
        files = Array.prototype.slice.call(files);

        if (!formReady(form)) { return; }
        if (!config.multiple && files.length > 1) {
            files = files.slice(0, 1);
            if (AP.admin && AP.admin.toast) { AP.admin.toast(t(form, 'one_only'), 'info'); }
        }

        var state = stateOf(form);
        files.forEach(function (file) {
            var task = { file: file, originalName: String(file.name || 'photo'), prepared: null, row: null };
            task.row = makeRow(form, task);
            setState(task, 'queued', t(form, 'queued'));
            state.queue.push(task);
        });

        var notice = AP.qs('[data-uploader-notice]', form);
        if (notice) { notice.textContent = ''; }
        run(form);
    }

    function run(form) {
        var state = stateOf(form);
        if (state.running) { return; }
        var task = state.queue.shift();
        if (!task) {
            finish(form);
            return;
        }

        if (!state.started) {
            state.started = true;
            form.classList.add('is-busy');
            form.setAttribute('aria-busy', 'true');
        }
        state.running = true;
        state.active = true;

        var config = settings(form);
        // A page left open for a while: fetch a fresh CSRF token before the batch's first photo.
        var ready = state.tokenChecked || !(AP.admin && AP.admin.refreshToken) ? Promise.resolve() : AP.admin.refreshToken(6000);
        state.tokenChecked = true;

        ready.then(function () {
            if (task.prepared) { return null; }
            setState(task, 'processing', t(form, 'processing'));
            return prepare(task.file, config).then(function (prepared) {
                task.prepared = prepared;
                showThumb(task, prepared.photo);
            });
        }).then(function () {
            setState(task, 'uploading', t(form, 'uploading', { percent: 0 }));
            if (task.row) { task.row.bar.value = 0; }
            return upload(form, task, function (percent) {
                if (!task.row) { return; }
                task.row.bar.value = percent;
                task.row.status.textContent = t(form, 'uploading', { percent: percent });
            });
        }).then(function (response) {
            // Settled before the page hears of it: a page that leaves at once (picker, slot form)
            // must not get the "uploads in progress" warning.
            state.active = false;
            succeeded(form, task, response);
        }, function (error) {
            state.active = false;
            failed(form, task, error);
        }).then(function () {
            state.running = false;
            run(form);
        });
    }

    function succeeded(form, task, response) {
        var state = stateOf(form);
        setState(task, 'done', t(form, settings(form).replace ? 'replaced' : 'done'));
        var media = response.media || {};
        if (task.row && typeof media.edit_url === 'string' && sameOrigin(media.edit_url)) {
            var link = el('a');
            link.href = media.edit_url;
            link.textContent = task.originalName;
            task.row.name.textContent = '';
            task.row.name.appendChild(link);
        }

        var event;
        try {
            event = new CustomEvent('ap:media-uploaded', { bubbles: true, cancelable: true, detail: { media: media, response: response } });
        } catch (e) {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent('ap:media-uploaded', true, true, { media: media, response: response });
        }
        var handled = !form.dispatchEvent(event);
        state.results.push({ response: response, handled: handled });
    }

    function failed(form, task, error) {
        var state = stateOf(form);
        setState(task, 'error', t(form, 'failed'));
        if (!task.row) { return; }
        task.row.error.textContent = errorText(form, error);
        if (state.failed.indexOf(task) === -1) { state.failed.push(task); }

        if (error && error.retryable) {
            var retry = el('button', 'btn btn--sm btn--ghost adm-uploads__retry');
            retry.type = 'button';
            retry.textContent = t(form, 'retry');
            retry.setAttribute('aria-label', t(form, 'retry_label', { name: task.originalName }));
            retry.addEventListener('click', function () {
                state.failed = state.failed.filter(function (other) { return other !== task; });
                task.tokenRetried = false;
                setState(task, 'queued', t(form, 'queued'));
                state.queue.push(task);
                run(form);
            });
            task.row.retry = retry;
            task.row.item.appendChild(retry);
        }
    }

    function summary(form, results) {
        if (!results.length) { return null; }
        var first = results[0].response || {};
        if (results.length === 1) {
            return { text: typeof first.message === 'string' && first.message ? first.message : t(form, 'done'), url: first.view_url || null };
        }
        var where = first.where || '';
        var service = first.service || '';
        var url = first.view_url || null;
        var sameWhere = results.every(function (r) { return (r.response.where || '') === where && (r.response.service || '') === service; });
        var sameUrl = results.every(function (r) { return (r.response.view_url || null) === url; });
        var batch = strings(form).batch || {};
        var key = sameWhere && where && typeof batch[where] === 'string' ? where : 'other';
        var text = typeof batch[key] === 'string' ? batch[key] : String(results.length);
        return { text: fill(text, { count: results.length, service: service }), url: sameUrl ? url : null };
    }

    function finish(form) {
        var state = stateOf(form);
        if (state.started) {
            state.started = false;
            state.tokenChecked = false;
            form.classList.remove('is-busy');
            form.removeAttribute('aria-busy');
        }

        var results = state.results;
        state.results = [];
        var detail = { uploaded: results.map(function (r) { return r.response; }), failed: state.failed.length };
        try {
            form.dispatchEvent(new CustomEvent('ap:uploader-done', { bubbles: true, detail: detail }));
        } catch (e) { /* very old browser: the reload below still happens */ }

        if (!results.length) { return; }
        var info = summary(form, results);
        var unhandled = results.some(function (r) { return !r.handled; });

        // New photos the page did not take in itself: reload it — unless some failed: their rows
        // stay visible, and the notice offers the reload instead.
        if (unhandled && !state.failed.length) {
            reloadWith(form, info);
            return;
        }
        showNotice(form, info, 'success', unhandled);
    }

    /* --- Result notice (survives the reload through sessionStorage) ------------------------------ */

    function showNotice(form, info, type, offerReload) {
        var slot = AP.qs('[data-uploader-notice]', form);
        if (!slot || !info || !info.text) { return; }
        slot.textContent = '';

        var box = el('div', 'adm-upload-notice' + (type === 'error' ? ' adm-upload-notice--error' : ''));
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');
        var icon = el('span', 'adm-upload-notice__icon');
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = type === 'error' ? ICON_ERROR : ICON_DONE;
        var text = el('p', 'adm-upload-notice__text');
        text.textContent = info.text;
        box.appendChild(icon);
        box.appendChild(text);

        if (info.url && sameOrigin(info.url)) {
            var link = el('a', 'btn btn--sm btn--secondary adm-upload-notice__link');
            link.setAttribute('href', info.url);
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener');
            link.textContent = t(form, 'view');
            box.appendChild(link);
        }

        if (offerReload) {
            var reload = el('button', 'btn btn--sm btn--ghost adm-upload-notice__reload');
            reload.type = 'button';
            reload.textContent = t(form, 'show_new');
            reload.addEventListener('click', function () { reloadWith(form, info); });
            box.appendChild(reload);
        }

        var close = el('button', 'adm-icon-btn adm-icon-btn--sm adm-upload-notice__close');
        close.type = 'button';
        close.setAttribute('aria-label', t(form, 'close'));
        close.innerHTML = ICON_CLOSE;
        close.addEventListener('click', function () { slot.textContent = ''; });
        box.appendChild(close);

        slot.appendChild(box);
    }

    function reloadWith(form, info) {
        try {
            window.sessionStorage.setItem(NOTICE_KEY, JSON.stringify({ form: form.id || '', text: info ? info.text : '', url: info ? info.url : null, at: Date.now() }));
        } catch (e) { /* private mode: the page reloads without the message */ }

        AP.qsa('.adm-uploads__item.is-done .adm-uploads__status', form).forEach(function (status) {
            status.textContent = t(form, 'reloading');
        });
        leaving = true;
        window.location.assign(window.location.href.replace(/#.*$/, ''));
    }

    function restoreNotice() {
        var saved = null;
        try {
            saved = JSON.parse(window.sessionStorage.getItem(NOTICE_KEY) || 'null');
            window.sessionStorage.removeItem(NOTICE_KEY);
        } catch (e) {
            saved = null;
        }
        if (!saved || !saved.text || Date.now() - (saved.at || 0) > NOTICE_TTL) { return; }
        var form = (saved.form && document.getElementById(saved.form)) || AP.qs('form[data-uploader]');
        if (form && form.matches('form[data-uploader]')) {
            showNotice(form, { text: saved.text, url: saved.url }, 'success');
            var notice = AP.qs('[data-uploader-notice]', form);
            if (notice && notice.scrollIntoView && form.getBoundingClientRect().top > window.innerHeight) {
                notice.scrollIntoView({ block: 'center' });
            }
        }
    }

    /* --- Enhancement ------------------------------------------------------------------------------ */

    function hasFiles(event) {
        var types = event.dataTransfer && event.dataTransfer.types;
        return !!types && Array.prototype.indexOf.call(types, 'Files') !== -1;
    }

    function enhance(form) {
        if (!form || form.__apUploaderReady) { return; }
        form.__apUploaderReady = true;

        var config = settings(form);
        var input = AP.qs('[data-uploader-input]', form);
        var submit = AP.qs('[data-uploader-submit]', form);
        var zone = AP.qs('[data-uploader-drop]', form) || form;

        form.classList.add('is-enhanced');
        if (submit) { submit.hidden = true; }

        if (input) {
            input.removeAttribute('required');
            input.multiple = config.multiple;
            input.addEventListener('change', function () {
                var files = input.files ? Array.prototype.slice.call(input.files) : [];
                input.value = '';
                add(form, files);
            });
        }

        // Once enhanced, the form never posts the original file the classic way.
        form.addEventListener('submit', function (event) { event.preventDefault(); });

        // A click anywhere on the drop zone opens the file chooser (not on its own controls).
        zone.addEventListener('click', function (event) {
            if (!input || event.target.closest('a, button, label, input, select, textarea')) { return; }
            input.click();
        });

        var depth = 0;
        zone.addEventListener('dragenter', function (event) {
            if (!hasFiles(event)) { return; }
            event.preventDefault();
            depth += 1;
            form.classList.add('is-dragover');
        });
        zone.addEventListener('dragover', function (event) {
            if (!hasFiles(event)) { return; }
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
        });
        zone.addEventListener('dragleave', function () {
            depth = Math.max(0, depth - 1);
            if (!depth) { form.classList.remove('is-dragover'); }
        });
        zone.addEventListener('drop', function (event) {
            if (!hasFiles(event)) { return; }
            event.preventDefault();
            depth = 0;
            form.classList.remove('is-dragover');
            add(form, event.dataTransfer.files);
        });

        var destination = AP.qs('[data-uploader-destination]', form);
        if (destination) {
            destination.addEventListener('change', function () {
                var select = AP.qs('[data-uploader-service]', form);
                var service = AP.qs('input[name="destination"][value="service"]', form);
                if (select) {
                    select.required = !!(service && service.checked);
                    if (service && service.checked && !select.value) { select.focus(); }
                }
            });
        }
    }

    function init(scope) {
        AP.qsa('form[data-uploader]', scope || document).forEach(enhance);
    }

    /** The uploader a paste goes to: the one in an open dialog, else the first visible one. */
    function activeUploader() {
        var forms = AP.qsa('dialog[open] form[data-uploader], .adm-dialog.is-open form[data-uploader]');
        if (!forms.length) { forms = AP.qsa('form[data-uploader]'); }
        for (var i = 0; i < forms.length; i++) {
            if (forms[i].getClientRects().length) { return forms[i]; }
        }
        return null;
    }

    document.addEventListener('paste', function (event) {
        var target = event.target;
        if (target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName))) { return; }
        var data = event.clipboardData;
        var files = data && data.files ? Array.prototype.slice.call(data.files).filter(function (file) { return /^image\//.test(file.type); }) : [];
        if (!files.length) { return; }
        var form = activeUploader();
        if (!form) { return; }
        event.preventDefault();
        add(form, files.map(function (file) { return renamePasted(form, file); }));
    });

    // A photo dropped beside a drop zone must not replace the admin page with the image.
    document.addEventListener('dragover', function (event) {
        if (hasFiles(event) && AP.qs('form[data-uploader]')) { event.preventDefault(); }
    });
    document.addEventListener('drop', function (event) {
        if (hasFiles(event) && AP.qs('form[data-uploader]') && !event.defaultPrevented) { event.preventDefault(); }
    });

    /** Photos being prepared, sent or waiting their turn somewhere on the page. */
    function uploading() {
        return AP.qsa('form[data-uploader]').some(function (form) {
            var state = form.__apUploads;
            return !!state && (state.active || state.queue.length > 0);
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (leaving || !uploading()) { return undefined; }
        event.preventDefault();
        event.returnValue = '';
        return '';
    });

    AP.uploader = { init: init, enhance: enhance, add: add };

    AP.ready(function () {
        init(document);
        restoreNotice();
    });
})();
