/* eslint-env serviceworker */
/**
 * Quran Reader service worker.
 *
 * Three caches, all versioned together:
 *   static  — the app shell (CSS, JS, fonts, icons, offline page)
 *   pages   — rendered surah/other pages the reader has visited or downloaded
 *   api     — GET API replies (chapter list, search)
 *
 * Assets carry a ?v=<filemtime> query for cache busting, so lookups fall back
 * to ignoreSearch when the exact URL is not cached; that is what makes the
 * precached shell usable offline.
 *
 * Bump CACHE_VERSION when the shell changes shape. pages/caches are dropped on
 * activate, so readers re-download their offline pack after a big update.
 */
'use strict';

var CACHE_VERSION = 'v3';
var STATIC_CACHE = 'quran-static-' + CACHE_VERSION;
var PAGE_CACHE = 'quran-pages-' + CACHE_VERSION;
var API_CACHE = 'quran-api-' + CACHE_VERSION;
var CACHE_PREFIX = 'quran-';

/** Shell files, relative to the service-worker scope. */
var SHELL = [
    './',
    'offline.php',
    'settings.php',
    'manifest.webmanifest',
    'assets/css/app.css',
    'assets/css/sanctuary.css',
    'assets/css/reader-sanctuary.css',
    'assets/js/theme.js',
    'assets/js/app.js',
    'assets/js/pwa.js',
    'assets/js/marks.js',
    'assets/js/reader.js',
    'assets/js/search.js',
    'assets/js/typeahead.js',
    'assets/img/favicon.svg',
    'assets/img/icon-192.png',
    'assets/img/icon-512.png',
    'assets/fonts/amiri-quran-arabic.woff2',
    'assets/fonts/amiri-regular-arabic.woff2',
    'assets/fonts/amiri-bold-arabic.woff2',
    'assets/fonts/playfair-display-latin.woff2',
    'assets/fonts/plus-jakarta-sans-latin.woff2',
    'assets/fonts/hind-siliguri-bengali-400.woff2',
    'assets/fonts/hind-siliguri-bengali-600.woff2',
    'assets/fonts/hind-siliguri-bengali-700.woff2'
];

function scoped(path) {
    return new URL(path, self.registration.scope).toString();
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            // addAll() rejects on the first failure; request one by one so a
            // single missing optional file cannot break the install.
            return Promise.all(SHELL.map(function (path) {
                return cache.add(new Request(scoped(path), { cache: 'reload' })).catch(function () {
                    return null;
                });
            }));
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(names.map(function (name) {
                if (name.indexOf(CACHE_PREFIX) === 0 &&
                    [STATIC_CACHE, PAGE_CACHE, API_CACHE].indexOf(name) === -1) {
                    return caches.delete(name);
                }
                return null;
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

/** Cache a response only when it is a real, cacheable success. */
function cachePut(cacheName, request, response) {
    if (!response || response.status !== 200 || response.type === 'opaque') {
        return Promise.resolve(response);
    }
    var copy = response.clone();
    caches.open(cacheName).then(function (cache) {
        cache.put(request, copy).catch(function () { /* quota */ });
    });
    return response;
}

function networkFirst(request, cacheName, fallbackRequest) {
    return fetch(request).then(function (response) {
        return cachePut(cacheName, request, response);
    }).catch(function () {
        return caches.match(request).then(function (hit) {
            if (hit) {
                return hit;
            }
            return caches.match(request, { ignoreSearch: true }).then(function (loose) {
                if (loose) {
                    return loose;
                }
                if (fallbackRequest) {
                    return caches.match(fallbackRequest);
                }
                return Response.error();
            });
        });
    });
}

function cacheFirst(request) {
    return caches.match(request).then(function (hit) {
        if (hit) {
            return hit;
        }
        return fetch(request).then(function (response) {
            return cachePut(STATIC_CACHE, request, response);
        }).catch(function () {
            return caches.match(request, { ignoreSearch: true }).then(function (loose) {
                return loose || Response.error();
            });
        });
    });
}

self.addEventListener('fetch', function (event) {
    var request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    var url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Recitation audio is streamed; caching it is explicitly out of scope.
    if (/\.mp3$/i.test(url.pathname)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request, PAGE_CACHE, scoped('offline.php')));
        return;
    }

    if (url.pathname.indexOf('/api/') !== -1) {
        event.respondWith(networkFirst(request, API_CACHE));
        return;
    }

    event.respondWith(cacheFirst(request));
});

self.addEventListener('message', function (event) {
    var data = event.data || {};

    if (data.type === 'CACHE_NAMES') {
        if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({
                version: CACHE_VERSION,
                static: STATIC_CACHE,
                pages: PAGE_CACHE,
                api: API_CACHE
            });
        }
        return;
    }

    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
