/* global window, document, fetch */
/**
 * Site-wide behaviour: theme, settings, authentication forms, bookmarks,
 * guest data and small progressive enhancements.
 *
 * Everything degrades gracefully: pages are fully rendered on the server, this
 * file only adds interactivity.
 */
(function () {
    'use strict';

    var root = document.documentElement;

    function meta(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    }

    var BASE = meta('app-base') || '';
    var AUTHED = meta('authed') === '1';
    var PREFIX = 'quran.';
    var SIZES = ['small', 'medium', 'large', 'xlarge'];

    // ------------------------------------------------------------------ store
    var store = {
        getString: function (key, fallback) {
            try {
                var value = window.localStorage.getItem(PREFIX + key);
                return value === null ? fallback : value;
            } catch (e) {
                return fallback;
            }
        },
        setString: function (key, value) {
            try {
                window.localStorage.setItem(PREFIX + key, String(value));
            } catch (e) { /* storage disabled */ }
        },
        getJSON: function (key, fallback) {
            var raw = this.getString(key, null);
            if (!raw) {
                return fallback;
            }
            try {
                return JSON.parse(raw);
            } catch (e) {
                return fallback;
            }
        },
        setJSON: function (key, value) {
            this.setString(key, JSON.stringify(value));
        },
        remove: function (key) {
            try {
                window.localStorage.removeItem(PREFIX + key);
            } catch (e) { /* ignore */ }
        },
        clearAll: function () {
            ['theme', 'fontSize', 'translation', 'reciter', 'bookmarks', 'marks', 'progress', 'chaptersCache'].forEach(function (key) {
                this.remove(key);
            }, this);
        }
    };

    // -------------------------------------------------------------------- api
    function csrfToken() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }

    /**
     * Resolve an API path. Markup built by u() already contains the base
     * ("/quran-project/public_html/api/..."); relative call sites do not.
     * Prefixing both produced a doubled path and a 404 on every form submit
     * whenever the app is installed in a subdirectory.
     */
    function apiUrl(path) {
        var value = String(path === undefined || path === null ? '' : path);
        if (value.charAt(0) === '/') {
            return value;
        }
        return BASE + '/' + value.replace(/^\/+/, '');
    }

    function api(path, data, options) {
        options = options || {};
        var method = options.method || (data === undefined ? 'GET' : 'POST');
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var init = {
            method: method,
            credentials: 'same-origin',
            headers: headers
        };

        if (data !== undefined && method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = csrfToken();
            init.body = JSON.stringify(data);
        } else if (method === 'POST') {
            headers['X-CSRF-Token'] = csrfToken();
        }

        return fetch(apiUrl(path), init).then(function (response) {
            return response.text().then(function (text) {
                var payload = null;
                try {
                    payload = text ? JSON.parse(text) : null;
                } catch (e) {
                    payload = null;
                }

                if (!response.ok || !payload || payload.ok !== true) {
                    var message = (payload && payload.error && payload.error.message) || 'Something went wrong. Please try again.';
                    var error = new Error(message);
                    error.status = response.status;
                    error.details = (payload && payload.error && payload.error.details) || null;
                    error.code = (payload && payload.error && payload.error.code) || '';
                    throw error;
                }

                return payload.data;
            });
        });
    }

    // ------------------------------------------------------------------ toast
    var toastTimer = null;
    function toast(message, kind) {
        var el = document.querySelector('.toast');
        if (!el) {
            el = document.createElement('div');
            el.className = 'toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.className = 'toast is-visible' + (kind === 'error' ? ' toast-error' : '');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            el.className = 'toast';
        }, 3200);
    }

    var PLAYING_STYLES = ['card', 'border', 'bar', 'off'];

    /** Mark how the reciting verse is drawn; stored per account and per device. */
    function applyPlayingStyle(style) {
        var value = PLAYING_STYLES.indexOf(style) === -1 ? 'card' : style;
        root.setAttribute('data-playing-style', value);
        return value;
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ---------------------------------------------------------------- chapters
    var chaptersPromise = null;
    function loadChapters() {
        if (chaptersPromise) {
            return chaptersPromise;
        }
        var cached = store.getJSON('chaptersCache', null);
        if (cached && cached.length === 114) {
            chaptersPromise = Promise.resolve(cached);
            return chaptersPromise;
        }
        chaptersPromise = api('api/quran/chapters.php').then(function (data) {
            var list = (data && data.chapters) || [];
            if (list.length) {
                store.setJSON('chaptersCache', list);
            }
            return list;
        }).catch(function () {
            chaptersPromise = null;
            return [];
        });
        return chaptersPromise;
    }

    // -------------------------------------------------------------- bookmarks
    var Bookmarks = {
        local: function () {
            var list = store.getJSON('bookmarks', []);
            return Array.isArray(list) ? list : [];
        },
        saveLocal: function (list) {
            store.setJSON('bookmarks', list);
            this.updateCount();
        },
        hasLocal: function (surah, verse) {
            return this.local().some(function (item) {
                return Number(item.surah) === Number(surah) && Number(item.verse) === Number(verse);
            });
        },
        addLocal: function (surah, verse) {
            var list = this.local();
            if (!this.hasLocal(surah, verse)) {
                list.push({ surah: Number(surah), verse: Number(verse), ts: Date.now() });
                this.saveLocal(list);
            }
        },
        removeLocal: function (surah, verse) {
            this.saveLocal(this.local().filter(function (item) {
                return !(Number(item.surah) === Number(surah) && Number(item.verse) === Number(verse));
            }));
        },
        updateCount: function () {
            var badge = document.querySelector('[data-bookmark-count]');
            var total = AUTHED ? null : this.local().length;
            if (badge && total !== null) {
                badge.textContent = String(total);
                badge.hidden = total === 0;
            }
        }
    };

    // ------------------------------------------------------------------ theme
    function applyTheme(pref) {
        var resolved = pref;
        if (pref !== 'light' && pref !== 'dark') {
            resolved = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        root.setAttribute('data-theme', resolved);
        root.setAttribute('data-theme-preference', pref);
        // The manifest reads this to pick the launch splash colour.
        try {
            document.cookie = 'quran_theme=' + resolved + '; path=/; max-age=31536000; samesite=lax';
        } catch (e) { /* ignore */ }
        store.setString('theme', pref);
        syncThemeLabel();
        if (AUTHED) {
            api('api/settings/save.php', { theme: pref }).catch(function () { /* non-fatal */ });
        }
    }

    /** Keep the toggle's accessible name in step with the current theme. */
    function syncThemeLabel() {
        var dark = root.getAttribute('data-theme') === 'dark';
        var label = dark ? 'Switch to light mode' : 'Switch to dark mode';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        });
    }

    function initThemeToggle() {
        syncThemeLabel();
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            });
        });

        // "System" preference follows the OS while the page is open.
        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var onChange = function () {
                if (root.getAttribute('data-theme-preference') === 'system') {
                    root.setAttribute('data-theme', mq.matches ? 'dark' : 'light');
                    syncThemeLabel();
                }
            };
            if (mq.addEventListener) {
                mq.addEventListener('change', onChange);
            } else if (mq.addListener) {
                mq.addListener(onChange);
            }
        }
    }

    function applyFontSize(size) {
        if (SIZES.indexOf(size) === -1) {
            size = 'medium';
        }
        root.setAttribute('data-font-size', size);
        store.setString('fontSize', size);
        if (AUTHED) {
            api('api/settings/save.php', { arabic_font_size: size }).catch(function () { /* non-fatal */ });
        }
    }

    // --------------------------------------------------------- continue reading
    function initContinueSlot() {
        var slot = document.querySelector('[data-continue-slot]');
        if (!slot || AUTHED) {
            return;
        }
        var progress = store.getJSON('progress', null);
        if (!progress || !progress.surah) {
            return;
        }

        loadChapters().then(function (chapters) {
            var chapter = chapters.filter(function (item) {
                return Number(item.id) === Number(progress.surah);
            })[0];
            var label = chapter ? chapter.name_simple + ' ' + progress.verse : progress.surah + ':' + progress.verse;
            var link = document.createElement('a');
            link.className = 'btn btn-primary btn-lg';
            link.href = BASE + '/surah.php?n=' + progress.surah + '#verse-' + progress.verse;
            link.textContent = 'Continue reading · ' + label;
            slot.appendChild(link);

            var fallback = document.querySelector('[data-continue-default]');
            if (fallback) {
                fallback.classList.add('is-hidden');
            }
        });
    }

    // -------------------------------------------------------- guest bookmarks
    function initGuestBookmarks() {
        var container = document.querySelector('[data-guest-bookmarks]');
        if (!container) {
            return;
        }

        var list = Bookmarks.local();
        var empty = document.querySelector('[data-guest-empty]');

        if (!list.length) {
            container.hidden = true;
            if (empty) {
                empty.hidden = false;
            }
            return;
        }
        if (empty) {
            empty.hidden = true;
        }

        loadChapters().then(function (chapters) {
            var byId = {};
            chapters.forEach(function (item) { byId[Number(item.id)] = item; });

            // One collapsible node per surah, newest verse first inside.
            var groups = {};
            var order = [];
            list.slice().sort(function (a, b) {
                return (Number(b.ts) || 0) - (Number(a.ts) || 0);
            }).forEach(function (item) {
                var surah = Number(item.surah);
                if (!groups[surah]) {
                    groups[surah] = { surah: surah, chapter: byId[surah], items: [] };
                    order.push(surah);
                }
                groups[surah].items.push(item);
            });

            var html = ['<p class="muted small">' + list.length + ' verse' + (list.length === 1 ? '' : 's') +
                ' saved on this device</p>', '<div class="saved-tree" data-local-bookmark-list>'];

            order.forEach(function (surah) {
                var group = groups[surah];
                var chapter = group.chapter || { name_simple: 'Surah ' + surah, name_arabic: '' };
                html.push(
                    '<details class="saved-group" open>' +
                        '<summary>' +
                            '<span class="saved-group-num">' + surah + '</span>' +
                            '<span class="saved-group-title"><strong>' + escapeHtml(chapter.name_simple) + '</strong>' +
                                (chapter.name_arabic
                                    ? '<span class="saved-group-arabic" lang="ar" dir="rtl">' + escapeHtml(chapter.name_arabic) + '</span>'
                                    : '') +
                            '</span>' +
                            '<span class="saved-group-count">' + group.items.length + '</span>' +
                        '</summary>' +
                        '<ol class="bookmark-list saved-group-items">'
                );
                group.items.forEach(function (item) {
                    html.push(
                        '<li class="bookmark" data-local-item data-surah="' + surah + '" data-verse="' + Number(item.verse) + '">' +
                            '<div class="bookmark-head">' +
                                '<a class="bookmark-ref" href="' + BASE + '/surah.php?n=' + surah + '#verse-' + Number(item.verse) + '">' +
                                    '<span class="saved-verse-ref">' + surah + ':' + Number(item.verse) + '</span>' +
                                '</a>' +
                                '<button type="button" class="icon-btn sm" data-remove-local aria-label="Remove bookmark">' +
                                    '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M5 7h14M9.5 7V5.5h5V7M7 7l.8 12.5h8.4L17 7"/></svg>' +
                                '</button>' +
                            '</div>' +
                        '</li>'
                    );
                });
                html.push('</ol></details>');
            });

            html.push('</div>');
            container.innerHTML = html.join('');

            container.addEventListener('click', function (event) {
                var button = event.target.closest('[data-remove-local]');
                if (!button) {
                    return;
                }
                var row = button.closest('[data-local-item]');
                var group = row ? row.closest('.saved-group') : null;
                Bookmarks.removeLocal(row.getAttribute('data-surah'), row.getAttribute('data-verse'));
                row.remove();
                if (group && !group.querySelector('[data-local-item]')) {
                    group.remove();
                }
                toast('Bookmark removed');
                if (!Bookmarks.local().length) {
                    window.location.reload();
                }
            });
        });
    }

    // ------------------------------------------------------------ import banner
    /** Guest highlights/notes live in the same shape the marks API accepts. */
    function guestMarks() {
        var map = store.getJSON('marks', {});
        if (!map || typeof map !== 'object') {
            return [];
        }
        return Object.keys(map).map(function (key) {
            var parts = String(key).split(':');
            var mark = map[key] || {};
            return {
                surah: Number(parts[0]),
                verse: Number(parts[1]),
                color: mark.color || '',
                note: mark.note || ''
            };
        }).filter(function (item) {
            return item.surah > 0 && item.verse > 0 && (item.color || item.note);
        });
    }

    function initImportBanner() {
        var banner = document.querySelector('[data-guest-banner]');
        if (!banner || !AUTHED) {
            return;
        }
        var list = Bookmarks.local();
        var marks = guestMarks();
        if (!list.length && !marks.length) {
            return;
        }

        banner.hidden = false;
        var text = banner.querySelector('[data-guest-banner-text]');
        if (text) {
            var parts = [];
            if (list.length) {
                parts.push(list.length + ' bookmark' + (list.length === 1 ? '' : 's'));
            }
            if (marks.length) {
                parts.push(marks.length + ' highlight' + (marks.length === 1 ? '' : 's') + '/note' + (marks.length === 1 ? '' : 's'));
            }
            text.textContent = 'You have ' + parts.join(' and ') +
                ' saved on this device. Import them into your account?';
        }

        var importBtn = banner.querySelector('[data-import-bookmarks]');
        if (importBtn) {
            importBtn.addEventListener('click', function () {
                importBtn.disabled = true;
                var jobs = [];
                if (list.length) {
                    jobs.push(api('api/bookmarks/import.php', { items: list }));
                }
                if (marks.length) {
                    jobs.push(api('api/marks/import.php', { items: marks }));
                }
                Promise.all(jobs).then(function (results) {
                    var imported = results.reduce(function (sum, result) {
                        return sum + (result && result.imported ? result.imported : 0);
                    }, 0);
                    if (list.length) {
                        store.remove('bookmarks');
                    }
                    if (marks.length) {
                        store.remove('marks');
                    }
                    banner.hidden = true;
                    toast('Imported ' + imported + ' item(s)');
                    window.setTimeout(function () { window.location.reload(); }, 700);
                }).catch(function (error) {
                    importBtn.disabled = false;
                    toast(error.message, 'error');
                });
            });
        }

        var dismiss = banner.querySelector('[data-dismiss-banner]');
        if (dismiss) {
            dismiss.addEventListener('click', function () {
                banner.hidden = true;
            });
        }
    }

    // ------------------------------------------------------------------ header
    function initHeader() {
        document.querySelectorAll('[data-jump-surah]').forEach(function (select) {
            select.addEventListener('change', function () {
                window.location.href = BASE + '/surah.php?n=' + encodeURIComponent(select.value);
            });
        });

        document.querySelectorAll('[data-auto-submit]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) {
                    select.form.submit();
                }
            });
        });

        document.querySelectorAll('[data-translation-form]').forEach(function (form) {
            form.addEventListener('change', function (event) {
                if (event.target && event.target.type === 'radio') {
                    form.submit();
                }
            });
        });
    }

    // ----------------------------------------------------------- surah filter
    function initSurahFilter() {
        var input = document.querySelector('[data-surah-filter]');
        var list = document.querySelector('[data-surah-list]');
        if (!input || !list) {
            return;
        }
        var empty = document.querySelector('[data-surah-empty]');
        var items = Array.prototype.slice.call(list.querySelectorAll('li'));

        input.addEventListener('input', function () {
            var query = input.value.trim().toLowerCase();
            var visible = 0;

            items.forEach(function (item) {
                var card = item.querySelector('.surah-card');
                if (!card) {
                    return;
                }
                var haystack = (card.getAttribute('data-name') || '');
                var number = card.getAttribute('data-number') || '';
                var match = query === '' || haystack.indexOf(query) !== -1 || number.indexOf(query) === 0;
                item.hidden = !match;
                if (match) {
                    visible++;
                }
            });

            if (empty) {
                empty.hidden = visible !== 0;
            }
        });
    }

    // ---------------------------------------------------------------- settings
    function initSettingsForm() {
        // The settings page and the account dashboard both host a preferences
        // form, so wire every instance rather than only the first one.
        document.querySelectorAll('[data-settings-form]').forEach(function (form) {
            wireSettingsForm(form);
        });
    }

    /** Mirror the effective Quran typeface onto the radio group on the page. */
    function syncArabicFontChoice() {
        var current = root.getAttribute('data-arabic-font') || 'noto-naskh';
        document.querySelectorAll('input[name="arabic_font"]').forEach(function (input) {
            input.checked = input.value === current;
        });
    }

    /** @param {HTMLFormElement} form */
    function wireSettingsForm(form) {
        var status = form.querySelector('[data-settings-status]');

        // A typeface is a visual choice, so preview it the moment it is picked.
        // The value is only written to storage/account on submit, like the other
        // preferences — leaving without saving puts the old face back.
        form.addEventListener('change', function (event) {
            var input = event.target;
            if (!input || input.name !== 'arabic_font' || !input.checked) {
                return;
            }
            root.setAttribute('data-arabic-font', input.value);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var data = {};
            new FormData(form).forEach(function (value, key) { data[key] = value; });

            // Apply theme + size immediately, even before the request resolves.
            if (data.theme) {
                applyTheme(data.theme);
            }
            if (data.arabic_font_size) {
                root.setAttribute('data-font-size', data.arabic_font_size);
                store.setString('fontSize', data.arabic_font_size);
            }
            if (data.translation_id) {
                store.setString('translation', data.translation_id);
            }
            if (data.reciter_id) {
                store.setString('reciter', data.reciter_id);
            }
            if (data.playing_style) {
                store.setString('playingStyle', applyPlayingStyle(data.playing_style));
            }
            if (data.arabic_font) {
                root.setAttribute('data-arabic-font', data.arabic_font);
                store.setString('arabicFont', data.arabic_font);
            }

            var done = function (message) {
                if (status) {
                    status.className = 'form-status muted small';
                    status.textContent = message;
                }
                toast(message);
            };

            if (AUTHED) {
                api('api/settings/save.php', data).then(function () {
                    done('Settings saved to your account.');
                }).catch(function (error) {
                    if (status) {
                        status.className = 'form-status is-error small';
                        status.textContent = error.message;
                    }
                });
            } else {
                done('Saved on this device.');
            }
        });

        var reset = document.querySelector('[data-reset-local]');
        if (reset) {
            reset.addEventListener('click', function () {
                var statusEl = document.querySelector('[data-reset-status]');
                if (!window.confirm('Clear bookmarks, reading position and preferences stored in this browser?')) {
                    return;
                }
                var guestBookmarks = Bookmarks.local();
                store.clearAll();
                if (statusEl) {
                    statusEl.textContent = guestBookmarks.length
                        ? 'Local data cleared. Your account bookmarks are untouched.'
                        : 'Local data cleared.';
                }
                window.setTimeout(function () { window.location.reload(); }, 900);
            });
        }
    }

    // ------------------------------------------------------------ auth forms
    function fieldErrors(error) {
        var details = error.details;
        if (!details) {
            return '';
        }
        var fields = details.fields || details;
        if (!fields || typeof fields !== 'object') {
            return '';
        }
        return Object.keys(fields).map(function (key) {
            return fields[key];
        }).join(' ');
    }

    function showFormError(form, error) {
        var box = form.querySelector('[data-form-error]');
        if (!box) {
            toast(error.message, 'error');
            return;
        }
        var extra = fieldErrors(error);
        box.textContent = extra && extra !== error.message ? error.message + ' ' + extra : error.message;
        box.hidden = false;
    }

    function showFormSuccess(form, message) {
        var box = form.querySelector('[data-form-success]');
        if (box) {
            box.textContent = message;
            box.hidden = false;
        }
    }

    function setBusy(form, busy) {
        form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = busy;
            if (busy) {
                button.dataset.label = button.textContent;
                button.textContent = 'Please wait…';
            } else if (button.dataset.label) {
                button.textContent = button.dataset.label;
            }
        });
    }

    function initAuthForms() {
        document.querySelectorAll('[data-auth-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var errorBox = form.querySelector('[data-form-error]');
                if (errorBox) {
                    errorBox.hidden = true;
                }
                setBusy(form, true);

                var data = {};
                new FormData(form).forEach(function (value, key) { data[key] = value; });

                api(form.getAttribute('data-api'), data).then(function (result) {
                    var type = form.getAttribute('data-auth-form');

                    if (type === 'forgot') {
                        setBusy(form, false);
                        form.reset();
                        showFormSuccess(form, (result && result.message) || 'If that email is registered, a reset link is on its way.');
                        return;
                    }
                    if (type === 'reset') {
                        setBusy(form, false);
                        showFormSuccess(form, (result && result.message) || 'Password updated. You can sign in now.');
                        window.setTimeout(function () {
                            window.location.href = form.getAttribute('data-login-url') || (BASE + '/login.php');
                        }, 1400);
                        return;
                    }

                    // Login / register succeeded. Only follow same-site paths.
                    var next = form.getAttribute('data-redirect');
                    var isSafePath = !!next && next.charAt(0) === '/' && next.charAt(1) !== '/' && next.charAt(1) !== '\\';
                    if (isSafePath) {
                        window.location.href = next;
                    } else {
                        window.location.href = BASE + '/account.php';
                    }
                }).catch(function (error) {
                    setBusy(form, false);
                    showFormError(form, error);
                });
            });
        });
    }

    // --------------------------------------------------------- account forms
    /**
     * Field level messages from a validation failure. The API reports them as
     * error.details.fields = { field_name: "message" }, which used to be thrown
     * away here: the form only said "check the highlighted fields" while no
     * field was actually highlighted or explained.
     */
    function fieldErrorMap(error) {
        var details = error && error.details;
        var fields = details && (details.fields || details);
        return fields && typeof fields === 'object' ? fields : {};
    }

    function clearFieldError(input) {
        input.removeAttribute('aria-invalid');
        input.classList.remove('is-invalid');

        var described = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (id) {
            return !!id && id.indexOf('field-error-') !== 0;
        });
        if (described.length) {
            input.setAttribute('aria-describedby', described.join(' '));
        } else {
            input.removeAttribute('aria-describedby');
        }

        var form = input.form;
        if (!form) {
            return;
        }
        var name = input.name;
        form.querySelectorAll('[data-field-error]').forEach(function (note) {
            if (note.getAttribute('data-field-error') === name) {
                note.remove();
            }
        });
    }

    function clearFieldErrors(form) {
        form.querySelectorAll('[aria-invalid="true"]').forEach(clearFieldError);
        form.querySelectorAll('[data-field-error]').forEach(function (note) {
            note.remove();
        });
    }

    /** Highlight each invalid field and print its own message beneath it. */
    function showFieldErrors(form, error) {
        clearFieldErrors(form);

        var fields = fieldErrorMap(error);
        var prefix = 'field-error-' + (form.getAttribute('data-account-form') || 'form') + '-';
        var first = null;

        Object.keys(fields).forEach(function (name) {
            var message = fields[name];
            var input = form.querySelector('[name="' + name + '"]');
            if (!message || !input) {
                return;
            }

            var note = document.createElement('p');
            note.className = 'field-error';
            note.id = prefix + name;
            note.setAttribute('data-field-error', name);
            note.textContent = String(message);

            var wrap = input.closest('.field');
            if (wrap) {
                wrap.appendChild(note);
            } else {
                input.parentNode.insertBefore(note, input.nextSibling);
            }

            input.setAttribute('aria-invalid', 'true');
            input.classList.add('is-invalid');
            var described = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (described.indexOf(note.id) === -1) {
                described.push(note.id);
            }
            input.setAttribute('aria-describedby', described.join(' '));

            if (!first) {
                first = input;
            }
        });

        if (first) {
            first.focus();
        }
    }

    function initAccountForms() {
        document.querySelectorAll('[data-account-form]').forEach(function (form) {
            form.addEventListener('input', function (event) {
                var input = event.target;
                if (input && input.getAttribute && input.getAttribute('aria-invalid') === 'true') {
                    clearFieldError(input);
                }
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var confirmMessage = form.getAttribute('data-confirm');
                if (confirmMessage && !window.confirm(confirmMessage)) {
                    return;
                }

                var status = form.querySelector('[data-form-status]');
                var data = {};
                new FormData(form).forEach(function (value, key) { data[key] = value; });

                setBusy(form, true);
                clearFieldErrors(form);
                api(form.getAttribute('data-api'), data).then(function (result) {
                    setBusy(form, false);
                    var message = (result && result.message) || 'Saved.';

                    if (form.getAttribute('data-account-form') === 'delete') {
                        window.location.href = BASE + '/';
                        return;
                    }
                    if (form.getAttribute('data-account-form') === 'password') {
                        form.reset();
                    }
                    clearFieldErrors(form);
                    if (status) {
                        status.className = 'form-status muted small';
                        status.textContent = message;
                    }
                    toast(message);
                }).catch(function (error) {
                    setBusy(form, false);
                    showFieldErrors(form, error);
                    if (status) {
                        status.className = 'form-status is-error small';
                        status.textContent = error.message;
                    } else {
                        toast(error.message, 'error');
                    }
                });
            });
        });

        document.querySelectorAll('[data-logout]').forEach(function (button) {
            button.addEventListener('click', function () {
                api(button.getAttribute('data-api'), {}).then(function () {
                    window.location.href = BASE + '/';
                }).catch(function () {
                    window.location.href = BASE + '/';
                });
            });
        });
    }

    // ------------------------------------------------------------ account data
    function initAccountSummary() {
        if (!AUTHED || !document.querySelector('[data-account-progress]')) {
            return;
        }

        api('api/progress/get.php').then(function (data) {
            var progress = data && data.progress;
            var el = document.querySelector('[data-account-progress]');
            if (!el) {
                return;
            }
            if (!progress) {
                el.textContent = 'Not started';
                return;
            }
            var link = document.createElement('a');
            link.href = BASE + '/surah.php?n=' + progress.surah + '#verse-' + progress.verse;
            link.textContent = (progress.surah_name || 'Surah ' + progress.surah) + ' ' + progress.verse;
            el.textContent = '';
            el.appendChild(link);
        }).catch(function () { /* ignore */ });

        api('api/bookmarks/list.php').then(function (data) {
            var el = document.querySelector('[data-account-bookmarks]');
            if (el && data && typeof data.total === 'number') {
                el.textContent = String(data.total);
            }
        }).catch(function () { /* ignore */ });
    }

    // ------------------------------------------------- saved bookmarks list
    /**
     * Remove a bookmark from the bookmarks page. The handler is delegated so it
     * also works for the grouped list after a group has been re-rendered.
     */
    function initSavedBookmarks() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest && event.target.closest('[data-remove-bookmark]');
            if (!button) {
                return;
            }
            event.preventDefault();

            var item = button.closest('[data-bookmark-item]');
            var payload = {
                id: Number(button.getAttribute('data-id')) || 0,
                surah: Number(button.getAttribute('data-surah')) || 0,
                verse: Number(button.getAttribute('data-verse')) || 0
            };

            button.disabled = true;
            api('api/bookmarks/remove.php', payload).then(function () {
                var group = item ? item.closest('.saved-group') : null;
                if (item) {
                    item.remove();
                }
                if (group && !group.querySelector('[data-bookmark-item]')) {
                    group.remove();
                }
                var pill = document.querySelector('[data-bookmark-count]');
                if (pill) {
                    var next = Math.max(0, (parseInt(pill.textContent, 10) || 0) - 1);
                    pill.textContent = String(next);
                    pill.hidden = next === 0;
                }
                toast('Bookmark removed');
                if (!document.querySelector('[data-bookmark-item]')) {
                    window.setTimeout(function () { window.location.reload(); }, 600);
                }
            }).catch(function (error) {
                button.disabled = false;
                toast(error.message, 'error');
            });
        });
    }

    // ------------------------------------------------------ header geometry
    /**
     * The header can be one or two rows depending on the viewport. The reader
     * toolbar sticks directly underneath it, so publish the measured height as
     * --header-h instead of relying on the fixed fallback in the stylesheet.
     */
    function initHeaderHeight() {
        var header = document.querySelector('.site-header');
        if (!header) {
            return;
        }
        var toolbar = document.querySelector('.reader-toolbar');

        function sync() {
            var headerHeight = Math.round(header.getBoundingClientRect().height);
            if (headerHeight <= 0) {
                return;
            }
            var toolbarHeight = toolbar ? Math.round(toolbar.getBoundingClientRect().height) : 0;
            var root = document.documentElement;
            root.style.setProperty('--header-h', headerHeight + 'px');
            // Sticky stack height plus a little breathing room.
            root.style.setProperty('--sticky-offset', (headerHeight + toolbarHeight + 10) + 'px');
        }

        sync();

        if (typeof ResizeObserver === 'function') {
            var observer = new ResizeObserver(sync);
            observer.observe(header);
            if (toolbar) {
                observer.observe(toolbar);
            }
        } else {
            window.addEventListener('resize', sync);
        }
        window.addEventListener('orientationchange', sync);
    }

    // ------------------------------------------------------------- boot
    /** Native <details> only closes from its summary; close it on outside click
        and on Escape so the translation sheet behaves like a popover. */
    function initDismissableDetails() {
        document.addEventListener('click', function (event) {
            document.querySelectorAll('details.translation-picker[open]').forEach(function (details) {
                if (!details.contains(event.target)) {
                    details.removeAttribute('open');
                }
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('details.translation-picker[open]').forEach(function (details) {
                details.removeAttribute('open');
            });
        });
    }

    function boot() {
        // The server already stamped the account value; localStorage wins when
        // this device has a newer choice (guest, or changed before saving).
        applyPlayingStyle(store.getString('playingStyle', root.getAttribute('data-playing-style') || 'card'));
        // A guest's typeface lives in localStorage only, so the server-rendered
        // radio would otherwise fall back to the first option.
        syncArabicFontChoice();
        initDismissableDetails();
        initThemeToggle();
        initHeader();
        initHeaderHeight();
        initSurahFilter();
        initContinueSlot();
        initGuestBookmarks();
        initSavedBookmarks();
        initImportBanner();
        initSettingsForm();
        initAuthForms();
        initAccountForms();
        initAccountSummary();
        Bookmarks.updateCount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // Public API for reader.js and search.js
    window.QuranApp = {
        BASE: BASE,
        AUTHED: AUTHED,
        api: api,
        store: store,
        toast: toast,
        escapeHtml: escapeHtml,
        applyFontSize: applyFontSize,
        loadChapters: loadChapters,
        bookmarks: Bookmarks,
        SIZES: SIZES
    };
})();
