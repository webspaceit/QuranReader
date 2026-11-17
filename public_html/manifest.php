<?php
declare(strict_types=1);

/**
 * Web app manifest.
 *
 * Identical to manifest.webmanifest, except that the splash colours follow the
 * reader's theme. Chrome paints the launch splash from `background_color` before
 * any CSS runs, so a dark-mode reader opening the installed app would otherwise
 * get a cream flash and then a dark app.
 *
 * The theme preference lives in localStorage, which the server cannot see, so the
 * inline prepaint script mirrors it into a `quran_theme` cookie and this endpoint
 * reads that. No cookie (first visit) falls back to the light splash.
 */

require __DIR__ . '/_bootstrap.php';

$manifest = json_decode((string) file_get_contents(__DIR__ . '/manifest.webmanifest'), true);
if (!is_array($manifest)) {
    http_response_code(500);
    exit;
}

$theme = strtolower(trim((string) ($_COOKIE['quran_theme'] ?? '')));
$dark = $theme === 'dark';

$surface = $dark ? '#0f1512' : '#fbf9f4';
$manifest['background_color'] = $surface;
$manifest['theme_color'] = $surface;

header('Content-Type: application/manifest+json; charset=utf-8');
// Per-reader output: never let a proxy or the service worker hand one theme's
// splash to another.
header('Cache-Control: no-store, private');
header('Vary: Cookie');

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
