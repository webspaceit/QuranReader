<?php
/**
 * Locates the application directory and boots it.
 *
 * Supported layouts:
 *   A) <root>/public_html/... + <root>/app/...     (recommended, app outside web root)
 *   B) public_html/index.php + public_html/app/... (everything in the web root)
 *
 * The QURAN_APP_DIR environment variable can point at the app directory when a
 * host uses a different structure.
 */
declare(strict_types=1);

$candidates = [];

$envDir = getenv('QURAN_APP_DIR');
if (is_string($envDir) && $envDir !== '') {
    $candidates[] = rtrim($envDir, '/\\');
}

$candidates[] = dirname(__DIR__) . '/app';
$candidates[] = __DIR__ . '/app';

foreach ($candidates as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
        return;
    }
}

http_response_code(500);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Setup required</title></head><body>';
echo '<h1>Application files not found</h1>';
echo '<p>The website could not locate the <code>app/bootstrap.php</code> file.</p>';
echo '<p>Upload the <code>app</code> directory next to <code>public_html</code> (recommended), '
    . 'or inside it, and reload this page. Full instructions are in README.md.</p>';
echo '</body></html>';
