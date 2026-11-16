<?php
/**
 * Server-rendered sign-out (works without JavaScript).
 *
 * POST only, with a CSRF token, so a random link cannot sign visitors out.
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Quran\Auth\Auth;
use Quran\Support\Csrf;
use Quran\Support\Session;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Csrf::verifyRequest()) {
    redirect('account.php');
}

Auth::logout();
Session::flash('status', 'You have been signed out.');
redirect('');
