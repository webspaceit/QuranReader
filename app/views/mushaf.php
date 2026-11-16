<?php
/**
 * Page-based Mushaf view: continuous Arabic text for one physical page of the
 * Madinah Mushaf, with verse end markers.
 *
 * @var int $page
 * @var int $maxPage
 * @var int $minPage
 * @var array $verses
 * @var array|null $translation
 * @var array $translations
 */
$currentSurah = 0;
?>
<section class="section">
    <div class="container">
        <header class="page-head mushaf-head">
            <div>
                <p class="eyebrow">Mushaf</p>
                <h1>Page <?= (int) $page ?></h1>
            </div>
            <div class="mushaf-controls">
                <a class="btn btn-ghost btn-sm <?= $page <= $minPage ? 'is-disabled' : '' ?>"
                   href="<?= e(u('page.php?n=' . max($minPage, $page - 1))) ?>" rel="prev">← Previous</a>
                <form class="inline-select" method="get" action="<?= e(u('page.php')) ?>">
                    <label class="visually-hidden" for="page-jump">Page number</label>
                    <input id="page-jump" type="number" name="n" min="<?= (int) $minPage ?>" max="<?= (int) $maxPage ?>"
                           value="<?= (int) $page ?>" inputmode="numeric">
                    <button class="btn btn-sm btn-primary" type="submit">Go</button>
                </form>
                <a class="btn btn-ghost btn-sm <?= $page >= $maxPage ? 'is-disabled' : '' ?>"
                   href="<?= e(u('page.php?n=' . min($maxPage, $page + 1))) ?>" rel="next">Next →</a>
            </div>
        </header>

        <?php if ($verses === []): ?>
            <div class="empty-state card">
                <h2>No verses on this page</h2>
                <p>Page data may not be imported yet. Run the importer or open the surah reader instead.</p>
            </div>
        <?php else: ?>
            <div class="mushaf-frame">
                <div class="mushaf-page" lang="ar" dir="rtl">
                    <?php foreach ($verses as $verse): ?>
                        <?php
                        $surahNumber = (int) $verse['surah_number'];
                        if ($surahNumber !== $currentSurah):
                            $currentSurah = $surahNumber;
                            ?>
                            <div class="mushaf-surah-head">
                                <span class="mushaf-surah-name"><?= e($verse['name_arabic']) ?></span>
                                <span class="mushaf-surah-meta"><?= $surahNumber ?></span>
                            </div>
                        <?php endif; ?>
                        <span class="mushaf-verse" id="verse-<?= $surahNumber ?>-<?= (int) $verse['verse_number'] ?>"><?= e($verse['text_uthmani']) ?><span class="ayah-marker"><?= e(\Quran\Support\Text::toArabicDigits((int) $verse['verse_number'])) ?></span></span>
                    <?php endforeach; ?>
                </div>

                <div class="mushaf-caption">
                    <span class="muted small">Page <?= (int) $page ?> of <?= (int) $maxPage ?></span>
                    <span class="muted small">
                        Reading the flow view is easier on screen —
                        <a href="<?= e(surah_url((int) ($verses[0]['surah_number'] ?? 1))) ?>">open the surah reader</a>
                        for translations, audio and bookmarks.
                    </span>
                </div>
            </div>

            <div class="mushaf-legend muted small">
                Verse markers use Arabic-Indic numerals; the small number in the margin is the surah number.
            </div>
        <?php endif; ?>
    </div>
</section>
