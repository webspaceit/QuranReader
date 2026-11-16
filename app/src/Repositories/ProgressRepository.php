<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Database;

final class ProgressRepository
{
    /** @return array<string,mixed>|null */
    public static function get(int $userId): ?array
    {
        return Database::first(
            'SELECT p.surah_number, p.verse_number, p.updated_at, c.name_simple, c.name_arabic
             FROM reading_progress p
             LEFT JOIN chapters c ON c.id = p.surah_number
             WHERE p.user_id = ?',
            [$userId]
        );
    }

    public static function save(int $userId, int $surah, int $verse): void
    {
        Database::execute(
            'INSERT INTO reading_progress (user_id, surah_number, verse_number)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE surah_number = VALUES(surah_number), verse_number = VALUES(verse_number)',
            [$userId, $surah, $verse]
        );
    }

    public static function clear(int $userId): void
    {
        Database::execute('DELETE FROM reading_progress WHERE user_id = ?', [$userId]);
    }
}
