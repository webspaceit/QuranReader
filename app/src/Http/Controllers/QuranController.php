<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Http\ApiException;
use Quran\Http\Request;
use Quran\Repositories\ChapterRepository;
use Quran\Repositories\TranslationRepository;
use Quran\Repositories\VerseRepository;
use Quran\Support\RateLimiter;
use Quran\Support\Text;

/**
 * Public Quran endpoints (no account required).
 */
final class QuranController
{
    public function search(): array
    {
        $ip     = Request::ip();
        $bucket = RateLimiter::hit('search', $ip, 120, 60);
        if (!$bucket['allowed']) {
            throw ApiException::tooManyRequests($bucket['retry_after']);
        }

        $query = Request::string('q');
        $query = mb_substr($query, 0, 120, 'UTF-8');

        if (mb_strlen(trim($query), 'UTF-8') < 2) {
            return [
                'query'      => $query,
                'total'      => 0,
                'results'    => [],
                'chapters'   => [],
                'limit'      => 20,
                'offset'     => 0,
                'message'    => 'Type at least two characters.',
            ];
        }

        $translationId = TranslationRepository::resolve(Request::string('translation_id'));
        $limit         = max(1, min(30, Request::int('limit', 20)));
        $offset        = max(0, min(2000, Request::int('offset', 0)));

        $surah = Request::int('surah', 0);
        $juz   = Request::int('juz', 0);
        $page  = Request::int('page', 0);

        $search = VerseRepository::search($query, [
            'translation_id' => $translationId,
            'surah'          => $surah > 0 ? $surah : null,
            'juz'            => $juz > 0 ? $juz : null,
            'page'           => $page > 0 ? $page : null,
            'limit'          => $limit,
            'offset'         => $offset,
        ]);

        $plainQuery = Text::containsArabic($query)
            ? Text::normalizeArabic($query)
            : Text::normalizeLatin($query);

        $results = [];
        foreach ($search['results'] as $row) {
            $translation  = (string) ($row['translation_text'] ?? '');
            $arabic       = (string) $row['text_uthmani'];
            $inArabic     = $plainQuery !== '' && mb_stripos(Text::normalizeArabic($arabic), $plainQuery) !== false;
            $preview      = Text::excerpt($inArabic ? $arabic : ($translation !== '' ? $translation : $arabic), 180);

            $results[] = [
                'verse_key'        => $row['surah_number'] . ':' . $row['verse_number'],
                'surah'            => (int) $row['surah_number'],
                'verse'            => (int) $row['verse_number'],
                'surah_name'       => (string) $row['name_simple'],
                'surah_name_arabic'=> (string) $row['name_arabic'],
                'arabic'           => $arabic,
                'translation'      => $translation,
                'preview'          => $preview,
                // Pre-escaped HTML with <mark> around the matches, so the client
                // can show the same highlighting as the server-rendered page.
                'arabic_html'      => Text::highlight($arabic, $query),
                'preview_html'     => Text::highlight($preview, $query),
                'matched'          => $inArabic ? 'arabic' : 'translation',
                'url'              => surah_url((int) $row['surah_number'], (int) $row['verse_number']),
            ];
        }

        $chapters = [];
        if ($surah === 0) {
            foreach (ChapterRepository::search($query, 5) as $chapter) {
                $chapters[] = [
                    'id'           => (int) $chapter['id'],
                    'name_simple'  => (string) $chapter['name_simple'],
                    'name_arabic'  => (string) $chapter['name_arabic'],
                    'translated'   => (string) $chapter['translated_name'],
                    'verses_count' => (int) $chapter['verses_count'],
                    'url'          => surah_url((int) $chapter['id']),
                ];
            }
        }

        return [
            'query'          => $query,
            'total'          => $search['total'],
            'results'        => $results,
            'chapters'       => $chapters,
            'limit'          => $limit,
            'offset'         => $offset,
            'translation_id' => $translationId,
        ];
    }

    /** Lightweight chapter list used by the quick-jump dialog. */
    public function chapters(): array
    {
        $out = [];
        foreach (ChapterRepository::all() as $chapter) {
            $out[] = [
                'id'           => (int) $chapter['id'],
                'name_simple'  => (string) $chapter['name_simple'],
                'name_arabic'  => (string) $chapter['name_arabic'],
                'translated'   => (string) $chapter['translated_name'],
                'verses_count' => (int) $chapter['verses_count'],
                'place'        => (string) $chapter['revelation_place'],
            ];
        }

        return ['chapters' => $out];
    }
}
