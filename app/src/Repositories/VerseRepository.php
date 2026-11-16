<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Config;
use Quran\Support\Database;
use Quran\Support\Text;

final class VerseRepository
{
    /**
     * Verses of one surah, joined with the requested translation.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function bySurah(int $surah, ?string $translationId): array
    {
        $params = [$translationId ?? ''];
        $params[] = $surah;

        return Database::select(
            'SELECT v.id, v.surah_number, v.verse_number, v.verse_key, v.text_uthmani, v.page, v.juz, v.sajda,
                    vt.text AS translation_text
             FROM verses v
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             WHERE v.surah_number = ?
             ORDER BY v.verse_number',
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function byPage(int $page, ?string $translationId): array
    {
        return Database::select(
            'SELECT v.id, v.surah_number, v.verse_number, v.verse_key, v.text_uthmani, v.page, v.juz, v.sajda,
                    c.name_simple, c.name_english, c.translated_name, c.name_arabic, vt.text AS translation_text
             FROM verses v
             JOIN chapters c ON c.id = v.surah_number
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             WHERE v.page = ?
             ORDER BY v.id',
            [$translationId ?? '', $page]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function byJuz(int $juz, ?string $translationId): array
    {
        return Database::select(
            'SELECT v.id, v.surah_number, v.verse_number, v.verse_key, v.text_uthmani, v.page, v.juz, v.sajda,
                    c.name_simple, c.name_arabic, vt.text AS translation_text
             FROM verses v
             JOIN chapters c ON c.id = v.surah_number
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             WHERE v.juz = ?
             ORDER BY v.id',
            [$translationId ?? '', $juz]
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $surah, int $verse, ?string $translationId): ?array
    {
        return Database::first(
            'SELECT v.*, c.name_simple, c.name_arabic, vt.text AS translation_text
             FROM verses v
             JOIN chapters c ON c.id = v.surah_number
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             WHERE v.surah_number = ? AND v.verse_number = ?',
            [$translationId ?? '', $surah, $verse]
        );
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM verses');
    }

    public static function countBySurah(int $surah): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM verses WHERE surah_number = ?', [$surah]);
    }

    public static function exists(int $surah, int $verse): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM verses WHERE surah_number = ? AND verse_number = ?',
            [$surah, $verse]
        ) > 0;
    }

    /** Random verse, used for the home page "verse of the moment". */
    /** @return array<string,mixed>|null */
    public static function random(?string $translationId): ?array
    {
        $row = Database::first('SELECT id, surah_number, verse_number FROM verses ORDER BY RAND() LIMIT 1');
        if ($row === null) {
            return null;
        }
        return self::find((int) $row['surah_number'], (int) $row['verse_number'], $translationId);
    }

    /**
     * Search Arabic text and/or the selected translation.
     *
     * @param array{translation_id?:?string,surah?:?int,juz?:?int,page?:?int,limit?:int,offset?:int,order?:string} $options
     *        `order` is `relevance` (default) or `canonical`.
     * @return array{total:int,results:array<int,array<string,mixed>>}
     */
    public static function search(string $query, array $options = []): array
    {
        $raw      = trim($query);
        $limit    = max(1, min(50, (int) ($options['limit'] ?? 20)));
        $offset   = max(0, (int) ($options['offset'] ?? 0));
        $transId  = $options['translation_id'] ?? null;

        if ($raw === '') {
            return ['total' => 0, 'results' => []];
        }

        $arabic = Text::containsArabic($raw);
        $plain  = $arabic ? Text::normalizeArabic($raw) : Text::normalizeLatin($raw);
        if ($plain === '') {
            return ['total' => 0, 'results' => []];
        }

        $like = '%' . ChapterRepository::escapeLike($plain) . '%';

        $where  = ['(vt.text_plain LIKE ?' . ($arabic ? ' OR v.text_plain LIKE ?' : '') . ')'];
        $params = [$transId ?? '', $like];
        if ($arabic) {
            $params[] = $like;
        }

        if (!empty($options['surah'])) {
            $where[]  = 'v.surah_number = ?';
            $params[] = (int) $options['surah'];
        }
        if (!empty($options['juz'])) {
            $where[]  = 'v.juz = ?';
            $params[] = (int) $options['juz'];
        }
        if (!empty($options['page'])) {
            $where[]  = 'v.page = ?';
            $params[] = (int) $options['page'];
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) Database::value(
            'SELECT COUNT(*)
             FROM verses v
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             WHERE ' . $whereSql,
            $params
        );

        // Relevance: an exact translation match first, then verses whose
        // translation starts with the term, then verses containing it most
        // often; ties fall back to canonical (surah/verse) order so paging is
        // stable. The Arabic column has no useful length weighting here, so it
        // only contributes through the containment test.
        $orderSql    = 'v.id';
        $orderParams = [];
        if (($options['order'] ?? 'relevance') !== 'canonical') {
            $orderSql = 'CASE WHEN vt.text_plain = ? THEN 0 WHEN vt.text_plain LIKE ? THEN 1 ELSE 2 END,
                         (LENGTH(vt.text_plain) - LENGTH(REPLACE(vt.text_plain, ?, \'\'))) / LENGTH(?) DESC,
                         v.id';
            $orderParams = [$plain, $plain . '%', $plain, $plain];
        }

        $results = Database::select(
            'SELECT v.id, v.surah_number, v.verse_number, v.verse_key, v.text_uthmani,
                    c.name_simple, c.name_arabic, vt.text AS translation_text
             FROM verses v
             JOIN chapters c ON c.id = v.surah_number
             LEFT JOIN verse_translations vt ON vt.verse_id = v.id AND vt.translation_id = ?
             WHERE ' . $whereSql . '
             ORDER BY ' . $orderSql . '
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            array_merge($params, $orderParams)
        );

        return ['total' => $total, 'results' => $results];
    }
}
