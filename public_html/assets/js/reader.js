/**
 * Surah reader: verse-by-verse audio, bookmarks, copy/share, font size and
 * reading-progress tracking.
 *
 * The player owns a single <audio> element for the whole page, so playback
 * continues smoothly while the user scrolls.
 */
(function () {
    'use strict';

    var reader = document.querySelector('[data-reader]');
    if (!reader || !window.QuranApp) {
        return;
    }

    var app = window.QuranApp;
    var verses = Array.prototype.slice.call(reader.querySelectorAll('[data-verse-item]'));
    if (!verses.length) {
        return;
    }

    var player = reader.querySelector('[data-player]');
    var audio = player ? player.querySelector('[data-audio-element]') : null;
    var playToggle = player ? player.querySelector('[data-player-toggle]') : null;
    var prevBtn = player ? player.querySelector('[data-player-prev]') : null;
    var nextBtn = player ? player.querySelector('[data-player-next]') : null;
    var labelEl = player ? player.querySelector('[data-player-label]') : null;
    var progressEl = player ? player.querySelector('[data-player-progress]') : null;
    var timeEl = player ? player.querySelector('[data-player-time]') : null;
    var scrub = player ? player.querySelector('[data-player-scrub]') : null;
    var reciterSelect = player ? player.querySelector('[data-reciter-select]') : null;
    var closeBtn = player ? player.querySelector('[data-player-close]') : null;

    var reciters = {};
    try {
        reciters = JSON.parse(reader.getAttribute('data-reciters') || '{}') || {};
    } catch (e) {
        reciters = {};
    }

    var state = {
        index: -1,
        base: reader.getAttribute('data-audio-base') || '',
        reciter: reader.getAttribute('data-reciter') || '',
        errorShown: false
    };

    var surahNumber = Number(reader.getAttribute('data-surah')) || 1;
    var surahName = reader.getAttribute('data-surah-name') || '';

    // ------------------------------------------------------------------ audio
    function verseOf(index) {
        return verses[index] || null;
    }

    function audioUrl(index) {
        var el = verseOf(index);
        if (!el || !state.base) {
            return '';
        }
        return state.base + '/' + el.getAttribute('data-global') + '.mp3';
    }

    function openPlayer() {
        if (!player) {
            return;
        }
        player.hidden = false;
        document.body.classList.add('player-open');
        syncPlayerHeight();
    }

    /** Publish the measured player height so content is never covered. */
    function syncPlayerHeight() {
        if (!player || player.hidden) {
            return;
        }
        var height = Math.ceil(player.getBoundingClientRect().height);
        if (height > 0) {
            document.documentElement.style.setProperty('--player-h', height + 'px');
        }
    }

    if (player) {
        window.addEventListener('resize', syncPlayerHeight);
        window.addEventListener('orientationchange', syncPlayerHeight);
    }

    function highlight(index) {
        verses.forEach(function (el, i) {
            el.classList.toggle('is-playing', i === index);
            if (i === index) {
                el.setAttribute('aria-current', 'true');
            } else {
                el.removeAttribute('aria-current');
            }
        });
    }

    function clearHighlight() {
        verses.forEach(function (el) {
            el.classList.remove('is-playing');
            el.removeAttribute('aria-current');
        });
    }

    function updateLabel() {
        var el = verseOf(state.index);
        if (!el || !labelEl) {
            return;
        }
        labelEl.textContent = document.title.split('—')[0].trim() + ' · ' + surahNumber + ':' + el.getAttribute('data-verse');
        var number = Number(el.getAttribute('data-verse'));
        var total = verses.length;
        labelEl.title = 'Verse ' + number + ' of ' + total;
    }

    function playIndex(index, options) {
        options = options || {};
        if (index < 0 || index >= verses.length) {
            return;
        }

        var url = audioUrl(index);
        if (!url) {
            app.toast('Audio is not configured for this reciter.', 'error');
            return;
        }

        openPlayer();
        state.index = index;
        state.errorShown = false;
        retriedVerse = '';
        audio.src = url;
        updateLabel();
        highlight(index);

        var el = verseOf(index);
        if (el && options.scroll !== false) {
            var rect = el.getBoundingClientRect();
            if (rect.top < 80 || rect.bottom > window.innerHeight - 40) {
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        }

        prefetchNext();
        updateMediaSession();

        if (options.autoplay !== false) {
            var promise = audio.play();
            if (promise && promise.catch) {
                promise.catch(function () {
                    setPlayingVisual(false);
                    app.toast('Press play to start the recitation.', 'error');
                });
            }
        }
    }

    function setPlayingVisual(playing) {
        if (playToggle) {
            playToggle.classList.toggle('is-playing', playing);
            playToggle.setAttribute('aria-label', playing ? 'Pause' : 'Play');
        }
    }

    function togglePlayback() {
        if (!audio) {
            return;
        }
        if (state.index === -1) {
            playIndex(0);
            return;
        }
        if (audio.paused) {
            openPlayer();
            var promise = audio.play();
            if (promise && promise.catch) {
                promise.catch(function () {});
            }
        } else {
            audio.pause();
        }
    }

    function step(delta) {
        var next = state.index + delta;
        if (next < 0) {
            next = 0;
        }
        if (next >= verses.length) {
            return;
        }
        playIndex(next);
    }

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        var total = Math.floor(seconds);
        var minutes = Math.floor(total / 60);
        var rest = total % 60;
        return minutes + ':' + (rest < 10 ? '0' : '') + rest;
    }

    // ------------------------------------------------------ playback resilience
    // Phones stop recitation for reasons a desktop rarely shows: a dropped
    // mobile connection mid-verse, a suspended tab, a codec hiccup, or Safari
    // refusing an automatic play() without a fresh tap. Each of those used to
    // end the recitation silently; now they are detected and recovered, or the
    // bad verse is skipped so the session carries on.
    var prefetchEl = null;
    var stall = { timer: 0, lastTime: 0, idle: 0 };
    var retriedVerse = '';

    function prefetchNext() {
        var next = audioUrl(state.index + 1);
        if (!next) {
            return;
        }
        if (!prefetchEl) {
            prefetchEl = new Audio();
            prefetchEl.preload = 'auto';
        }
        if (prefetchEl.src !== next) {
            prefetchEl.src = next;
        }
    }

    function stopWatchdog() {
        if (stall.timer) {
            window.clearInterval(stall.timer);
            stall.timer = 0;
        }
        stall.idle = 0;
    }

    function startWatchdog() {
        stopWatchdog();
        stall.lastTime = audio.currentTime;
        stall.timer = window.setInterval(function () {
            if (audio.paused || audio.ended) {
                stall.idle = 0;
                stall.lastTime = audio.currentTime;
                return;
            }
            if (Math.abs(audio.currentTime - stall.lastTime) > 0.25) {
                stall.idle = 0;
                stall.lastTime = audio.currentTime;
                return;
            }
            stall.idle += 1;
            // Three quiet ticks (~15 s) while "playing" means the stream died.
            if (stall.idle >= 3) {
                stall.idle = 0;
                recoverPlayback();
            }
        }, 5000);
    }

    /** Reload the current verse once, then move on rather than stalling out. */
    function recoverPlayback() {
        if (state.index < 0) {
            return;
        }
        var key = String(state.index);
        if (retriedVerse !== key) {
            retriedVerse = key;
            var at = audio.currentTime;
            var url = audioUrl(state.index);
            audio.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'retry=1';
            audio.addEventListener('loadedmetadata', function once() {
                audio.removeEventListener('loadedmetadata', once);
                try { audio.currentTime = at; } catch (e) { /* seek may fail */ }
                var p = audio.play();
                if (p && p.catch) { p.catch(function () { /* user must tap */ }); }
            });
            audio.load();
            return;
        }
        if (state.index + 1 < verses.length) {
            app.toast('Skipping a verse the connection could not load.');
            playIndex(state.index + 1);
        } else {
            setPlayingVisual(false);
            app.toast('The recitation could not be loaded. Check your connection.', 'error');
        }
    }

    function updateMediaSession() {
        if (!('mediaSession' in navigator)) {
            return;
        }
        var el = verseOf(state.index);
        if (!el) {
            return;
        }
        try {
            navigator.mediaSession.metadata = new window.MediaMetadata({
                title: (document.title.split('—')[0] || '').trim() + ' ' + surahNumber + ':' + el.getAttribute('data-verse'),
                artist: state.reciterName || 'Quran Reader',
                album: document.title.split('—')[0].trim(),
            });
        } catch (e) { /* older browsers */ }
    }

    if (audio) {
        audio.addEventListener('play', function () {
            setPlayingVisual(true);
            startWatchdog();
            updateMediaSession();
        });
        audio.addEventListener('pause', function () {
            setPlayingVisual(false);
            stopWatchdog();
        });
        audio.addEventListener('playing', function () {
            setPlayingVisual(true);
            stopWatchdog();
            startWatchdog();
        });
        audio.addEventListener('waiting', function () {
            if (playToggle) {
                playToggle.classList.add('is-buffering');
            }
        });
        audio.addEventListener('canplay', function () {
            if (playToggle) {
                playToggle.classList.remove('is-buffering');
            }
        });
        audio.addEventListener('timeupdate', function () {
            if (!progressEl) {
                return;
            }
            var ratio = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
            progressEl.style.width = Math.min(100, Math.max(0, ratio)) + '%';
            if (timeEl) {
                timeEl.textContent = formatTime(audio.currentTime);
            }
            if (scrub) {
                scrub.setAttribute('aria-valuenow', String(Math.round(ratio)));
            }
        });
        audio.addEventListener('ended', function () {
            stopWatchdog();
            if (state.index + 1 < verses.length) {
                playIndex(state.index + 1, { scroll: true });
            } else {
                setPlayingVisual(false);
                clearHighlight();
            }
        });
        audio.addEventListener('error', function () {
            if (!audio.src) {
                return;
            }
            setPlayingVisual(false);
            stopWatchdog();
            // One automatic retry, then skip rather than stopping the session.
            recoverPlayback();
        });

        // Lock-screen / notification controls, and the reason Android keeps the
        // audio session alive when the reader is backgrounded.
        if ('mediaSession' in navigator) {
            var actions = {
                play: function () { togglePlayback(); },
                pause: function () { audio.pause(); },
                nexttrack: function () { step(1); },
                previoustrack: function () { step(-1); },
            };
            Object.keys(actions).forEach(function (name) {
                try {
                    navigator.mediaSession.setActionHandler(name, actions[name]);
                } catch (e) { /* unsupported action */ }
            });
        }

        window.addEventListener('pagehide', stopWatchdog);
    }

    if (playToggle) {
        playToggle.addEventListener('click', togglePlayback);
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', function () { step(-1); });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () { step(1); });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            if (audio) {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
            }
            player.hidden = true;
            document.body.classList.remove('player-open');
            clearHighlight();
            setPlayingVisual(false);
        });
    }
    if (scrub && audio) {
        scrub.addEventListener('click', function (event) {
            if (!audio.duration) {
                return;
            }
            var rect = scrub.getBoundingClientRect();
            var ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
            audio.currentTime = ratio * audio.duration;
        });
        scrub.addEventListener('keydown', function (event) {
            if (!audio.duration) {
                return;
            }
            if (event.key === 'ArrowRight') {
                audio.currentTime = Math.min(audio.duration, audio.currentTime + 5);
                event.preventDefault();
            } else if (event.key === 'ArrowLeft') {
                audio.currentTime = Math.max(0, audio.currentTime - 5);
                event.preventDefault();
            }
        });
    }

    if (reciterSelect) {
        reciterSelect.addEventListener('change', function () {
            var id = reciterSelect.value;
            if (reciters[id]) {
                state.base = reciters[id];
                state.reciter = id;
                app.store.setString('reciter', id);
                if (app.AUTHED) {
                    app.api('api/settings/save.php', { reciter_id: id }).catch(function () {});
                }
                if (state.index >= 0 && audio && !audio.paused) {
                    playIndex(state.index);
                } else if (state.index >= 0) {
                    updateLabel();
                }
            }
        });
    }

    // Keyboard shortcuts (ignored while typing).
    document.addEventListener('keydown', function (event) {
        var tag = (event.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target.isContentEditable) {
            return;
        }
        if (!player || player.hidden) {
            return;
        }
        if (event.code === 'Space') {
            event.preventDefault();
            togglePlayback();
        } else if (event.key === 'ArrowRight') {
            step(1);
        } else if (event.key === 'ArrowLeft') {
            step(-1);
        }
    });

    // ------------------------------------------------------------ verse actions
    function setBookmarkVisual(button, saved) {
        button.setAttribute('aria-pressed', saved ? 'true' : 'false');
        button.title = saved ? 'Remove bookmark' : 'Bookmark this verse';
        var svg = button.querySelector('svg');
        if (svg) {
            svg.setAttribute('fill', saved ? 'currentColor' : 'none');
        }
    }

    function updateCountPill(delta) {
        var pill = document.querySelector('[data-bookmark-count]');
        if (!pill) {
            return;
        }
        var current = parseInt(pill.textContent, 10) || 0;
        var next = Math.max(0, current + delta);
        pill.textContent = String(next);
        pill.hidden = next === 0;
    }

    function toggleBookmark(button, el) {
        var surah = Number(el.getAttribute('data-surah'));
        var verse = Number(el.getAttribute('data-verse'));
        var saved = button.getAttribute('aria-pressed') === 'true';
        var next = !saved;

        // Optimistic UI; rolled back if the request fails.
        setBookmarkVisual(button, next);
        updateCountPill(next ? 1 : -1);

        function rollback() {
            setBookmarkVisual(button, saved);
            updateCountPill(next ? -1 : 1);
        }

        if (app.AUTHED) {
            var path = next ? 'api/bookmarks/add.php' : 'api/bookmarks/remove.php';
            app.api(path, { surah: surah, verse: verse }).then(function () {
                app.toast(next ? 'Bookmark saved' : 'Bookmark removed');
            }).catch(function (error) {
                // No status means the request never reached the server (offline).
                // Keep the change on this device; the guest banner offers to
                // import it once the reader is back online.
                if (!error.status) {
                    if (next) {
                        app.bookmarks.addLocal(surah, verse);
                    } else {
                        app.bookmarks.removeLocal(surah, verse);
                    }
                    app.toast(next
                        ? 'Bookmark saved on this device — it will sync when you are back online'
                        : 'Bookmark removed on this device');
                    return;
                }
                rollback();
                app.toast(error.message, 'error');
            });
        } else {
            if (next) {
                app.bookmarks.addLocal(surah, verse);
            } else {
                app.bookmarks.removeLocal(surah, verse);
            }
            app.toast(next ? 'Bookmark saved on this device' : 'Bookmark removed');
        }
    }

    function verseText(el) {
        var arabic = el.querySelector('.verse-arabic');
        var translation = el.querySelector('.verse-translation');
        var reference = surahName + ' — Quran ' + surahNumber + ':' + el.getAttribute('data-verse');
        var text = '';
        if (arabic) {
            text += arabic.textContent.replace(/\s+/g, ' ').trim();
        }
        if (translation) {
            text += '\n\n' + translation.textContent.replace(/\s+/g, ' ').trim();
        }
        return text + '\n\n(' + reference + ')';
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.top = '-1000px';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            }
            document.body.removeChild(area);
        });
    }

    verses.forEach(function (el, index) {
        var bookmarkBtn = el.querySelector('[data-action="bookmark"]');
        var playBtn = el.querySelector('[data-action="play"]');
        var copyBtn = el.querySelector('[data-action="copy"]');
        var shareBtn = el.querySelector('[data-action="share"]');

        if (bookmarkBtn) {
            bookmarkBtn.addEventListener('click', function () {
                toggleBookmark(bookmarkBtn, el);
            });
        }
        if (playBtn) {
            playBtn.addEventListener('click', function () {
                playIndex(index);
            });
        }
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                copyText(verseText(el)).then(function () {
                    app.toast('Verse copied');
                }).catch(function () {
                    app.toast('Could not copy. Select the text manually.', 'error');
                });
            });
        }
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                var url = window.location.origin + window.location.pathname +
                    '?n=' + surahNumber + '#verse-' + el.getAttribute('data-verse');
                var shareData = {
                    title: surahName + ' ' + surahNumber + ':' + el.getAttribute('data-verse'),
                    text: verseText(el),
                    url: url
                };
                if (navigator.share) {
                    navigator.share(shareData).catch(function () { /* dismissed */ });
                } else {
                    copyText(url).then(function () {
                        app.toast('Link copied');
                    }).catch(function () {
                        app.toast('Sharing is not available in this browser.', 'error');
                    });
                }
            });
        }
    });

    // Bookmarks kept in localStorage also apply to signed-in readers: that is
    // where a change made offline waits until the guest banner imports it.
    verses.forEach(function (el) {
        var btn = el.querySelector('[data-action="bookmark"]');
        if (!btn) {
            return;
        }
        if (app.bookmarks.hasLocal(Number(el.getAttribute('data-surah')), Number(el.getAttribute('data-verse')))) {
            setBookmarkVisual(btn, true);
            el.setAttribute('data-saved', '1');
        }
    });

    // Play the whole surah from the top.
    var playSurahBtn = reader.querySelector('[data-play-surah]');
    if (playSurahBtn) {
        playSurahBtn.addEventListener('click', function () {
            playIndex(0);
        });
    }

    // Font size buttons
    reader.querySelectorAll('[data-font-size]').forEach(function (button) {
        button.addEventListener('click', function () {
            var size = button.getAttribute('data-font-size');
            app.applyFontSize(size);
            reader.querySelectorAll('[data-font-size]').forEach(function (other) {
                var active = other === button;
                other.classList.toggle('is-active', active);
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        });
    });

    // -------------------------------------------------------------- progress
    var lastRecorded = -1;
    var lastSent = 0;
    var pending = null;
    var ticking = false;

    function record(verseNumber) {
        pending = { surah: surahNumber, verse: verseNumber, ts: Date.now() };
        if (!app.AUTHED) {
            app.store.setJSON('progress', pending);
            return;
        }
        var now = Date.now();
        if (now - lastSent < 4000) {
            return;
        }
        lastSent = now;
        app.api('api/progress/save.php', { surah: surahNumber, verse: verseNumber }).catch(function () {});
    }

    function onScroll() {
        if (ticking) {
            return;
        }
        ticking = true;
        window.requestAnimationFrame(function () {
            ticking = false;
            var threshold = window.innerHeight * 0.42;
            var current = -1;
            for (var i = verses.length - 1; i >= 0; i--) {
                if (verses[i].getBoundingClientRect().top < threshold) {
                    current = i;
                    break;
                }
            }
            if (current >= 0 && current !== lastRecorded) {
                lastRecorded = current;
                record(Number(verses[current].getAttribute('data-verse')));
            }
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });

    function flush() {
        if (app.AUTHED && pending && navigator.sendBeacon) {
            var token = document.querySelector('meta[name="csrf-token"]');
            var body = new FormData();
            body.append('surah', pending.surah);
            body.append('verse', pending.verse);
            body.append('_csrf', token ? token.getAttribute('content') : '');
            navigator.sendBeacon(app.BASE + '/api/progress/save.php', body);
        }
    }

    window.addEventListener('pagehide', flush);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            flush();
        }
    });

    // Initialise from a #verse-N deep link.
    onScroll();
})();
