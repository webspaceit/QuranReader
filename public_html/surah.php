<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$site    = new Quran\Web\Site();
$surahNo = isset($_GET['n']) ? (int) $_GET['n'] : 1;

if ($surahNo < 1 || $surahNo > 114) {
    $site->notFound('Surah numbers run from 1 to 114.');
    return;
}

$site->surah($surahNo);
