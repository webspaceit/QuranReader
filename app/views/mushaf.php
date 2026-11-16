<?php
/**
 * Mushaf page reader — illuminated page header, verse cards with translation,
 * page navigation and a page-details rail.
 *
 * @var int $page
 * @var int $maxPage
 * @var int $minPage
 * @var array $verses
 * @var array|null $translation
 * @var array $translations
 * @var string $basmala
 */
$surahsOnPage = [];
$juz          = null;
$sajdaCount   = 0;

foreach ($verses as $verse) {
    $sid = (int) $verse['surah_number'];
    if (!isset($surahsOnPage[$sid])) {
        $surahsOnPage[$sid] = [
            'number'  => $sid,
            'simple'  => (string) $verse['name_simple'],
            'english' => (string) ($verse['translated_name'] ?? $verse['name_english'] ?? ''),
            'arabic'  => (string) $verse['name_arabic'],
            'first'   => (int) $verse['verse_number'],
        ];
    }
    if ($juz === null) {
        $juz = (int) $verse['juz'];
    }
    if ((int) ($verse['sajda'] ?? 0) === 1) {
        $sajdaCount++;
    }
}

$ayahCount   = count($verses);
$firstSurah  = $surahsOnPage === [] ? 1 : (int) array_key_first($surahsOnPage);
$surahTitles = array_map(static fn(array $s): string => $s['simple'], array_values($surahsOnPage));
$surahArabic = implode(' • ', array_map(static fn(array $s): string => $s['arabic'], array_values($surahsOnPage)));
$surahEnglish = $surahsOnPage === [] ? '' : (string) reset($surahsOnPage)['english'];
$editionName = (string) ($translation['english_name'] ?? 'Translation');
$editionBy   = (string) ($translation['author_name'] ?? '');
$hasPrev     = $page > $minPage;
$hasNext     = $page < $maxPage;
?>
<div class="mushaf-reader">

    <!-- ======================= sticky utility bar ======================= -->
    <div class="mf-bar">
        <div class="container mf-bar-inner">
            <form class="mf-jump" method="get" action="<?= e(u('page.php')) ?>">
                <span class="mf-jump-chip" aria-hidden="true"><?= (int) $page ?></span>
                <label class="visually-hidden" for="page-jump">Mushaf page number</label>
                <input id="page-jump" type="number" name="n" inputmode="numeric"
                       min="<?= (int) $minPage ?>" max="<?= (int) $maxPage ?>" value="<?= (int) $page ?>">
                <span class="mf-jump-of">of <?= (int) $maxPage ?></span>
                <button class="btn btn-sm btn-primary" type="submit">Go</button>
            </form>

            <div class="mf-switch" role="group" aria-label="Reading mode">
                <span class="mf-tab is-active">Mushaf page</span>
                <a class="mf-tab" href="<?= e(surah_url($firstSurah)) ?>">Verse by verse</a>
            </div>

            <div class="mf-bar-actions">
                <a class="mf-step <?= $hasPrev ? '' : 'is-disabled' ?>" rel="prev"
                   href="<?= e(u('page.php?n=' . max($minPage, $page - 1))) ?>" aria-label="Previous Mushaf page">&larr;</a>
                <a class="mf-step <?= $hasNext ? '' : 'is-disabled' ?>" rel="next"
                   href="<?= e(u('page.php?n=' . min($maxPage, $page + 1))) ?>" aria-label="Next Mushaf page">&rarr;</a>

                <details class="translation-picker">
                    <summary class="btn btn-ghost btn-sm">
                        <?= icon('translate', 'ico', 17) ?>
                        <span class="hide-sm"><?= e($editionName) ?></span>
                    </summary>
                    <div class="picker-panel">
                        <p class="picker-title">Translation</p>
                        <form method="get" action="<?= e(u('page.php')) ?>" data-translation-form>
                            <input type="hidden" name="n" value="<?= (int) $page ?>">
                            <?php foreach ($translations as $item): ?>
                                <label class="radio-row">
                                    <input type="radio" name="translation" value="<?= e($item['id']) ?>"
                                        <?= ($translation['id'] ?? '') === $item['id'] ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= e($item['english_name']) ?></strong>
                                        <small class="muted"><?= e($item['language_name']) ?> · <?= e($item['author_name']) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <noscript><button class="btn btn-sm btn-primary" type="submit">Apply</button></noscript>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <?php if ($verses === []): ?>
        <div class="container">
            <div class="empty-state card">
                <h1>No verses on this page</h1>
                <p>Page data may not be imported yet. Run the importer or open the <a href="<?= e(surah_url(1)) ?>">surah reader</a> instead.</p>
            </div>
        </div>
    <?php else: ?>

    <div class="container mf-layout">

        <!-- ======================= reading stream ======================= -->
        <div class="mf-main">
            <div class="mf-cartouche">
                <svg class="mf-cartouche-art" viewBox="0 0 400 200" fill="none" stroke="currentColor" aria-hidden="true">
                    <circle cx="200" cy="100" r="80" stroke-dasharray="4 4" stroke-width="1.5"/>
                    <circle cx="200" cy="100" r="130" stroke-width="1"/>
                    <polygon points="200,30 220,90 280,100 220,110 200,170 180,110 120,100 180,90" stroke-width="1"/>
                    <path d="M50 100 Q 100 20 200 20 T 350 100 T 200 180 T 50 100" stroke-width="0.75"/>
                </svg>

                <div class="mf-cartouche-inner">
                    <span class="mf-cartouche-chip">
                        Page <?= (int) $page ?> · Juz <?= (int) ($juz ?? 1) ?> · <?= (int) $ayahCount ?> ayat
                    </span>
                    <h1 class="mf-cartouche-arabic" lang="ar" dir="rtl"><?= e($surahArabic) ?></h1>
                    <p class="mf-cartouche-title">
                        <?php if (count($surahsOnPage) === 1): ?>
                            Surah <?= e($surahTitles[0]) ?>
                            <?php if ($surahEnglish !== ''): ?> · <?= e($surahEnglish) ?><?php endif; ?>
                        <?php else: ?>
                            <?= e(implode(' · ', $surahTitles)) ?>
                        <?php endif; ?>
                    </p>
                    <div class="mf-cartouche-meta">
                        <span>Madinah Mushaf</span>
                        <span aria-hidden="true">•</span>
                        <span>Page <?= (int) $page ?> of <?= (int) $maxPage ?></span>
                        <?php if ($sajdaCount > 0): ?>
                            <span aria-hidden="true">•</span>
                            <span><?= $sajdaCount ?> sajda<?= $sajdaCount === 1 ? '' : 's' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mf-rule" aria-hidden="true">
                        <span></span>
                        <?= icon('logo', 'ico', 15) ?>
                        <span></span>
                    </div>
                    <?php if (!empty($basmala)): ?>
                        <p class="mf-basmala" lang="ar" dir="rtl"><?= e($basmala) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mf-stream">
                <?php foreach ($verses as $verse): ?>
                    <?php
                    $vSurah = (int) $verse['surah_number'];
                    $vNumber = (int) $verse['verse_number'];
                    $vAnchor = 'verse-' . $vSurah . '-' . $vNumber;
                    $readerUrl = surah_url($vSurah, $vNumber);
                    ?>
                    <article class="mf-ayah" id="<?= e($vAnchor) ?>">
                        <header class="mf-ayah-head">
                            <div class="mf-ayah-id">
                                <span class="mf-medallion" aria-hidden="true"><?= $vSurah ?>:<?= $vNumber ?></span>
                                <span class="mf-ayah-label">Ayah <?= $vNumber ?></span>
                                <?php if ((int) ($verse['sajda'] ?? 0) === 1): ?>
                                    <span class="mf-sajda">Sajda</span>
                                <?php endif; ?>
                            </div>
                            <a class="mini-btn" href="<?= e($readerUrl) ?>" title="Open in the surah reader" aria-label="Open <?= e($verse['name_simple']) ?> <?= $vSurah ?>:<?= $vNumber ?> in the reader">
                                <?= icon('arrow-right', 'ico', 15) ?>
                            </a>
                        </header>

                        <p class="mf-ayah-text" lang="ar" dir="rtl"><?= e($verse['text_uthmani']) ?></p>

                        <?php if (!empty($verse['translation_text'])): ?>
                            <div class="mf-ayah-translation">
                                <p class="mf-translation-text"><?= e($verse['translation_text']) ?></p>
                                <div class="mf-attrib">
                                    <span><?= e($editionName) ?><?= $editionBy !== '' ? ' · ' . e($editionBy) : '' ?></span>
                                    <a href="<?= e($readerUrl) ?>">Read in reader</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <nav class="mf-pager" aria-label="Mushaf pages">
                <a class="btn btn-ghost <?= $hasPrev ? '' : 'is-disabled' ?>" rel="prev"
                   href="<?= e(u('page.php?n=' . max($minPage, $page - 1))) ?>">&larr; Previous page</a>
                <span class="mf-pager-label">Page <?= (int) $page ?> of <?= (int) $maxPage ?></span>
                <a class="btn btn-primary <?= $hasNext ? '' : 'is-disabled' ?>" rel="next"
                   href="<?= e(u('page.php?n=' . min($maxPage, $page + 1))) ?>">Next page &rarr;</a>
            </nav>
        </div>

        <!-- ======================= page details rail ======================= -->
        <aside class="mf-rail">
            <div class="side-card">
                <div class="side-head">
                    <h2 class="side-title"><?= icon('book', 'ico', 16) ?> Page details</h2>
                    <span class="side-count"><?= (int) $ayahCount ?> ayat</span>
                </div>
                <dl class="mf-facts">
                    <div><dt>Mushaf page</dt><dd><?= (int) $page ?> of <?= (int) $maxPage ?></dd></div>
                    <div><dt>Juz</dt><dd><?= (int) ($juz ?? 1) ?></dd></div>
                    <div><dt>Surahs</dt><dd><?= count($surahsOnPage) ?></dd></div>
                    <div><dt>Translation</dt><dd><?= e($editionName) ?></dd></div>
                </dl>
            </div>

            <div class="side-card">
                <div class="side-head">
                    <h2 class="side-title"><?= icon('bookmark', 'ico', 16) ?> On this page</h2>
                </div>
                <div class="quick-list">
                    <?php foreach ($surahsOnPage as $surah): ?>
                        <a class="quick-item" href="<?= e(surah_url((int) $surah['number'])) ?>">
                            <span class="quick-id"><?= (int) $surah['number'] ?></span>
                            <span class="quick-body">
                                <span class="quick-name"><?= e($surah['simple']) ?></span>
                                <span class="quick-note">from ayah <?= (int) $surah['first'] ?></span>
                            </span>
                            <span class="quick-ar" lang="ar" dir="rtl"><?= e($surah['arabic']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="side-card">
                <div class="side-head">
                    <h2 class="side-title"><?= icon('arrow-right', 'ico', 16) ?> Keep reading</h2>
                </div>
                <div class="quick-list">
                    <a class="quick-item" href="<?= e(u('juz.php?n=' . (int) ($juz ?? 1))) ?>">
                        <span class="quick-id"><?= (int) ($juz ?? 1) ?></span>
                        <span class="quick-body"><span class="quick-name">Juz <?= (int) ($juz ?? 1) ?></span><span class="quick-note">continue this part</span></span>
                    </a>
                    <a class="quick-item" href="<?= e(surah_url($firstSurah)) ?>">
                        <span class="quick-id"><?= $firstSurah ?></span>
                        <span class="quick-body"><span class="quick-name">Surah reader</span><span class="quick-note">translation, audio, bookmarks</span></span>
                    </a>
                    <a class="quick-item" href="<?= e(u('settings.php')) ?>">
                        <span class="quick-id"><?= icon('download', 'ico', 14) ?></span>
                        <span class="quick-body"><span class="quick-name">Offline reading</span><span class="quick-note">download every surah</span></span>
                    </a>
                </div>
            </div>
        </aside>
    </div>
    <?php endif; ?>
</div>
