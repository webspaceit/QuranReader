<?php
/**
 * Reciters & audio — directory of the recitation editions the reader streams.
 *
 * @var array<int,array<string,mixed>> $reciters
 * @var int $totalReciters
 * @var array<string,int> $styles
 * @var string $query
 * @var string $activeStyle
 * @var array<string,mixed>|null $featured
 * @var array<string,mixed>|null $sampleVerse
 * @var string $audioHost
 * @var int $totalVerses
 */
$listen = static function (array $reciter, int $surah = 1): string {
    return u('surah.php?n=' . $surah) . '&reciter=' . rawurlencode((string) $reciter['id']);
};

$listUrl = static function (string $style, string $q): string {
    $params = [];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($style !== '') {
        $params['style'] = $style;
    }
    return u('reciters.php') . ($params === [] ? '' : '?' . http_build_query($params)) . '#directory';
};

$initial    = static fn(string $arabic): string => mb_substr($arabic, 0, 1);
$bitrate    = (int) ($featured['bitrate'] ?? 128);
$ayahCount  = $totalVerses > 0 ? $totalVerses : 6236;
?>
<div class="home-shell">
    <div class="container home-wrap">

        <!-- ============================== hero ============================== -->
        <section class="rc-hero" aria-labelledby="rc-title">

            <div class="rc-crumb">
                <div class="rc-crumb-left">
                    <span class="rc-crumb-lead">Sanctuary soundscape</span>
                    <span aria-hidden="true">/</span>
                    <span>Reciters &amp; audio</span>
                </div>
                <span class="rc-live">
                    <i aria-hidden="true"></i>
                    <?= (int) $totalReciters ?> reciters · <?= number_format($ayahCount) ?> ayahs each
                </span>
            </div>

            <div class="rc-hero-grid">
                <?php if ($featured): ?>
                    <article class="rc-feature">
                        <svg class="rc-feature-art" viewBox="0 0 400 260" fill="none" stroke="currentColor" aria-hidden="true">
                            <circle cx="200" cy="130" r="86" stroke-dasharray="4 5" stroke-width="1.2"/>
                            <circle cx="200" cy="130" r="130" stroke-width="1"/>
                            <polygon points="200,60 222,118 282,130 222,142 200,200 178,142 118,130 178,118" stroke-width="1"/>
                        </svg>

                        <div class="rc-feature-inner">
                            <div class="rc-feature-tags">
                                <span class="rc-feature-tag"><?= icon('play', 'ico', 12) ?> Default reciter</span>
                                <?php if ((string) $featured['style'] !== ''): ?>
                                    <span class="rc-feature-tag is-ghost"><?= e($featured['style']) ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="rc-feature-ar" lang="ar" dir="rtl"><?= e($featured['name']) ?></p>
                            <h1 class="rc-feature-name" id="rc-title"><?= e($featured['english_name']) ?></h1>
                            <p class="rc-feature-note">
                                Every ayah is a separate <?= $bitrate ?> kbps MP3, streamed on demand by the reader and
                                cached for offline listening.
                            </p>

                            <dl class="rc-feature-stats">
                                <div><dd>114</dd><dt>Surahs</dt></div>
                                <div><dd><?= number_format($ayahCount) ?></dd><dt>Ayahs</dt></div>
                                <div><dd><?= $bitrate ?>k</dd><dt>Bitrate</dt></div>
                            </dl>

                            <div class="rc-feature-actions">
                                <a class="btn btn-primary" href="<?= e($listen($featured, 1)) ?>">
                                    <?= icon('play', 'ico', 16) ?> Listen to Al-Fatihah
                                </a>
                                <a class="btn btn-ghost" href="<?= e(u('page.php?n=1')) ?>">
                                    <?= icon('book', 'ico', 16) ?> Mushaf page
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endif; ?>

                <aside class="rc-preview" aria-label="How the audio is served">
                    <?php if ($sampleVerse): ?>
                        <div class="rc-preview-head">
                            <span class="rc-preview-kicker"><?= icon('play', 'ico', 13) ?> Sample ayah</span>
                            <span class="rc-ref">
                                <?= e($sampleVerse['name_simple']) ?> · <?= (int) $sampleVerse['surah_number'] ?>:<?= (int) $sampleVerse['verse_number'] ?>
                            </span>
                        </div>
                        <p class="rc-preview-ar" lang="ar" dir="rtl"><?= e($sampleVerse['text_uthmani']) ?></p>
                        <?php if (!empty($sampleVerse['translation_text'])): ?>
                            <p class="rc-preview-tr"><?= e($sampleVerse['translation_text']) ?></p>
                        <?php endif; ?>
                        <a class="rc-preview-cta" href="<?= e(u('surah.php?n=' . (int) $sampleVerse['surah_number']) . '&reciter=' . rawurlencode((string) ($featured['id'] ?? '')) . '#verse-' . (int) $sampleVerse['verse_number']) ?>">
                            <?= icon('play', 'ico', 14) ?> Play this ayah in the reader
                        </a>
                    <?php endif; ?>

                    <ul class="rc-facts">
                        <li><span>Delivery</span><strong>One MP3 per ayah</strong></li>
                        <li><span>Bitrate</span><strong><?= $bitrate ?> kbps</strong></li>
                        <li><span>Streamed from</span><strong><?= e($audioHost) ?></strong></li>
                        <li><span>Offline</span><strong>Cached by the reader</strong></li>
                    </ul>
                </aside>
            </div>
        </section>

        <!-- ============================ filter hub ========================== -->
        <section class="rc-hub" aria-label="Find a reciter">
            <form class="rc-search" method="get" action="<?= e(u('reciters.php')) ?>" role="search">
                <label class="visually-hidden" for="reciter-q">Search reciters by name</label>
                <svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.2 4.2"/>
                </svg>
                <input id="reciter-q" type="search" name="q" value="<?= e($query) ?>"
                       placeholder="Search by name — English or Arabic" autocomplete="off" enterkeyhint="search">
                <?php if ($activeStyle !== ''): ?>
                    <input type="hidden" name="style" value="<?= e($activeStyle) ?>">
                <?php endif; ?>
                <button class="btn btn-primary btn-sm" type="submit">Search</button>
            </form>

            <div class="rc-result">
                Showing <strong><?= count($reciters) ?></strong> of <?= (int) $totalReciters ?> reciters<?php
                if ($query !== '') {
                    echo ' for “' . e($query) . '”';
                }
                ?>
            </div>

            <div class="rc-chips">
                <a class="rc-chip <?= $activeStyle === '' ? 'is-active' : '' ?>" href="<?= e($listUrl('', $query)) ?>">
                    All reciters <span><?= (int) $totalReciters ?></span>
                </a>
                <?php foreach ($styles as $label => $count): ?>
                    <a class="rc-chip <?= $activeStyle === $label ? 'is-active' : '' ?>" href="<?= e($listUrl((string) $label, $query)) ?>">
                        <?= e($label) ?> <span><?= (int) $count ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============================= directory ========================== -->
        <section class="rc-directory" id="directory" aria-labelledby="rc-dir-title">
            <header class="rc-head">
                <div>
                    <span class="rc-kicker">Recitation editions</span>
                    <h2 class="rc-title" id="rc-dir-title">The reciter directory</h2>
                    <p class="rc-sub">
                        Each edition is a complete per-ayah recording. Pick a reciter, then open any surah — your choice
                        follows you until you change it.
                    </p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= e(u('settings.php')) ?>">Set a default</a>
            </header>

            <?php if ($reciters === []): ?>
                <div class="card empty-state">
                    <h3>No reciter matches that search</h3>
                    <p>Try a different spelling, or <a href="<?= e(u('reciters.php')) ?>">show every reciter</a>.</p>
                </div>
            <?php else: ?>
                <ul class="rc-grid">
                    <?php foreach ($reciters as $reciter): ?>
                        <li class="rc-card">
                            <div class="rc-card-top">
                                <span class="rc-mono" lang="ar" dir="rtl" aria-hidden="true"><?= e($initial((string) $reciter['name'])) ?></span>
                                <?php if ((string) $reciter['style'] !== ''): ?>
                                    <span class="rc-badge"><?= e($reciter['style']) ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="rc-card-ar" lang="ar" dir="rtl"><?= e($reciter['name']) ?></p>
                            <h3 class="rc-card-name"><?= e($reciter['english_name']) ?></h3>
                            <p class="rc-card-meta">
                                <?= (int) $reciter['bitrate'] ?> kbps · <?= number_format($ayahCount) ?> ayah files
                                <?php if ((int) $reciter['is_default'] === 1): ?>
                                    · <span class="rc-default">Default</span>
                                <?php endif; ?>
                            </p>

                            <div class="rc-card-actions">
                                <a class="btn btn-primary btn-sm" href="<?= e($listen($reciter, 1)) ?>">
                                    <?= icon('play', 'ico', 15) ?> Listen
                                </a>
                                <a class="mini-btn" href="<?= e(u('surah.php?n=36') . '&reciter=' . rawurlencode((string) $reciter['id'])) ?>"
                                   title="Listen to Ya-Sin with this reciter" aria-label="Listen to Surah Ya-Sin with <?= e($reciter['english_name']) ?>">
                                    <?= icon('book', 'ico', 15) ?>
                                </a>
                                <a class="mini-btn" href="<?= e(u('settings.php')) ?>"
                                   title="Choose this reciter as your default" aria-label="Set <?= e($reciter['english_name']) ?> as your default reciter">
                                    <?= icon('check', 'ico', 15) ?>
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- =========================== how it works ======================== -->
        <section class="rc-band" aria-label="About recitation audio">
            <article class="rc-band-card">
                <span class="rc-band-icon"><?= icon('play', 'ico', 18) ?></span>
                <div>
                    <h3>Verse-by-verse files</h3>
                    <p>
                        Audio is fetched one ayah at a time, so the reader can start instantly and resume exactly where
                        you stopped instead of loading a whole surah.
                    </p>
                </div>
            </article>
            <article class="rc-band-card">
                <span class="rc-band-icon is-gold"><?= icon('download', 'ico', 18) ?></span>
                <div>
                    <h3>Offline after first play</h3>
                    <p>
                        The service worker stores recitations you have played, so a surah you listened to once keeps
                        working without a connection.
                    </p>
                </div>
            </article>
            <article class="rc-band-card">
                <span class="rc-band-icon"><?= icon('check', 'ico', 18) ?></span>
                <div>
                    <h3>One default, every page</h3>
                    <p>
                        Your default reciter is saved in <a href="<?= e(u('settings.php')) ?>">settings</a> — signed in it
                        follows your account, as a guest it stays in this browser.
                    </p>
                </div>
            </article>
        </section>
    </div>
</div>
