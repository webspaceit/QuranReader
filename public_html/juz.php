<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$site = new Quran\Web\Site();

// No ?n= means "show the index of the thirty juz".
if (!isset($_GET['n']) || $_GET['n'] === '') {
    $site->juzIndex();
    return;
}

$juzNo = (int) $_GET['n'];
if ($juzNo < 1 || $juzNo > 30) {
    $site->notFound('Juz numbers run from 1 to 30.');
    return;
}

$site->juz($juzNo);
