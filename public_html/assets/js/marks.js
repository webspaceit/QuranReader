/* global window, document */
/**
 * Highlights and notes.
 *
 * One colour and one private note per verse. Members are served their marks by
 * PHP and every change goes through /api/marks/*. Guests keep the same shape in
 * localStorage ("quran.marks") and the guest banner imports them on sign-in.
 *
 * Loaded on the reader and on the bookmarks page.
 */
(function () {
    'use strict';

    var app = window.QuranApp;
    if (!app) {
        return;
    }

    var KEYS = ['yellow', 'green', 'blue', 'pink', 'purple'];
    var MAX_NOTE = 1000;

    // -------------------------------------------------------------- local store
    var Marks = {
        /** @returns {Object<string,{color?:string,note?:string,ts:number}>} */
        local: function () {
            var raw = app.store.getJSON('marks', {});
            return raw && typeof raw === 'object' && !Array.isArray(raw) ? raw : {};
        },
        saveLocal: function (map) {
            app.store.setJSON('marks', map);
        },
        key: function (surah, verse) {
            return Number(surah) + ':' + Number(verse);
        },
        get: function (surah, verse) {
            var mark = this.local()[this.key(surah, verse)];
            return mark || null;
        },
        count: function () {
            return Object.keys(this.local()).length;
        },
        set: function (surah, verse, patch) {
            var map = this.local();
            var key = this.key(surah, verse);
            var mark = map[key] || { ts: Date.now() };
            if (Object.prototype.hasOwnProperty.call(patch, 'color')) {
                if (patch.color) {
                    mark.color = patch.color;
                } else {
                    delete mark.color;
                }
            }
            if (Object.prototype.hasOwnProperty.call(patch, 'note')) {
                if (patch.note && String(patch.note).trim() !== '') {
                    mark.note = String(patch.note).trim();
                } else {
                    delete mark.note;
                }
            }
            mark.ts = Date.now();
            if (!mark.color && !mark.note) {
                delete map[key];
            } else {
                map[key] = mark;
            }
            this.saveLocal(map);
            return map[key] || null;
        },
        remove: function (surah, verse) {
            var map = this.local();
            delete map[this.key(surah, verse)];
            this.saveLocal(map);
        }
    };

    app.marks = Marks;

    // ------------------------------------------------------------------- saving
    /**
     * Persist a partial mark (`{color}` or `{note}`) and resolve with the verse
     * mark as the server sees it.
     */
    function persist(surah, verse, patch) {
        if (!app.AUTHED) {
            var mark = Marks.set(surah, verse, patch);
            return Promise.resolve({ mark: mark });
        }
        var body = { surah: Number(surah), verse: Number(verse) };
        if (Object.prototype.hasOwnProperty.call(patch, 'color')) {
            body.color = patch.color || '';
        }
        if (Object.prototype.hasOwnProperty.call(patch, 'note')) {
            body.note = patch.note || '';
        }
        return app.api('api/marks/save.php', body).catch(function (error) {
            // No HTTP status: the request never reached the server (offline).
            // Store it locally and let the guest banner import it later.
            if (error && error.status) {
                throw error;
            }
            return { mark: Marks.set(surah, verse, patch), offline: true };
        });
    }

    // ------------------------------------------------------------- reader visuals
    function setColorVisual(li, color) {
        if (color) {
            li.setAttribute('data-highlight', color);
        } else {
            li.removeAttribute('data-highlight');
        }
        var button = li.querySelector('[data-action="highlight"]');
        if (button) {
            button.setAttribute('aria-pressed', color ? 'true' : 'false');
            button.setAttribute('data-color', color || '');
            button.title = color ? 'Change highlight' : 'Highlight this verse';
        }
    }

    function renderNote(li, note) {
        var main = li.querySelector('.verse-main');
        if (!main) {
            return;
        }
        var block = main.querySelector('[data-verse-note]');
        var button = li.querySelector('[data-action="note"]');

        if (!note) {
            if (block) {
                block.remove();
            }
            if (button) {
                button.setAttribute('aria-pressed', 'false');
                button.title = 'Add a note';
            }
            return;
        }

        if (!block) {
            block = document.createElement('div');
            block.className = 'verse-note';
            block.setAttribute('data-verse-note', '');
            block.innerHTML = '<p class="verse-note-text"></p>' +
                '<button type="button" class="link-btn" data-action="note-edit">Edit note</button>';
            main.appendChild(block);
        }
        block.querySelector('.verse-note-text').textContent = note;
        if (button) {
            button.setAttribute('aria-pressed', 'true');
            button.title = 'Edit your note';
        }
    }

    // ------------------------------------------------------------- highlight pop
    var openPop = null;

    function closePop() {
        if (openPop) {
            if (openPop.parentNode) {
                openPop.parentNode.classList.remove('has-pop');
            }
            openPop.remove();
            openPop = null;
        }
    }

    function applyColor(li, color) {
        var surah = Number(li.getAttribute('data-surah'));
        var verse = Number(li.getAttribute('data-verse'));
        var previous = li.getAttribute('data-highlight') || '';

        setColorVisual(li, color);

        persist(surah, verse, { color: color }).then(function (result) {
            if (result && result.offline) {
                app.toast('Highlight saved on this device — import it when you are back online');
                return;
            }
            app.toast(color ? 'Highlight saved' : 'Highlight removed');
        }).catch(function (error) {
            setColorVisual(li, previous);
            app.toast(error.message, 'error');
        });
    }

    function openHighlightPop(li, button) {
        if (openPop && openPop.parentNode === li) {
            closePop();
            return;
        }
        closePop();

        var template = document.querySelector('[data-highlight-template]');
        if (!template) {
            return;
        }
        var pop = template.content.firstElementChild.cloneNode(true);
        var current = li.getAttribute('data-highlight') || '';

        Array.prototype.forEach.call(pop.querySelectorAll('.swatch'), function (swatch) {
            var color = swatch.getAttribute('data-color') || '';
            if (color === current) {
                swatch.setAttribute('aria-pressed', 'true');
            }
            swatch.addEventListener('click', function () {
                closePop();
                applyColor(li, color);
            });
        });

        // Position under the button, clamped to the verse width.
        var verseRect = li.getBoundingClientRect();
        var buttonRect = button.getBoundingClientRect();
        pop.style.top = (buttonRect.bottom - verseRect.top + 6) + 'px';

        // The popover hangs below the verse; lift this verse above its later
        // siblings so the swatches stay clickable.
        li.classList.add('has-pop');
        li.appendChild(pop);
        var width = pop.offsetWidth;
        var left = buttonRect.left - verseRect.left;
        left = Math.max(4, Math.min(left, li.clientWidth - width - 4));
        pop.style.left = left + 'px';

        openPop = pop;
        var first = pop.querySelector('.swatch');
        if (first) {
            first.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        if (!openPop) {
            return;
        }
        if (openPop.contains(event.target)) {
            return;
        }
        var trigger = event.target.closest && event.target.closest('[data-action="highlight"]');
        if (!trigger) {
            closePop();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && openPop) {
            closePop();
        }
    });

    // ------------------------------------------------------------------ note form
    function noteEditorFor(li) {
        return li.querySelector('[data-note-editor]');
    }

    function openNoteEditor(li) {
        var existing = noteEditorFor(li);
        if (existing) {
            existing.querySelector('[data-note-input]').focus();
            return;
        }

        var template = document.querySelector('[data-note-template]');
        if (!template) {
            return;
        }

        var surah = Number(li.getAttribute('data-surah'));
        var verse = Number(li.getAttribute('data-verse'));
        var note = '';
        var block = li.querySelector('[data-verse-note]');
        if (block) {
            note = block.querySelector('.verse-note-text').textContent || '';
        }

        var form = template.content.firstElementChild.cloneNode(true);
        var input = form.querySelector('[data-note-input]');
        var label = form.querySelector('[data-note-label]');
        var removeBtn = form.querySelector('[data-note-delete]');
        var fieldId = 'note-' + surah + '-' + verse;

        input.id = fieldId;
        if (label) {
            label.setAttribute('for', fieldId);
            label.textContent = 'Your note on verse ' + verse;
        }
        input.value = note;
        if (removeBtn) {
            removeBtn.hidden = note === '';
        }

        var main = li.querySelector('.verse-main') || li;
        main.appendChild(form);
        input.focus();
        input.setSelectionRange(note.length, note.length);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var value = input.value.trim();
            if (value.length > MAX_NOTE) {
                app.toast('Keep notes under ' + MAX_NOTE + ' characters.', 'error');
                return;
            }
            var button = form.querySelector('button[type="submit"]');
            button.disabled = true;

            persist(surah, verse, { note: value }).then(function (result) {
                renderNote(li, value);
                form.remove();
                app.toast(result && result.offline
                    ? 'Note saved on this device — import it when you are back online'
                    : (value === '' ? 'Note removed' : 'Note saved'));
            }).catch(function (error) {
                button.disabled = false;
                app.toast(error.message, 'error');
            });
        });

        form.querySelector('[data-note-cancel]').addEventListener('click', function () {
            form.remove();
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                removeBtn.disabled = true;
                persist(surah, verse, { note: '' }).then(function (result) {
                    renderNote(li, '');
                    form.remove();
                    app.toast(result && result.offline
                        ? 'Note removed on this device — import it later'
                        : 'Note removed');
                }).catch(function (error) {
                    removeBtn.disabled = false;
                    app.toast(error.message, 'error');
                });
            });
        }
    }

    // --------------------------------------------------------------- reader wiring
    function initReader() {
        var reader = document.querySelector('[data-reader]');
        if (!reader) {
            return;
        }
        var verses = Array.prototype.slice.call(reader.querySelectorAll('[data-verse-item]'));

        // Hydrate from localStorage — for guests this is the only source, for
        // signed-in readers it is where offline edits wait for the next import.
        if (true) {
            verses.forEach(function (li) {
                var mark = Marks.get(li.getAttribute('data-surah'), li.getAttribute('data-verse'));
                if (!mark) {
                    return;
                }
                if (mark.color) {
                    setColorVisual(li, mark.color);
                }
                if (mark.note) {
                    renderNote(li, mark.note);
                }
            });
        }

        verses.forEach(function (li) {
            var highlightBtn = li.querySelector('[data-action="highlight"]');
            var noteBtn = li.querySelector('[data-action="note"]');

            if (highlightBtn) {
                highlightBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    openHighlightPop(li, highlightBtn);
                });
            }
            if (noteBtn) {
                noteBtn.addEventListener('click', function () {
                    openNoteEditor(li);
                });
            }
            li.addEventListener('click', function (event) {
                var edit = event.target.closest && event.target.closest('[data-action="note-edit"]');
                if (edit) {
                    openNoteEditor(li);
                }
            });
        });

        // Deep link from the bookmarks page: ?edit=note#verse-64
        var params = new URLSearchParams(window.location.search);
        if (params.get('edit') === 'note' && window.location.hash.indexOf('#verse-') === 0) {
            var verseNumber = window.location.hash.replace('#verse-', '');
            var target = reader.querySelector('#verse-' + verseNumber);
            if (target) {
                window.setTimeout(function () {
                    openNoteEditor(target);
                }, 250);
            }
        }
    }

    // ------------------------------------------------------------ bookmarks page
    function colorName(color) {
        return color ? color.charAt(0).toUpperCase() + color.slice(1) + ' highlight' : '';
    }

    function initSavedMarks() {
        var list = document.querySelector('[data-mark-list]');

        // Members: remove a mark from the list.
        document.addEventListener('click', function (event) {
            var button = event.target.closest && event.target.closest('[data-mark-remove]');
            if (!button) {
                return;
            }
            var item = button.closest('[data-mark-item]');
            var surah = button.getAttribute('data-surah');
            var verse = button.getAttribute('data-verse');
            button.disabled = true;

            function done() {
                if (item) {
                    item.remove();
                }
                if (list && !list.querySelector('[data-mark-item]')) {
                    window.location.reload();
                }
                app.toast('Highlight and note removed');
            }

            if (app.AUTHED) {
                app.api('api/marks/remove.php', { surah: Number(surah), verse: Number(verse) })
                    .then(done)
                    .catch(function (error) {
                        button.disabled = false;
                        app.toast(error.message, 'error');
                    });
            } else {
                Marks.remove(surah, verse);
                done();
            }
        });

        // Guests: build the list from localStorage.
        var guestHost = document.querySelector('[data-guest-marks]');
        if (guestHost && !app.AUTHED) {
            var map = Marks.local();
            var keys = Object.keys(map).sort(function (a, b) {
                return (map[b].ts || 0) - (map[a].ts || 0);
            });

            var empty = document.querySelector('[data-guest-marks-empty]');
            if (!keys.length) {
                guestHost.hidden = true;
                if (empty) {
                    empty.hidden = false;
                }
            } else {
                if (empty) {
                    empty.hidden = true;
                }
                // Group by surah: one collapsible node per surah, newest first.
                var groupOrder = [];
                var groups = {};
                keys.forEach(function (key) {
                    var parts = key.split(':');
                    var surah = parts[0];
                    if (!groups[surah]) {
                        groups[surah] = { items: [] };
                        groupOrder.push(surah);
                    }
                    groups[surah].items.push({ key: key, verse: parts[1], mark: map[key] });
                });

                var html = '<p class="muted small">' + keys.length +
                    ' verse' + (keys.length === 1 ? '' : 's') + ' saved on this device</p><div class="saved-tree">';

                groupOrder.forEach(function (surah) {
                    html += '<details class="saved-group" open><summary>' +
                        '<span class="saved-group-num">' + surah + '</span>' +
                        '<span class="saved-group-title"><strong>Surah ' + surah + '</strong></span>' +
                        '<span class="saved-group-count">' + groups[surah].items.length + '</span>' +
                        '</summary><ol class="mark-list saved-group-items">';

                    groups[surah].items.forEach(function (entry) {
                        var mark = entry.mark;
                        var verse = entry.verse;
                        var highlight = mark.color ? ' data-highlight="' + app.escapeHtml(mark.color) + '"' : '';
                        var note = mark.note
                            ? '<p class="mark-note">' + app.escapeHtml(mark.note) + '</p>'
                            : '';
                        var label = mark.color
                            ? '<span class="visually-hidden">' + app.escapeHtml(colorName(mark.color)) + '</span>'
                            : '';
                        html += '<li class="mark" data-mark-item data-surah="' + surah + '" data-verse="' + verse + '"' + highlight + '>' +
                            '<div class="mark-head">' +
                                '<a class="mark-ref" href="' + app.BASE + '/surah.php?n=' + surah + '#verse-' + verse + '">' +
                                    '<span class="saved-verse-ref">' + surah + ':' + verse + '</span>' +
                                '</a>' +
                                '<div class="mark-actions">' + label +
                                    '<button type="button" class="icon-btn sm" data-mark-remove data-surah="' + surah +
                                        '" data-verse="' + verse + '" aria-label="Remove highlight and note">' +
                                        '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M5 7h14M9.5 7V5.5h5V7M7 7l.8 12.5h8.4L17 7"/></svg>' +
                                    '</button>' +
                                '</div>' +
                            '</div>' + note +
                        '</li>';
                    });

                    html += '</ol></details>';
                });

                html += '</div>';
                guestHost.innerHTML = html;
            }
        }

        // Keep the tab strip in step with the current anchor.
        var links = document.querySelectorAll('.saved-nav-link');
        if (links.length) {
            var sync = function () {
                var hash = window.location.hash === '#marks' ? '#marks' : '#bookmarks';
                Array.prototype.forEach.call(links, function (link) {
                    link.classList.toggle('is-active', link.getAttribute('href') === hash);
                });
            };
            sync();
            window.addEventListener('hashchange', sync);
        }
    }

    function boot() {
        initReader();
        initSavedMarks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
