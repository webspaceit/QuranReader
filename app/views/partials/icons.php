<?php
/**
 * One SVG sprite for the whole site. Referenced with <use href="#i-name">,
 * which keeps long pages (Al-Baqarah has 286 verses) small: each icon becomes
 * about 40 bytes instead of a few hundred.
 *
 * Paths carry no fill/stroke of their own, so the referencing <svg> decides.
 */
?>
<svg class="svg-sprite" aria-hidden="true" focusable="false" width="0" height="0" style="position:absolute;overflow:hidden">
    <symbol id="i-logo" viewBox="0 0 32 32">
        <path d="M16 3.2l3.4 4.1 5.2.9-1.2 5.1 1.2 5.1-5.2.9L16 23.4l-3.4-4.1-5.2-.9 1.2-5.1-1.2-5.1 5.2-.9z"/>
        <path d="M16 9.5v10"/>
    </symbol>
    <symbol id="i-sun" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="4.2"/>
        <path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M18.7 5.3l-1.6 1.6M6.9 17.1l-1.6 1.6"/>
    </symbol>
    <symbol id="i-moon" viewBox="0 0 24 24">
        <path d="M20.5 14.3A8.6 8.6 0 0 1 9.7 3.5 8.8 8.8 0 1 0 20.5 14.3z"/>
    </symbol>
    <symbol id="i-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8.2" r="3.6"/>
        <path d="M4.8 20c.6-3.6 3.6-5.6 7.2-5.6s6.6 2 7.2 5.6"/>
    </symbol>
    <symbol id="i-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="6.5"/>
        <path d="M16 16l4.2 4.2"/>
    </symbol>
    <symbol id="i-back" viewBox="0 0 24 24">
        <path d="M14.5 5.5L8 12l6.5 6.5"/>
    </symbol>
    <symbol id="i-prev" viewBox="0 0 24 24">
        <path d="M15 6l-6 6 6 6"/>
    </symbol>
    <symbol id="i-next" viewBox="0 0 24 24">
        <path d="M9 6l6 6-6 6"/>
    </symbol>
    <symbol id="i-play" viewBox="0 0 24 24">
        <path d="M8 5.5v13l10-6.5z"/>
    </symbol>
    <symbol id="i-pause" viewBox="0 0 24 24">
        <path d="M7.5 5.5h3.2v13H7.5zM13.3 5.5h3.2v13h-3.2z"/>
    </symbol>
    <symbol id="i-bookmark" viewBox="0 0 24 24">
        <path d="M6.5 4.5h11v15l-5.5-3.8-5.5 3.8z"/>
    </symbol>
    <symbol id="i-copy" viewBox="0 0 24 24">
        <rect x="8.5" y="8.5" width="11" height="11" rx="2"/>
        <path d="M15.5 8.5v-2a2 2 0 0 0-2-2h-7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h2"/>
    </symbol>
    <symbol id="i-share" viewBox="0 0 24 24">
        <circle cx="17.5" cy="6" r="2.6"/>
        <circle cx="6.5" cy="12" r="2.6"/>
        <circle cx="17.5" cy="18" r="2.6"/>
        <path d="M8.8 10.8l6.4-3.4M8.8 13.2l6.4 3.4"/>
    </symbol>
    <symbol id="i-translate" viewBox="0 0 24 24">
        <path d="M4 6h9M8.5 4v2c0 3.4-2 6.3-4.5 7.5M6 10.5c1 2 2.6 3.4 4.5 4"/>
        <path d="M13 19l3.5-8L20 19M14.3 16.4h4.4"/>
    </symbol>
    <symbol id="i-trash" viewBox="0 0 24 24">
        <path d="M5 7h14M9.5 7V5.5h5V7M7 7l.8 12.5h8.4L17 7"/>
    </symbol>
    <symbol id="i-close" viewBox="0 0 24 24">
        <path d="M6 6l12 12M18 6L6 18"/>
    </symbol>
    <symbol id="i-highlight" viewBox="0 0 24 24">
        <path d="M4 20.5h16"/>
        <path d="M8.4 16.8L5.6 14l7.8-7.8 2.8 2.8z"/>
        <path d="M14.6 4.8l1.9-1.9 2.8 2.8-1.9 1.9"/>
    </symbol>
    <symbol id="i-note" viewBox="0 0 24 24">
        <path d="M5.5 4.5h9.5l4.5 4.5v10.5h-14z"/>
        <path d="M14.5 4.5V9H19"/>
        <path d="M8.5 13h7M8.5 16.2h4.5"/>
    </symbol>
    <symbol id="i-book" viewBox="0 0 24 24">
        <path d="M12 6.4c-1.8-1.2-4.1-1.7-6.5-1.5v12.3c2.4-.2 4.7.3 6.5 1.5 1.8-1.2 4.1-1.7 6.5-1.5V4.9c-2.4-.2-4.7.3-6.5 1.5z"/>
        <path d="M12 6.4v12.3"/>
    </symbol>
    <symbol id="i-arrow-right" viewBox="0 0 24 24">
        <path d="M5 12h13"/>
        <path d="M13 7l5 5-5 5"/>
    </symbol>
    <symbol id="i-check" viewBox="0 0 24 24">
        <path d="M5 12.6l4.4 4.4L19 7.4"/>
    </symbol>
    <symbol id="i-download" viewBox="0 0 24 24">
        <path d="M12 4v10"/>
        <path d="M8 10.5l4 4 4-4"/>
        <path d="M5 18.5h14"/>
    </symbol>
    <symbol id="i-wifi-off" viewBox="0 0 24 24">
        <path d="M3 4l18 16"/>
        <path d="M7.4 13.2a7.4 7.4 0 0 1 3.1-1.7M11.9 8.6a11 11 0 0 1 6.6 1.9"/>
        <path d="M5.2 10.3a11 11 0 0 1 2.6-1.6M16.7 5.9a11 11 0 0 1 2.1 1.1"/>
        <path d="M10.3 17.2a4 4 0 0 1 3.4 0M12 20.2h.01"/>
    </symbol>
</svg>
