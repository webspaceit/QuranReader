<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$admin = new Quran\Web\Admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $admin->saveFooter();
} else {
    $admin->dashboard();
}
?>