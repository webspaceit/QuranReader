<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Database;

final class BookmarkRepository
{
    /**
     * Bookmarks with the verse text and translation for display.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forUser(int $userId, ?string $translationId = null): array
    {
        return Database::select(
            'SELECT b.id, b.surah_number, b.verse_number, b.note, b.created_at,
                    v.text_uthmani, c.name_simple, c.name_arabic, c.verses_count,
                    vt.text AS translation_text
             FROM bookmarks b
             JOIN chapters c ON c.id = b.surah_number
             LEFT JOIN verses v ON v.surah_number = b.surah_number AND v.verse_number = b.verse_number
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC, b.id DESC',
            [$translationId ?? '', $userId]
        );
    }

    /** @return array<int,int> "surah:verse" keys, used to render the saved state. */
    public static function keysForUser(int $userId): array
    {
        $rows = Database::select('SELECT surah_number, verse_number FROM bookmarks WHERE user_id = ?', [$userId]);
        $out  = [];
        foreach ($rows as $row) {
            $out[] = (int) $row['surah_number'] . ':' . (int) $row['verse_number'];
        }
        return $out;
    }

    public static function exists(int $userId, int $surah, int $verse): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM bookmarks WHERE user_id = ? AND surah_number = ? AND verse_number = ?',
            [$userId, $surah, $verse]
        ) > 0;
    }

    public static function add(int $userId, int $surah, int $verse, ?string $note = null): bool
    {
        $affected = Database::execute(
            'INSERT INTO bookmarks (user_id, surah_number, verse_number, note)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE note = COALESCE(VALUES(note), note)',
            [$userId, $surah, $verse, $note]
        );

        return $affected > 0;
    }

    public static function remove(int $userId, int $surah, int $verse): bool
    {
        return Database::execute(
            'DELETE FROM bookmarks WHERE user_id = ? AND surah_number = ? AND verse_number = ?',
            [$userId, $surah, $verse]
        ) > 0;
    }

    public static function removeById(int $userId, int $id): bool
    {
        return Database::execute('DELETE FROM bookmarks WHERE user_id = ? AND id = ?', [$userId, $id]) > 0;
    }

    public static function countForUser(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM bookmarks WHERE user_id = ?', [$userId]);
    }

    /**
     * Import guest bookmarks collected in localStorage.
     *
     * @param array<int,array{surah:int,verse:int}> $items
     * @return int number of rows inserted
     */
    public static function importMany(int $userId, array $items, int $max = 500): int
    {
        $inserted = 0;
        $seen     = [];

        foreach (array_slice($items, 0, $max) as $item) {
            $surah = (int) ($item['surah'] ?? 0);
            $verse = (int) ($item['verse'] ?? 0);
            $key   = $surah . ':' . $verse;

            if ($surah < 1 || $surah > 114 || $verse < 1 || $verse > 300 || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $exists = (int) Database::value(
                'SELECT COUNT(*) FROM verses WHERE surah_number = ? AND verse_number = ?',
                [$surah, $verse]
            );
            if ($exists === 0) {
                continue;
            }

            $affected = Database::execute(
                'INSERT IGNORE INTO bookmarks (user_id, surah_number, verse_number) VALUES (?, ?, ?)',
                [$userId, $surah, $verse]
            );
            $inserted += $affected > 0 ? 1 : 0;
        }

        return $inserted;
    }
}
