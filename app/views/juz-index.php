<?php
/** @var array $juzs */
?>
<section class="section">
    <div class="container">
        <header class="page-head">
            <h1>The 30 juz</h1>
            <p class="muted">The Quran is divided into thirty juz, traditionally read one per day during Ramadan.</p>
        </header>

        <ol class="juz-list">
            <?php foreach ($juzs as $juz): ?>
                <li>
                    <a class="juz-card" href="<?= e(u('juz.php?n=' . (int) $juz['number'])) ?>">
                        <span class="juz-card-num"><?= (int) $juz['number'] ?></span>
                        <span class="juz-card-body">
                            <span class="juz-card-title">Juz <?= (int) $juz['number'] ?></span>
                            <span class="juz-card-range">
                                <?= e($juz['first_name']) ?> <?= (int) $juz['first_verse'] ?> —
                                <?= e($juz['last_name']) ?> <?= (int) $juz['last_verse'] ?>
                            </span>
                        </span>
                        <span class="juz-card-count muted small"><?= (int) $juz['verses_count'] ?> verses</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if ($juzs === []): ?>
            <div class="empty-state card">
                <h2>Juz data is not available yet</h2>
                <p>Import the Quran content first: <code>php bin/import-quran.php</code></p>
            </div>
        <?php endif; ?>
    </div>
</section>
