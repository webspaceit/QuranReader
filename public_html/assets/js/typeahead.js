/**
 * Search suggestions for the home page search box.
 *
 * Progressive enhancement: without JavaScript the form still submits to
 * search.php. With it, up to six matching verses (and matching surah names)
 * appear under the field as you type.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-typeahead]');
    if (!form || !window.QuranApp) {
        return;
    }

    var app = window.QuranApp;
    var input = form.querySelector('input[name="q"]');
    if (!input) {
        return;
    }

    var endpoint = form.getAttribute('data-api-base') || 'api/quran/search.php';
    var MIN_CHARS = 2;
    var LIMIT = 6;
    var MIN_INTERVAL = 220;

    var list = document.createElement('ul');
    list.className = 'typeahead';
    list.id = 'typeahead-options';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Search suggestions');
    list.hidden = true;

    var live = document.createElement('span');
    live.className = 'visually-hidden';
    live.setAttribute('aria-live', 'polite');
    live.setAttribute('data-typeahead-status', 'true');

    form.appendChild(list);
    form.appendChild(live);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    var items = [];
    var activeIndex = -1;
    var timer = null;
    var lastRun = 0;
    var requestId = 0;
    var controller = null;
    var cache = {};

    function close() {
        list.hidden = true;
        list.innerHTML = '';
        items = [];
        activeIndex = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    function setActive(index) {
        var options = list.querySelectorAll('[role="option"]');
        if (!options.length) {
            return;
        }
        activeIndex = (index + options.length) % options.length;
        options.forEach(function (option, i) {
            var selected = i === activeIndex;
            option.classList.toggle('is-active', selected);
            option.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', options[activeIndex].id);
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function open() {
        if (!items.length) {
            return;
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function go(index) {
        var item = items[index];
        if (!item || !item.url) {
            return;
        }
        window.location.href = item.url;
    }

    function render(data) {
        var rows = (data.results || []).slice(0, LIMIT);
        var chapters = (data.chapters || []).slice(0, 2);
        items = [];

        var html = '';

        chapters.forEach(function (chapter, i) {
            var id = 'typeahead-opt-' + items.length;
            items.push({ url: chapter.url });
            html += '<li class="typeahead-item" role="option" aria-selected="false" id="' + id + '" data-index="' + (items.length - 1) + '">' +
                '<span class="typeahead-ref">' +
                    '<span class="typeahead-title">' + app.escapeHtml(chapter.name_simple) + '</span>' +
                    '<span class="typeahead-meta">Surah ' + Number(chapter.id) + '</span>' +
                '</span>' +
                '<span class="typeahead-arabic" lang="ar" dir="rtl">' + app.escapeHtml(chapter.name_arabic) + '</span>' +
                '</li>';
        });

        rows.forEach(function (row) {
            var id = 'typeahead-opt-' + items.length;
            items.push({ url: row.url });
            var preview = row.preview || '';
            if (preview.length > 90) {
                preview = preview.slice(0, 90).replace(/\s+\S*$/, '') + '…';
            }
            html += '<li class="typeahead-item" role="option" aria-selected="false" id="' + id + '" data-index="' + (items.length - 1) + '">' +
                '<span class="typeahead-ref">' +
                    '<span class="typeahead-title">' + app.escapeHtml(row.surah_name) + '</span>' +
                    '<span class="typeahead-meta">' + Number(row.surah) + ':' + Number(row.verse) + '</span>' +
                '</span>' +
                '<span class="typeahead-preview">' + app.escapeHtml(preview) + '</span>' +
                '</li>';
        });

        if (!html) {
            close();
            live.textContent = 'No matches';
            return;
        }

        list.innerHTML = html;
        open();
        live.textContent = (data.total || items.length) + ' matches available';
    }

    function request(query) {
        var url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(query) + '&limit=' + LIMIT;

        if (cache[query]) {
            render(cache[query]);
            return;
        }

        if (controller && typeof controller.abort === 'function') {
            controller.abort();
        }
        controller = typeof AbortController === 'function' ? new AbortController() : null;

        var current = ++requestId;
        var options = { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
        if (controller) {
            options.signal = controller.signal;
        }

        fetch(url, options)
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (current !== requestId || !payload || payload.ok !== true) {
                    return;
                }
                cache[query] = payload.data;
                if (input.value.trim() !== query) {
                    return;
                }
                render(payload.data);
            })
            .catch(function () {
                /* Aborted or offline: the form still works normally. */
            });
    }

    function run() {
        var query = input.value.trim();
        lastRun = Date.now();

        if (query.length < MIN_CHARS) {
            close();
            live.textContent = '';
            return;
        }
        request(query);
    }

    input.addEventListener('input', function () {
        window.clearTimeout(timer);
        if (input.value.trim() === '') {
            close();
            return;
        }
        var wait = Math.max(MIN_INTERVAL - (Date.now() - lastRun), 0);
        timer = window.setTimeout(run, Math.max(wait, MIN_INTERVAL));
    });

    input.addEventListener('keydown', function (event) {
        if (list.hidden) {
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex - 1);
        } else if (event.key === 'Enter') {
            if (activeIndex >= 0) {
                event.preventDefault();
                go(activeIndex);
            } else {
                close();
            }
        } else if (event.key === 'Escape') {
            close();
        }
    });

    // mousedown (not click) so the field does not lose focus before navigation.
    list.addEventListener('mousedown', function (event) {
        var option = event.target.closest('[role="option"]');
        if (!option) {
            return;
        }
        event.preventDefault();
        go(Number(option.getAttribute('data-index')));
    });

    list.addEventListener('mousemove', function (event) {
        var option = event.target.closest('[role="option"]');
        if (option) {
            setActive(Number(option.getAttribute('data-index')));
        }
    });

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            close();
        }
    });

    form.addEventListener('submit', function () {
        window.clearTimeout(timer);
        close();
    });

    input.addEventListener('focus', function () {
        if (input.value.trim().length >= MIN_CHARS && items.length) {
            open();
        }
    });
})();
