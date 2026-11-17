<?php
/**
 * Prints the internal URL for login.php with APP_BASE forced, so the suite can
 * prove the override works without booting the whole app twice.
 *
 *   php qa/base-probe.php /        -> /login.php
 *   php qa/base-probe.php /quran   -> /quran/login.php
 */
declare(strict_types=1);

putenv('APP_BASE=' . ($argv[1] ?? '/'));
require dirname(__DIR__) . '/public_html/_bootstrap.php';

echo u('login.php');
