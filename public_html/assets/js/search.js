/**
 * Search-as-you-type. The server renders results first (works without JS);
 * this script refreshes them without a page reload.
 */
(function () {
    'use strict';

    var panel = document.querySelector('[data-search-page]');
    if (!panel || !window.QuranApp) {
        return;
    }

    var app = window.QuranApp;
    var input = panel.querySelector('[data-search-input]');
    var results = document.querySelector('[data-results]');
    var status = document.querySelector('[data-search-status]');
    var chapterContainer = document.querySelector('.chapter-matches');
    var translationSelect = panel.querySelector('select[name="translation"]');
    var surahSelect = panel.querySelector('select[name="surah"]');
    var juzSelect = panel.querySelector('select[name="juz"]');

    if (!input || !results) {
        return;
    }

    var timer = null;
    var requestId = 0;
    var lastQuery = input.value.trim();

    function renderStatus(data) {
        if (!status) {
            return;
        }
        if (!data.total) {
            status.innerHTML = '<p class="muted">No verses matched “' + app.escapeHtml(data.query) + '”.</p>';
            return;
        }
        var plural = data.total === 1 ? 'verse' : 'verses';
        var shown = Math.min(data.total, data.results.length);
        status.innerHTML = '<p class="muted"><strong>' + Number(data.total).toLocaleString() + '</strong> ' + plural +
            ' matched “' + app.escapeHtml(data.query) + '”. Showing ' + shown + '.</p>';
    }

    function renderChapters(chapters) {
        if (!chapterContainer) {
            if (!chapters || !chapters.length) {
                return;
            }
            var host = document.querySelector('[data-search-status]');
            if (!host) {
                return;
            }
            chapterContainer = document.createElement('div');
            chapterContainer.className = 'chapter-matches';
            host.insertAdjacentElement('afterend', chapterContainer);
        }

        if (!chapters || !chapters.length) {
            chapterContainer.innerHTML = '';
            chapterContainer.hidden = true;
            return;
        }

        chapterContainer.hidden = false;
        chapterContainer.innerHTML = chapters.map(function (chapter) {
            return '<a class="chapter-match" href="' + chapter.url + '">' +
                '<span class="chapter-match-num">' + Number(chapter.id) + '</span>' +
                '<span><strong>' + app.escapeHtml(chapter.name_simple) + '</strong>' +
                '<small class="muted">' + app.escapeHtml(chapter.translated) + ' · ' + Number(chapter.verses_count) + ' verses</small></span>' +
                '<span class="chapter-match-ar" lang="ar" dir="rtl">' + app.escapeHtml(chapter.name_arabic) + '</span>' +
                '</a>';
        }).join('');
    }

    function renderResults(rows) {
        if (!rows.length) {
            results.innerHTML = '';
            return;
        }

        results.innerHTML = rows.map(function (row) {
            // The API ships pre-escaped HTML for the parts that can contain
            // <mark> highlighting; fall back to plain escaping for old payloads.
            var previewHtml = row.preview_html || app.escapeHtml(row.preview || '');
            var arabicHtml = row.arabic_html || app.escapeHtml(row.arabic || '');
            var translation = row.translation
                ? '<p class="result-translation">' + previewHtml + '</p>'
                : '';
            return '<li class="result">' +
                '<div class="result-head">' +
                    '<a class="result-ref" href="' + row.url + '">' + app.escapeHtml(row.surah_name) +
                    ' <span class="muted">' + row.surah + ':' + row.verse + '</span></a>' +
                    '<span class="pill" lang="ar" dir="rtl">' + app.escapeHtml(row.surah_name_arabic) + '</span>' +
                '</div>' +
                '<p class="result-arabic" lang="ar" dir="rtl">' + arabicHtml + '</p>' +
                translation +
                '</li>';
        }).join('');
    }

    function run() {
        var query = input.value.trim();
        lastQuery = query;

        if (query.length < 2) {
            results.innerHTML = '';
            if (status) {
                status.innerHTML = query.length === 1
                    ? '<p class="muted">Type at least two characters.</p>'
                    : '';
            }
            renderChapters([]);
            return;
        }

        var current = ++requestId;
        var params = {
            q: query,
            limit: 20,
            translation_id: translationSelect ? translationSelect.value : '',
            surah: surahSelect ? surahSelect.value : '0',
            juz: juzSelect ? juzSelect.value : '0'
        };

        var queryString = Object.keys(params).map(function (key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }).join('&');

        var path = panel.getAttribute('data-api-base') || 'api/quran/search.php';

        fetch(app.BASE + '/' + path.replace(/^\//, '') + '?' + queryString, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (current !== requestId || !payload || payload.ok !== true) {
                return;
            }
            var data = payload.data;
            if (data.query !== lastQuery) {
                return;
            }
            renderResults(data.results || []);
            renderStatus(data);
            renderChapters(data.chapters || []);
        }).catch(function () {
            if (current === requestId && status) {
                status.innerHTML = '<p class="muted">Search is unavailable right now. Please submit the form.</p>';
            }
        });
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(run, 350);
    }

    input.addEventListener('input', function () {
        if (input.value.trim() === '') {
            window.clearTimeout(timer);
            run();
            return;
        }
        schedule();
    });

    [translationSelect, surahSelect, juzSelect].forEach(function (select) {
        if (select) {
            select.addEventListener('change', function () {
                window.clearTimeout(timer);
                run();
            });
        }
    });

    // Keep the form usable: Enter submits and navigates normally.
    panel.addEventListener('submit', function () {
        window.clearTimeout(timer);
    });
})();
