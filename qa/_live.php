<?php
$html = (string) file_get_contents('http://localhost/quran-project/public_html/');
if (preg_match('~©[^<]*<a[^>]*>[^<]*</a>\.?~u', $html, $m)) { echo "live footer: " . trim($m[0]) . PHP_EOL; }
else { echo "live footer: no link found\n"; }
