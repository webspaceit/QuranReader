<?php
declare(strict_types=1);

use Quran\Support\Config;

if (!defined('QURAN_APP')) {
    define('QURAN_APP', __DIR__);
    define('QURAN_ROOT', dirname(__DIR__));
    define('QURAN_STORAGE', QURAN_ROOT . '/storage');
}

require QURAN_APP . '/autoload.php';
require QURAN_APP . '/helpers.php';

Config::load(QURAN_ROOT);

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

// -----------------------------------------------------------------------------
// Error handling: never show stack traces or credentials to visitors.
// -----------------------------------------------------------------------------
$debug = Config::debug();

ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', QURAN_STORAGE . '/logs/php-error.log');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    // Deprecations never break a request; record them for the next upgrade.
    // (E_STRICT is not referenced: the constant is deprecated as of PHP 8.4.)
    if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
        \Quran\Support\Logger::warning('PHP deprecation: ' . $message, ['at' => $file . ':' . $line]);
        return true;
    }

    // In development, warnings become exceptions so bugs surface immediately.
    if (Config::debug()) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    \Quran\Support\Logger::warning('PHP warning: ' . $message, ['at' => $file . ':' . $line]);
    return true;
});

set_exception_handler(static function (\Throwable $e): void {
    \Quran\Support\ErrorHandler::handle($e);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        \Quran\Support\ErrorHandler::handle(
            new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
        );
    }
});

// -----------------------------------------------------------------------------
// Web context: session and security headers. CLI scripts skip this.
// -----------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    // Compress responses in PHP when the host has no mod_deflate. Apache's
    // deflate filter skips bodies that are already encoded, so this is safe
    // alongside .htaccess compression.
    if (!ini_get('zlib.output_compression')
        && function_exists('ob_gzhandler')
        && stripos((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip') !== false) {
        ob_start('ob_gzhandler');
    }

    \Quran\Support\Session::start();
    \Quran\Support\SecurityHeaders::send();
}
