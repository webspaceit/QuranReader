/**
 * Surah picker for the header search box.
 *
 * The header field does two jobs: submit a text search, or jump straight to a
 * surah. Focusing it drops down the full list of 114 surahs; typing filters
 * them by name, translation or number, and the first row turns into "search
 * the Quran for …" so the old behaviour is still one Enter away.
 *
 * Progressive enhancement: without JavaScript the form is a plain GET to
 * search.php, exactly as before.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-surah-picker]');
    var app = window.QuranApp;
    if (!form || !app) {
        return;
    }

    var input = form.querySelector('input[name="q"]');
    if (!input) {
        return;
    }

    var CACHE_KEY = 'quran.chapters.v1';
    var MAX_ROWS = 114;

    var list = document.createElement('ul');
    list.className = 'surah-picker';
    list.id = 'surah-picker-list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Surahs');
    list.hidden = true;
    form.appendChild(list);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    var chapters = null;
    var loading = false;
    var options = [];
    var activeIndex = -1;

    function readCache() {
        try {
            var raw = window.localStorage.getItem(CACHE_KEY);
            var parsed = raw ? JSON.parse(raw) : null;
            if (parsed && parsed.length === 114) {
                return parsed;
            }
        } catch (e) { /* private mode */ }
        return null;
    }

    function writeCache(rows) {
        try {
            window.localStorage.setItem(CACHE_KEY, JSON.stringify(rows));
        } catch (e) { /* quota or private mode */ }
    }

    function base(path) {
        return (app.BASE || '') + '/' + path.replace(/^\/+/, '');
    }

    function load() {
        if (chapters) {
            return Promise.resolve(chapters);
        }
        var cached = readCache();
        if (cached) {
            chapters = cached;
            return Promise.resolve(chapters);
        }
        if (loading) {
            return null;
        }
        loading = true;
        return fetch(base('api/quran/chapters.php'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                loading = false;
                var rows = payload && payload.ok && payload.data ? payload.data.chapters : null;
                if (rows && rows.length) {
                    chapters = rows;
                    writeCache(rows);
                }
                return chapters;
            })
            .catch(function () {
                loading = false;
                return null;
            });
    }

    /** Fold Arabic diacritics and case so "kahf", "Al-Kahf" and "الكهف" agree. */
    function fold(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06ED\u0640]/g, '')
            .replace(/[\u0622\u0623\u0625\u0671]/g, '\u0627')
            .replace(/[\u0649\u0626]/g, '\u064A')
            .replace(/[\u0624]/g, '\u0648')
            .replace(/[\u0629]/g, '\u0647')
            .replace(/[^a-z0-9\u0600-\u06FF]+/g, ' ')
            .trim();
    }

    function matches(chapter, term) {
        if (!term) {
            return true;
        }
        var haystack = fold(chapter.name_simple + ' ' + chapter.translated + ' ' + chapter.name_arabic);
        return haystack.indexOf(term) !== -1 || String(chapter.id) === term;
    }

    function close() {
        list.hidden = true;
        list.innerHTML = '';
        options = [];
        activeIndex = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    function setActive(index) {
        var nodes = list.querySelectorAll('[role="option"]');
        if (!nodes.length) {
            return;
        }
        activeIndex = (index + nodes.length) % nodes.length;
        nodes.forEach(function (node, i) {
            var on = i === activeIndex;
            node.classList.toggle('is-active', on);
            node.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', nodes[activeIndex].id);
        nodes[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function go(index) {
        var option = options[index];
        if (!option) {
            return;
        }
        if (option.search) {
            form.submit();
            return;
        }
        window.location.href = option.url;
    }

    function render() {
        var term = fold(input.value);
        var rows = (chapters || []).filter(function (chapter) {
            return matches(chapter, term);
        }).slice(0, MAX_ROWS);

        options = [];
        var html = '';

        if (input.value.trim() !== '') {
            options.push({ search: true });
            html += '<li class="surah-picker-item is-search" role="option" aria-selected="false" id="surah-opt-0" data-index="0">' +
                '<span class="surah-picker-title">Search the Quran for &ldquo;' + app.escapeHtml(input.value.trim()) + '&rdquo;</span>' +
                '</li>';
        }

        rows.forEach(function (chapter) {
            var index = options.length;
            options.push({ url: base('surah.php?n=' + Number(chapter.id)) });
            html += '<li class="surah-picker-item" role="option" aria-selected="false" id="surah-opt-' + index + '" data-index="' + index + '">' +
                '<span class="surah-picker-num">' + Number(chapter.id) + '</span>' +
                '<span class="surah-picker-title">' + app.escapeHtml(chapter.name_simple) +
                    '<span class="surah-picker-translated">' + app.escapeHtml(chapter.translated) + '</span>' +
                '</span>' +
                '<span class="surah-picker-arabic" lang="ar" dir="rtl">' + app.escapeHtml(chapter.name_arabic) + '</span>' +
                '</li>';
        });

        if (!html) {
            html = '<li class="surah-picker-empty" role="presentation">No surah matches that name.</li>';
        }

        list.innerHTML = html;
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        activeIndex = -1;
    }

    function open() {
        if (!chapters) {
            list.innerHTML = '<li class="surah-picker-empty" role="presentation">Loading surahs…</li>';
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            load().then(function (rows) {
                if (rows) {
                    render();
                } else {
                    close();
                }
            });
            return;
        }
        render();
    }

    input.addEventListener('focus', open);

    input.addEventListener('input', function () {
        if (!chapters) {
            open();
            return;
        }
        render();
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
            } else if (input.value.trim() !== '' && options.length && options[0].search) {
                // Nothing highlighted but the search row is first: run the search.
                event.preventDefault();
                go(0);
            }
        } else if (event.key === 'Escape') {
            close();
        }
    });

    // mousedown, not click: the field would blur first and close the list.
    list.addEventListener('mousedown', function (event) {
        var item = event.target.closest('[role="option"]');
        if (!item) {
            return;
        }
        event.preventDefault();
        go(Number(item.getAttribute('data-index')));
    });

    list.addEventListener('mousemove', function (event) {
        var item = event.target.closest('[role="option"]');
        if (item) {
            setActive(Number(item.getAttribute('data-index')));
        }
    });

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            close();
        }
    });

    form.addEventListener('submit', close);

    // The header hides the search field on small screens; make sure an open
    // list does not stay in the DOM (and in the a11y tree) after that happens.
    window.addEventListener('resize', function () {
        if (!list.hidden && form.offsetParent === null) {
            close();
        }
    });

    // Preload quietly once the page is idle so the first focus is instant.
    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(load, { timeout: 3000 });
    } else {
        window.setTimeout(load, 1200);
    }
})();
