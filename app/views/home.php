<?php
/**
 * @var array $chapters
 * @var array $juzs
 * @var array $popular
 * @var array|null $progress
 * @var array|null $verseOfMoment
 * @var array|null $currentUser
 * @var array $settings
 * @var string $translationId
 */
$totalVerses = 0;
foreach ($chapters as $chapter) {
    $totalVerses += (int) $chapter['verses_count'];
}
?>
<section class="hero">
    <div class="container hero-inner">
        <p class="eyebrow">القرآن الكريم</p>
        <h1>Read the Quran, calmly.</h1>
        <p class="hero-lead">
            Clear Uthmani script, trusted translations, verse-by-verse recitation and a reader that
            remembers exactly where you stopped.
        </p>

        <form class="search-form hero-search" action="<?= e(u('search.php')) ?>" method="get" role="search"
              data-typeahead data-api-base="<?= e(u('api/quran/search.php')) ?>">
            <label class="visually-hidden" for="hero-q">Search the Quran</label>
            <svg class="search-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.2 4.2"/>
            </svg>
            <input id="hero-q" type="search" name="q" placeholder="Search a verse or surah"
                   autocomplete="off" enterkeyhint="search" aria-autocomplete="list" aria-expanded="false">
            <button class="btn btn-primary" type="submit">Search</button>
        </form>

        <div class="hero-actions">
            <?php if ($progress): ?>
                <a class="btn btn-primary btn-lg" href="<?= e(surah_url((int) $progress['surah_number'], (int) $progress['verse_number'])) ?>">
                    Continue reading · <?= e($progress['name_simple'] ?? '') ?> <?= (int) $progress['verse_number'] ?>
                </a>
            <?php else: ?>
                <div data-continue-slot class="continue-slot"></div>
                <a class="btn btn-primary btn-lg" data-continue-default href="<?= e(surah_url(1)) ?>">Start with Al-Fatihah</a>
            <?php endif; ?>
            <a class="btn btn-ghost btn-lg" href="<?= e(u('juz.php')) ?>">Browse by juz</a>
        </div>

        <dl class="hero-stats">
            <div><dt>Surahs</dt><dd>114</dd></div>
            <div><dt>Verses</dt><dd><?= number_format($totalVerses ?: 6236) ?></dd></div>
            <div><dt>Juz</dt><dd>30</dd></div>
            <div><dt>Translations</dt><dd><?= (int) ($translationCount ?? 1) ?></dd></div>
        </dl>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Popular surahs</h2>
            <a class="link-more" href="<?= e(u('surah.php?n=1')) ?>">Start reading →</a>
        </div>
        <div class="chip-row">
            <?php foreach ($popular as $chapter): ?>
                <a class="chip" href="<?= e(surah_url((int) $chapter['id'])) ?>">
                    <span class="chip-arabic" lang="ar" dir="rtl"><?= e($chapter['name_arabic']) ?></span>
                    <span class="chip-latin"><?= e($chapter['name_simple']) ?></span>
                    <span class="chip-meta"><?= (int) $chapter['verses_count'] ?> verses</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($verseOfMoment): ?>
<section class="section section-tight">
    <div class="container">
        <div class="verse-card">
            <p class="eyebrow">A verse to reflect on</p>
            <p class="verse-card-arabic" lang="ar" dir="rtl"><?= e($verseOfMoment['text_uthmani']) ?></p>
            <?php if (!empty($verseOfMoment['translation_text'])): ?>
                <p class="verse-card-translation"><?= e($verseOfMoment['translation_text']) ?></p>
            <?php endif; ?>
            <a class="verse-card-link" href="<?= e(surah_url((int) $verseOfMoment['surah_number'], (int) $verseOfMoment['verse_number'])) ?>">
                <?= e($verseOfMoment['name_simple']) ?> · <?= (int) $verseOfMoment['surah_number'] ?>:<?= (int) $verseOfMoment['verse_number'] ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" id="surahs">
    <div class="container">
        <div class="section-head">
            <h2>All 114 surahs</h2>
            <div class="inline-filter">
                <label class="visually-hidden" for="surah-filter">Filter surahs</label>
                <input id="surah-filter" type="search" placeholder="Filter by name or number" data-surah-filter autocomplete="off">
            </div>
        </div>

        <ul class="surah-grid" data-surah-list>
            <?php foreach ($chapters as $chapter): ?>
                <li>
                    <a class="surah-card" href="<?= e(surah_url((int) $chapter['id'])) ?>"
                       data-name="<?= e(mb_strtolower($chapter['name_simple'] . ' ' . $chapter['name_english'] . ' ' . $chapter['translated_name'] . ' ' . $chapter['name_arabic'])) ?>"
                       data-number="<?= (int) $chapter['id'] ?>">
                        <span class="surah-num" aria-hidden="true"><?= (int) $chapter['id'] ?></span>
                        <span class="surah-body">
                            <span class="surah-name-en"><?= e($chapter['name_simple']) ?></span>
                            <span class="surah-name-sub"><?= e($chapter['translated_name']) ?></span>
                            <span class="surah-meta">
                                <?= (int) $chapter['verses_count'] ?> verses ·
                                <?= $chapter['revelation_place'] === 'madinah' ? 'Madinah' : 'Makkah' ?>
                            </span>
                        </span>
                        <span class="surah-name-ar" lang="ar" dir="rtl"><?= e($chapter['name_arabic']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="empty-state" data-surah-empty hidden>No surah matches that filter.</p>
    </div>
</section>

<section class="section section-muted" id="juz">
    <div class="container">
        <div class="section-head">
            <h2>The 30 juz</h2>
            <a class="link-more" href="<?= e(u('juz.php')) ?>">View juz list →</a>
        </div>
        <ul class="juz-grid">
            <?php foreach ($juzs as $juz): ?>
                <li>
                    <a class="juz-chip" href="<?= e(u('juz.php?n=' . (int) $juz['number'])) ?>">
                        <span class="juz-num"><?= (int) $juz['number'] ?></span>
                        <span class="juz-range"><?= e($juz['first_name']) ?> <?= (int) $juz['first_verse'] ?> – <?= e($juz['last_name']) ?> <?= (int) $juz['last_verse'] ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php if (!$currentUser): ?>
<section class="section">
    <div class="container">
        <div class="cta-card">
            <div>
                <h2>Keep your place, on every device</h2>
                <p class="muted">
                    Create a free account to sync bookmarks and reading progress. Everything you do as a guest
                    stays on this device until you decide to import it.
                </p>
            </div>
            <div class="cta-actions">
                <a class="btn btn-primary" href="<?= e(u('register.php')) ?>">Create account</a>
                <a class="btn btn-ghost" href="<?= e(u('login.php')) ?>">Sign in</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
