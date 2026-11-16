<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Database;

final class ChapterRepository
{
    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::select(
            'SELECT id, name_arabic, name_simple, name_english, translated_name, revelation_place,
                    revelation_order, verses_count, bismillah_pre, pages_from, pages_to
             FROM chapters ORDER BY id'
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM chapters WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function previous(int $id): ?array
    {
        return Database::first('SELECT * FROM chapters WHERE id < ? ORDER BY id DESC LIMIT 1', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function next(int $id): ?array
    {
        return Database::first('SELECT * FROM chapters WHERE id > ? ORDER BY id ASC LIMIT 1', [$id]);
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM chapters');
    }

    /**
     * Chapters whose name matches the query (Arabic or Latin).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function search(string $query, int $limit = 8): array
    {
        $needle = '%' . self::escapeLike($query) . '%';

        return Database::select(
            'SELECT id, name_arabic, name_simple, name_english, translated_name, verses_count
             FROM chapters
             WHERE name_arabic LIKE ? OR name_simple LIKE ? OR name_english LIKE ? OR translated_name LIKE ?
             ORDER BY id LIMIT ' . max(1, min(50, $limit)),
            [$needle, $needle, $needle, $needle]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function juzList(): array
    {
        return Database::select(
            'SELECT j.number, j.first_surah, j.first_verse, j.last_surah, j.last_verse, j.verses_count,
                    c1.name_simple AS first_name, c2.name_simple AS last_name
             FROM juzs j
             LEFT JOIN chapters c1 ON c1.id = j.first_surah
             LEFT JOIN chapters c2 ON c2.id = j.last_surah
             ORDER BY j.number'
        );
    }

    /** @return array<string,mixed>|null */
    public static function juz(int $number): ?array
    {
        return Database::first(
            'SELECT j.*, c1.name_simple AS first_name, c2.name_simple AS last_name
             FROM juzs j
             LEFT JOIN chapters c1 ON c1.id = j.first_surah
             LEFT JOIN chapters c2 ON c2.id = j.last_surah
             WHERE j.number = ?',
            [$number]
        );
    }

    public static function maxPage(): int
    {
        return (int) Database::value('SELECT MAX(page) FROM verses');
    }

    public static function minPage(): int
    {
        return (int) Database::value('SELECT MIN(page) FROM verses WHERE page > 0');
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
