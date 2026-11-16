<?php
/**
 * @var array $juz
 * @var array $verses
 * @var array|null $translation
 * @var array $translations
 * @var int|null $prevJuz
 * @var int|null $nextJuz
 */
$currentSurah = 0;
?>
<section class="section">
    <div class="container container-narrow">
        <header class="page-head">
            <p class="eyebrow">Juz <?= (int) $juz['number'] ?></p>
            <h1>Juz <?= (int) $juz['number'] ?></h1>
            <p class="muted">
                <?= e($juz['first_name']) ?> <?= (int) $juz['first_verse'] ?> —
                <?= e($juz['last_name']) ?> <?= (int) $juz['last_verse'] ?>
                · <?= (int) $juz['verses_count'] ?> verses
            </p>
            <div class="page-head-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(u('juz.php')) ?>">All juz</a>
                <form class="inline-select" method="get" action="<?= e(u('juz.php')) ?>">
                    <label class="visually-hidden" for="juz-switch">Juz</label>
                    <select id="juz-switch" name="n" data-auto-submit>
                        <?php for ($i = 1; $i <= 30; $i++): ?>
                            <option value="<?= $i ?>" <?= $i === (int) $juz['number'] ? 'selected' : '' ?>>Juz <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <noscript><button class="btn btn-sm btn-ghost" type="submit">Go</button></noscript>
                </form>
            </div>
        </header>

        <div class="verse-list verse-list-compact">
            <?php foreach ($verses as $verse): ?>
                <?php
                $surahNumber = (int) $verse['surah_number'];
                if ($surahNumber !== $currentSurah):
                    $currentSurah = $surahNumber;
                    ?>
                    <h2 class="surah-divider">
                        <span class="surah-divider-num"><?= $surahNumber ?></span>
                        <span><?= e($verse['name_simple']) ?></span>
                        <span class="surah-divider-ar" lang="ar" dir="rtl"><?= e($verse['name_arabic']) ?></span>
                    </h2>
                <?php endif; ?>

                <article class="verse verse-compact" id="verse-<?= $surahNumber ?>-<?= (int) $verse['verse_number'] ?>">
                    <div class="verse-main">
                        <p class="verse-arabic" lang="ar" dir="rtl"><?= e($verse['text_uthmani']) ?><span class="ayah-marker"><?= e(\Quran\Support\Text::toArabicDigits((int) $verse['verse_number'])) ?></span></p>
                        <?php if (!empty($verse['translation_text'])): ?>
                            <p class="verse-translation"><?= e($verse['translation_text']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="verse-actions">
                        <a class="verse-ref" href="<?= e(surah_url($surahNumber, (int) $verse['verse_number'])) ?>">
                            <?= $surahNumber ?>:<?= (int) $verse['verse_number'] ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <nav class="surah-pagination">
            <?php if ($prevJuz): ?>
                <a class="pagination-link prev" href="<?= e(u('juz.php?n=' . $prevJuz)) ?>">
                    <span class="pagination-label">← Juz <?= (int) $prevJuz ?></span>
                </a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($nextJuz): ?>
                <a class="pagination-link next" href="<?= e(u('juz.php?n=' . $nextJuz)) ?>">
                    <span class="pagination-label">Juz <?= (int) $nextJuz ?> →</span>
                </a>
            <?php endif; ?>
        </nav>

        <p class="muted small">Translation: <?= e($translation['english_name'] ?? '—') ?>.
            <a href="<?= e(u('surah.php?n=' . (int) $juz['first_surah'])) ?>">Open in reader</a></p>
    </div>
</section>
