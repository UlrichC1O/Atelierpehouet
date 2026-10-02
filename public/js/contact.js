/* Ateliers Pehouet — contact.js: message draft, character counter, inline hints, sending state, focus on first error. */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    var DRAFT_KEY = 'ap-contact-message';

    function draftStore() {
        try { return window.sessionStorage; } catch (e) { return null; }
    }

    AP.ready(function () {
        var form = AP.qs('[data-contact-form]');
        if (!form) { return; }

        // The server never sends the message back (it would overflow the session cookie),
        // so this tab keeps the draft until the request has been received.
        var message = form.querySelector('textarea[name="message"]');
        var store = draftStore();
        if (message && store) {
            try {
                if (document.querySelector('.contact__success')) {
                    store.removeItem(DRAFT_KEY);
                } else if (!message.value && store.getItem(DRAFT_KEY)) {
                    message.value = store.getItem(DRAFT_KEY);
                }
            } catch (e) { /* storage unavailable: the form still works */ }
            message.addEventListener('input', function () {
                try { store.setItem(DRAFT_KEY, message.value); } catch (e) { /* full or blocked */ }
            });
        }

        AP.qsa('[data-counter]', form).forEach(function (field) {
            var counter = document.getElementById(field.getAttribute('data-counter'));
            if (!counter) { return; }
            var template = counter.getAttribute('data-template') || '__COUNT__';
            var max = parseInt(field.getAttribute('maxlength'), 10) || 0;
            var update = function () {
                counter.textContent = template.replace('__COUNT__', field.value.length);
                counter.classList.toggle('is-near', max > 0 && field.value.length > max * 0.9);
            };
            field.addEventListener('input', update);
            update();
        });

        // Clear the invalid state as soon as the visitor fixes a field.
        form.addEventListener('input', function (e) {
            var field = e.target.closest('.field, .checkbox');
            if (field && e.target.checkValidity && e.target.checkValidity()) {
                field.classList.remove('field--invalid');
                e.target.removeAttribute('aria-invalid');
            }
        });

        form.addEventListener('submit', function (e) {
            var firstInvalid = AP.qsa('input, select, textarea', form).filter(function (el) {
                return el.name !== 'website' && el.willValidate && !el.checkValidity();
            })[0];
            if (firstInvalid) {
                e.preventDefault();
                var wrap = firstInvalid.closest('.field, .checkbox');
                if (wrap) {
                    wrap.classList.remove('field--invalid');
                    void wrap.offsetWidth;
                    wrap.classList.add('field--invalid');
                }
                firstInvalid.setAttribute('aria-invalid', 'true');
                firstInvalid.focus();
                if (firstInvalid.reportValidity) { firstInvalid.reportValidity(); }
                return;
            }
            var submit = form.querySelector('[data-contact-submit]');
            if (submit) {
                submit.classList.add('is-sending');
                var label = submit.querySelector('.btn__label');
                if (label && submit.getAttribute('data-sending-label')) { label.textContent = submit.getAttribute('data-sending-label'); }
            }
        });

        // After a failed submission, bring the first error into view.
        var errorField = form.querySelector('[aria-invalid="true"]');
        if (errorField) {
            errorField.focus({ preventScroll: true });
            errorField.scrollIntoView({ block: 'center', behavior: 'auto' });
        } else if (window.location.hash === '#contact-form') {
            var main = document.getElementById('contact-form');
            if (main) { main.focus({ preventScroll: true }); }
        }
    });
})();
