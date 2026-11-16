<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Database;

/**
 * Verse highlights (one colour per verse) and verse notes (one note per verse).
 *
 * Both live in separate tables — `highlights` and `notes` — but they are shown,
 * exported and imported as a single "mark" per verse, because that is what the
 * reader edits. A verse may have a colour, a note, or both.
 */
final class MarkRepository
{
    /** Colours offered in the reader. Kept in sync with app.css and marks.js. */
    public const COLORS = ['yellow', 'green', 'blue', 'pink', 'purple'];

    public const MAX_NOTE_LENGTH = 1000;
    public const MAX_HIGHLIGHTS   = 5000;
    public const MAX_NOTES        = 2000;

    public static function isValidColor(string $color): bool
    {
        return in_array($color, self::COLORS, true);
    }

    // ---------------------------------------------------------------- reading

    /**
     * All marks of one user, newest first, with the verse text for display.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forUser(int $userId, ?string $translationId = null): array
    {
        return Database::select(
            'SELECT m.surah_number, m.verse_number, m.color, m.note, m.updated_at,
                    v.text_uthmani, c.name_simple, c.name_arabic,
                    vt.text AS translation_text
             FROM (
                 SELECT surah_number, verse_number, color, NULL AS note, updated_at
                 FROM highlights WHERE user_id = ?
                 UNION ALL
                 SELECT surah_number, verse_number, NULL AS color, note, updated_at
                 FROM notes WHERE user_id = ?
             ) AS m
             JOIN chapters c ON c.id = m.surah_number
             LEFT JOIN verses v ON v.surah_number = m.surah_number AND v.verse_number = m.verse_number
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             ORDER BY m.updated_at DESC, m.surah_number, m.verse_number',
            [$userId, $userId, $translationId ?? '']
        );
    }

    /**
     * Marks of one surah, indexed by verse number, used by the reader.
     *
     * @return array<int,array{color:?string,note:?string}>
     */
    public static function forSurah(int $userId, int $surah): array
    {
        $out = [];

        foreach (Database::select(
            'SELECT verse_number, color FROM highlights WHERE user_id = ? AND surah_number = ?',
            [$userId, $surah]
        ) as $row) {
            $verse = (int) $row['verse_number'];
            $out[$verse]['color'] = (string) $row['color'];
            $out[$verse]['note']  = $out[$verse]['note'] ?? null;
        }

        foreach (Database::select(
            'SELECT verse_number, note FROM notes WHERE user_id = ? AND surah_number = ?',
            [$userId, $surah]
        ) as $row) {
            $verse = (int) $row['verse_number'];
            $out[$verse]['note']  = (string) $row['note'];
            $out[$verse]['color'] = $out[$verse]['color'] ?? null;
        }

        ksort($out);

        return $out;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $userId, int $surah, int $verse): ?array
    {
        $color = Database::value(
            'SELECT color FROM highlights WHERE user_id = ? AND surah_number = ? AND verse_number = ?',
            [$userId, $surah, $verse]
        );
        $note = Database::value(
            'SELECT note FROM notes WHERE user_id = ? AND surah_number = ? AND verse_number = ?',
            [$userId, $surah, $verse]
        );

        if ($color === null && $note === null) {
            return null;
        }

        return [
            'surah' => $surah,
            'verse' => $verse,
            'color' => $color === null ? null : (string) $color,
            'note'  => $note === null ? null : (string) $note,
        ];
    }

    public static function countHighlights(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM highlights WHERE user_id = ?', [$userId]);
    }

    public static function countNotes(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notes WHERE user_id = ?', [$userId]);
    }

    // ---------------------------------------------------------------- writing

    /** Set or clear (null) the colour of a verse. */
    public static function setColor(int $userId, int $surah, int $verse, ?string $color): bool
    {
        if ($color === null) {
            return self::clearColor($userId, $surah, $verse);
        }

        return Database::execute(
            'INSERT INTO highlights (user_id, surah_number, verse_number, color)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE color = VALUES(color)',
            [$userId, $surah, $verse, $color]
        ) > 0;
    }

    public static function clearColor(int $userId, int $surah, int $verse): bool
    {
        return Database::execute(
            'DELETE FROM highlights WHERE user_id = ? AND surah_number = ? AND verse_number = ?',
            [$userId, $surah, $verse]
        ) > 0;
    }

    /** Set or clear (null) the note of a verse. */
    public static function setNote(int $userId, int $surah, int $verse, ?string $note): bool
    {
        if ($note === null || trim($note) === '') {
            return self::clearNote($userId, $surah, $verse);
        }

        return Database::execute(
            'INSERT INTO notes (user_id, surah_number, verse_number, note)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE note = VALUES(note)',
            [$userId, $surah, $verse, trim($note)]
        ) > 0;
    }

    public static function clearNote(int $userId, int $surah, int $verse): bool
    {
        return Database::execute(
            'DELETE FROM notes WHERE user_id = ? AND surah_number = ? AND verse_number = ?',
            [$userId, $surah, $verse]
        ) > 0;
    }

    public static function clearAll(int $userId, int $surah, int $verse): bool
    {
        $a = self::clearColor($userId, $surah, $verse);
        $b = self::clearNote($userId, $surah, $verse);

        return $a || $b;
    }

    /**
     * Merge marks collected while browsing as a guest.
     *
     * @param array<int,array{surah:int,verse:int,color?:?string,note?:?string}> $items
     * @return int number of verses that ended up with a mark
     */
    public static function importMany(int $userId, array $items, int $max = 500): int
    {
        $applied = 0;
        $seen    = [];

        foreach (array_slice($items, 0, $max) as $item) {
            $surah = (int) ($item['surah'] ?? 0);
            $verse = (int) ($item['verse'] ?? 0);
            $key   = $surah . ':' . $verse;

            if ($surah < 1 || $surah > 114 || $verse < 1 || $verse > 300 || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (!VerseRepository::exists($surah, $verse)) {
                continue;
            }

            $color = isset($item['color']) ? (string) $item['color'] : '';
            $note  = isset($item['note']) ? trim((string) $item['note']) : '';

            if ($color !== '' && self::isValidColor($color) && self::countHighlights($userId) < self::MAX_HIGHLIGHTS) {
                self::setColor($userId, $surah, $verse, $color);
                $applied++;
            }
            if ($note !== '' && self::countNotes($userId) < self::MAX_NOTES) {
                self::setNote($userId, $surah, $verse, mb_substr($note, 0, self::MAX_NOTE_LENGTH, 'UTF-8'));
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * Marks grouped for the "Notes & highlights" list: one entry per verse.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function groupRows(array $rows): array
    {
        $byVerse = [];
        foreach ($rows as $row) {
            $key = (int) $row['surah_number'] . ':' . (int) $row['verse_number'];
            if (!isset($byVerse[$key])) {
                $byVerse[$key] = [
                    'surah'       => (int) $row['surah_number'],
                    'verse'       => (int) $row['verse_number'],
                    'verse_key'   => $key,
                    'surah_name'  => (string) ($row['name_simple'] ?? ''),
                    'surah_arabic'=> (string) ($row['name_arabic'] ?? ''),
                    'arabic'      => (string) ($row['text_uthmani'] ?? ''),
                    'translation' => (string) ($row['translation_text'] ?? ''),
                    'color'       => null,
                    'note'        => null,
                    'updated_at'  => (string) $row['updated_at'],
                ];
            }
            if (!empty($row['color'])) {
                $byVerse[$key]['color'] = (string) $row['color'];
            }
            if (isset($row['note']) && $row['note'] !== null && $row['note'] !== '') {
                $byVerse[$key]['note'] = (string) $row['note'];
            }
            if ((string) $row['updated_at'] > $byVerse[$key]['updated_at']) {
                $byVerse[$key]['updated_at'] = (string) $row['updated_at'];
            }
        }

        $out = array_values($byVerse);
        usort($out, static fn (array $a, array $b) => strcmp($b['updated_at'], $a['updated_at']));

        return $out;
    }
}
