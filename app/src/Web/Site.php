<?php
declare(strict_types=1);

namespace Quran\Web;

use Quran\Auth\Auth;
use Quran\Repositories\BookmarkRepository;
use Quran\Repositories\ChapterRepository;
use Quran\Repositories\MarkRepository;
use Quran\Repositories\ProgressRepository;
use Quran\Repositories\ReciterRepository;
use Quran\Repositories\SettingsRepository;
use Quran\Repositories\TranslationRepository;
use Quran\Repositories\VerseRepository;
use Quran\Support\Config;
use Quran\Support\Csrf;
use Quran\Support\Session;
use Quran\Support\Text;

/**
 * Server-side rendering for the public pages.
 *
 * Pages are rendered on the server so the reader works on slow connections and
 * without JavaScript; the scripts in assets/js only enhance the experience.
 */
final class Site
{
    /** Surahs offered as quick links on the home page. */
    private const POPULAR = [1, 2, 3, 18, 36, 55, 56, 67, 112, 113, 114];

    // -------------------------------------------------------------------------
    // Shared context
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function context(array $extra = []): array
    {
        $settings = Auth::settings();
        $userId   = Auth::id();

        $defaults = [
            'appName'       => (string) Config::get('app.name', 'Quran Reader'),
            'pageTitle'     => '',
            'metaDescription' => 'Read the Quran online with Arabic text, trusted translations, recitation audio, search and bookmarks.',
            'canonical'     => absolute_url(),
            'currentUser'   => Auth::user(),
            'settings'      => $settings,
            'csrfToken'     => Csrf::token(),
            'bookmarkCount' => $userId === null ? 0 : BookmarkRepository::countForUser($userId),
            'flashStatus'   => Session::flash('status'),
            'flashError'    => Session::flash('error'),
            'activeNav'     => '',
            'bodyClass'     => '',
            'pageScripts'   => [],
        ];

        return array_merge($defaults, $extra);
    }

    /** @param array<string,mixed> $data */
    private static function render(string $template, array $data): void
    {
        render_page($template, self::context($data));
    }

    // -------------------------------------------------------------------------
    // Home
    // -------------------------------------------------------------------------

    public function home(): void
    {
        $chapters = ChapterRepository::all();
        $byId     = [];
        foreach ($chapters as $chapter) {
            $byId[(int) $chapter['id']] = $chapter;
        }

        $popular = [];
        foreach (self::POPULAR as $id) {
            if (isset($byId[$id])) {
                $popular[] = $byId[$id];
            }
        }

        $userId   = Auth::id();
        $progress = $userId === null ? null : ProgressRepository::get($userId);
        $settings = Auth::settings();

        $random = VerseRepository::random($settings['translation_id']);

        self::render('home', [
            'pageTitle'     => 'Read the Quran online — Arabic text, translations and recitation',
            'activeNav'     => 'home',
            'pageScripts'   => ['assets/js/typeahead.js'],
            'chapters'      => $chapters,
            'juzs'          => ChapterRepository::juzList(),
            'popular'       => $popular,
            'progress'      => $progress,
            'verseOfMoment' => $random,
            'translationId' => $settings['translation_id'],
            'translationCount' => count(TranslationRepository::all()),
        ]);
    }

    // -------------------------------------------------------------------------
    // Surah reader
    // -------------------------------------------------------------------------

    public function surah(int $number): void
    {
        $chapter = ChapterRepository::find($number);
        if ($chapter === null) {
            self::notFound('That surah does not exist.');
            return;
        }

        $settings      = Auth::settings();
        $requested     = isset($_GET['translation']) ? (string) $_GET['translation'] : null;
        $translationId = TranslationRepository::resolve($requested ?? $settings['translation_id']);
        $translation   = TranslationRepository::find($translationId);

        $verses   = VerseRepository::bySurah($number, $translationId);
        $userId   = Auth::id();
        $saved    = $userId === null ? [] : BookmarkRepository::keysForUser($userId);
        $marks    = $userId === null ? [] : MarkRepository::forSurah($userId, $number);

        $reciterId = ReciterRepository::resolve(
            isset($_GET['reciter']) ? (string) $_GET['reciter'] : $settings['reciter_id']
        );

        self::render('surah', [
            'pageTitle'     => $chapter['name_simple'] . ' (' . $chapter['name_arabic'] . ') — Surah ' . $number,
            'metaDescription' => sprintf(
                'Read Surah %s (%s), surah number %d with %d verses. Arabic text with %s translation.',
                $chapter['name_simple'],
                $chapter['translated_name'],
                $number,
                (int) $chapter['verses_count'],
                $translation['english_name'] ?? 'English'
            ),
            'canonical'     => absolute_url('surah.php?n=' . $number),
            'bodyClass'     => 'page-reader',
            'activeNav'     => 'read',
            'pageScripts'   => ['assets/js/reader.js', 'assets/js/marks.js'],
            'pageStyles'    => ['assets/css/reader-sanctuary.css'],
            'chapter'       => $chapter,
            'chapters'      => ChapterRepository::all(),
            'verses'        => $verses,
            'translation'   => $translation,
            'translations'  => TranslationRepository::all(),
            'reciters'      => ReciterRepository::all(),
            'reciter'       => ReciterRepository::find($reciterId),
            'prevChapter'   => ChapterRepository::previous($number),
            'nextChapter'   => ChapterRepository::next($number),
            'savedKeys'     => $saved,
            'marks'         => $marks,
            'markColors'    => MarkRepository::COLORS,
            'basmala'       => self::basmala($chapter),
            'progress'      => $userId === null ? null : ProgressRepository::get($userId),
        ]);
    }

    /** The basmala line is verse 1:1 of Al-Fatihah, so nothing is hardcoded. */
    private static function basmala(array $chapter): string
    {
        if ((int) $chapter['bismillah_pre'] !== 1 || (int) $chapter['id'] === 1) {
            return '';
        }

        static $cached = null;
        if ($cached === null) {
            $row    = \Quran\Support\Database::first(
                'SELECT text_uthmani FROM verses WHERE surah_number = 1 AND verse_number = 1'
            );
            $cached = $row === null ? '' : (string) $row['text_uthmani'];
        }

        return $cached;
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    public function search(): void
    {
        $query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $query = mb_substr($query, 0, 120, 'UTF-8');

        $settings      = Auth::settings();
        $translationId = TranslationRepository::resolve(
            isset($_GET['translation']) ? (string) $_GET['translation'] : $settings['translation_id']
        );

        $surahFilter = isset($_GET['surah']) ? (int) $_GET['surah'] : 0;
        $juzFilter   = isset($_GET['juz']) ? (int) $_GET['juz'] : 0;

        $result = ['total' => 0, 'results' => []];
        $chapterMatches = [];

        if (mb_strlen($query, 'UTF-8') >= 2) {
            $result = VerseRepository::search($query, [
                'translation_id' => $translationId,
                'surah'          => $surahFilter > 0 ? $surahFilter : null,
                'juz'            => $juzFilter > 0 ? $juzFilter : null,
                'limit'          => 30,
                'offset'         => 0,
            ]);
            $chapterMatches = ChapterRepository::search($query, 6);
        }

        self::render('search', [
            'pageTitle'     => $query === '' ? 'Search the Quran' : 'Search results for “' . $query . '”',
            'canonical'     => $query === '' ? absolute_url('search.php') : absolute_url('search.php?q=' . rawurlencode($query)),
            'activeNav'     => 'search',
            'pageScripts'   => ['assets/js/search.js'],
            'chapters'      => ChapterRepository::all(),
            'query'         => $query,
            'result'        => $result,
            'chapterMatches'=> $chapterMatches,
            'translation'   => TranslationRepository::find($translationId),
            'translations'  => TranslationRepository::all(),
            'surahFilter'   => $surahFilter,
            'juzFilter'     => $juzFilter,
        ]);
    }

    // -------------------------------------------------------------------------
    // Juz + Mushaf page navigation
    // -------------------------------------------------------------------------

    public function juzIndex(): void
    {
        self::render('juz-index', [
            'pageTitle' => 'Juz list — the 30 parts of the Quran',
            'canonical' => absolute_url('juz.php'),
            'activeNav' => 'read',
            'juzs'      => ChapterRepository::juzList(),
        ]);
    }

    public function juz(int $number): void
    {
        $juz = ChapterRepository::juz($number);
        if ($juz === null) {
            self::notFound('That juz does not exist.');
            return;
        }

        $settings      = Auth::settings();
        $translationId = TranslationRepository::resolve(
            isset($_GET['translation']) ? (string) $_GET['translation'] : $settings['translation_id']
        );

        self::render('juz', [
            'pageTitle'    => 'Juz ' . $number . ' — Quran',
            'canonical'    => absolute_url('juz.php?n=' . $number),
            'activeNav'    => 'read',
            'juz'          => $juz,
            'verses'       => VerseRepository::byJuz($number, $translationId),
            'translation'  => TranslationRepository::find($translationId),
            'translations' => TranslationRepository::all(),
            'prevJuz'      => $number > 1 ? $number - 1 : null,
            'nextJuz'      => $number < 30 ? $number + 1 : null,
        ]);
    }

    public function mushaf(): void
    {
        $max  = ChapterRepository::maxPage();
        $min  = max(1, ChapterRepository::minPage());
        $max  = $max > 0 ? $max : 604;

        $requested = isset($_GET['n']) ? (int) $_GET['n'] : null;
        if ($requested !== null && ($requested < $min || $requested > $max)) {
            self::notFound('That page is outside the 604-page Mushaf.');
            return;
        }

        $page = $requested ?? $min;

        $settings      = Auth::settings();
        $translationId = TranslationRepository::resolve(
            isset($_GET['translation']) ? (string) $_GET['translation'] : $settings['translation_id']
        );

        $verses = VerseRepository::byPage($page, $translationId);

        // Show the basmala above the first verse when a page opens a surah.
        $basmala = '';
        if ($verses !== [] && (int) $verses[0]['verse_number'] === 1) {
            $chapter = ChapterRepository::find((int) $verses[0]['surah_number']);
            if ($chapter !== null) {
                $basmala = self::basmala($chapter);
            }
        }

        self::render('mushaf', [
            'pageTitle'    => 'Mushaf page ' . $page,
            'canonical'    => absolute_url('page.php?n=' . $page),
            'activeNav'    => 'read',
            'page'         => $page,
            'maxPage'      => $max,
            'minPage'      => $min,
            'verses'       => $verses,
            'basmala'      => $basmala,
            'translation'  => TranslationRepository::find($translationId),
            'translations' => TranslationRepository::all(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Reciters & audio
    // -------------------------------------------------------------------------

    public function reciters(): void
    {
        $all   = ReciterRepository::all();
        $q     = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $style = isset($_GET['style']) ? trim((string) $_GET['style']) : '';
        $style = mb_substr($style, 0, 50);

        $styles = [];
        foreach ($all as $reciter) {
            $label = (string) $reciter['style'];
            if ($label !== '') {
                $styles[$label] = ($styles[$label] ?? 0) + 1;
            }
        }

        $needle = mb_strtolower(mb_substr($q, 0, 100));
        $list   = array_values(array_filter($all, static function (array $reciter) use ($needle, $style): bool {
            if ($style !== '' && (string) $reciter['style'] !== $style) {
                return false;
            }
            if ($needle === '') {
                return true;
            }
            $haystack = mb_strtolower(
                $reciter['english_name'] . ' ' . $reciter['name'] . ' ' . $reciter['id'] . ' ' . $reciter['style']
            );
            return str_contains($haystack, $needle);
        }));

        $featured = null;
        foreach ($all as $reciter) {
            if ((int) $reciter['is_default'] === 1) {
                $featured = $reciter;
                break;
            }
        }
        $featured ??= $all[0] ?? null;

        $settings = Auth::settings();
        $sample   = VerseRepository::random($settings['translation_id']);
        $host     = parse_url((string) ($featured['audio_base'] ?? (string) Config::get('content.audio_base', ''))) ?: [];

        self::render('reciters', [
            'pageTitle'       => 'Reciters & audio — verse-by-verse recitation',
            'metaDescription' => 'Listen to the Quran recited verse by verse by ' . count($all)
                . ' reciters, streamed per ayah and cached for offline reading.',
            'canonical'       => absolute_url('reciters.php'),
            'activeNav'       => 'audio',
            'bodyClass'       => 'page-audio',
            'reciters'        => $list,
            'totalReciters'   => count($all),
            'styles'          => $styles,
            'query'           => $q,
            'activeStyle'     => $style,
            'featured'        => $featured,
            'sampleVerse'     => $sample,
            'audioHost'       => (string) ($host['host'] ?? 'cdn.islamic.network'),
            'totalVerses'     => VerseRepository::count(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Account area
    // -------------------------------------------------------------------------

    public function bookmarks(): void
    {
        $userId = Auth::id();
        $settings = Auth::settings();
        $translationId = TranslationRepository::resolve($settings['translation_id']);

        self::render('bookmarks', [
            'pageTitle'    => 'My bookmarks',
            'canonical'    => absolute_url('bookmarks.php'),
            'activeNav'    => 'bookmarks',
            'items'        => $userId === null ? [] : BookmarkRepository::forUser($userId, $translationId),
            'marks'        => $userId === null ? [] : MarkRepository::groupRows(
                MarkRepository::forUser($userId, $translationId)
            ),
            'markColors'   => MarkRepository::COLORS,
            'translation'  => TranslationRepository::find($translationId),
            'translations' => TranslationRepository::all(),
            'pageScripts'  => ['assets/js/marks.js'],
        ]);
    }

    public function settings(): void
    {
        self::render('settings', [
            'pageTitle'    => 'Settings',
            'canonical'    => absolute_url('settings.php'),
            'activeNav'    => 'settings',
            'translations' => TranslationRepository::all(),
            'reciters'     => ReciterRepository::all(),
            'fontSizes'    => SettingsRepository::FONT_SIZES,
            'themes'       => SettingsRepository::THEMES,
        ]);
    }

    public function about(): void
    {
        $translations = TranslationRepository::all();
        $reciters     = ReciterRepository::all();

        self::render('about', [
            'pageTitle'    => 'About this project',
            'canonical'    => absolute_url('about.php'),
            'activeNav'    => 'about',
            'translations' => $translations,
            'reciters'     => $reciters,
            'verseCount'   => VerseRepository::count(),
            'chapterCount' => ChapterRepository::count(),
        ]);
    }

    public function account(): void
    {
        if (!Auth::check()) {
            redirect('login.php');
        }

        self::render('account', [
            'pageTitle'    => 'Your account',
            'canonical'    => absolute_url('account.php'),
            'activeNav'    => 'account',
            'userRow'      => Auth::user(),
            // The dashboard carries the same preferences form as settings.php.
            'translations' => TranslationRepository::all(),
            'reciters'     => ReciterRepository::all(),
            'fontSizes'    => SettingsRepository::FONT_SIZES,
            'themes'       => SettingsRepository::THEMES,
        ]);
    }

    // -------------------------------------------------------------------------
    // Authentication pages
    // -------------------------------------------------------------------------

    public function login(): void
    {
        if (Auth::check()) {
            redirect('account.php');
        }

        self::render('auth/login', [
            'pageTitle' => 'Sign in',
            'canonical' => absolute_url('login.php'),
            'activeNav' => 'account',
            'redirect'  => safe_redirect_path(isset($_GET['next']) ? (string) $_GET['next'] : '', ''),
        ]);
    }

    public function register(): void
    {
        if (Auth::check()) {
            redirect('account.php');
        }

        self::render('auth/register', [
            'pageTitle' => 'Create an account',
            'canonical' => absolute_url('register.php'),
            'activeNav' => 'account',
        ]);
    }

    public function forgotPassword(): void
    {
        self::render('auth/forgot', [
            'pageTitle' => 'Reset your password',
            'canonical' => absolute_url('forgot-password.php'),
            'activeNav' => 'account',
        ]);
    }

    public function resetPassword(): void
    {
        $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
        $reset = $token !== '' ? Auth::findValidReset($token) : null;

        self::render('auth/reset', [
            'pageTitle' => 'Choose a new password',
            'canonical' => absolute_url('reset-password.php'),
            'activeNav' => 'account',
            'token'     => $token,
            'valid'     => $reset !== null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    public function notFound(string $message = 'The page you are looking for could not be found.'): void
    {
        http_response_code(404);
        self::render('errors/404', [
            'pageTitle' => 'Page not found',
            'message'   => $message,
        ]);
    }
}
