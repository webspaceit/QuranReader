<?php
declare(strict_types=1);

namespace Quran\Repositories;

use PDOException;
use Quran\Support\Database;

final class UserRepository
{
    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Database::first('SELECT * FROM users WHERE email = ? LIMIT 1', [self::normalizeEmail($email)]);
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        return Database::first(
            'SELECT id, email, full_name, created_at, last_login_at FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return int|null New user id, or null when the email is already taken.
     */
    public static function create(string $email, string $passwordHash, ?string $fullName): ?int
    {
        try {
            return Database::insert(
                'INSERT INTO users (email, password_hash, full_name) VALUES (?, ?, ?)',
                [self::normalizeEmail($email), $passwordHash, $fullName]
            );
        } catch (PDOException $e) {
            // 23000 = integrity constraint violation (duplicate email).
            if ($e->getCode() === '23000') {
                return null;
            }
            throw $e;
        }
    }

    public static function touchLogin(int $id): void
    {
        Database::execute('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public static function updateProfile(int $id, ?string $fullName): void
    {
        Database::execute('UPDATE users SET full_name = ? WHERE id = ?', [$fullName, $id]);
    }

    public static function emailExists(string $email): bool
    {
        return (int) Database::value('SELECT COUNT(*) FROM users WHERE email = ?', [self::normalizeEmail($email)]) > 0;
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }
}
