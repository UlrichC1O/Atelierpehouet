/* Ateliers Pehouet — admin.js: behaviours of the admin CMS (docs/CMS.md §6).
   Needs core.js (window.AP). Every screen works without it; this only enhances:
     [data-adm-nav-toggle] / [data-adm-nav-close]   sidebar drawer below 64em (html.adm-nav-open)
     form[data-confirm]                             confirmation dialog before submitting
                                                    (+ data-confirm-label, data-confirm-variant, data-confirm-title)
     form[data-adm-dirty]                           unsaved-changes tracking: .is-dirty on the form and its
                                                    [data-adm-savebar], [data-adm-dirty-status] text, leave warning,
                                                    Ctrl/⌘+S submits; form[data-adm-busy] marks the submit button busy
     [data-adm-tabs]                                accessible tabs (.adm-tabs__list > .adm-tabs__tab[aria-controls]
                                                    + .adm-tabs__panel); data-adm-tabs="key" remembers the tab
     textarea[data-autosize]                        grows with its text
     [data-maxlength]                               live counter in [data-counter-for="{id}"] (created when missing)
     [data-adm-flash-close]                         dismisses its .adm-flash
     input[data-adm-filter="#scope"]                live filter of the [data-adm-filter-item] inside #scope (items may
                                                    carry data-adm-filter-text; [data-adm-filter-group] hide when empty);
                                                    input attributes data-adm-filter-count="#el" (result count) and
                                                    data-adm-filter-empty="#el" (shown when nothing matches; default:
                                                    [data-adm-filter-empty] inside #scope)
     [data-adm-reveal="{input id}"]                 show / hide a password
     [data-adm-dialog-open="#id"] / [data-adm-dialog-close]   open / close a <dialog class="adm-dialog">
     [data-adm-toast="message"] (+ data-adm-toast-type)        show a toast
     [data-adm-autofocus]                           focused on load (the validation summary)
   Session (docs/CMS.md §13 D20), on pages whose <body> has data-adm-token-url (GET admin.token ⇒ {token}):
     the CSRF token (meta + every input[name=_token]) is refreshed when the tab becomes visible again,
     every 10 min while a form[data-adm-dirty] has changes (keeps the session alive), before each POST
     form leaves (form[data-adm-no-token] opts out) and before/after fetchJSON writes (one retry on 419).
     A session that is gone shows a notice (sign in again in another tab) instead of losing the page.
     Drafts: on submit, a changed POST form[data-adm-dirty] is copied to sessionStorage (key: its action;
     never passwords, files or hidden fields; [data-adm-no-draft] fields skipped); a page with a success
     flash ([data-adm-flash="success"]) forgets it, the same form shown again without one gets it back
     (with a "back to the saved version" button), unless the page lists validation errors.
   API: AP.admin = { csrf(), refreshToken(timeoutMs) → Promise<token|null>, fetchJSON(url, opts),
                     toast(message, type), openDialog(el), closeDialog(el),
                     confirm({message, label, variant, title}) → Promise<bool>, t(key, replace),
                     markClean(form) (also forgets its draft), autosize(textarea), refresh(scope) }
   Raw XMLHttpRequest uploads should await AP.admin.refreshToken() first (the form's _token is updated).
   Strings come from <body data-adm-i18n> (lang/{fr,en}/admin.php "js"). */
(function () {
    'use strict';

    var AP = window.AP;
    if (!AP) { return; }

    var root = document.documentElement;
    var i18n = {};
    try { i18n = JSON.parse(document.body.getAttribute('data-adm-i18n') || '{}') || {}; } catch (e) { i18n = {}; }

    // The admin never shows the public intro loader (head.js may have flagged the first view).
    root.classList.remove('is-loading');

    var ICONS = {
        success: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.8 2.8L16.2 9"/></svg>',
        error: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>',
        info: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.8h.01"/></svg>',
        warning: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3.5 21.5 20H2.5Z"/><path d="M12 9.5V14M12 17h.01"/></svg>',
        close: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/></svg>'
    };

    function t(key, replace) {
        var text = Object.prototype.hasOwnProperty.call(i18n, key) ? String(i18n[key]) : key;
        if (replace) {
            Object.keys(replace).sort(function (a, b) { return b.length - a.length; }).forEach(function (name) {
                text = text.split(':' + name).join(String(replace[name]));
            });
        }
        return text;
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function closest(el, selector) {
        return el && el.closest ? el.closest(selector) : null;
    }

    /* --- Requests ------------------------------------------------------------- */

    function firstError(errors) {
        var keys = errors && typeof errors === 'object' ? Object.keys(errors) : [];
        for (var i = 0; i < keys.length; i++) {
            var list = errors[keys[i]];
            if (Array.isArray(list) && list.length) { return String(list[0]); }
            if (typeof list === 'string' && list) { return list; }
        }
        return '';
    }

    function errorMessage(status, data) {
        if (status === 419) { return t('expired'); }
        if (status === 413) { return t('too_large'); }
        if (status >= 400 && status < 500 && data) {
            if (status === 422 && data.errors) {
                var first = firstError(data.errors);
                if (first) { return first; }
            }
            if (typeof data.message === 'string' && data.message) { return data.message; }
        }
        return t('error');
    }

    /**
     * fetch() for the admin's JSON endpoints: same-origin cookies, Accept JSON, CSRF header.
     * opts: {method, body (FormData | URLSearchParams | plain object ⇒ JSON), headers, signal}.
     * FormData with PUT/PATCH/DELETE is sent as POST + _method (PHP only parses multipart POST bodies).
     * Writes first refresh a CSRF token older than 5 minutes; an HTTP 419 (expired token) refreshes it
     * and sends the request once more (docs/CMS.md §13 D20).
     * Resolves with the parsed JSON (or null); rejects with an Error {message (translated), status, data}.
     */
    function fetchJSON(url, opts) {
        opts = opts || {};
        var method = String(opts.method || (opts.body ? 'POST' : 'GET')).toUpperCase();
        var write = method !== 'GET' && method !== 'HEAD';
        var ready = write && tokenUrl && Date.now() - lastRefresh > TOKEN_STALE ? refreshToken() : Promise.resolve(null);

        return ready.then(function () { return sendJSON(url, opts, write); });
    }

    function sendJSON(url, opts, retry) {
        var body = opts.body;
        var method = String(opts.method || (body ? 'POST' : 'GET')).toUpperCase();
        var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        var token = csrf();

        if (method !== 'GET' && method !== 'HEAD') { headers['X-CSRF-TOKEN'] = token; }
        Object.keys(opts.headers || {}).forEach(function (name) { headers[name] = opts.headers[name]; });

        if (typeof FormData !== 'undefined' && body instanceof FormData) {
            // A form's own _token field wins over the header on the server: keep it current.
            if (body.has('_token')) { body.set('_token', token); }
            if (method !== 'POST' && method !== 'GET') {
                body.set('_method', method);
                method = 'POST';
            }
        } else if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
            if (body.has('_token')) { body.set('_token', token); }
        } else if (body && typeof body === 'object' && !(typeof Blob !== 'undefined' && body instanceof Blob)) {
            headers['Content-Type'] = 'application/json';
            if (Object.prototype.hasOwnProperty.call(body, '_token')) { body._token = token; }
            body = JSON.stringify(body);
        }

        return fetch(url, { method: method, headers: headers, body: body, credentials: 'same-origin', signal: opts.signal })
            .then(function (response) {
                if (response.status === 419 && retry && tokenUrl) {
                    return refreshToken().then(function (fresh) {
                        if (fresh) { return sendJSON(url, opts, false); }
                        return parseJSON(response);
                    });
                }
                return parseJSON(response);
            }, function (failure) {
                if (failure && failure.name === 'AbortError') { throw failure; }
                var error = new Error(t('network'));
                error.status = 0;
                error.data = null;
                throw error;
            });
    }

    function parseJSON(response) {
        return response.text().then(function (text) {
            var data = null;
            if (text) {
                try { data = JSON.parse(text); } catch (e) { data = null; }
            }
            if (!response.ok) {
                var error = new Error(errorMessage(response.status, data));
                error.status = response.status;
                error.data = data;
                throw error;
            }
            return data;
        });
    }

    /* --- Session: fresh CSRF token, drafts of the forms (docs/CMS.md §13 D20) ------------ */

    // Rendered by admin.layouts.app (signed-in pages only): GET admin.token ⇒ {token}.
    var tokenUrl = document.body.getAttribute('data-adm-token-url') || '';
    var loginUrl = document.body.getAttribute('data-adm-login-url') || '';
    var TOKEN_STALE = 5 * 60 * 1000;
    var KEEPALIVE = 10 * 60 * 1000;
    var DRAFT_PREFIX = 'adm-draft:';
    var DRAFT_LAST = 'adm-draft-last';
    var DRAFT_TTL = 24 * 60 * 60 * 1000;
    var lastRefresh = Date.now();
    var refreshing = null;
    var sessionGone = false;
    var sessionNotice = null;

    function setToken(token) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) { meta.setAttribute('content', token); }
        AP.qsa('input[name="_token"]').forEach(function (input) { input.value = token; });
    }

    /**
     * Asks the server for the session's CSRF token and puts it in the meta tag and every form.
     * Resolves with the token, or null (network failure, timeout, or the session is gone — then a
     * notice asks to sign in again in another tab). Concurrent calls share one request.
     */
    function refreshToken(timeout) {
        if (!tokenUrl || typeof fetch !== 'function') { return Promise.resolve(null); }
        if (refreshing) { return refreshing; }

        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, timeout || 8000) : null;

        refreshing = fetch(tokenUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            if (response.status === 401) {
                sessionLost();
                return null;
            }
            if (!response.ok) { return null; }
            return response.json().then(function (data) {
                var token = data && typeof data.token === 'string' ? data.token : '';
                if (!token) { return null; }
                setToken(token);
                lastRefresh = Date.now();
                sessionBack();
                return token;
            });
        }).catch(function () {
            return null;
        }).then(function (token) {
            clearTimeout(timer);
            refreshing = null;
            return token;
        });

        return refreshing;
    }

    function anyDirty() {
        return dirtyForms.some(function (form) { return isDirty(form) && document.contains(form); });
    }

    function sessionLost() {
        sessionGone = true;
        dirtyForms.forEach(function (form) { if (document.contains(form)) { saveDraft(form); } });
        if (sessionNotice) { return; }

        var note = document.createElement('div');
        note.className = 'adm-flash adm-flash--error adm-session';
        note.setAttribute('role', 'alert');

        var icon = document.createElement('span');
        icon.className = 'adm-flash__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = ICONS.warning;

        var body = document.createElement('div');
        body.className = 'adm-flash__body';
        var text = document.createElement('p');
        text.textContent = t('session_lost');
        body.appendChild(text);

        if (loginUrl) {
            var link = document.createElement('a');
            link.className = 'btn btn--sm adm-session__login';
            link.href = loginUrl;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = t('session_login');
            body.appendChild(link);
        }

        note.appendChild(icon);
        note.appendChild(body);
        var main = AP.qs('.adm-content') || document.body;
        main.insertBefore(note, main.firstChild);
        sessionNotice = note;
    }

    /** After a blocked save: bring the notice (and its sign-in link) into view. */
    function revealSessionNotice() {
        if (!sessionNotice) { return; }
        sessionNotice.scrollIntoView({ block: 'center', behavior: AP.motion() ? 'smooth' : 'auto' });
        var link = AP.qs('.adm-session__login', sessionNotice);
        if (link) { link.focus({ preventScroll: true }); }
    }

    function sessionBack() {
        var wasGone = sessionGone;
        sessionGone = false;
        if (sessionNotice) {
            leave(sessionNotice);
            sessionNotice = null;
        }
        if (wasGone) { toast(t('session_back'), 'success'); }
    }

    // Back on the tab after a while (or after signing in again elsewhere): fresh token.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && (sessionGone || Date.now() - lastRefresh > 60 * 1000)) {
            refreshToken();
        }
    });

    // Unsaved changes keep the session alive (a request every 10 minutes, even in a background tab).
    if (tokenUrl) {
        setInterval(function () {
            if (anyDirty()) { refreshToken(); }
        }, KEEPALIVE);
    }

    /** POST forms of this site carrying a _token field get a fresh one just before they leave. */
    function needsToken(form) {
        if (!tokenUrl || form.hasAttribute('data-adm-no-token') || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') { return false; }
        if (!AP.qs('input[name="_token"]', form)) { return false; }
        try { return new URL(form.action, window.location.href).origin === window.location.origin; } catch (e) { return false; }
    }

    function draftKey(form) {
        return DRAFT_PREFIX + String(form.action || window.location.href).split('#')[0];
    }

    /** Drafts are for forms that save something (POST), not for filters and searches. */
    function keepsDrafts(form) {
        return Boolean(form.__apDirty) && (form.getAttribute('method') || 'get').toLowerCase() === 'post';
    }

    /** The fields a draft keeps: what the admin typed or chose (never passwords, files or hidden values). */
    function draftFields(form) {
        return Array.prototype.filter.call(form.elements, function (el) {
            var type = (el.type || '').toLowerCase();
            return el.name && !el.disabled && el.name !== '_token' && el.name !== '_method' && !el.hasAttribute('data-adm-no-draft')
                && ['hidden', 'password', 'file', 'submit', 'button', 'reset', 'image', 'fieldset', 'output', 'object'].indexOf(type) === -1;
        });
    }

    function readDraft(key) {
        var store = storage();
        if (!store) { return null; }
        try {
            var draft = JSON.parse(store.getItem(key) || 'null');
            if (draft && Array.isArray(draft.values) && Date.now() - (draft.at || 0) < DRAFT_TTL) { return draft; }
            if (draft) { store.removeItem(key); }
        } catch (e) { /* unreadable: ignored */ }
        return null;
    }

    function forgetDraft(key) {
        var store = storage();
        if (!store || !key) { return; }
        try {
            store.removeItem(key);
            if (store.getItem(DRAFT_LAST) === key) { store.removeItem(DRAFT_LAST); }
        } catch (e) { /* ignore */ }
    }

    /** Copies a changed form to sessionStorage (this tab only), keyed by its action. */
    function saveDraft(form) {
        var store = storage();
        if (!store || !keepsDrafts(form) || !isDirty(form)) { return false; }
        var values = draftFields(form).map(function (el) {
            var type = (el.type || '').toLowerCase();
            if (type === 'checkbox' || type === 'radio') { return [el.name, el.value, el.checked]; }
            if (type === 'select-multiple') {
                return [el.name, Array.prototype.filter.call(el.options, function (o) { return o.selected; }).map(function (o) { return o.value; })];
            }
            return [el.name, el.value];
        });
        try {
            store.setItem(draftKey(form), JSON.stringify({ at: Date.now(), values: values }));
            return true;
        } catch (e) {
            return false;
        }
    }

    /** Every submit: the changed form is kept until a page confirms the save (success flash). */
    function rememberSubmission(form) {
        var store = storage();
        if (!store) { return; }
        try {
            if (saveDraft(form)) {
                store.setItem(DRAFT_LAST, draftKey(form));
            } else {
                store.removeItem(DRAFT_LAST);
            }
        } catch (e) { /* ignore */ }
    }

    function announce(el) {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Puts a draft back into its form, field after field in page order with input/change events (as
     * if typed: page scripts deriving a field from another one see the same sequence); returns the
     * fields that changed.
     */
    function applyDraft(form, draft) {
        var byName = {};
        draft.values.forEach(function (entry) {
            if (Array.isArray(entry) && typeof entry[0] === 'string') { (byName[entry[0]] = byName[entry[0]] || []).push(entry); }
        });
        var seen = {};
        var changed = [];

        draftFields(form).forEach(function (el) {
            var entries = byName[el.name];
            if (!entries) { return; }
            var type = (el.type || '').toLowerCase();
            var index = seen[el.name] || 0;
            seen[el.name] = index + 1;

            if (type === 'checkbox' || type === 'radio') {
                var match = entries.filter(function (entry) { return entry[1] === el.value; })[0];
                if (match && el.checked !== Boolean(match[2])) {
                    el.checked = Boolean(match[2]);
                    changed.push(el);
                    announce(el);
                }
                return;
            }

            var entry = entries[index];
            if (!entry) { return; }
            if (type === 'select-multiple') {
                var wanted = Array.isArray(entry[1]) ? entry[1].map(String) : [];
                var before = Array.prototype.map.call(el.options, function (o) { return o.selected; }).join();
                Array.prototype.forEach.call(el.options, function (o) { o.selected = wanted.indexOf(o.value) !== -1; });
                if (Array.prototype.map.call(el.options, function (o) { return o.selected; }).join() !== before) {
                    changed.push(el);
                    announce(el);
                }
            } else if (typeof entry[1] === 'string' && el.value !== entry[1]) {
                if (type === 'select-one' && !Array.prototype.some.call(el.options, function (o) { return o.value === entry[1]; })) { return; }
                el.value = entry[1];
                changed.push(el);
                announce(el);
            }
        });

        return changed;
    }

    function draftNotice(form, key, changed) {
        var note = document.createElement('div');
        note.className = 'adm-flash adm-flash--info adm-draft';
        note.setAttribute('role', 'status');

        var icon = document.createElement('span');
        icon.className = 'adm-flash__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = ICONS.info;

        var body = document.createElement('div');
        body.className = 'adm-flash__body';
        var text = document.createElement('p');
        text.textContent = t('draft_restored');
        var discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'btn btn--sm btn--ghost adm-draft__discard';
        discard.textContent = t('draft_discard');
        body.appendChild(text);
        body.appendChild(discard);

        note.appendChild(icon);
        note.appendChild(body);
        form.parentNode.insertBefore(note, form);

        discard.addEventListener('click', function () {
            // The HTML defaults are the saved values.
            form.reset();
            changed.forEach(announce);
            forgetDraft(key);
            leave(note);
            toast(t('draft_discarded'), 'info');
        });
    }

    /**
     * On load: a success flash confirms the last submitted form was saved (its draft goes); a form
     * shown again without one gets its draft back — unless the page lists validation errors (the
     * server already shows the submitted values).
     */
    function restoreDrafts() {
        var store = storage();
        if (!store) { return; }
        // The session's success flash (admin.partials.flash), not a look-alike sample.
        if (AP.qs('[data-adm-flash="success"]')) {
            try { forgetDraft(store.getItem(DRAFT_LAST)); } catch (e) { /* ignore */ }
        }
        if (AP.qs('[data-adm-autofocus], .field--invalid')) { return; }

        AP.qsa('form[data-adm-dirty]').forEach(function (form) {
            var key = draftKey(form);
            var draft = keepsDrafts(form) ? readDraft(key) : null;
            if (!draft) { return; }
            var changed = applyDraft(form, draft);
            if (!changed.length) {
                forgetDraft(key);
                return;
            }
            setDirty(form, serialize(form) !== form.__apDirty.initial);
            draftNotice(form, key, changed);
        });
    }

    /* --- Toasts & leaving elements ---------------------------------------------- */

    function leave(el) {
        if (!el || el.__apLeaving) { return; }
        el.__apLeaving = true;
        el.classList.add('is-leaving');
        var remove = function () { if (el.parentNode) { el.parentNode.removeChild(el); } };
        if (AP.motion()) { setTimeout(remove, 420); } else { remove(); }
    }

    function toastRegion() {
        var region = AP.qs('[data-adm-toasts]');
        if (!region) {
            region = document.createElement('div');
            region.className = 'adm-toasts';
            region.setAttribute('data-adm-toasts', '');
            region.setAttribute('aria-live', 'polite');
            document.body.appendChild(region);
        }
        return region;
    }

    /** A transient message (type: success | error | info). Errors stay longer and are announced at once. */
    function toast(message, type) {
        type = type === 'error' || type === 'info' ? type : 'success';
        var el = document.createElement('div');
        el.className = 'adm-toast adm-toast--' + type;
        if (type === 'error') { el.setAttribute('role', 'alert'); }

        var icon = document.createElement('span');
        icon.className = 'adm-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = ICONS[type];

        var text = document.createElement('p');
        text.className = 'adm-toast__text';
        text.textContent = String(message == null ? '' : message);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'adm-toast__close';
        close.setAttribute('aria-label', t('close'));
        close.innerHTML = ICONS.close;

        el.appendChild(icon);
        el.appendChild(text);
        el.appendChild(close);
        toastRegion().appendChild(el);

        var remaining = type === 'error' ? 9000 : 5000;
        var started = 0;
        var timer = null;
        function dismiss() { clearTimeout(timer); leave(el); }
        function resume() { clearTimeout(timer); started = Date.now(); timer = setTimeout(dismiss, Math.max(1200, remaining)); }
        function pause() { clearTimeout(timer); remaining -= Date.now() - started; }
        el.addEventListener('mouseenter', pause);
        el.addEventListener('mouseleave', resume);
        el.addEventListener('focusin', pause);
        el.addEventListener('focusout', resume);
        close.addEventListener('click', dismiss);
        resume();

        return el;
    }

    /* --- Dialogs ------------------------------------------------------------------ */

    function supportsModal() {
        return typeof HTMLDialogElement === 'function' && typeof HTMLDialogElement.prototype.showModal === 'function';
    }

    function finishClose(dialog) {
        dialog.classList.remove('is-open', 'is-fallback');
        if (!AP.qs('dialog[open], .adm-dialog.is-open')) { root.classList.remove('adm-dialog-open'); }
        var back = dialog.__apReturn;
        dialog.__apReturn = null;
        if (back && typeof back.focus === 'function' && document.contains(back)) { back.focus(); }
        AP.emit('adm-dialog-close', { dialog: dialog, returnValue: dialog.returnValue || '' });
    }

    function wireDialog(dialog) {
        if (dialog.__apWired) { return; }
        dialog.__apWired = true;
        dialog.addEventListener('close', function () { finishClose(dialog); });
        // A click on the backdrop (outside the dialog box) closes it, unless data-adm-dialog-static.
        dialog.addEventListener('click', function (event) {
            if (event.target !== dialog || dialog.hasAttribute('data-adm-dialog-static')) { return; }
            var box = dialog.getBoundingClientRect();
            var inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
            if (!inside) { closeDialog(dialog); }
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && dialog.classList.contains('is-fallback')) {
                event.preventDefault();
                closeDialog(dialog);
            }
        });
    }

    function resolveDialog(dialog) {
        return typeof dialog === 'string' ? AP.qs(dialog) : dialog;
    }

    /** Opens a <dialog> modally (focus moves in, Esc and backdrop close it, focus returns afterwards). */
    function openDialog(dialog) {
        dialog = resolveDialog(dialog);
        if (!dialog || dialog.classList.contains('is-open')) { return; }
        wireDialog(dialog);
        dialog.__apReturn = document.activeElement;
        dialog.returnValue = '';

        if (supportsModal()) {
            if (!dialog.open) { dialog.showModal(); }
        } else {
            dialog.setAttribute('open', '');
            dialog.classList.add('is-fallback');
        }
        dialog.classList.add('is-open');
        root.classList.add('adm-dialog-open');

        var focusTarget = AP.qs('[autofocus], [data-adm-dialog-focus]', dialog);
        if (focusTarget) { focusTarget.focus(); }
        AP.emit('adm-dialog-open', { dialog: dialog });
    }

    function closeDialog(dialog, returnValue) {
        dialog = resolveDialog(dialog);
        if (!dialog) { return; }
        if (supportsModal() && dialog.open && !dialog.classList.contains('is-fallback')) {
            dialog.close(returnValue == null ? '' : String(returnValue));
        } else if (dialog.classList.contains('is-open')) {
            dialog.removeAttribute('open');
            dialog.returnValue = returnValue == null ? '' : String(returnValue);
            finishClose(dialog);
        }
    }

    var confirmDialog = null;

    function buildConfirmDialog() {
        var dialog = document.createElement('dialog');
        dialog.className = 'adm-dialog adm-dialog--confirm';
        dialog.setAttribute('aria-labelledby', 'adm-confirm-title');
        dialog.setAttribute('aria-describedby', 'adm-confirm-message');
        dialog.innerHTML =
            '<div class="adm-dialog__head"><h2 class="adm-dialog__title" id="adm-confirm-title"></h2></div>' +
            '<div class="adm-dialog__body"><span class="adm-dialog__icon" aria-hidden="true">' + ICONS.warning + '</span>' +
            '<p class="adm-dialog__message" id="adm-confirm-message"></p></div>' +
            '<div class="adm-dialog__foot"><button class="btn btn--sm btn--ghost" type="button" data-adm-confirm-cancel></button>' +
            '<button class="btn btn--sm" type="button" data-adm-confirm-ok></button></div>';
        document.body.appendChild(dialog);
        return dialog;
    }

    /** Asks before a sensitive action. options: {message, label, variant (danger|primary|secondary), title}. */
    function askConfirmation(options) {
        options = options || {};
        var message = options.message || t('confirm_title');
        if (!supportsModal()) {
            return Promise.resolve(window.confirm(message));
        }

        confirmDialog = confirmDialog || buildConfirmDialog();
        var dialog = confirmDialog;
        var ok = AP.qs('[data-adm-confirm-ok]', dialog);
        var cancel = AP.qs('[data-adm-confirm-cancel]', dialog);
        var danger = (options.variant || 'danger') === 'danger';

        AP.qs('.adm-dialog__title', dialog).textContent = options.title || t('confirm_title');
        AP.qs('.adm-dialog__message', dialog).textContent = message;
        AP.qs('.adm-dialog__icon', dialog).hidden = !danger;
        ok.textContent = options.label || t('confirm_ok');
        ok.className = 'btn btn--sm' + (danger ? ' adm-btn--danger' : options.variant === 'secondary' ? ' btn--secondary' : '');
        cancel.textContent = t('cancel');

        return new Promise(function (resolve) {
            var settled = false;
            function finish(value) {
                if (settled) { return; }
                settled = true;
                ok.removeEventListener('click', onOk);
                cancel.removeEventListener('click', onCancel);
                dialog.removeEventListener('close', onClose);
                resolve(value);
            }
            function onOk() { finish(true); closeDialog(dialog, 'ok'); }
            function onCancel() { finish(false); closeDialog(dialog, 'cancel'); }
            function onClose() { finish(false); }
            ok.addEventListener('click', onOk);
            cancel.addEventListener('click', onCancel);
            dialog.addEventListener('close', onClose);
            openDialog(dialog);
            // The safe choice has the focus for destructive actions.
            (danger ? cancel : ok).focus();
        });
    }

    /* --- Forms: submit, confirmation, dirty tracking ------------------------------ */

    function submitForm(form, submitter) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        } else {
            form.__apSubmitting = true;
            form.submit();
        }
    }

    var dirtyForms = [];

    function serialize(form) {
        var parts = [];
        Array.prototype.forEach.call(form.elements, function (el) {
            var type = (el.type || '').toLowerCase();
            if (!el.name || el.disabled || el.name === '_token' || ['file', 'submit', 'button', 'reset', 'image'].indexOf(type) !== -1) { return; }
            if (type === 'checkbox' || type === 'radio') {
                parts.push(el.name + '=' + (el.checked ? el.value : '\u0000'));
            } else if (type === 'select-multiple') {
                parts.push(el.name + '=' + Array.prototype.filter.call(el.options, function (o) { return o.selected; }).map(function (o) { return o.value; }).join('\u0001'));
            } else {
                parts.push(el.name + '=' + el.value);
            }
        });
        return parts.join('\u0002');
    }

    function setDirty(form, dirty) {
        if (!form.__apDirty) { return; }
        form.__apDirty.dirty = dirty;
        form.classList.toggle('is-dirty', dirty);
        AP.qsa('[data-adm-savebar]', form).forEach(function (bar) { bar.classList.toggle('is-dirty', dirty); });
        AP.qsa('[data-adm-dirty-status]', form).forEach(function (el) {
            var text = el.getAttribute(dirty ? 'data-dirty' : 'data-clean');
            if (text) { el.textContent = text; }
        });
    }

    function initDirty(form) {
        if (form.__apDirty) { return; }
        form.__apDirty = { initial: serialize(form), dirty: false };
        var check = AP.debounce(function () { setDirty(form, serialize(form) !== form.__apDirty.initial); }, 120);
        form.addEventListener('input', check);
        form.addEventListener('change', check);
        form.addEventListener('reset', function () { setTimeout(function () { markClean(form); }, 0); });
        dirtyForms.push(form);
    }

    function isDirty(form) {
        return Boolean(form && form.__apDirty && serialize(form) !== form.__apDirty.initial);
    }

    /** The current values become the saved state (e.g. after saving through fetchJSON). */
    function markClean(form) {
        if (!form || !form.__apDirty) { return; }
        form.__apDirty.initial = serialize(form);
        setDirty(form, false);
        forgetDraft(draftKey(form));
    }

    function setBusy(form, submitter, busy) {
        if (!form.hasAttribute('data-adm-busy')) { return; }
        var button = submitter || AP.qs('[type="submit"]', form);
        if (button) { button.classList.toggle('is-busy', busy); }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') { return; }
        var submitter = event.submitter || null;

        var message = form.getAttribute('data-confirm');
        if (message !== null && !form.__apConfirmed) {
            event.preventDefault();
            askConfirmation({
                message: message,
                label: form.getAttribute('data-confirm-label'),
                variant: form.getAttribute('data-confirm-variant') || 'danger',
                title: form.getAttribute('data-confirm-title')
            }).then(function (confirmed) {
                if (!confirmed) { return; }
                form.__apConfirmed = true;
                submitForm(form, submitter);
            });
            return;
        }

        if (event.defaultPrevented) {
            form.__apConfirmed = false;
            return;
        }

        // A page left open for hours: fetch the session's current CSRF token first. When the session
        // is gone, the page stays (changes kept) and a notice asks to sign in again in another tab.
        if (!form.__apTokenFresh && needsToken(form)) {
            event.preventDefault();
            if (form.__apTokenPending) { return; }
            form.__apTokenPending = true;
            rememberSubmission(form);
            setBusy(form, submitter, true);
            refreshToken(4000).then(function () {
                form.__apTokenPending = false;
                if (sessionGone) {
                    setBusy(form, submitter, false);
                    form.__apConfirmed = false;
                    revealSessionNotice();
                    return;
                }
                form.__apTokenFresh = true;
                submitForm(form, submitter);
            });
            return;
        }

        form.__apConfirmed = false;
        form.__apTokenFresh = false;
        rememberSubmission(form);
        form.__apSubmitting = true;
        setBusy(form, submitter, true);
    });

    window.addEventListener('beforeunload', function (event) {
        var unsaved = dirtyForms.some(function (form) {
            return form.__apDirty && form.__apDirty.dirty && !form.__apSubmitting && document.contains(form);
        });
        if (!unsaved) { return undefined; }
        event.preventDefault();
        event.returnValue = '';
        return '';
    });

    // Back/forward cache: a page restored after a submit must be usable again (with a current token).
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) { return; }
        AP.qsa('form').forEach(function (form) {
            form.__apSubmitting = false;
            form.__apTokenPending = false;
            form.__apTokenFresh = false;
        });
        AP.qsa('.btn.is-busy').forEach(function (button) { button.classList.remove('is-busy'); });
        refreshToken();
    });

    // Ctrl/⌘ + S saves the form being edited.
    document.addEventListener('keydown', function (event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey || String(event.key).toLowerCase() !== 's') { return; }
        var form = closest(document.activeElement, 'form[data-adm-dirty]') || AP.qs('form[data-adm-dirty].is-dirty');
        if (!form) { return; }
        event.preventDefault();
        submitForm(form);
    });

    /* --- Textareas & counters ------------------------------------------------------ */

    function autosize(textarea) {
        if (!textarea || textarea.offsetParent === null) { return; }
        var style = window.getComputedStyle(textarea);
        var borders = (parseFloat(style.borderTopWidth) || 0) + (parseFloat(style.borderBottomWidth) || 0);
        var max = Math.max(240, Math.round(window.innerHeight * 0.7));
        textarea.style.height = 'auto';
        var height = textarea.scrollHeight + borders;
        textarea.style.height = Math.min(height, max) + 'px';
        textarea.style.overflowY = height > max ? 'auto' : 'hidden';
    }

    function counterFor(input) {
        var counter = input.id ? AP.qs('[data-counter-for="' + input.id.replace(/"/g, '\\"') + '"]') : null;
        if (!counter) {
            counter = document.createElement('span');
            counter.className = 'adm-counter';
            counter.setAttribute('aria-hidden', 'true');
            if (input.id) { counter.setAttribute('data-counter-for', input.id); }
            input.insertAdjacentElement('afterend', counter);
        }
        return counter;
    }

    function updateCounter(input) {
        var max = parseInt(input.getAttribute('data-maxlength'), 10);
        if (!max) { return; }
        var count = String(input.value || '').length;
        var counter = counterFor(input);
        counter.textContent = count + ' / ' + max;
        counter.classList.toggle('is-near', count >= max * 0.9 && count <= max);
        counter.classList.toggle('is-over', count > max);
    }

    document.addEventListener('input', function (event) {
        var el = event.target;
        if (!el || !el.matches) { return; }
        if (el.matches('textarea[data-autosize]')) { autosize(el); }
        if (el.hasAttribute('data-maxlength')) { updateCounter(el); }
    });

    window.addEventListener('resize', AP.debounce(function () {
        AP.qsa('textarea[data-autosize]').forEach(autosize);
    }, 200));

    /* --- Tabs ------------------------------------------------------------------------ */

    function storage() {
        try { return window.sessionStorage; } catch (e) { return null; }
    }

    function initTabs(container) {
        if (container.__apTabs) { return; }
        var list = AP.qs('.adm-tabs__list, [role="tablist"]', container);
        var tabs = list ? AP.qsa('.adm-tabs__tab, [role="tab"]', list) : [];
        if (!tabs.length) { return; }
        container.__apTabs = true;

        var key = container.getAttribute('data-adm-tabs');
        var store = key ? storage() : null;
        var panels = tabs.map(function (tab) {
            var id = tab.getAttribute('aria-controls') || (tab.getAttribute('href') || '').replace(/^#/, '');
            return id ? document.getElementById(id) : null;
        });

        list.setAttribute('role', 'tablist');
        tabs.forEach(function (tab, i) {
            var panel = panels[i];
            tab.setAttribute('role', 'tab');
            if (!tab.id) { tab.id = (panel && panel.id ? panel.id : 'adm-tabs-' + i) + '-tab'; }
            if (panel) {
                tab.setAttribute('aria-controls', panel.id);
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', tab.id);
                if (!panel.hasAttribute('tabindex')) { panel.setAttribute('tabindex', '0'); }
                panel.__apSelect = function () { select(i, false); };
            }
        });

        function select(index, focus) {
            tabs.forEach(function (tab, i) {
                var on = i === index;
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.setAttribute('tabindex', on ? '0' : '-1');
                tab.classList.toggle('is-active', on);
                if (panels[i]) { panels[i].hidden = !on; }
            });
            if (focus) { tabs[index].focus(); }
            if (panels[index]) { AP.qsa('textarea[data-autosize]', panels[index]).forEach(autosize); }
            if (store) {
                try { store.setItem('adm-tabs:' + key, String(index)); } catch (e) { /* ignore */ }
            }
            container.dispatchEvent(new CustomEvent('ap:adm-tab', { bubbles: true, detail: { index: index, tab: tabs[index], panel: panels[index] } }));
        }

        var initial = -1;
        panels.forEach(function (panel, i) {
            if (initial === -1 && panel && AP.qs('[aria-invalid="true"], .field--invalid', panel)) { initial = i; }
        });
        if (initial === -1) {
            tabs.forEach(function (tab, i) { if (initial === -1 && tab.getAttribute('aria-selected') === 'true') { initial = i; } });
        }
        if (initial === -1 && store) {
            var saved = parseInt(store.getItem('adm-tabs:' + key), 10);
            if (saved >= 0 && saved < tabs.length) { initial = saved; }
        }

        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();
                select(i, false);
            });
            tab.addEventListener('keydown', function (event) {
                var next = null;
                if (event.key === 'ArrowRight') { next = (i + 1) % tabs.length; }
                else if (event.key === 'ArrowLeft') { next = (i - 1 + tabs.length) % tabs.length; }
                else if (event.key === 'Home') { next = 0; }
                else if (event.key === 'End') { next = tabs.length - 1; }
                if (next !== null) {
                    event.preventDefault();
                    select(next, true);
                }
            });
        });

        select(Math.max(0, initial), false);
    }

    /** Shows the tab panels hiding el; true when one had to be opened. */
    function revealInTabs(el) {
        var opened = false;
        var panel = closest(el, '.adm-tabs__panel[hidden], [role="tabpanel"][hidden]');
        while (panel) {
            if (typeof panel.__apSelect === 'function') {
                panel.__apSelect();
                opened = true;
            } else {
                break;
            }
            panel = closest(panel.parentElement, '.adm-tabs__panel[hidden], [role="tabpanel"][hidden]');
        }
        return opened;
    }

    function focusable(el) {
        return el && el.matches && el.matches('input, select, textarea, button, a[href], [tabindex]');
    }

    // In-page links (e.g. the validation summary) reveal and focus fields hidden in tabs.
    document.addEventListener('click', function (event) {
        var link = closest(event.target, 'a[href^="#"]');
        if (!link) { return; }
        var hash = link.getAttribute('href');
        if (hash.length < 2) { return; }
        var target = document.getElementById(decodeURIComponent(hash.slice(1)));
        if (!target) { return; }
        var opened = revealInTabs(target);
        if (!opened && !target.matches('input, select, textarea')) { return; }
        event.preventDefault();
        target.scrollIntoView({ block: 'center', behavior: AP.motion() ? 'smooth' : 'auto' });
        if (focusable(target)) { target.focus({ preventScroll: true }); }
    });

    /* --- Live filter ------------------------------------------------------------------ */

    function normalize(text) {
        var value = String(text || '').toLowerCase();
        if (value.normalize) { value = value.normalize('NFD').replace(/[̀-ͯ]/g, ''); }
        return value.replace(/\s+/g, ' ').trim();
    }

    function initFilter(input) {
        if (input.__apFilter) { return; }
        input.__apFilter = true;
        var selector = input.getAttribute('data-adm-filter');
        var scope = (selector && AP.qs(selector)) || document;
        var countSelector = input.getAttribute('data-adm-filter-count');
        var emptySelector = input.getAttribute('data-adm-filter-empty');

        function apply() {
            var terms = normalize(input.value).split(' ').filter(Boolean);
            var visible = 0;
            AP.qsa('[data-adm-filter-item]', scope).forEach(function (item) {
                if (item.__apFilterText === undefined) {
                    item.__apFilterText = normalize(item.getAttribute('data-adm-filter-text') || item.textContent);
                }
                var haystack = item.__apFilterText;
                if (terms.length) {
                    AP.qsa('input[type="text"], input:not([type]), textarea', item).forEach(function (field) { haystack += ' ' + normalize(field.value); });
                }
                var match = terms.every(function (term) { return haystack.indexOf(term) !== -1; });
                item.hidden = !match;
                if (match) { visible++; }
            });
            AP.qsa('[data-adm-filter-group]', scope).forEach(function (group) {
                var items = AP.qsa('[data-adm-filter-item]', group);
                group.hidden = items.length > 0 && items.every(function (item) { return item.hidden; });
            });
            var empties = emptySelector ? AP.qsa(emptySelector) : AP.qsa('[data-adm-filter-empty]', scope);
            empties.forEach(function (empty) { empty.hidden = visible > 0; });
            var countEl = countSelector ? AP.qs(countSelector) : null;
            if (countEl) {
                countEl.textContent = visible === 0 ? t('results_none') : visible === 1 ? t('results_one') : t('results_many', { count: visible });
            }
            AP.qsa('textarea[data-autosize]', scope).forEach(autosize);
        }

        input.addEventListener('input', AP.debounce(apply, 120));
        input.addEventListener('search', apply);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && input.value) {
                event.preventDefault();
                input.value = '';
                apply();
            }
        });
        if (input.value) { apply(); }
    }

    /* --- Delegated clicks: flash, reveal password, dialogs, toasts --------------------- */

    document.addEventListener('click', function (event) {
        var target = event.target;

        var flashClose = closest(target, '[data-adm-flash-close]');
        if (flashClose) {
            leave(closest(flashClose, '.adm-flash'));
            return;
        }

        var reveal = closest(target, '[data-adm-reveal]');
        if (reveal) {
            var field = document.getElementById(reveal.getAttribute('data-adm-reveal'));
            if (field) {
                var show = field.type === 'password';
                field.type = show ? 'text' : 'password';
                reveal.setAttribute('aria-pressed', show ? 'true' : 'false');
                var label = AP.qs('[data-adm-reveal-label]', reveal);
                if (label) { label.textContent = t(show ? 'hide_password' : 'show_password'); }
            }
            return;
        }

        var opener = closest(target, '[data-adm-dialog-open]');
        if (opener) {
            event.preventDefault();
            openDialog(opener.getAttribute('data-adm-dialog-open'));
            return;
        }

        var closer = closest(target, '[data-adm-dialog-close]');
        if (closer && closest(closer, 'dialog, .adm-dialog')) {
            event.preventDefault();
            closeDialog(closest(closer, 'dialog, .adm-dialog'), closer.getAttribute('value') || '');
            return;
        }

        var toaster = closest(target, '[data-adm-toast]');
        if (toaster) {
            toast(toaster.getAttribute('data-adm-toast'), toaster.getAttribute('data-adm-toast-type'));
        }
    });

    /* --- Navigation drawer (below 64em) ------------------------------------------------- */

    function initNav() {
        var toggle = AP.qs('[data-adm-nav-toggle]');
        var sidebar = AP.qs('[data-adm-sidebar]');
        if (!toggle || !sidebar) { return; }
        var main = AP.qs('.adm-main');
        var label = AP.qs('[data-adm-nav-label]', toggle);
        var desktop = window.matchMedia ? window.matchMedia('(min-width: 64em)') : null;

        function isOpen() { return root.classList.contains('adm-nav-open'); }

        function set(open, restoreFocus) {
            root.classList.toggle('adm-nav-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (label) { label.textContent = t(open ? 'menu_close' : 'menu_open'); }
            if (main) {
                if (open) { main.setAttribute('inert', ''); } else { main.removeAttribute('inert'); }
            }
            if (open) {
                var first = AP.qs('.adm-nav__link.is-active', sidebar) || AP.qs('a[href], button', sidebar);
                if (first) { setTimeout(function () { first.focus(); }, 30); }
            } else if (restoreFocus) {
                toggle.focus();
            }
        }

        toggle.addEventListener('click', function () { set(!isOpen(), true); });
        document.addEventListener('click', function (event) {
            if (isOpen() && closest(event.target, '[data-adm-nav-close]')) { set(false, true); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen()) { set(false, true); }
        });
        if (desktop) {
            var onChange = function () { if (desktop.matches && isOpen()) { set(false, false); } };
            if (desktop.addEventListener) { desktop.addEventListener('change', onChange); } else if (desktop.addListener) { desktop.addListener(onChange); }
        }
    }

    /* --- Init ---------------------------------------------------------------------------- */

    /** Wires every behaviour inside scope (a page, or HTML inserted later such as the media picker). */
    function refresh(scope) {
        scope = scope || document;
        AP.qsa('[data-adm-tabs]', scope).forEach(initTabs);
        AP.qsa('form[data-adm-dirty]', scope).forEach(initDirty);
        AP.qsa('[data-adm-filter]', scope).forEach(initFilter);
        AP.qsa('textarea[data-autosize]', scope).forEach(autosize);
        AP.qsa('[data-maxlength]', scope).forEach(updateCounter);
        AP.qsa('[data-adm-reveal]', scope).forEach(function (button) { button.hidden = false; });
    }

    AP.admin = {
        csrf: csrf,
        refreshToken: refreshToken,
        fetchJSON: fetchJSON,
        toast: toast,
        openDialog: openDialog,
        closeDialog: closeDialog,
        confirm: askConfirmation,
        t: t,
        markClean: markClean,
        autosize: autosize,
        refresh: refresh
    };

    AP.ready(function () {
        initNav();
        refresh(document);
        // After the page's own scripts (their ready callbacks come later): they see the restored values.
        setTimeout(restoreDrafts, 0);

        var summary = AP.qs('[data-adm-autofocus]');
        if (summary) { summary.focus(); }

        if (window.location.hash.length > 1) {
            var target = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
            if (target && revealInTabs(target)) { target.scrollIntoView({ block: 'center' }); }
        }
    });
})();
