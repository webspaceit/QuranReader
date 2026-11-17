<?php
/**
 * Home — "Quranic Sanctuary" layout.
 *
 * @var array $chapters
 * @var array $juzs
 * @var array $popular
 * @var array|null $progress
 * @var array|null $verseOfMoment
 * @var array|null $currentUser
 * @var array $settings
 * @var string $translationId
 * @var int $bookmarkCount
 * @var int $translationCount
 */
$totalVerses = 0;
$byId        = [];
foreach ($chapters as $chapter) {
    $byId[(int) $chapter['id']] = $chapter;
    $totalVerses += (int) $chapter['verses_count'];
}

/** "24m ago" style label for the last-read timestamp. */
$ago = static function (?string $sqlDate): string {
    if ($sqlDate === null || $sqlDate === '') {
        return '';
    }
    $ts = strtotime($sqlDate);
    if ($ts === false) {
        return '';
    }
    $minutes = (int) floor((time() - $ts) / 60);
    if ($minutes < 1) {
        return 'just now';
    }
    if ($minutes < 60) {
        return $minutes . 'm ago';
    }
    if ($minutes < 1440) {
        return (int) floor($minutes / 60) . 'h ago';
    }
    return (int) floor($minutes / 1440) . 'd ago';
};

$reading = null;
if ($progress) {
    $surahId = (int) $progress['surah_number'];
    $chapter = $byId[$surahId] ?? null;
    $total   = $chapter ? (int) $chapter['verses_count'] : 0;
    $verse   = (int) $progress['verse_number'];
    $reading = [
        'surah'  => $surahId,
        'verse'  => $verse,
        'name'   => (string) ($progress['name_simple'] ?? ($chapter['name_simple'] ?? 'Surah ' . $surahId)),
        'arabic' => (string) ($progress['name_arabic'] ?? ($chapter['name_arabic'] ?? '')),
        'total'  => $total,
        'page'   => $chapter ? (int) $chapter['pages_from'] : 0,
        'pct'    => $total > 0 ? max(1, min(100, (int) round($verse / $total * 100))) : 0,
        'when'   => $ago($progress['updated_at'] ?? null),
    ];
}

$placeLabel = static fn(string $place): string => $place === 'madinah' ? 'Madinan' : 'Meccan';
?>
<div class="home-shell">
    <div class="container home-wrap">

        <!-- ============================ masthead ============================ -->
        <section class="masthead" aria-labelledby="masthead-title">
            <div class="plaque">
                <svg class="plaque-motif" viewBox="0 0 200 200" fill="currentColor" aria-hidden="true">
                    <path d="M100 0 L122 38 L165 24 L160 69 L200 85 L176 122 L190 165 L145 160 L129 200 L92 176 L55 190 L60 145 L20 129 L44 92 L30 55 L75 60 Z"/>
                </svg>

                <div class="plaque-inner">
                    <div class="plaque-top">
                        <span class="badge-live"><?= $reading ? 'Continue your reading' : 'Begin your reading' ?></span>
                        <?php if ($reading && $reading['when'] !== ''): ?>
                            <span class="chip-soft">Last read <?= e($reading['when']) ?></span>
                        <?php else: ?>
                            <span class="chip-soft">114 surahs · 30 juz</span>
                        <?php endif; ?>
                    </div>

                    <div class="plaque-names">
                        <div>
                            <span class="plaque-kicker"><?= $reading ? 'Surah ' . (int) $reading['surah'] . ' · ' . e($reading['name']) : 'Start here' ?></span>
                            <h1 class="plaque-title" id="masthead-title">
                                <?= $reading ? e($reading['name']) : 'Al-Fatihah' ?>
                            </h1>
                        </div>
                        <p class="plaque-arabic" lang="ar" dir="rtl">
                            <?= e($reading ? $reading['arabic'] : 'الفَاتِحَة') ?>
                        </p>
                    </div>

                    <div class="progress-panel">
                        <?php if ($reading): ?>
                            <div class="progress-meta">
                                <span class="strong">
                                    Ayah <?= (int) $reading['verse'] ?><?= $reading['total'] > 0 ? ' of ' . (int) $reading['total'] : '' ?><?= $reading['page'] > 0 ? ' · Page ' . (int) $reading['page'] : '' ?>
                                </span>
                                <span class="progress-pct"><?= (int) $reading['pct'] ?>% of surah</span>
                            </div>
                            <div class="progress-track" role="progressbar" aria-valuenow="<?= (int) $reading['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Reading progress in this surah">
                                <div class="progress-fill" style="width: <?= (int) $reading['pct'] ?>%"></div>
                            </div>
                        <?php else: ?>
                            <div class="progress-meta">
                                <span class="strong">Open the first surah</span>
                                <span class="progress-pct">7 ayahs · Page 1</span>
                            </div>
                            <div class="progress-track" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Reading progress">
                                <div class="progress-fill" style="width: 0%"></div>
                            </div>
                        <?php endif; ?>
                        <p class="progress-quote">
                            <?php if ($verseOfMoment && !empty($verseOfMoment['translation_text'])): ?>
                                “<?= e(mb_substr((string) $verseOfMoment['translation_text'], 0, 150)) ?><?= mb_strlen((string) $verseOfMoment['translation_text']) > 150 ? '…' : '' ?>”
                            <?php else: ?>
                                Read in clear Uthmani script, with trusted translations and verse-by-verse recitation.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="action-deck">
                    <div class="deck-actions">
                        <?php if ($reading): ?>
                            <a class="btn btn-primary" href="<?= e(surah_url((int) $reading['surah'], (int) $reading['verse'])) ?>">
                                <?= icon('play', 'ico', 17) ?> Resume recitation
                            </a>
                            <a class="btn btn-ghost" href="<?= e(u('page.php?n=' . max(1, (int) $reading['page']))) ?>">
                                <?= icon('book', 'ico', 17) ?> Mushaf mode
                            </a>
                        <?php else: ?>
                            <div data-continue-slot class="continue-slot"></div>
                            <a class="btn btn-primary" data-continue-default href="<?= e(surah_url(1)) ?>">
                                <?= icon('play', 'ico', 17) ?> Start with Al-Fatihah
                            </a>
                            <a class="btn btn-ghost" href="<?= e(u('page.php?n=1')) ?>">
                                <?= icon('book', 'ico', 17) ?> Mushaf mode
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="deck-icons">
                        <a class="mini-btn" href="<?= e(u('bookmarks.php')) ?>" title="Bookmarks and notes" aria-label="Bookmarks and notes">
                            <?= icon('bookmark', 'ico', 16) ?>
                        </a>
                        <a class="mini-btn deck-search-btn" href="<?= e(u('search.php')) ?>" title="Search the Quran" aria-label="Search the Quran">
                            <?= icon('search', 'ico', 16) ?>
                        </a>
                    </div>

                    <form class="deck-search" action="<?= e(u('search.php')) ?>" method="get" role="search" data-surah-picker>
                        <label class="visually-hidden" for="deck-q">Search the Quran</label>
                        <svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.2 4.2"/></svg>
                        <input id="deck-q" type="search" name="q" placeholder="Search a surah or ayah"
                               autocomplete="off" enterkeyhint="search" aria-autocomplete="list" aria-expanded="false">
                    </form>
                </div>
            </div>

            <div class="side-stack">
                <?php if ($verseOfMoment): ?>
                    <div class="ayah-card">
                        <div class="ayah-head">
                            <span class="ayah-label"><?= icon('book', 'ico', 14) ?> Ayah to reflect on</span>
                            <span class="ayah-ref">
                                <?= e($verseOfMoment['name_simple']) ?> · <?= (int) $verseOfMoment['surah_number'] ?>:<?= (int) $verseOfMoment['verse_number'] ?>
                            </span>
                        </div>
                        <p class="ayah-arabic" lang="ar" dir="rtl"><?= e($verseOfMoment['text_uthmani']) ?></p>
                        <?php if (!empty($verseOfMoment['translation_text'])): ?>
                            <p class="ayah-translation"><?= e($verseOfMoment['translation_text']) ?></p>
                        <?php endif; ?>
                        <div class="ayah-tools">
                            <a class="btn btn-ghost btn-sm" href="<?= e(surah_url((int) $verseOfMoment['surah_number'], (int) $verseOfMoment['verse_number'])) ?>">
                                Read in context
                            </a>
                            <a class="mini-btn" href="<?= e(surah_url((int) $verseOfMoment['surah_number'], (int) $verseOfMoment['verse_number'])) ?>" title="Listen on the reader" aria-label="Listen on the reader">
                                <?= icon('play', 'ico', 15) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <dl class="stat-card">
                    <div class="stat-cell"><dd class="stat-val">114</dd><dt class="stat-lbl">Surahs</dt></div>
                    <div class="stat-cell"><dd class="stat-val"><?= number_format($totalVerses ?: 6236) ?></dd><dt class="stat-lbl">Ayahs</dt></div>
                    <div class="stat-cell"><dd class="stat-val">30</dd><dt class="stat-lbl">Juz</dt></div>
                    <div class="stat-cell"><dd class="stat-val"><?= (int) ($translationCount ?? 1) ?></dd><dt class="stat-lbl">Editions</dt></div>
                </dl>
            </div>
        </section>

        <!-- ============================ filter bar ========================== -->
        <section class="filter-bar" aria-label="Browse the Quran">
            <div class="tab-row">
                <a class="tab is-active" href="#surahs">All Surahs <span class="tab-count"><?= count($chapters) ?></span></a>
                <a class="tab" href="<?= e(u('juz.php')) ?>">By Juz <span class="tab-count">30</span></a>
                <a class="tab" href="<?= e(u('page.php?n=1')) ?>">Mushaf Pages</a>
                <a class="tab" href="<?= e(u('bookmarks.php')) ?>">Saved Verses</a>
            </div>
            <div class="filter-tools">
                <div class="inline-filter">
                    <label class="visually-hidden" for="surah-filter">Filter surahs</label>
                    <input id="surah-filter" type="search" placeholder="Filter by name or number" data-surah-filter autocomplete="off">
                </div>
                <div class="filter-search">
                    <form class="search-form" action="<?= e(u('search.php')) ?>" method="get" role="search"
                          data-typeahead data-api-base="<?= e(u('api/quran/search.php')) ?>">
                        <label class="visually-hidden" for="hero-q">Search the Quran</label>
                        <svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.2 4.2"/>
                        </svg>
                        <input id="hero-q" type="search" name="q" placeholder="Search verses"
                               autocomplete="off" enterkeyhint="search" aria-autocomplete="list" aria-expanded="false">
                    </form>
                </div>
            </div>
        </section>

        <!-- ======================= surahs + sidebar ======================== -->
        <section class="content-grid" id="surahs">
            <div>
                <div class="list-head">
                    <span>Showing <strong>1 – <?= count($chapters) ?></strong> of <?= count($chapters) ?> surahs</span>
                    <span class="legend"><i></i> Meccan <i class="gold"></i> Madinan</span>
                </div>

                <ul class="surah-matrix" data-surah-list>
                    <?php foreach ($chapters as $chapter): ?>
                        <li>
                            <a class="surah-card" href="<?= e(surah_url((int) $chapter['id'])) ?>"
                               data-name="<?= e(mb_strtolower($chapter['name_simple'] . ' ' . $chapter['name_english'] . ' ' . $chapter['translated_name'] . ' ' . $chapter['name_arabic'])) ?>"
                               data-number="<?= (int) $chapter['id'] ?>">
                                <span class="card-top">
                                    <span class="card-id">
                                        <span class="rub-badge" aria-hidden="true">
                                            <svg viewBox="0 0 100 100" fill="currentColor"><rect x="18" y="18" width="64" height="64" rx="6"/><rect x="18" y="18" width="64" height="64" rx="6" transform="rotate(45 50 50)"/></svg>
                                            <span><?= (int) $chapter['id'] ?></span>
                                        </span>
                                        <span class="card-titles">
                                            <span class="card-name"><?= e($chapter['name_simple']) ?></span>
                                            <span class="card-sub"><?= e($chapter['translated_name']) ?></span>
                                        </span>
                                    </span>
                                    <span class="card-arabic" lang="ar" dir="rtl"><?= e($chapter['name_arabic']) ?></span>
                                </span>
                                <span class="card-meta">
                                    <span class="meta-chips">
                                        <span class="chip-place <?= $chapter['revelation_place'] === 'madinah' ? 'is-madinah' : '' ?>"><?= e($placeLabel((string) $chapter['revelation_place'])) ?></span>
                                        <span><?= (int) $chapter['verses_count'] ?> ayahs</span>
                                        <span>·</span>
                                        <span>Page <?= (int) $chapter['pages_from'] ?><?= (int) $chapter['pages_to'] > (int) $chapter['pages_from'] ? '–' . (int) $chapter['pages_to'] : '' ?></span>
                                    </span>
                                    <span class="card-actions">
                                        <span class="mini-btn" aria-hidden="true"><?= icon('arrow-right', 'ico', 15) ?></span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="empty-state" data-surah-empty hidden>No surah matches that filter.</p>
            </div>

            <aside class="side-stack" aria-label="Quick access">
                <div class="side-card">
                    <div class="side-head">
                        <h2 class="side-title"><?= icon('book', 'ico', 16) ?> Juz quick jump</h2>
                        <span class="side-count">30 parts</span>
                    </div>
                    <p class="side-note">Jump straight into any section of the Mushaf.</p>
                    <div class="juz-mosaic">
                        <?php foreach ($juzs as $juz): ?>
                            <a class="juz-key" href="<?= e(u('juz.php?n=' . (int) $juz['number'])) ?>"><?= (int) $juz['number'] ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="side-card">
                    <div class="side-head">
                        <h2 class="side-title"><?= icon('bookmark', 'ico', 16) ?> Frequently read</h2>
                        <a class="side-count" href="<?= e(u('surah.php?n=1')) ?>">Start here</a>
                    </div>
                    <div class="quick-list">
                        <?php foreach (array_slice($popular, 0, 5) as $chapter): ?>
                            <a class="quick-item" href="<?= e(surah_url((int) $chapter['id'])) ?>">
                                <span class="quick-id"><?= (int) $chapter['id'] ?></span>
                                <span class="quick-body">
                                    <span class="quick-name"><?= e($chapter['name_simple']) ?></span>
                                    <span class="quick-note"><?= (int) $chapter['verses_count'] ?> ayahs · <?= e($placeLabel((string) $chapter['revelation_place'])) ?></span>
                                </span>
                                <span class="quick-ar" lang="ar" dir="rtl"><?= e($chapter['name_arabic']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!$currentUser): ?>
                    <div class="guest-card">
                        <span class="guest-kicker">Keep your place</span>
                        <h4>Sync across devices</h4>
                        <p>Bookmarks, notes and reading progress follow you when you sign in. Guest data stays on this device until you import it.</p>
                        <div class="guest-actions">
                            <a class="btn" href="<?= e(u('register.php')) ?>">Create account</a>
                            <a class="btn btn-ghost" href="<?= e(u('login.php')) ?>">Sign in</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="guest-card">
                        <span class="guest-kicker">Your library</span>
                        <h4><?= e($currentUser['full_name'] ?: 'Your account') ?></h4>
                        <p><?= (int) ($bookmarkCount ?? 0) ?> saved <?= (int) ($bookmarkCount ?? 0) === 1 ? 'verse' : 'verses' ?>. Highlights and notes appear on the bookmarks page.</p>
                        <div class="guest-actions">
                            <a class="btn" href="<?= e(u('bookmarks.php')) ?>">Open bookmarks</a>
                            <a class="btn btn-ghost" href="<?= e(u('settings.php')) ?>">Settings</a>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <!-- ========================= authenticity ========================== -->
        <section class="auth-band">
            <div class="auth-copy">
                <span class="auth-kicker"><?= icon('check', 'ico', 13) ?> Standard textual authenticity</span>
                <h2 class="auth-title">Mushaf al-Madinah Uthmanic script</h2>
                <p class="auth-text">
                    114 surahs and <?= number_format($totalVerses ?: 6236) ?> ayahs are served from imported Uthmani text with
                    trusted translations, verse-by-verse recitation and full offline reading. Quran text, translations and page
                    data come from the public Al Quran Cloud API; recitations stream from the Islamic Network CDN.
                </p>
            </div>
            <div class="auth-actions">
                <a class="btn btn-ghost" href="<?= e(u('about.php')) ?>">Data sources</a>
                <a class="btn btn-primary" href="<?= e(u('settings.php')) ?>">Download offline</a>
            </div>
        </section>
    </div>
</div>
