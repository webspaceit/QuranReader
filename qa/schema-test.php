<?php
declare(strict_types=1);
/**
 * Imports database/schema.sql into a throwaway database to prove the file is
 * complete and valid on a clean MySQL server.
 */
$name = 'quran_schema_verify';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `$name`");

$sql = (string) file_get_contents(__DIR__ . '/../database/schema.sql');
$sql = str_replace('`quran_website`', "`$name`", $sql);

$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
$ok = 0;
$fail = [];
foreach ($statements as $stmt) {
    // Drop leading comment lines but keep the statement they introduce.
    $lines = preg_split('/\r?\n/', $stmt);
    while ($lines !== [] && (trim($lines[0]) === '' || str_starts_with(trim($lines[0]), '--'))) {
        array_shift($lines);
    }
    $stmt = trim(implode("\n", $lines));
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (Throwable $e) {
        $fail[] = substr($stmt, 0, 60) . ' → ' . $e->getMessage();
    }
}

$db = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$name;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

$expected = ['bookmarks', 'chapters', 'highlights', 'juzs', 'notes', 'password_resets', 'rate_limits', 'reading_progress', 'reciters', 'sync_runs', 'translations', 'user_settings', 'users', 'verse_translations', 'verses'];
$missing = array_diff($expected, $tables);

$engine = $db->query("SELECT ENGINE FROM information_schema.tables WHERE table_schema = '$name' AND table_name = 'users'")->fetchColumn();
$collation = $db->query("SELECT TABLE_COLLATION FROM information_schema.tables WHERE table_schema = '$name' AND table_name = 'verses'")->fetchColumn();
$fk = (int) $db->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = '$name'")->fetchColumn();
$idx = (int) $db->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = '$name' AND table_name = 'verse_translations'")->fetchColumn();

echo "statements executed: $ok\n";
echo 'errors: ' . count($fail) . "\n";
foreach ($fail as $f) {
    echo "  - $f\n";
}
echo 'tables: ' . count($tables) . ' (' . implode(', ', $tables) . ")\n";
echo 'missing: ' . (($missing === []) ? 'none' : implode(', ', $missing)) . "\n";
echo "users engine: $engine\n";
echo "verses collation: $collation\n";
echo "foreign keys: $fk\n";
echo "verse_translations indexes: $idx\n";

$okAll = $fail === [] && $missing === [] && $engine === 'InnoDB' && str_starts_with((string) $collation, 'utf8mb4') && $fk >= 6;
$db->exec("DROP DATABASE `$name`");
echo $okAll ? "SCHEMA OK\n" : "SCHEMA PROBLEMS\n";
exit($okAll ? 0 : 1);
