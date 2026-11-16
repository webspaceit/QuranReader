/* global window, document, navigator, caches, fetch, URL */
/**
 * Progressive web app plumbing.
 *
 * - registers the service worker (sw.js)
 * - drives the "Download all surahs" panel in Settings
 * - exposes the install prompt when the browser offers one
 *
 * The download is page-side on purpose: the reader can cancel it, watch the
 * progress and resume later, and nothing depends on the worker staying alive
 * for minutes.
 */
(function () {
    'use strict';

    var app = window.QuranApp;
    var BASE = app ? app.BASE : '';
    var TOTAL = 114;

    var supported = 'serviceWorker' in navigator && window.isSecureContext;
    var state = {
        registration: null,
        names: null,
        busy: false,
        cancel: false,
        installEvent: null
    };

    function scoped(path) {
        return BASE + '/' + String(path).replace(/^\/+/, '');
    }

    function surahUrl(number) {
        return scoped('surah.php?n=' + number);
    }

    // ------------------------------------------------------------- registration
    function register() {
        if (!supported) {
            return Promise.resolve(null);
        }
        return navigator.serviceWorker.register(scoped('sw.js'), { scope: BASE + '/' })
            .then(function (registration) {
                state.registration = registration;

                // A newer worker is waiting: offer a reload rather than swapping
                // silently underneath an open page.
                registration.addEventListener('updatefound', function () {
                    var worker = registration.installing;
                    if (!worker) {
                        return;
                    }
                    worker.addEventListener('statechange', function () {
                        if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                            app && app.toast('A new version is ready. Reload to update.');
                        }
                    });
                });

                return navigator.serviceWorker.ready;
            })
            .then(function () {
                return names();
            })
            .catch(function () {
                return null;
            });
    }

    /** Ask the active worker which caches it owns, so versions never drift. */
    function names() {
        if (state.names) {
            return Promise.resolve(state.names);
        }
        var worker = navigator.serviceWorker && navigator.serviceWorker.controller;
        if (!worker) {
            return Promise.resolve(null);
        }
        return new Promise(function (resolve) {
            var channel = new MessageChannel();
            var settled = false;
            channel.port1.onmessage = function (event) {
                settled = true;
                state.names = event.data || null;
                resolve(state.names);
            };
            worker.postMessage({ type: 'CACHE_NAMES' }, [channel.port2]);
            window.setTimeout(function () {
                if (!settled) {
                    resolve(null);
                }
            }, 1500);
        });
    }

    function pagesCache() {
        return names().then(function (list) {
            if (!list || !list.pages) {
                throw new Error('Service worker is not ready yet.');
            }
            return caches.open(list.pages);
        });
    }

    // ------------------------------------------------------------------- status
    function cachedSurahNumbers(cache) {
        return cache.keys().then(function (requests) {
            var found = {};
            requests.forEach(function (request) {
                var match = /surah\.php\?n=(\d+)/.exec(request.url);
                if (match) {
                    found[Number(match[1])] = true;
                }
            });
            return Object.keys(found).map(Number);
        });
    }

    function formatSize(bytes) {
        if (!bytes || bytes < 1024) {
            return '';
        }
        var mb = bytes / (1024 * 1024);
        return mb >= 10 ? mb.toFixed(0) + ' MB' : mb.toFixed(1) + ' MB';
    }

    function refresh(panel) {
        var status = panel.querySelector('[data-offline-status]');
        var downloadBtn = panel.querySelector('[data-offline-download]');
        var clearBtn = panel.querySelector('[data-offline-clear]');

        if (!supported) {
            if (status) {
                status.textContent = 'Offline reading needs HTTPS (or localhost) and a browser with service workers.';
            }
            if (downloadBtn) {
                downloadBtn.disabled = true;
            }
            return Promise.resolve();
        }

        return pagesCache().then(function (cache) {
            return cachedSurahNumbers(cache);
        }).then(function (numbers) {
            var stored = numbers.length;
            var storage = navigator.storage && navigator.storage.estimate
                ? navigator.storage.estimate().catch(function () { return null; })
                : Promise.resolve(null);

            return storage.then(function (estimate) {
                var size = estimate ? formatSize(estimate.usage) : '';
                if (status) {
                    status.textContent = stored === 0
                        ? 'No surahs downloaded yet.'
                        : stored + ' of ' + TOTAL + ' surahs available offline' + (size ? ' · ' + size + ' stored by this site' : '') + '.';
                }
                if (downloadBtn) {
                    downloadBtn.disabled = state.busy || stored >= TOTAL;
                    downloadBtn.textContent = stored >= TOTAL ? 'All surahs downloaded' : 'Download all surahs';
                }
                if (clearBtn) {
                    clearBtn.hidden = stored === 0;
                }
            });
        }).catch(function () {
            if (status) {
                status.textContent = 'Offline storage is not available in this browser.';
            }
        });
    }

    // ----------------------------------------------------------------- download
    function download(panel) {
        if (state.busy) {
            return;
        }
        state.busy = true;
        state.cancel = false;

        var bar = panel.querySelector('[data-offline-bar]');
        var wrap = panel.querySelector('[data-offline-progress]');
        var detail = panel.querySelector('[data-offline-detail]');
        var downloadBtn = panel.querySelector('[data-offline-download]');
        var clearBtn = panel.querySelector('[data-offline-clear]');
        var stopBtn = panel.querySelector('[data-offline-stop]');
        var status = panel.querySelector('[data-offline-status]');

        if (wrap) {
            wrap.hidden = false;
        }
        if (downloadBtn) {
            downloadBtn.disabled = true;
            downloadBtn.textContent = 'Downloading…';
        }
        if (clearBtn) {
            clearBtn.hidden = true;
        }
        if (stopBtn) {
            stopBtn.hidden = false;
        }
        if (bar) {
            bar.max = TOTAL;
            bar.value = 0;
        }

        if (navigator.storage && navigator.storage.persist) {
            // Ask the browser to keep the offline copy when it needs space.
            navigator.storage.persist().catch(function () {});
        }

        var done = 0;
        var failed = 0;

        function step(number) {
            if (state.cancel || number > TOTAL) {
                return Promise.resolve();
            }
            return fetch(surahUrl(number), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (response) {
                    if (!response || response.status !== 200) {
                        failed++;
                        return null;
                    }
                    return pagesCache().then(function (cache) {
                        return cache.put(surahUrl(number), response);
                    });
                })
                .catch(function () {
                    failed++;
                })
                .then(function () {
                    done++;
                    if (bar) {
                        bar.value = done;
                    }
                    if (detail) {
                        detail.textContent = done + ' of ' + TOTAL + (failed ? ' · ' + failed + ' failed' : '');
                    }
                    return step(number + 1);
                });
        }

        return step(1).then(function () {
            state.busy = false;
            if (detail) {
                detail.textContent = state.cancel
                    ? 'Download stopped after ' + done + ' surahs — you can resume any time.'
                    : done + ' of ' + TOTAL + ' surahs downloaded' + (failed ? ' · ' + failed + ' failed' : '') + '.';
            }
            if (app) {
                app.toast(state.cancel ? 'Download stopped' : 'Surahs available offline');
            }
            if (stopBtn) {
                stopBtn.hidden = true;
            }
            if (status) {
                status.textContent = '';
            }
            return refresh(panel);
        });
    }

    function clear(panel) {
        if (state.busy) {
            return;
        }
        state.busy = true;
        return pagesCache().then(function () {
            return names();
        }).then(function (list) {
            return caches.delete(list.pages);
        }).then(function () {
            state.busy = false;
            if (app) {
                app.toast('Offline copy removed');
            }
            var detail = panel.querySelector('[data-offline-detail]');
            var wrap = panel.querySelector('[data-offline-progress]');
            if (detail) {
                detail.textContent = '';
            }
            if (wrap) {
                wrap.hidden = true;
            }
            return refresh(panel);
        }).catch(function () {
            state.busy = false;
        });
    }

    // ------------------------------------------------------------ install prompt
    // Mirrors the oop-rms behaviour: a dismissible bar on first visit, an
    // explicit "Install app" button in Settings, and iOS instructions because
    // Safari never fires beforeinstallprompt.
    var DISMISS_KEY = 'quran.installDismissedAt';
    var DISMISS_DAYS = 14;

    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
    }

    function isIos() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent)
            || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    }

    function dismissedRecently() {
        var at = parseInt(window.localStorage.getItem(DISMISS_KEY) || '0', 10);
        return at > 0 && (Date.now() - at) < DISMISS_DAYS * 864e5;
    }

    function dismissBanner() {
        try {
            window.localStorage.setItem(DISMISS_KEY, String(Date.now()));
        } catch (error) { /* private mode */ }
        document.body.classList.remove('has-install-bar');
        var bar = document.getElementById('installBar');
        if (bar) {
            bar.remove();
        }
    }

    function showBanner() {
        if (isStandalone() || dismissedRecently() || document.getElementById('installBar')) {
            return;
        }

        var bar = document.createElement('div');
        bar.id = 'installBar';
        bar.className = 'install-bar';
        bar.setAttribute('role', 'dialog');
        bar.setAttribute('aria-label', 'Install the app');

        var icon = document.createElement('img');
        icon.src = scoped('assets/img/icon-192.png');
        icon.alt = '';
        icon.className = 'install-bar-icon';

        var text = document.createElement('div');
        text.className = 'install-bar-text';
        var strong = document.createElement('strong');
        var span = document.createElement('span');
        if (state.installEvent) {
            strong.textContent = 'Install the app';
            span.textContent = 'One-tap access to the reader, including offline surahs.';
        } else {
            strong.textContent = 'Add to Home Screen';
            span.textContent = 'Tap Share, then "Add to Home Screen".';
        }
        text.appendChild(strong);
        text.appendChild(span);

        var primary = document.createElement('button');
        primary.type = 'button';
        primary.className = 'install-bar-primary';
        primary.textContent = state.installEvent ? 'Install' : 'Got it';
        primary.addEventListener('click', function () {
            if (!state.installEvent) {
                dismissBanner();
                return;
            }
            state.installEvent.prompt();
            state.installEvent.userChoice.then(function () {
                state.installEvent = null;
                dismissBanner();
            }).catch(function () {});
        });

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'install-bar-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = '&times;';
        close.addEventListener('click', dismissBanner);

        bar.appendChild(icon);
        bar.appendChild(text);
        bar.appendChild(primary);
        bar.appendChild(close);
        document.body.appendChild(bar);
        document.body.classList.add('has-install-bar');
    }

    function initInstall(panel) {
        var button = panel ? panel.querySelector('[data-install-app]') : null;
        var hint = panel ? panel.querySelector('[data-install-hint]') : null;

        // Always offered: when the browser has no prompt we explain the manual
        // route instead of hiding the option.
        if (button) {
            button.hidden = false;
        }

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            state.installEvent = event;
            if (button) {
                button.hidden = false;
            }
            window.setTimeout(showBanner, 4000);
        });

        window.addEventListener('appinstalled', function () {
            state.installEvent = null;
            dismissBanner();
            if (app) {
                app.toast('Installed. Open it from your home screen.');
            }
        });

        if (button) {
            button.addEventListener('click', function () {
                if (state.installEvent) {
                    state.installEvent.prompt();
                    state.installEvent.userChoice.then(function () {
                        state.installEvent = null;
                    }).catch(function () {});
                    return;
                }
                if (hint) {
                    hint.hidden = false;
                    hint.textContent = isIos()
                        ? 'On iPhone or iPad: tap the Share button, then "Add to Home Screen".'
                        : 'In Chrome or Edge: open the browser menu and choose "Install app" (or "Add to Home screen").';
                } else {
                    showBanner();
                }
            });
        }

        // Safari never fires beforeinstallprompt, so offer the instructions
        // once the reader has had a look around.
        if (!isStandalone() && isIos()) {
            window.setTimeout(showBanner, 6000);
        }
    }
    function initPanel() {
        var panel = document.querySelector('[data-offline]');
        if (!panel) {
            return Promise.resolve();
        }

        var downloadBtn = panel.querySelector('[data-offline-download]');
        var clearBtn = panel.querySelector('[data-offline-clear]');
        var stopBtn = panel.querySelector('[data-offline-stop]');

        if (downloadBtn) {
            downloadBtn.addEventListener('click', function () {
                if (state.busy) {
                    state.cancel = true;
                    return;
                }
                download(panel);
            });
        }
        if (stopBtn) {
            stopBtn.addEventListener('click', function () {
                state.cancel = true;
            });
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                clear(panel);
            });
        }

        initInstall(panel);
        return refresh(panel);
    }

    function boot() {
        register().then(function () {
            return initPanel();
        });

        if (!document.querySelector('[data-offline]')) {
            initInstall(null);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
