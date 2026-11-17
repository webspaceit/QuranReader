<?php
require 'public_html/_bootstrap.php';
use Quran\Repositories\SiteSettingsRepository as S;

S::put([
    'footer_copyright_holder' => 'Quran Reader. All rights reserved. Developed by <a href="https://webspaceit.com">Web Space IT</a>',
]);

$value = S::all()['footer_copyright_holder'];
echo "stored:   " . $value . PHP_EOL;
echo "rendered: " . S::inline($value) . PHP_EOL;
