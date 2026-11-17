#!/usr/bin/env php
<?php
/**
 * Brings an existing database up to the current schema without a dump/restore.
 *
 *   php bin/migrate.php            # apply what is missing
 *   php bin/migrate.php status     # report only, change nothing
 *
 * Only additive column changes are handled: every step is idempotent and skips
 * work that is already done, so it is safe to run on every deploy.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Quran\Support\Database;

/** Columns added after the first release. Keep in sync with database/schema.sql. */
$columns = [
    ['user_settings', 'playing_style', "VARCHAR(16) NULL DEFAULT NULL AFTER `theme`"],
    ['user_settings', 'arabic_font', "VARCHAR(24) NULL DEFAULT NULL AFTER `arabic_font_size`"],
];

$status = ($argv[1] ?? '') === 'status';
$applied = 0;

foreach ($columns as [$table, $column, $definition]) {
    $exists = (int) Database::value(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        [$table, $column]
    ) > 0;

    if ($exists) {
        echo "skip    {$table}.{$column} — already present\n";
        continue;
    }

    if ($status) {
        echo "missing {$table}.{$column}\n";
        continue;
    }

    Database::execute("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    echo "added   {$table}.{$column}\n";
    $applied++;
}

echo $status
    ? "status only — nothing was changed\n"
    : "done — {$applied} column(s) added\n";
