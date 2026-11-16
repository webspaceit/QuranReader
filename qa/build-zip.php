<?php
/**
 * Builds the distributable archive. Development helper: run from the project
 * root with `php qa/build-zip.php`.
 *
 * Excluded: local credentials, logs, the test harness, QA screenshots and the
 * archive itself. Everything else (source, views, assets, docs, schema) ships.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$zipPath = $root . '/quran-website-v1.zip';
@unlink($zipPath);

$skipDirs = [
    'qa', 'delivery', 'browser-results', '.git', 'dist', 'node_modules',
    'storage/logs', 'storage/cache',
    'quran-package', 'quran-package2',
    // Screenshots dropped in by the editor/chat client; never part of a release.
    '.cloud-attachments', '.idea', '.vscode',
];
$skipFiles = ['app/config/config.php', '.env', '.DS_Store', 'Thumbs.db'];
$skipNames = ['quran-website-v1.zip'];

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create $zipPath\n");
    exit(1);
}

$added = 0;
$skipped = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

    // Safety net: never walk deeper than a normal source tree.
    if (substr_count($rel, '/') > 10) {
        $skipped++;
        continue;
    }

    foreach ($skipDirs as $dir) {
        if ($rel === $dir || str_starts_with($rel, $dir . '/')) {
            $skipped++;
            continue 2;
        }
    }
    if (in_array($rel, $skipFiles, true) || in_array($file->getFilename(), $skipNames, true)) {
        $skipped++;
        continue;
    }
    if (str_ends_with($rel, '.log') || str_ends_with($rel, '.zip')) {
        $skipped++;
        continue;
    }
    if ($file->isDir()) {
        continue;
    }

    $zip->addFile($file->getPathname(), $rel);
    $added++;
}

// Keep the runtime folders present (with their .gitignore) after unzipping.
foreach (['storage/logs', 'storage/cache'] as $dir) {
    $keep = $dir . '/.gitignore';
    if (is_file($root . '/' . $keep)) {
        $zip->addFile($root . '/' . $keep, $keep);
        $added++;
    }
}

$zip->close();

printf("entries added: %d (skipped paths: %d)\n", $added, $skipped);
printf("archive: %s (%.1f KB)\n", $zipPath, filesize($zipPath) / 1024);

// Report a compact inventory by top-level folder.
$check = new ZipArchive();
$check->open($zipPath);
$buckets = [];
for ($i = 0; $i < $check->numFiles; $i++) {
    $name = $check->getNameIndex($i);
    $top = explode('/', $name)[0];
    $buckets[$top] = ($buckets[$top] ?? 0) + 1;
}
ksort($buckets);
foreach ($buckets as $top => $count) {
    printf("  %-16s %d\n", $top, $count);
}
printf("total entries: %d\n", $check->numFiles);
$check->close();
