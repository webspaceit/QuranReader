<?php
/**
 * End-to-end test suite. Runs the real application code (pages + API + MySQL)
 * through qa/req.php, so no web server has to be started.
 *
 *   php qa/run-tests.php
 */
declare(strict_types=1);

$root   = dirname(__DIR__);
$php    = PHP_BINARY;
$sid    = 'qa' . bin2hex(random_bytes(6));
$pass   = 0;
$fail   = 0;
$notes  = [];

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------

function call(string $method, string $path, array $body = [], ?string $csrf = null, int $attempt = 1): array
{
    global $php, $sid;

    $payload = $body;

    // proc_open with an argument array bypasses cmd.exe, so Windows percent
    // expansion cannot mangle URL-encoded Arabic in the query string.
    $args = [
        $php,
        __DIR__ . '/req.php',
        $method,
        $path,
        $body === [] ? '{}' : json_encode($body, JSON_UNESCAPED_UNICODE),
        $sid,
        $csrf ?? '',
    ];
    $proc = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return ['status' => 0, 'body' => 'proc_open failed', 'json' => null];
    }
    $raw  = (string) stream_get_contents($pipes[1]);
    $err  = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    $status = 0;
    $headers = [];
    if (preg_match_all('/^<<<H:(.+)>>>\r?\n/m', $raw, $hm)) {
        $headers = $hm[1];
    }
    if (preg_match('/^<<<STATUS:(\d{3})>>>\r?\n/m', $raw, $m)) {
        $status = (int) $m[1];
    }
    if (preg_match('/^<<<SID:([^>]*)>>>\r?\n/m', $raw, $m)) {
        if ($m[1] !== '') {
            $sid = $m[1];
        }
    }
    $body = $raw;
    if (($pos = strpos($raw, "<<<HEADERS-END>>>\n")) !== false) {
        $body = substr($raw, $pos + strlen("<<<HEADERS-END>>>\n"));
    }
    if ($body === '' && $err !== '') {
        $body = $err;
    }
    // Windows occasionally fails to create the PHP session mutex when many CGI
    // processes start in quick succession ("Cannot create mutex (error 5)").
    // The request never reaches the app in that case, so retry it once.
    $mutexFailure = str_contains($raw, 'Cannot create mutex') || str_contains($err, 'Cannot create mutex');
    if ($attempt === 1 && ($status === 0 || $mutexFailure)) {
        usleep(200000);
        return call($method, $path, $payload, $csrf, 2);
    }

    return [
        'status'  => $status,
        'body'    => $body,
        'headers' => implode("\n", $headers),
        'json'    => json_decode($body, true),
    ];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $notes;
    if ($ok) {
        $pass++;
        echo "  PASS  $label\n";
    } else {
        $fail++;
        $notes[] = $label;
        echo "  FAIL  $label" . ($detail !== '' ? "\n        → " . substr(str_replace("\n", ' ', $detail), 0, 300) : '') . "\n";
    }
}

function csrfOf(string $html): ?string
{
    return preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m) ? $m[1] : null;
}

function countOf(string $haystack, string $needle): int
{
    return substr_count($haystack, $needle);
}

$db = new PDO('mysql:host=127.0.0.1;port=3306;dbname=quran_website;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec('DELETE FROM rate_limits');
$db->exec("DELETE FROM users WHERE email LIKE '%@example.test'");

$start = microtime(true);

// ---------------------------------------------------------------------------
echo "\n== Pages ==\n";
$r   = call('GET', '/');
$tok = csrfOf($r['body']);
check('home returns 200', $r['status'] === 200, (string) $r['status']);
check('home lists surahs (Al-Fatihah … An-Nas)', str_contains($r['body'], 'Al-Fatihah') && str_contains($r['body'], 'An-Nas'));
check('home has search box', str_contains($r['body'], 'name="q"'));
check('home has continue-reading slot', str_contains($r['body'], 'data-continue-slot'));
check('home exposes CSRF meta token', is_string($tok) && strlen($tok) >= 32);
check('home shows juz list', str_contains($r['body'], 'juz.php?n=1'));
check('home links popular surahs', str_contains($r['body'], 'surah.php?n=36'));
check('home has theme toggle', str_contains($r['body'], 'data-theme-toggle'));
$h = strtolower($r['headers']);
check('CSP header present', str_contains($h, "content-security-policy:") && str_contains($h, "default-src 'self'"), $r['headers']);
check('X-Frame-Options SAMEORIGIN', str_contains($h, 'x-frame-options: sameorigin'));
check('X-Content-Type-Options nosniff', str_contains($h, 'x-content-type-options: nosniff'));
check('Referrer-Policy set', str_contains($h, 'referrer-policy:'));
check('Permissions-Policy set', str_contains($h, 'permissions-policy:'));
check('framework version header hidden', !str_contains($h, 'x-powered-by'));
check('session cookie is HttpOnly + SameSite', str_contains($h, 'httponly') && str_contains($h, 'samesite=lax'));

$css = (string) file_get_contents($root . '/public_html/assets/css/app.css');
check('fonts are self-hosted', str_contains($css, '@font-face') && str_contains($css, 'amiri-quran-arabic.woff2'));
check('audio uses the public CDN', str_contains((string) file_get_contents($root . '/app/config/config.php'), 'cdn.islamic.network'));

$r = call('GET', '/surah.php?n=2');
check('surah 2 → 200', $r['status'] === 200, (string) $r['status']);
check('arabic text present (2:1)', str_contains($r['body'], 'الٓمٓ'));
check('translation present (2:1)', str_contains($r['body'], 'Alif, Lam, Meem'));
check('basmala rendered once in header', countOf($r['body'], 'class="basmala"') === 1);
check('verse anchors present', str_contains($r['body'], 'id="verse-255"'));
check('rtl markup for arabic', str_contains($r['body'], 'class="verse-arabic" lang="ar" dir="rtl"'));
check('audio base points at the CDN', str_contains($r['body'], 'data-audio-base="https://cdn.islamic.network'));
check('bookmark button per verse', str_contains($r['body'], 'data-action="bookmark"'));
check('copy + share actions', str_contains($r['body'], 'data-action="copy"') && str_contains($r['body'], 'data-action="share"'));
check('prev/next surah navigation', str_contains($r['body'], 'surah.php?n=1') && str_contains($r['body'], 'surah.php?n=3'));
check('translation selector present', str_contains($r['body'], 'name="translation"'));
check('attribution for translation', str_contains($r['body'], 'Saheeh International'));
check('verse count correct (286)', str_contains($r['body'], '286 verses'));

$r = call('GET', '/surah.php?n=1&translation=bn.bengali');
check('bengali translation renders', str_contains($r['body'], 'lang="bn"') && str_contains($r['body'], 'Muhiuddin Khan'), $r['status'] . ' ' . substr($r['body'], 0, 160));
check('bengali script is present', (bool) preg_match('/[\x{0980}-\x{09FF}]/u', $r['body']));
check('translation paragraph is left-to-right', str_contains($r['body'], 'dir="ltr"'));

$r = call('GET', '/surah.php?n=112&translation=en.yusufali');
check('translation switch works', $r['status'] === 200 && str_contains($r['body'], 'Say: He is Allah'));

$r = call('GET', '/surah.php?n=1');
check('Al-Fatihah keeps basmala as verse 1', $r['status'] === 200 && str_contains($r['body'], 'verse-1'));

$r = call('GET', '/surah.php?n=999');
check('invalid surah → 404', $r['status'] === 404, (string) $r['status']);

echo "\n== Search ==\n";
$r = call('GET', '/search.php?q=mercy');
check('search page 200', $r['status'] === 200);
check('latin keyword finds verses', countOf($r['body'], '<li class="result"') >= 5, 'matches=' . countOf($r['body'], '<li class="result"'));
check('results show surah + verse reference', str_contains($r['body'], 'class="result-ref"'));
$r = call('GET', '/search.php?q=' . rawurlencode('الرحمن'));
check('arabic search without diacritics', countOf($r['body'], '<li class="result"') >= 5, 'matches=' . countOf($r['body'], '<li class="result"'));
$r = call('GET', '/search.php?q=maryam');
check('surah-name search works', str_contains($r['body'], 'Maryam'));
$r = call('GET', '/search.php?q=xyzzynotfound');
check('no-results state shown', str_contains($r['body'], 'No results'));
$r = call('GET', '/search.php?q=mercy&juz=30');
check('juz filter applied', $r['status'] === 200);

echo "\n== Navigation pages ==\n";
foreach ([
    '/juz.php' => 'Juz',
    '/juz.php?n=1' => 'Al-Fatihah',
    '/juz.php?n=30' => 'Juz 30',
    '/page.php?n=1' => 'Mushaf page 1',
    '/page.php?n=604' => 'Mushaf page 604',
    '/settings.php' => 'Settings',
    '/about.php' => 'About',
    '/bookmarks.php' => 'bookmark',
    '/login.php' => 'Sign in',
    '/register.php' => 'Create',
    '/forgot-password.php' => 'password',
    '/reset-password.php?token=deadbeef' => 'password',
] as $path => $needle) {
    $r = call('GET', $path);
    check("GET $path", $r['status'] === 200 && stripos($r['body'], $needle) !== false, 'status ' . $r['status']);
}
$r = call('GET', '/page.php?n=605');
check('page 605 → 404', $r['status'] === 404);
$r = call('GET', '/nope.php');
check('unknown page → 404', $r['status'] === 404);
$r = call('GET', '/account.php');
check('guest account page redirects to login', $r['status'] === 302, (string) $r['status']);

echo "\n== Configuration via .env ==\n";
check('defaults.php ships the config structure', is_file($root . '/app/config/defaults.php'));
$example = (string) file_get_contents($root . '/.env.example');
foreach (['APP_URL', 'APP_ENV', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'MAIL_TRANSPORT', 'SYNC_TOKEN', 'CONTENT_TRANSLATIONS', 'SESSION_IDLE_TIMEOUT', 'QURAN_API_BASE_URL'] as $key) {
    check(".env.example documents $key", str_contains($example, $key . '='));
}
check('.env stays out of version control', str_contains((string) file_get_contents($root . '/.gitignore'), '.env'));
check('root .htaccess denies dotfiles', str_contains((string) file_get_contents($root . '/.htaccess'), '(^|/)\\. - [F,L]'));

if (!defined('QURAN_APP')) {
    define('QURAN_APP', $root . '/app');
}
require_once $root . '/app/src/Support/Config.php';
putenv('APP_NAME=EnvCheck');
putenv('DB_DATABASE=envcheck_db');
putenv('CONTENT_TRANSLATIONS=aa.one,bb.two');
putenv('SESSION_IDLE_TIMEOUT=42');
\Quran\Support\Config::load($root);
check('real env overrides the merged config', \Quran\Support\Config::get('app.name') === 'EnvCheck');
check('env values reach the database block', \Quran\Support\Config::get('database.database') === 'envcheck_db');
check('comma lists are parsed', \Quran\Support\Config::get('content.translations') === ['aa.one', 'bb.two']);
check('integers are cast', \Quran\Support\Config::get('session.idle_timeout') === 42);
check('defaults still fill untouched keys', is_array(\Quran\Support\Config::get('content.reciters')) && count(\Quran\Support\Config::get('content.reciters')) === 7);

echo "\n== Progressive web app ==\n";
$r = call('GET', '/manifest.webmanifest');
$manifest = json_decode($r['body'], true);
check('manifest is served', $r['status'] === 200, (string) $r['status']);
check('manifest parses as JSON', is_array($manifest) && json_last_error() === JSON_ERROR_NONE);
check('manifest declares name, start_url and scope', ($manifest['name'] ?? '') !== '' && ($manifest['start_url'] ?? '') !== '' && ($manifest['scope'] ?? '') !== '');
check('manifest lists three icons', count($manifest['icons'] ?? []) === 3, (string) count($manifest['icons'] ?? []));
check('manifest has a maskable icon', in_array('maskable', array_column($manifest['icons'], 'purpose'), true));
check('manifest is standalone', ($manifest['display'] ?? '') === 'standalone');
check('manifest pins an app id', ($manifest['id'] ?? '') !== '');

$pwaJs = (string) file_get_contents($root . '/public_html/assets/js/pwa.js');
check('install bar is implemented', str_contains($pwaJs, 'installBar') && str_contains($pwaJs, 'beforeinstallprompt'));
check('install prompt degrades to instructions', str_contains($pwaJs, 'Add to Home Screen') && str_contains($pwaJs, 'isIos'));
check('installed app suppresses the bar', str_contains($pwaJs, 'display-mode: standalone'));
$r = call('GET', '/settings.php');
check('settings always offers the install button', str_contains($r['body'], 'data-install-app') && !preg_match('/data-install-app[^>]*hidden/', $r['body']));
check('settings has an inline install hint', str_contains($r['body'], 'data-install-hint'));
check('install bar styles ship', str_contains((string) file_get_contents($root . '/public_html/assets/css/app.css'), '.install-bar'));

$r = call('GET', '/sw.js');
check('service worker is served', $r['status'] === 200 && str_contains($r['body'], 'CACHE_VERSION'), (string) $r['status']);
check('service worker skips audio streaming', str_contains($r['body'], '.mp3'));
check('service worker has an offline fallback', str_contains($r['body'], 'offline.php'));

$r = call('GET', '/offline.php');
check('offline page renders', $r['status'] === 200 && str_contains($r['body'], 'You are offline'), (string) $r['status']);
check('offline page is self-contained (no app css/js)', !str_contains($r['body'], 'assets/js/app.js'));

$r = call('GET', '/settings.php');
check('settings links the manifest', str_contains($r['body'], 'rel="manifest"'));
check('settings offers the offline download', str_contains($r['body'], 'data-offline-download'));
check('layout registers the pwa script', str_contains($r['body'], 'assets/js/pwa.js'));
check('layout ships an apple touch icon', str_contains($r['body'], 'apple-touch-icon'));

foreach (['icon-192.png' => 192, 'icon-512.png' => 512, 'icon-maskable-512.png' => 512, 'apple-touch-icon.png' => 180] as $file => $size) {
    $path = $root . '/public_html/assets/img/' . $file;
    $info = is_file($path) ? getimagesize($path) : null;
    check("icon $file is a {$size}px PNG", $info !== false && $info[0] === $size && $info[1] === $size, $path);
}

$htaccess = (string) file_get_contents($root . '/public_html/.htaccess');
check('htaccess sets the manifest MIME type', str_contains($htaccess, 'application/manifest+json'));
check('htaccess keeps sw.js out of the long cache', str_contains($htaccess, 'sw\\.js'));

echo "\n== Public API ==\n";
$r = call('GET', '/api/health.php');
check('health endpoint ok', ($r['json']['ok'] ?? false) === true, $r['body']);
check('health reports content_ready', ($r['json']['data']['content_ready'] ?? false) === true);
$r = call('GET', '/api/quran/chapters.php');
check('chapters API returns 114', count($r['json']['data']['chapters'] ?? []) === 114);
$r = call('GET', '/api/quran/search.php?q=peace&limit=5');
check('search API latin', ($r['json']['data']['total'] ?? 0) > 0 && count($r['json']['data']['results'] ?? []) <= 5);
$r = call('GET', '/api/quran/search.php?q=' . rawurlencode('الرحمن'));
check('search API arabic', ($r['json']['data']['total'] ?? 0) > 0);
$r = call('GET', '/api/quran/search.php?q=a');
check('search API ignores 1-char queries', ($r['json']['data']['total'] ?? -1) === 0);

echo "\n== Auth guards ==\n";
foreach (['/api/bookmarks/list.php', '/api/progress/get.php', '/api/settings/get.php', '/api/auth/me.php'] as $path) {
    $r = call('GET', $path);
    check("guest blocked from $path", $r['status'] === 401, (string) $r['status']);
}
$r = call('POST', '/api/auth/login.php', ['email' => 'x@example.com', 'password' => 'whatever123']);
check('POST without CSRF → 419', $r['status'] === 419, (string) $r['status']);
$r = call('POST', '/api/bookmarks/add.php', ['surah' => 1, 'verse' => 1], $tok);
check('guest POST with token → 401', $r['status'] === 401, (string) $r['status']);
$r = call('GET', '/api/marks/list.php');
check('guest blocked from /api/marks/list.php', $r['status'] === 401, (string) $r['status']);
$r = call('POST', '/api/marks/save.php', ['surah' => 1, 'verse' => 1, 'color' => 'yellow'], $tok);
check('guest blocked from /api/marks/save.php', $r['status'] === 401, (string) $r['status']);

echo "\n== Registration / login ==\n";
$email = 'qa' . bin2hex(random_bytes(4)) . '@example.test';
$pass1 = 'Str0ng-password!42';
$r = call('POST', '/api/auth/register.php', [
    'email' => $email, 'password' => $pass1, 'password_confirmation' => $pass1, 'full_name' => 'QA Tester',
], $tok);
check('register succeeds', $r['status'] === 200, $r['body']);
check('register returns user', ($r['json']['data']['user']['email'] ?? '') === $email);
$tok = $r['json']['data']['csrf_token'] ?? $tok;

$r = call('POST', '/api/auth/register.php', [
    'email' => $email, 'password' => $pass1, 'password_confirmation' => $pass1,
], $tok);
check('duplicate email rejected without disclosure', $r['status'] === 422, (string) $r['status']);

$r = call('POST', '/api/auth/register.php', [
    'email' => 'weak@example.test', 'password' => 'short', 'password_confirmation' => 'short',
], $tok);
check('weak password rejected', $r['status'] === 422);

echo "\n== Bookmarks ==\n";
$r = call('POST', '/api/bookmarks/add.php', ['surah' => 2, 'verse' => 255, 'note' => 'Ayat al-Kursi'], $tok);
check('add bookmark', $r['status'] === 200, $r['body']);
$r = call('GET', '/api/bookmarks/list.php');
check('list bookmarks', ($r['json']['data']['items'][0]['verse_key'] ?? '') === '2:255');
check('bookmark carries verse text + translation', mb_strlen((string) ($r['json']['data']['items'][0]['arabic'] ?? '')) > 10);
$r = call('POST', '/api/bookmarks/add.php', ['surah' => 2, 'verse' => 255], $tok);
check('duplicate bookmark is idempotent', $r['status'] === 200 && ($r['json']['data']['already_saved'] ?? false) === true, $r['body']);
$r = call('POST', '/api/bookmarks/add.php', ['surah' => 115, 'verse' => 1], $tok);
check('out-of-range surah rejected', $r['status'] === 422, (string) $r['status']);
$r = call('POST', '/api/bookmarks/add.php', ['surah' => 2, 'verse' => 999], $tok);
check('non-existent verse rejected', $r['status'] === 422, (string) $r['status']);
$r = call('POST', '/api/bookmarks/remove.php', ['surah' => 2, 'verse' => 255], $tok);
check('remove bookmark', $r['status'] === 200 && ($r['json']['data']['removed'] ?? false) === true, $r['body']);
$r = call('GET', '/api/bookmarks/list.php');
check('bookmark list empty afterwards', count($r['json']['data']['items'] ?? [1]) === 0);

$r = call('POST', '/api/bookmarks/import.php', ['items' => [['surah' => 36, 'verse' => 1], ['surah' => 55, 'verse' => 13]]], $tok);
check('guest bookmarks merged on sign-up', $r['status'] === 200 && ($r['json']['data']['imported'] ?? 0) === 2, $r['body']);

echo "\n== Account dashboard preferences ==\n";
$r = call('GET', '/account.php');
check('dashboard shows reading preferences', str_contains($r['body'], 'Reading preferences'), $r['status'] . ' ' . substr($r['body'], 0, 120));
check('dashboard has a translation select', str_contains($r['body'], 'name="translation_id"'));
check('translation select offers bengali', str_contains($r['body'], 'value="bn.bengali"') && str_contains($r['body'], 'value="bn.hoque"'));
check('translation select lists all editions', countOf($r['body'], '<option value="en.') >= 4 && countOf($r['body'], '<option value="bn.') === 2);
check('dashboard form posts to the settings API', str_contains($r['body'], 'data-settings-form') && str_contains($r['body'], 'api/settings/save.php'));
$r = call('POST', '/api/settings/save.php', ['translation_id' => 'bn.bengali', 'reciter_id' => 'ar.alafasy', 'theme' => 'dark', 'arabic_font_size' => 'large'], $tok);
check('dashboard saves a translation choice', $r['status'] === 200 && ($r['json']['data']['settings']['translation_id'] ?? '') === 'bn.bengali', $r['body']);
$r = call('GET', '/account.php');
check('dashboard reflects the saved translation', str_contains($r['body'], 'value="bn.bengali" selected'), 'selected option missing');
call('POST', '/api/settings/save.php', ['translation_id' => 'en.sahih'], $tok);

// Reciting-verse style: offered on both preference surfaces and persisted.
$r = call('GET', '/settings.php');
check('settings offers the reciting-verse style', str_contains($r['body'], 'name="playing_style"') && countOf($r['body'], '<option value="border"') >= 1 && str_contains($r['body'], 'value="off"'));
$r = call('GET', '/account.php');
check('dashboard offers the reciting-verse style', str_contains($r['body'], 'name="playing_style"'));
$r = call('POST', '/api/settings/save.php', ['playing_style' => 'bar'], $tok);
check('reciting style saves', $r['status'] === 200 && ($r['json']['data']['settings']['playing_style'] ?? '') === 'bar', $r['body']);
$r = call('GET', '/surah.php?n=1');
check('reciting style is applied to the page', str_contains($r['body'], 'data-playing-style="bar"'), 'attribute missing');
$r = call('POST', '/api/settings/save.php', ['playing_style' => 'nonsense'], $tok);
check('unknown reciting style rejected', $r['status'] === 422, (string) $r['status']);
call('POST', '/api/settings/save.php', ['playing_style' => 'card'], $tok);

echo "\n== Highlights and notes ==\n";
$r = call('POST', '/api/marks/save.php', ['surah' => 2, 'verse' => 255, 'color' => 'yellow'], $tok);
check('save highlight', $r['status'] === 200 && ($r['json']['data']['mark']['color'] ?? '') === 'yellow', $r['body']);

$r = call('GET', '/surah.php?n=2');
check('reader renders the saved highlight', str_contains($r['body'], 'data-highlight="yellow"'));

$r = call('POST', '/api/marks/save.php', ['surah' => 2, 'verse' => 255, 'note' => 'Ayat al-Kursi — memorize this.'], $tok);
check('save note', $r['status'] === 200 && ($r['json']['data']['mark']['note'] ?? '') !== '', $r['body']);
check('note keeps the existing highlight', ($r['json']['data']['mark']['color'] ?? '') === 'yellow', $r['body']);

$r = call('GET', '/surah.php?n=2');
check('reader renders the saved note', str_contains($r['body'], 'Ayat al-Kursi — memorize this.'));

$r = call('GET', '/api/marks/list.php');
$item = $r['json']['data']['items'][0] ?? [];
check('marks list returns one verse', ($item['verse_key'] ?? '') === '2:255' && ($item['color'] ?? '') === 'yellow', $r['body']);
check('marks list carries the note and the verse text', ($item['note'] ?? '') !== '' && mb_strlen((string) ($item['arabic'] ?? '')) > 10);
check('mark counts are reported', ($r['json']['data']['highlights'] ?? 0) === 1 && ($r['json']['data']['notes'] ?? 0) === 1, $r['body']);

$r = call('POST', '/api/marks/save.php', ['surah' => 2, 'verse' => 255, 'color' => 'blue'], $tok);
check('highlight colour can change', ($r['json']['data']['mark']['color'] ?? '') === 'blue', $r['body']);

$r = call('POST', '/api/marks/save.php', ['surah' => 2, 'verse' => 255, 'color' => 'octarine'], $tok);
check('unknown colour rejected', $r['status'] === 422, (string) $r['status']);

$r = call('POST', '/api/marks/save.php', ['surah' => 2, 'verse' => 255, 'note' => str_repeat('a', 1001)], $tok);
check('over-long note rejected', $r['status'] === 422, (string) $r['status']);

$r = call('POST', '/api/marks/save.php', ['surah' => 2, 'verse' => 255], $tok);
check('save without color or note rejected', $r['status'] === 422, (string) $r['status']);

$r = call('POST', '/api/marks/save.php', ['surah' => 2, 'verse' => 255, 'color' => ''], $tok);
$mark = $r['json']['data']['mark'] ?? [];
check('highlight can be cleared while the note stays', array_key_exists('color', $mark) && $mark['color'] === null && ($mark['note'] ?? '') !== '', $r['body']);

$r = call('GET', '/surah.php?n=2');
check('cleared highlight no longer rendered', !str_contains($r['body'], 'data-highlight="'));
check('note still rendered after the highlight was cleared', str_contains($r['body'], 'Ayat al-Kursi — memorize this.'));

$r = call('GET', '/bookmarks.php');
check('bookmarks page lists notes and highlights', str_contains($r['body'], 'Notes &amp; highlights') && str_contains($r['body'], 'Ayat al-Kursi — memorize this.'), substr($r['body'], 0, 200));

// Grouping: every surah is a single collapsible node, verses sit underneath it.
call('POST', '/api/bookmarks/import.php', ['items' => [['surah' => 2, 'verse' => 1], ['surah' => 2, 'verse' => 2]]], $tok);
$r = call('GET', '/bookmarks.php');
check('saved lists render as a tree', str_contains($r['body'], 'class="saved-tree"') && str_contains($r['body'], '<details class="saved-group" open>'));
$bookmarkSection = substr($r['body'], (int) strpos($r['body'], 'id="bookmarks"'), (int) strpos($r['body'], 'id="marks"') - (int) strpos($r['body'], 'id="bookmarks"'));
check('one node per surah (two verses in Al-Baqarah, one group)', countOf($bookmarkSection, 'saved-group-num">2<') === 1, 'groups=' . countOf($bookmarkSection, 'saved-group-num">2<'));
check('verses are nested inside the surah node', str_contains($r['body'], 'saved-group-items'));
check('group shows a verse count', str_contains($r['body'], 'class="saved-group-count"'));


$r = call('POST', '/api/marks/import.php', [
    'items' => [
        ['surah' => 112, 'verse' => 1, 'color' => 'green', 'note' => 'Tawhid'],
        ['surah' => 112, 'verse' => 2, 'color' => 'purple'],
        ['surah' => 999, 'verse' => 1, 'color' => 'yellow'],
        ['surah' => 112, 'verse' => 3, 'color' => 'not-a-colour'],
    ],
], $tok);
check('guest marks merged (invalid entries skipped)', $r['status'] === 200 && ($r['json']['data']['imported'] ?? 0) === 3, $r['body']);

$r = call('GET', '/api/marks/list.php');
check('merged marks are listed per verse', count($r['json']['data']['items'] ?? []) === 3, $r['body']);

$r = call('POST', '/api/marks/remove.php', ['surah' => 112, 'verse' => 1, 'scope' => 'note'], $tok);
$mark = $r['json']['data']['mark'] ?? [];
check('note-only removal keeps the highlight', array_key_exists('note', $mark) && $mark['note'] === null && ($mark['color'] ?? '') === 'green', $r['body']);

$r = call('POST', '/api/marks/remove.php', ['surah' => 112, 'verse' => 1], $tok);
check('remove highlight and note', array_key_exists('mark', $r['json']['data']) && $r['json']['data']['mark'] === null, $r['body']);

$r = call('GET', '/bookmarks.php');
$markSection = substr($r['body'], (int) strpos($r['body'], 'id="marks"'));
check('notes and highlights are grouped by surah too', countOf($markSection, 'saved-group-num">112<') === 1, 'groups=' . countOf($markSection, 'saved-group-num">112<'));

echo "\n== Reading progress ==\n";
$r = call('POST', '/api/progress/save.php', ['surah' => 18, 'verse' => 10], $tok);
check('save progress', $r['status'] === 200, $r['body']);
$r = call('GET', '/api/progress/get.php');
check('read progress back', ($r['json']['data']['progress']['surah'] ?? 0) === 18 && ($r['json']['data']['progress']['verse'] ?? 0) === 10, $r['body']);
$r = call('POST', '/api/progress/save.php', ['surah' => 2, 'verse' => 5000], $tok);
check('invalid verse progress rejected', $r['status'] === 422);

echo "\n== Settings ==\n";
$r = call('POST', '/api/settings/save.php', [
    'translation_id' => 'en.pickthall', 'reciter_id' => 'ar.husary', 'arabic_font_size' => 'large', 'theme' => 'dark',
], $tok);
check('save settings', $r['status'] === 200, $r['body']);
$r = call('GET', '/api/settings/get.php');
check('settings persisted', ($r['json']['data']['settings']['theme'] ?? '') === 'dark' && ($r['json']['data']['settings']['reciter_id'] ?? '') === 'ar.husary');
$r = call('POST', '/api/settings/save.php', ['theme' => 'purple'], $tok);
check('invalid theme rejected', $r['status'] === 422);
$r = call('POST', '/api/settings/save.php', ['translation_id' => 'not.a.translation'], $tok);
check('unknown translation rejected', $r['status'] === 422);

echo "\n== Session lifecycle ==\n";
$r = call('GET', '/api/auth/me.php');
check('me returns current user', ($r['json']['data']['user']['email'] ?? '') === $email);
$r = call('GET', '/surah.php?n=18');
check('reader reflects saved settings', str_contains($r['body'], 'Pickthall'));
$r = call('POST', '/api/auth/logout.php', [], $tok);
check('logout', $r['status'] === 200);
$r = call('GET', '/api/auth/me.php');
check('me → 401 after logout', $r['status'] === 401);
$tok = csrfOf(call('GET', '/login.php')['body']) ?? $tok;
$r = call('POST', '/api/auth/login.php', ['email' => $email, 'password' => 'wrong-password'], $tok);
check('wrong password → 401 generic', $r['status'] === 401 && str_contains($r['body'], 'Incorrect email or password'), $r['body']);
$r = call('POST', '/api/auth/login.php', ['email' => $email, 'password' => $pass1], $tok);
check('login succeeds', $r['status'] === 200, $r['body']);
$tok = $r['json']['data']['csrf_token'] ?? $tok;
$r = call('GET', '/api/progress/get.php');
check('progress survives new session', ($r['json']['data']['progress']['surah'] ?? 0) === 18);
$r = call('GET', '/api/bookmarks/list.php');
check('bookmarks survive new session', count($r['json']['data']['items'] ?? []) >= 2);

echo "\n== Password reset ==\n";
$log = $root . '/storage/logs/mail.log';
$before = is_file($log) ? (string) file_get_contents($log) : '';
$r = call('POST', '/api/auth/forgot-password.php', ['email' => $email], $tok);
check('forgot-password answers generically', $r['status'] === 200 && str_contains((string) ($r['json']['data']['message'] ?? ''), 'If that email'));
$r = call('POST', '/api/auth/forgot-password.php', ['email' => 'nobody@example.test'], $tok);
check('unknown email gets the same answer', $r['status'] === 200 && str_contains((string) ($r['json']['data']['message'] ?? ''), 'If that email'));
$after = is_file($log) ? (string) file_get_contents($log) : '';
preg_match_all('/reset-password\.php\?token=([a-f0-9]{64})/', substr($after, strlen($before)), $m);
$reset = end($m[1]) ?: '';
check('reset link produced (mail log transport)', $reset !== '');
$r = call('GET', '/reset-password.php?token=' . $reset);
check('reset page accepts token', str_contains($r['body'], 'name="password"') && !str_contains($r['body'], 'invalid or has expired'));
$r = call('POST', '/api/auth/reset-password.php', [
    'token' => $reset, 'password' => 'N3w-password!99', 'password_confirmation' => 'N3w-password!99',
], $tok);
check('password reset succeeds', $r['status'] === 200, $r['body']);
$r = call('POST', '/api/auth/reset-password.php', [
    'token' => $reset, 'password' => 'Another!pass99', 'password_confirmation' => 'Another!pass99',
], $tok);
check('reset token is single-use', $r['status'] === 422);
$r = call('POST', '/api/auth/login.php', ['email' => $email, 'password' => 'N3w-password!99'], $tok);
check('login with the new password', $r['status'] === 200, $r['body']);
// Login rotates the CSRF token, so later requests must use the new one.
$tok = $r['json']['data']['csrf_token'] ?? $tok;

echo "\n== Rate limiting ==\n";
$limitHit = 0;
$seen = [];
for ($i = 1; $i <= 9; $i++) {
    $r = call('POST', '/api/auth/login.php', ['email' => 'bruteforce@example.test', 'password' => 'wrong-password'], $tok);
    $seen[] = $r['status'];
    if ($r['status'] === 429) { $limitHit = $i; break; }
}
check('login throttled (HTTP 429)', $limitHit > 0, 'statuses: ' . implode(',', $seen));

echo "\n== Database invariants ==\n";
$verses = (int) $db->query('SELECT COUNT(*) FROM verses')->fetchColumn();
$chapters = (int) $db->query('SELECT COUNT(*) FROM chapters')->fetchColumn();
$juzs = (int) $db->query('SELECT COUNT(*) FROM juzs')->fetchColumn();
$tr = (int) $db->query('SELECT COUNT(*) FROM translations')->fetchColumn();
check('6236 verses stored', $verses === 6236, (string) $verses);
check('114 chapters stored', $chapters === 114, (string) $chapters);
check('30 juz stored', $juzs === 30, (string) $juzs);
check('translations imported', $tr >= 1, (string) $tr);
$bn = (int) $db->query("SELECT COUNT(*) FROM translations WHERE language_code = 'bn'")->fetchColumn();
check('bengali editions imported', $bn === 2, (string) $bn);
$bnRows = (int) $db->query("SELECT COUNT(*) FROM verse_translations WHERE translation_id IN ('bn.bengali','bn.hoque')")->fetchColumn();
check('bengali verses stored (6236 × 2)', $bnRows === 12472, (string) $bnRows);
$basmala = $db->query("SELECT COUNT(*) FROM verses WHERE surah_number <> 1 AND verse_number = 1 AND text_uthmani LIKE '%بِسْمِ%'")->fetchColumn();
check('no duplicated basmala in first verses', (int) $basmala === 0, 'rows=' . $basmala);
$inside = (int) $db->query("SELECT COUNT(*) FROM verses WHERE verse_number > 1 AND text_uthmani LIKE '%بِسْمِ%'")->fetchColumn();
check('basmala inside a verse (11:41, 27:30) kept intact', $inside >= 2, 'rows=' . $inside);
$hash = $db->query("SELECT password_hash FROM users WHERE email = " . $db->quote($email))->fetchColumn();
check('password stored as bcrypt/argon hash', is_string($hash) && password_get_info($hash)['algo'] !== null && !str_contains((string) $hash, 'N3w-password'));

// cleanup
$db->exec("DELETE FROM users WHERE email LIKE '%@example.test'");
$db->exec('DELETE FROM rate_limits');

$elapsed = round(microtime(true) - $start, 1);
echo "\n------------------------------------------------------------\n";
echo "RESULT: {$pass} passed, {$fail} failed in {$elapsed}s\n";
if ($fail > 0) {
    echo "Failed checks:\n - " . implode("\n - ", $notes) . "\n";
}
exit($fail === 0 ? 0 : 1);
