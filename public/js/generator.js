/* Ateliers Pehouet — generator.js: the live "Atelier numérique".
   Debounced updates of the preview, dice for a random word, copy link, URL sync and a
   localStorage history of the last 8 creations. The page works without it (GET form). */
(function () {
    'use strict';
    var AP = window.AP;
    if (!AP) { return; }

    var HISTORY_KEY = 'ap-generator-history';

    AP.ready(function () {
        var form = AP.qs('[data-generator-form]');
        if (!form) { return; }
        var artUrl = form.getAttribute('data-art-url');
        var words = [];
        try { words = JSON.parse(form.getAttribute('data-words') || '[]'); } catch (e) { words = []; }

        var img = AP.qs('[data-generator-img]');
        var stage = AP.qs('[data-generator-stage]');
        var errorBox = AP.qs('[data-generator-error]');
        var download = AP.qs('[data-generator-download]');
        var openLink = AP.qs('[data-generator-open]');
        var copyBtn = AP.qs('[data-generator-copy]');
        var randomBtn = AP.qs('[data-generator-random]');
        var submitBtn = AP.qs('[data-generator-submit]');
        var status = AP.qs('[data-generator-status]');
        var historyWrap = AP.qs('[data-generator-history-wrap]');
        var historyList = AP.qs('[data-generator-history]');
        var clearBtn = AP.qs('[data-generator-clear]');
        var seedInput = form.querySelector('[name="seed"]');
        var altTemplate = img.getAttribute('data-alt-template') || '';
        var current = img.getAttribute('src');

        if (randomBtn) { randomBtn.hidden = false; }
        if (copyBtn) { copyBtn.hidden = false; }
        if (submitBtn) { submitBtn.hidden = true; }

        function settings() {
            var style = (form.querySelector('[name="style"]:checked') || {}).value || 'pehouet';
            var seed = (seedInput.value || '').trim().slice(0, 60) || (words[0] || 'Pehouet');
            var size = form.querySelector('[name="size"]').value;
            var animate = form.querySelector('[name="animate"]').checked ? '1' : '0';
            return { style: style, seed: seed, size: size, animate: animate };
        }
        function query(s, extra) {
            var p = new URLSearchParams({ style: s.style, seed: s.seed, size: s.size, animate: s.animate });
            if (extra) { Object.keys(extra).forEach(function (k) { p.set(k, extra[k]); }); }
            return p.toString();
        }
        function styleLabel(key) {
            var input = form.querySelector('[name="style"][value="' + key + '"]');
            var name = input && input.parentNode.querySelector('.style-card__name');
            return name ? name.textContent.trim() : key;
        }

        function update() {
            var s = settings();
            var src = artUrl + '?' + query(s);
            if (src === current) { return; }
            current = src;
            errorBox.hidden = true;
            stage.classList.add('is-loading');
            stage.classList.remove('is-fresh');
            img.src = src;
            img.alt = altTemplate.replace('__SEED__', s.seed).replace('__STYLE__', styleLabel(s.style));
            if (download) { download.href = artUrl + '?' + query(s, { download: 1 }); }
            if (openLink) { openLink.href = src; }
            try {
                history.replaceState(null, '', window.location.pathname + '?' + new URLSearchParams({ style: s.style, seed: s.seed, size: s.size, animate: s.animate }).toString() + '#studio');
            } catch (e) { /* ignore */ }
        }
        var debounced = AP.debounce(update, 450);

        img.addEventListener('load', function () {
            stage.classList.remove('is-loading');
            if (AP.motion()) {
                stage.classList.remove('is-fresh');
                void stage.offsetWidth;
                stage.classList.add('is-fresh');
            }
            remember(settings(), img.src);
        });
        img.addEventListener('error', function () {
            stage.classList.remove('is-loading');
            errorBox.hidden = false;
        });

        form.addEventListener('submit', function (e) { e.preventDefault(); update(); });
        seedInput.addEventListener('input', debounced);
        form.addEventListener('change', function (e) {
            if (e.target !== seedInput) { update(); }
        });

        if (randomBtn) {
            randomBtn.addEventListener('click', function () {
                var word = words.length ? AP.pick(words) : 'Pehouet';
                seedInput.value = word + ' ' + Math.floor(AP.rand(1, 100));
                var styles = AP.qsa('[name="style"]', form);
                var pick = styles[Math.floor(Math.random() * styles.length)];
                if (pick) { pick.checked = true; }
                update();
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var url = window.location.href;
                var done = function () {
                    var label = copyBtn.querySelector('.btn__label');
                    var original = label.textContent;
                    label.textContent = copyBtn.getAttribute('data-label-copied');
                    if (status) { status.textContent = copyBtn.getAttribute('data-label-copied'); }
                    window.setTimeout(function () { label.textContent = original; }, 2000);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, function () { fallbackCopy(url); done(); });
                } else {
                    fallbackCopy(url);
                    done();
                }
            });
        }
        function fallbackCopy(text) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try { document.execCommand('copy'); } catch (e) { /* ignore */ }
            area.remove();
        }

        /* --- history --- */
        function load() {
            try { return JSON.parse(window.localStorage.getItem(HISTORY_KEY) || '[]'); } catch (e) { return []; }
        }
        function save(list) {
            try { window.localStorage.setItem(HISTORY_KEY, JSON.stringify(list)); } catch (e) { /* ignore */ }
        }
        function remember(s, src) {
            var list = load().filter(function (item) { return item.src !== src; });
            list.unshift({ style: s.style, seed: s.seed, size: s.size, animate: s.animate, src: src });
            save(list.slice(0, 8));
            render();
        }
        function render() {
            if (!historyList) { return; }
            var list = load();
            historyWrap.hidden = list.length === 0;
            historyList.textContent = '';
            list.forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'generator__history-item';
                var btn = document.createElement('button');
                btn.type = 'button';
                var thumb = document.createElement('img');
                thumb.src = item.src;
                thumb.alt = '';
                thumb.width = 96;
                thumb.height = 96;
                thumb.loading = 'lazy';
                var label = document.createElement('span');
                label.textContent = item.seed + ' · ' + styleLabel(item.style);
                btn.appendChild(thumb);
                btn.appendChild(label);
                btn.addEventListener('click', function () {
                    seedInput.value = item.seed;
                    var radio = form.querySelector('[name="style"][value="' + item.style + '"]');
                    if (radio) { radio.checked = true; }
                    form.querySelector('[name="size"]').value = item.size;
                    form.querySelector('[name="animate"]').checked = item.animate === '1';
                    update();
                    stage.scrollIntoView({ behavior: AP.motion() ? 'smooth' : 'auto', block: 'center' });
                });
                li.appendChild(btn);
                historyList.appendChild(li);
            });
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', function () { save([]); render(); });
        }
        render();
        if (img.complete && img.naturalWidth) { remember(settings(), img.src); }
    });
})();
