<?php
require 'public_html/_bootstrap.php';
use Quran\Repositories\SiteSettingsRepository as S;
foreach (Quran\Support\Database::select('SELECT setting_key, setting_value FROM site_settings') as $r) {
    echo $r['setting_key'] . ' = ' . var_export($r['setting_value'], true) . PHP_EOL;
}
echo "\nrendered holder: " . S::inline(S::all()['footer_copyright_holder']) . PHP_EOL;
$html = (string) file_get_contents('http://localhost/quran-project/public_html/');
preg_match('~©[^<]*<a[^>]*>[^<]*</a>[^<]*~u', $html, $m);
echo "footer line: " . trim($m[0] ?? '(no link)') . PHP_EOL;
