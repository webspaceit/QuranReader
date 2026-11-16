#!/usr/bin/env php
<?php
/**
 * Small account utility for hosts where email is unreliable.
 *
 *   php bin/user.php stats
 *   php bin/user.php create user@example.com "a-long-password" "Full Name"
 *   php bin/user.php reset-link user@example.com     # prints a one-time reset URL
 *   php bin/user.php reset-password user@example.com "new-password"
 *   php bin/user.php list
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Quran\Auth\Auth;
use Quran\Repositories\BookmarkRepository;
use Quran\Repositories\UserRepository;
use Quran\Support\Database;

$command = $argv[1] ?? 'help';

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

switch ($command) {
    case 'stats':
        printf("Users:      %d\n", (int) Database::value('SELECT COUNT(*) FROM users'));
        printf("Bookmarks:  %d\n", (int) Database::value('SELECT COUNT(*) FROM bookmarks'));
        printf("Chapters:   %d\n", (int) Database::value('SELECT COUNT(*) FROM chapters'));
        printf("Verses:     %d\n", (int) Database::value('SELECT COUNT(*) FROM verses'));
        printf("Translations loaded: %d\n", (int) Database::value('SELECT COUNT(*) FROM translations'));
        $last = Database::first('SELECT status, finished_at, message FROM sync_runs ORDER BY id DESC LIMIT 1');
        if ($last) {
            printf("Last sync:  %s at %s\n", $last['status'], $last['finished_at'] ?? '—');
        }
        exit(0);

    case 'list':
        $users = Database::select('SELECT id, email, full_name, created_at, last_login_at FROM users ORDER BY id');
        if ($users === []) {
            echo "No users yet.\n";
            exit(0);
        }
        foreach ($users as $user) {
            printf(
                "#%d  %-32s %-24s joined %s  last login %s\n",
                (int) $user['id'],
                $user['email'],
                $user['full_name'] ?? '—',
                substr((string) $user['created_at'], 0, 10),
                $user['last_login_at'] ?? 'never'
            );
        }
        exit(0);

    case 'create':
        $email    = $argv[2] ?? '';
        $password = $argv[3] ?? '';
        $name     = $argv[4] ?? null;

        if ($email === '' || $password === '') {
            fail('Usage: php bin/user.php create email@example.com "password" ["Full Name"]');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            fail('That does not look like an email address.');
        }
        $issues = Auth::passwordIssues($password);
        if ($issues !== []) {
            fail(implode(' ', $issues));
        }
        if (UserRepository::emailExists($email)) {
            fail('An account with that email already exists.');
        }

        $result = Auth::register($email, $password, $name);
        if ($result === null) {
            fail('Could not create the account.');
        }
        echo "Account created with id {$result['id']}.\n";
        exit(0);

    case 'reset-link':
        $email = $argv[2] ?? '';
        $user  = UserRepository::findByEmail($email);
        if ($user === null) {
            fail('No account with that email.');
        }
        $token = Auth::createResetToken((int) $user['id'], 60);
        echo "One-time reset link (valid 60 minutes):\n";
        echo absolute_url('reset-password.php?token=' . urlencode($token)), "\n";
        exit(0);

    case 'reset-password':
        $email    = $argv[2] ?? '';
        $password = $argv[3] ?? '';
        $user     = UserRepository::findByEmail($email);
        if ($user === null) {
            fail('No account with that email.');
        }
        $issues = Auth::passwordIssues($password);
        if ($issues !== []) {
            fail(implode(' ', $issues));
        }
        UserRepository::updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        Database::execute('DELETE FROM password_resets WHERE user_id = ?', [(int) $user['id']]);
        echo "Password updated.\n";
        exit(0);

    case 'delete':
        $email = $argv[2] ?? '';
        $user  = UserRepository::findByEmail($email);
        if ($user === null) {
            fail('No account with that email.');
        }
        printf(
            "Deleted account #%d (%d bookmarks).\n",
            (int) $user['id'],
            BookmarkRepository::countForUser((int) $user['id'])
        );
        UserRepository::delete((int) $user['id']);
        exit(0);

    default:
        echo "Quran Reader — account utility\n\n";
        echo "  php bin/user.php stats\n";
        echo "  php bin/user.php list\n";
        echo "  php bin/user.php create email@example.com \"password\" [\"Full Name\"]\n";
        echo "  php bin/user.php reset-link email@example.com\n";
        echo "  php bin/user.php reset-password email@example.com \"new-password\"\n";
        echo "  php bin/user.php delete email@example.com\n";
        exit(0);
}
