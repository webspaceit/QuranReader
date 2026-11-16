<?php
/** @var string $message */
?>
<section class="section">
    <div class="container container-narrow center-text">
        <p class="eyebrow">404</p>
        <h1>Page not found</h1>
        <p class="muted"><?= e($message) ?></p>
        <div class="form-actions center-actions">
            <a class="btn btn-primary" href="<?= e(u('')) ?>">Go to the reader</a>
            <a class="btn btn-ghost" href="<?= e(u('search.php')) ?>">Search the Quran</a>
        </div>
    </div>
</section>
