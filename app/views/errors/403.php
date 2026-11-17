<?php
/** @var string $message */
?>
<section class="section">
    <div class="container container-narrow center-text">
        <p class="eyebrow">403</p>
        <h1>Access denied</h1>
        <p class="muted"><?= e($message) ?></p>
        <div class="form-actions center-actions">
            <a class="btn btn-primary" href="<?= e(u('')) ?>">Go to the reader</a>
            <?php if ($currentUser): ?>
                <a class="btn btn-ghost" href="<?= e(u('account.php')) ?>">Your account</a>
            <?php else: ?>
                <a class="btn btn-ghost" href="<?= e(u('login.php')) ?>">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</section>
