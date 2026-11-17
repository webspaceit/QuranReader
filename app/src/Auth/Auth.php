<?php
declare(strict_types=1);

namespace Quran\Auth;

use Quran\Repositories\ProgressRepository;
use Quran\Repositories\SettingsRepository;
use Quran\Repositories\UserRepository;
use Quran\Support\Config;
use Quran\Support\Csrf;
use Quran\Support\Database;
use Quran\Support\Logger;
use Quran\Support\Session;

/**
 * Session based authentication.
 *
 * Passwords are hashed with password_hash() (bcrypt/argon2 depending on the
 * PHP build) and verified with password_verify(). The plain password is never
 * stored or logged.
 */
final class Auth
{
    private const SESSION_KEY = 'user_id';

    /** @var array<string,mixed>|null */
    private static ?array $cachedUser = null;

    private static bool $loaded = false;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$cachedUser;
        }
        self::$loaded = true;

        $id = Session::get(self::SESSION_KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return self::$cachedUser = null;
        }

        $user = UserRepository::findById((int) $id);
        if ($user === null) {
            Session::forget(self::SESSION_KEY);
            return self::$cachedUser = null;
        }

        return self::$cachedUser = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Super admin: the owner of the installation. Either the account carries
     * the is_admin flag, or its email matches app.admin_email — the second path
     * lets a hosting install nominate an owner from .env alone.
     */
    public static function isAdmin(): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        if ((int) ($user['is_admin'] ?? 0) === 1) {
            return true;
        }

        $owner = strtolower(trim((string) Config::get('app.admin_email', '')));
        return $owner !== '' && strtolower((string) $user['email']) === $owner;
    }

    /**
     * Verify credentials. Returns the user row on success.
     *
     * A dummy hash is verified when the email is unknown so that response
     * timing does not reveal whether an account exists.
     *
     * @return array<string,mixed>|null
     */
    public static function attempt(string $email, string $password): ?array
    {
        $user = UserRepository::findByEmail($email);

        if ($user === null) {
            // Equalise response time with the "existing account" path.
            password_verify($password, self::dummyHash());
            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        // Transparently upgrade hashes when PHP's default algorithm changes.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            UserRepository::updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        return $user;
    }

    /** Establish an authenticated session (prevents session fixation). */
    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, $userId);
        Csrf::rotate();
        UserRepository::touchLogin($userId);

        self::$cachedUser = UserRepository::findById($userId);
        self::$loaded     = true;
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        self::$cachedUser = null;
        self::$loaded     = true;
        Session::regenerate();
        Csrf::rotate();
    }

    /**
     * @return array{id:int}|null null when the email is already registered
     */
    public static function register(string $email, string $password, ?string $fullName): ?array
    {
        $fullName = $fullName === null ? null : trim($fullName);
        if ($fullName === '') {
            $fullName = null;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id   = UserRepository::create($email, $hash, $fullName);

        if ($id === null) {
            return null;
        }

        return ['id' => $id];
    }

    /**
     * A hash that is only used to keep failed logins as slow as successful
     * ones. Generated once per request, with the same cost as real hashes.
     */
    private static function dummyHash(): string
    {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        }
        return $hash;
    }

    public static function passwordIssues(string $password): array
    {
        $issues = [];
        if (mb_strlen($password, 'UTF-8') < 8) {
            $issues['password'] = 'Password must be at least 8 characters.';
        } elseif (mb_strlen($password, 'UTF-8') > 200) {
            $issues['password'] = 'Password must be 200 characters or fewer.';
        }
        return $issues;
    }

    // -------------------------------------------------------------------------
    // Password reset
    // -------------------------------------------------------------------------

    /** Create a single-use reset token. Returns the raw token (never stored). */
    public static function createResetToken(int $userId, int $ttlMinutes = 60): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        // Invalidate previous unused tokens for this user.
        Database::execute(
            'UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );

        Database::execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE), ?)',
            [$userId, $hash, max(5, $ttlMinutes), client_ip()]
        );

        return $token;
    }

    /** @return array<string,mixed>|null */
    public static function findValidReset(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $row = Database::first(
            'SELECT pr.*, u.email FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > UTC_TIMESTAMP()
             LIMIT 1',
            [hash('sha256', $token)]
        );

        return $row;
    }

    /** @return bool true when the password was changed. */
    public static function resetPassword(string $token, string $newPassword): bool
    {
        $row = self::findValidReset($token);
        if ($row === null) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        Database::transaction(static function () use ($row, $hash): void {
            UserRepository::updatePassword((int) $row['user_id'], $hash);
            Database::execute('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = ?', [(int) $row['id']]);
            Database::execute(
                'DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL',
                [(int) $row['user_id']]
            );
        });

        Logger::info('Password reset completed', ['user_id' => (int) $row['user_id']]);

        return true;
    }

    /** @return array{translation_id:string,reciter_id:string,arabic_font_size:string,theme:string} */
    public static function settings(): array
    {
        $id = self::id();
        return $id === null ? SettingsRepository::defaults() : SettingsRepository::get($id);
    }

    /** @return array<string,mixed>|null */
    public static function progress(): ?array
    {
        $id = self::id();
        return $id === null ? null : ProgressRepository::get($id);
    }
}
