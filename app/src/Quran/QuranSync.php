<?php
declare(strict_types=1);

namespace Quran\Quran;

use Quran\Support\Config;
use Quran\Support\Database;
use Quran\Support\Logger;
use Quran\Support\Text;

/**
 * Imports Quran content from the API into MySQL.
 *
 * The project intentionally does not ship the Quran text as source code: it is
 * fetched once (or refreshed on a schedule) and cached in the database, so the
 * reader, search and navigation stay fast and offline-friendly.
 *
 * Every write is an idempotent upsert, so re-running the importer is safe.
 */
final class QuranSync
{
    private QuranApiClient $api;

    /** @var callable|null */
    private $logger;

    private ?string $basmala = null;

    /** @var array<int,array<string,mixed>> */
    private array $stats = [
        'chapters'     => 0,
        'verses'       => 0,
        'translations' => 0,
        'juz'          => 0,
        'reciters'     => 0,
        'errors'       => [],
    ];

    public function __construct(QuranApiClient $api, ?callable $logger = null)
    {
        $this->api    = $api;
        $this->logger = $logger;
    }

    /**
     * @param array{surahs?:array<int,int>|null,translations?:array<int,string>|null,skip_chapters?:bool} $options
     * @return array<string,mixed>
     */
    public function run(array $options = []): array
    {
        $translations   = $options['translations'] ?? $this->configuredTranslations();
        $onlySurahs     = $options['surahs'] ?? null;
        $skipChapters   = (bool) ($options['skip_chapters'] ?? false);

        $runId = Database::insert('INSERT INTO sync_runs (task, status) VALUES (?, ?)', ['content', 'running']);

        try {
            $this->log('Preparing translation metadata...');
            $this->syncTranslations($translations);

            if (!$skipChapters) {
                $this->log('Importing chapter list...');
                $this->syncChapters();
            }

            $this->syncReciters();

            $numbers = $onlySurahs ?? range(1, 114);
            $total   = count($numbers);

            foreach ($numbers as $index => $number) {
                $this->log(sprintf('[%d/%d] Surah %d', $index + 1, $total, $number));
                try {
                    $this->syncSurah((int) $number, $translations);
                } catch (\Throwable $e) {
                    $this->stats['errors'][] = 'Surah ' . $number . ': ' . $e->getMessage();
                    $this->log('  ! ' . $e->getMessage());
                    Logger::error('Surah import failed', ['surah' => $number, 'error' => $e->getMessage()]);
                }
                // Be polite to the public API.
                usleep(120000);
            }

            $this->log('Recalculating juz boundaries and page ranges...');
            $this->rebuildJuzs();
            $this->rebuildChapterStats();

            $status  = $this->stats['errors'] === [] ? 'success' : 'failed';
            $message = sprintf(
                '%d chapters, %d verses, %d translation rows, %d juz, %d reciters, %d errors',
                $this->stats['chapters'],
                $this->stats['verses'],
                $this->stats['translations'],
                $this->stats['juz'],
                $this->stats['reciters'],
                count($this->stats['errors'])
            );

            Database::execute(
                'UPDATE sync_runs SET status = ?, message = ?, finished_at = UTC_TIMESTAMP() WHERE id = ?',
                [$status, mb_substr($message, 0, 5000), $runId]
            );

            $this->log('Done. ' . $message);

            return $this->stats;
        } catch (\Throwable $e) {
            Database::execute(
                'UPDATE sync_runs SET status = ?, message = ?, finished_at = UTC_TIMESTAMP() WHERE id = ?',
                ['failed', mb_substr($e->getMessage(), 0, 5000), $runId]
            );
            throw $e;
        }
    }

    /** @return array<int,string> */
    private function configuredTranslations(): array
    {
        $list = Config::get('content.translations', []);
        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }

    /** @param array<int,string> $identifiers */
    private function syncTranslations(array $identifiers): void
    {
        $catalog = $this->api->translationCatalog();
        $default = (string) Config::get('content.default_translation', '');

        foreach ($identifiers as $identifier) {
            $meta  = $catalog[$identifier] ?? null;
            // `name` is the short label ("Hilali & Khan"); `englishName` is the
            // translator's full name, used as the attribution line.
            $name  = is_array($meta) ? (string) ($meta['name'] ?? $identifier) : $identifier;
            $full  = is_array($meta) ? (string) ($meta['englishName'] ?? $name) : $name;
            $author = $full !== $name ? $full : '';
            $lang  = is_array($meta) ? (string) ($meta['language'] ?? 'en') : 'en';
            $dir   = in_array($lang, ['ar', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
            $langName = self::languageName($lang);

            Database::execute(
                'INSERT INTO translations (id, name, english_name, author_name, language_code, language_name, direction, is_default, synced_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), english_name = VALUES(english_name),
                     author_name = VALUES(author_name),
                     language_code = VALUES(language_code), language_name = VALUES(language_name),
                     direction = VALUES(direction), is_default = VALUES(is_default)',
                [$identifier, $name, $name, $author, $lang, $langName, $dir, $identifier === $default ? 1 : 0]
            );
        }
    }

    private function syncChapters(): void
    {
        $chapters   = $this->api->chapters();
        $enrichment = $this->api->chapterEnrichment();

        foreach ($chapters as $chapter) {
            $id       = (int) ($chapter['number'] ?? 0);
            if ($id < 1 || $id > 114) {
                continue;
            }
            $english  = (string) ($chapter['englishName'] ?? '');
            $subtitle = (string) ($chapter['englishNameTranslation'] ?? '');
            $arabic   = (string) ($chapter['name'] ?? '');
            $place    = strtolower((string) ($chapter['revelationType'] ?? 'Meccan')) === 'medinan' ? 'madinah' : 'makkah';
            $verses   = (int) ($chapter['numberOfAyahs'] ?? 0);

            $simple      = $english;
            $order       = 0;
            $bismillah   = $id === 9 ? 0 : 1;
            $pagesFrom   = 0;
            $pagesTo     = 0;

            if (isset($enrichment[$id])) {
                $extra = $enrichment[$id];
                $simple = (string) ($extra['name_simple'] ?? $simple);
                $order  = (int) ($extra['revelation_order'] ?? 0);
                $bismillah = !empty($extra['bismillah_pre']) && $id !== 9 ? 1 : 0;
                $pages  = $extra['pages'] ?? null;
                if (is_array($pages) && count($pages) === 2) {
                    $pagesFrom = (int) $pages[0];
                    $pagesTo   = (int) $pages[1];
                }
            }

            Database::execute(
                'INSERT INTO chapters (id, name_arabic, name_simple, name_english, translated_name, revelation_place,
                                       revelation_order, verses_count, bismillah_pre, pages_from, pages_to)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name_arabic = VALUES(name_arabic), name_simple = VALUES(name_simple),
                     name_english = VALUES(name_english), translated_name = VALUES(translated_name),
                     revelation_place = VALUES(revelation_place), revelation_order = VALUES(revelation_order),
                     verses_count = VALUES(verses_count),
                     bismillah_pre = IF(VALUES(bismillah_pre) = 1, 1, bismillah_pre),
                     pages_from = IF(VALUES(pages_from) > 0, VALUES(pages_from), pages_from),
                     pages_to = IF(VALUES(pages_to) > 0, VALUES(pages_to), pages_to)',
                [$id, $arabic, $simple, $english, $subtitle, $place, $order, $verses, $bismillah, $pagesFrom, $pagesTo]
            );

            $this->stats['chapters']++;
        }
    }

    /** @param array<int,string> $translations */
    private function syncSurah(int $number, array $translations): void
    {
        $payload = $this->api->surah($number, $translations);
        $ayahs   = $payload['arabic'];

        $chapter      = Database::first('SELECT id, bismillah_pre FROM chapters WHERE id = ?', [$number]);
        $stripBasmala = $chapter !== null && (int) $chapter['bismillah_pre'] === 1 && $number !== 1;
        $basmala      = $stripBasmala ? $this->basmala() : '';

        $verseRows = [];
        foreach ($ayahs as $ayah) {
            $global  = (int) ($ayah['number'] ?? 0);
            $inSurah = (int) ($ayah['numberInSurah'] ?? 0);
            if ($global <= 0 || $inSurah <= 0) {
                continue;
            }

            $text = self::cleanArabic((string) ($ayah['text'] ?? ''));
            if ($stripBasmala && $inSurah === 1) {
                $text = Text::stripBasmala($text, $basmala);
            }

            $verseRows[$inSurah] = [
                'id'        => $global,
                'surah'     => $number,
                'verse'     => $inSurah,
                'key'       => $number . ':' . $inSurah,
                'text'      => $text,
                'plain'     => Text::normalizeArabic($text),
                'juz'       => (int) ($ayah['juz'] ?? 0),
                'hizb'      => (int) ($ayah['hizbQuarter'] ?? 0),
                'page'      => (int) ($ayah['page'] ?? 0),
                'sajda'     => !empty($ayah['sajda']) ? 1 : 0,
            ];
        }

        if ($verseRows === []) {
            throw new \RuntimeException('No verses returned.');
        }

        $translationRows = [];
        foreach ($payload['translations'] as $identifier => $list) {
            foreach ($list as $ayah) {
                $inSurah = (int) ($ayah['numberInSurah'] ?? 0);
                if (!isset($verseRows[$inSurah])) {
                    continue;
                }
                $text = trim((string) ($ayah['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $translationRows[] = [
                    'verse_id'  => $verseRows[$inSurah]['id'],
                    'translation' => $identifier,
                    'text'      => $text,
                    'plain'     => Text::normalizeLatin(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                ];
            }
        }

        Database::transaction(static function () use ($verseRows, $translationRows): void {
            foreach (array_chunk($verseRows, 200) as $chunk) {
                $placeholders = [];
                $params       = [];
                foreach ($chunk as $row) {
                    $placeholders[] = '(?,?,?,?,?,?,?,?,?,?)';
                    array_push(
                        $params,
                        $row['id'], $row['surah'], $row['verse'], $row['key'], $row['text'],
                        $row['plain'], $row['juz'], $row['hizb'], $row['page'], $row['sajda']
                    );
                }
                Database::execute(
                    'INSERT INTO verses (id, surah_number, verse_number, verse_key, text_uthmani, text_plain, juz, hizb_quarter, page, sajda)
                     VALUES ' . implode(',', $placeholders) . '
                     ON DUPLICATE KEY UPDATE text_uthmani = VALUES(text_uthmani), text_plain = VALUES(text_plain),
                        juz = VALUES(juz), hizb_quarter = VALUES(hizb_quarter), page = VALUES(page), sajda = VALUES(sajda)',
                    $params
                );
            }

            foreach (array_chunk($translationRows, 200) as $chunk) {
                $placeholders = [];
                $params       = [];
                foreach ($chunk as $row) {
                    $placeholders[] = '(?,?,?,?)';
                    array_push($params, $row['verse_id'], $row['translation'], $row['text'], $row['plain']);
                }
                Database::execute(
                    'INSERT INTO verse_translations (verse_id, translation_id, text, text_plain)
                     VALUES ' . implode(',', $placeholders) . '
                     ON DUPLICATE KEY UPDATE text = VALUES(text), text_plain = VALUES(text_plain)',
                    $params
                );
            }
        });

        $this->stats['verses']       += count($verseRows);
        $this->stats['translations'] += count($translationRows);

        $this->log(sprintf('  - %d verses, %d translation rows', count($verseRows), count($translationRows)));
    }

    private function syncReciters(): void
    {
        $reciters = Config::get('content.reciters', []);
        if (!is_array($reciters)) {
            return;
        }

        $base = rtrim((string) Config::get('content.audio_base', 'https://cdn.islamic.network/quran/audio'), '/');
        $sort = 0;
        $hasDefault = (int) Database::value('SELECT COUNT(*) FROM reciters WHERE is_default = 1');

        foreach ($reciters as $id => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $sort++;
            $isDefault = !empty($meta['default']) && $hasDefault === 0 ? 1 : 0;
            $bitrate   = (int) ($meta['bitrate'] ?? 128);

            Database::execute(
                'INSERT INTO reciters (id, name, english_name, style, bitrate, audio_base, is_default, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), english_name = VALUES(english_name),
                     style = VALUES(style), bitrate = VALUES(bitrate), audio_base = VALUES(audio_base),
                     sort_order = VALUES(sort_order)',
                [
                    (string) $id,
                    (string) ($meta['name'] ?? $id),
                    (string) ($meta['english_name'] ?? $id),
                    (string) ($meta['style'] ?? 'Murattal'),
                    $bitrate,
                    $base . '/' . $bitrate . '/' . $id,
                    $isDefault,
                    $sort,
                ]
            );
            if ($isDefault === 1) {
                $hasDefault = 1;
            }
            $this->stats['reciters']++;
        }
    }

    private function rebuildJuzs(): void
    {
        Database::execute('DELETE FROM juzs');

        $rows = Database::select(
            'SELECT juz, COUNT(*) AS verses,
                    MIN(id) AS first_id, MAX(id) AS last_id
             FROM verses WHERE juz > 0 GROUP BY juz ORDER BY juz'
        );

        foreach ($rows as $row) {
            $first = Database::first('SELECT surah_number, verse_number FROM verses WHERE id = ?', [(int) $row['first_id']]);
            $last  = Database::first('SELECT surah_number, verse_number FROM verses WHERE id = ?', [(int) $row['last_id']]);
            if ($first === null || $last === null) {
                continue;
            }

            Database::execute(
                'INSERT INTO juzs (number, first_surah, first_verse, last_surah, last_verse, verses_count)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE first_surah = VALUES(first_surah), first_verse = VALUES(first_verse),
                     last_surah = VALUES(last_surah), last_verse = VALUES(last_verse), verses_count = VALUES(verses_count)',
                [
                    (int) $row['juz'],
                    (int) $first['surah_number'],
                    (int) $first['verse_number'],
                    (int) $last['surah_number'],
                    (int) $last['verse_number'],
                    (int) $row['verses'],
                ]
            );
            $this->stats['juz']++;
        }
    }

    private function rebuildChapterStats(): void
    {
        Database::execute(
            'UPDATE chapters c
             JOIN (
                SELECT surah_number, COUNT(*) AS total,
                       MIN(page) AS pfrom, MAX(page) AS pto
                FROM verses GROUP BY surah_number
             ) v ON v.surah_number = c.id
             SET c.verses_count = v.total,
                 c.pages_from = IF(v.pfrom > 0, v.pfrom, c.pages_from),
                 c.pages_to = IF(v.pto > 0, v.pto, c.pages_to)'
        );
    }

    /** Al-Fatihah 1:1 is the basmala; it is used to strip duplicated prefixes. */
    private function basmala(): string
    {
        if ($this->basmala !== null) {
            return $this->basmala;
        }

        $row = Database::first('SELECT text_uthmani FROM verses WHERE surah_number = 1 AND verse_number = 1');
        if ($row !== null) {
            return $this->basmala = self::cleanArabic((string) $row['text_uthmani']);
        }

        // First run: fetch Al-Fatihah once to learn the exact basmala spelling.
        $payload = $this->api->surah(1, []);
        $first   = $payload['arabic'][0]['text'] ?? '';
        return $this->basmala = self::cleanArabic((string) $first);
    }

    public static function cleanArabic(string $text): string
    {
        // Some editions prefix the first verse with a byte-order mark.
        $text = (string) preg_replace('/^\x{FEFF}/u', '', $text);
        $text = trim($text);
        return $text;
    }

    private static function languageName(string $code): string
    {
        $map = [
            'en' => 'English', 'ar' => 'Arabic', 'ur' => 'Urdu', 'bn' => 'Bengali',
            'id' => 'Indonesian', 'tr' => 'Turkish', 'fr' => 'French', 'es' => 'Spanish',
            'ru' => 'Russian', 'de' => 'German', 'zh' => 'Chinese', 'ms' => 'Malay',
            'fa' => 'Persian', 'hi' => 'Hindi', 'pt' => 'Portuguese', 'it' => 'Italian',
        ];
        return $map[$code] ?? strtoupper($code);
    }

    private function log(string $message): void
    {
        if (is_callable($this->logger)) {
            call_user_func($this->logger, $message);
        }
    }
}
