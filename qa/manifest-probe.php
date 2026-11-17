<?php
/**
 * Prints the manifest exactly as a browser with the given theme cookie would
 * receive it. The request harness cannot set cookies, and the splash colour is
 * the whole point of manifest.php, so it gets its own probe.
 *
 *   php qa/manifest-probe.php dark
 */
declare(strict_types=1);

$_COOKIE['quran_theme'] = (string) ($argv[1] ?? '');

require dirname(__DIR__) . '/public_html/manifest.php';
