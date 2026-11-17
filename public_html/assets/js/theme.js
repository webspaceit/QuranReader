/**
 * Applies the saved theme, Arabic text size and Quran typeface before first
 * paint, so there is no flash of the wrong colours or the wrong script.
 * Deliberately tiny and dependency-free.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var KEY_THEME = 'quran.theme';
    var KEY_SIZE = 'quran.fontSize';
    var KEY_FONT = 'quran.arabicFont';
    var SIZES = ['small', 'medium', 'large', 'xlarge'];
    var FONTS = ['amiri-quran', 'scheherazade', 'noto-naskh', 'nastaliq'];

    function meta(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    }

    function read(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function resolve(pref) {
        if (pref === 'light' || pref === 'dark') {
            return pref;
        }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    var authed = meta('authed') === '1';
    var storedTheme = read(KEY_THEME);
    var themePref = storedTheme || meta('server-theme') || 'system';

    var storedSize = read(KEY_SIZE);
    var size = storedSize || meta('server-font-size') || 'medium';
    if (SIZES.indexOf(size) === -1) {
        size = 'medium';
    }

    // Quran typeface: same precedence as the theme — this browser first, then
    // the account, then the default. Set before first paint to avoid a swap.
    var storedFont = read(KEY_FONT);
    var arabicFont = storedFont || meta('server-arabic-font') || 'noto-naskh';
    if (FONTS.indexOf(arabicFont) === -1) {
        arabicFont = 'noto-naskh';
    }

    root.setAttribute('data-theme', resolve(themePref));
    root.setAttribute('data-theme-preference', themePref);
    root.setAttribute('data-font-size', size);
    root.setAttribute('data-arabic-font', arabicFont);

    if (!storedTheme && authed && meta('server-theme')) {
        try {
            window.localStorage.setItem(KEY_THEME, meta('server-theme'));
        } catch (e) { /* storage may be disabled */ }
    }
    if (!storedSize && authed && meta('server-font-size')) {
        try {
            window.localStorage.setItem(KEY_SIZE, meta('server-font-size'));
        } catch (e) { /* ignore */ }
    }
    if (!storedFont && authed && meta('server-arabic-font')) {
        try {
            window.localStorage.setItem(KEY_FONT, meta('server-arabic-font'));
        } catch (e) { /* ignore */ }
    }

    // Follow the operating system while the preference is "system".
    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function () {
            if (root.getAttribute('data-theme-preference') === 'system') {
                root.setAttribute('data-theme', resolve('system'));
            }
        };
        if (mq.addEventListener) {
            mq.addEventListener('change', onChange);
        } else if (mq.addListener) {
            mq.addListener(onChange);
        }
    }
})();
