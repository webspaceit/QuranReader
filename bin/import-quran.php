#!/usr/bin/env php
<?php
/**
 * Import / refresh Quran content from the public API into MySQL.
 *
 * Usage:
 *   php bin/import-quran.php                 # full import (chapters + verses + translations)
 *   php bin/import-quran.php --surah=2       # only one surah (repeatable)
 *   php bin/import-quran.php --surah=2,3,4   # a few surahs
 *   php bin/import-quran.php --skip-chapters # keep existing chapter metadata
 *   php bin/import-quran.php --translations=en.sahih,en.pickthall
 *
 * The script is idempotent: rows are upserted, so it is safe to re-run.
 * Expect 3–8 minutes for a full import on shared hosting.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Quran\Http\CurlHttpClient;
use Quran\Quran\QuranApiClient;
use Quran\Quran\QuranSync;
use Quran\Support\Database;

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$options = [
    'surahs'        => null,
    'translations'  => null,
    'skip_chapters' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php bin/import-quran.php [--surah=1,2,3] [--translations=en.sahih] [--skip-chapters]\n";
        exit(0);
    }
    if (strpos($arg, '--surah=') === 0) {
        $list = array_filter(array_map('intval', explode(',', substr($arg, 8))));
        $list = array_filter($list, static fn (int $n): bool => $n >= 1 && $n <= 114);
        $options['surahs'] = $list === [] ? null : array_values($list);
        continue;
    }
    if (strpos($arg, '--translations=') === 0) {
        $options['translations'] = array_values(array_filter(array_map('trim', explode(',', substr($arg, 15)))));
        continue;
    }
    if ($arg === '--skip-chapters') {
        $options['skip_chapters'] = true;
        continue;
    }
    fwrite(STDERR, "Unknown option: {$arg}\n");
    exit(2);
}

echo "Quran content import\n";
echo "====================\n";

try {
    Database::ping();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Check app/config/config.php, then import database/schema.sql first.\n");
    exit(1);
}

$chapters = (int) Database::value('SELECT COUNT(*) FROM chapters');
if ($chapters === 0) {
    echo "Note: the chapters table is empty - importing chapter metadata from the API.\n";
}

$api  = new QuranApiClient(new CurlHttpClient());
$sync = new QuranSync($api, static function (string $line): void {
    echo $line, "\n";
    @flush();
});

try {
    $stats = $sync->run($options);
} catch (Throwable $e) {
    fwrite(STDERR, "\nImport failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "If this is a TLS error, set quran_api.cainfo in app/config/config.php to a cacert.pem file.\n");
    exit(1);
}

echo "\nSummary\n-------\n";
printf("Chapters:     %d\n", $stats['chapters']);
printf("Verses:       %d\n", $stats['verses']);
printf("Translations: %d rows\n", $stats['translations']);
printf("Juz:          %d\n", $stats['juz']);
printf("Reciters:     %d\n", $stats['reciters']);

if ($stats['errors'] !== []) {
    echo "\nErrors:\n";
    foreach ($stats['errors'] as $error) {
        echo '  - ', $error, "\n";
    }
    exit(1);
}

echo "\nDone. Open the site and confirm the reader, search and audio.\n";
exit(0);
