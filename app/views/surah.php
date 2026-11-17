<?php
/**
 * Surah reader.
 *
 * @var array $chapter
 * @var array $chapters
 * @var array $verses
 * @var array|null $translation
 * @var array $translations
 * @var array $reciters
 * @var array|null $reciter
 * @var array|null $prevChapter
 * @var array|null $nextChapter
 * @var array<int,string> $savedKeys
 * @var array<int,array{color:?string,note:?string}> $marks
 * @var array<int,string> $markColors
 * @var string $basmala
 * @var array|null $progress
 * @var array $settings
 */
$savedLookup = array_flip($savedKeys);
$audioBase   = $reciter['audio_base'] ?? '';
$fontSize    = $settings['arabic_font_size'] ?? 'medium';
$fontSizes   = ['small' => 'A−', 'medium' => 'A', 'large' => 'A+', 'xlarge' => 'A++'];

$reciterMap = [];
foreach ($reciters as $item) {
    $reciterMap[(string) $item['id']] = (string) $item['audio_base'];
}
?>
<div class="reader"
     data-reader
     data-surah="<?= (int) $chapter['id'] ?>"
     data-surah-name="<?= e($chapter['name_simple']) ?>"
     data-verse-count="<?= (int) $chapter['verses_count'] ?>"
     data-translation="<?= e($translation['id'] ?? '') ?>"
     data-reciter="<?= e($reciter['id'] ?? '') ?>"
     data-audio-base="<?= e($audioBase) ?>"
     data-reciters="<?= e(json_encode($reciterMap, JSON_UNESCAPED_SLASHES)) ?>"
     data-font-size="<?= e($fontSize) ?>">

    <div class="reader-toolbar">
        <div class="container reader-toolbar-inner">
            <div class="toolbar-group">
                <a class="btn btn-ghost btn-sm" href="<?= e(u('')) ?>" aria-label="Back to home">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14.5 5.5L8 12l6.5 6.5"/>
                    </svg>
                    Home
                </a>

                <form class="jump-form" method="get" action="<?= e(u('surah.php')) ?>">
                    <label class="visually-hidden" for="jump-surah">Jump to surah</label>
                    <select id="jump-surah" name="n" data-jump-surah>
                        <?php foreach ($chapters as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === (int) $chapter['id'] ? 'selected' : '' ?>>
                                <?= (int) $item['id'] ?>. <?= e($item['name_simple']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-sm btn-ghost" type="submit">Go</button></noscript>
                </form>
            </div>

            <div class="toolbar-group">
                <div class="font-controls" role="group" aria-label="Arabic font size">
                    <?php foreach ($fontSizes as $key => $label): ?>
                        <button type="button"
                                class="font-btn <?= $key === $fontSize ? 'is-active' : '' ?>"
                                data-font-size="<?= e($key) ?>"
                                aria-pressed="<?= $key === $fontSize ? 'true' : 'false' ?>"
                                title="Arabic text size: <?= e($key) ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>

                <details class="translation-picker">
                    <summary class="btn btn-ghost btn-sm">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                            <path d="M4 6h9M8.5 4v2c0 3.4-2 6.3-4.5 7.5M6 10.5c1 2 2.6 3.4 4.5 4"/>
                            <path d="M13 19l3.5-8L20 19M14.3 16.4h4.4"/>
                        </svg>
                        <span class="hide-sm"><?= e($translation['english_name'] ?? 'Translation') ?></span>
                    </summary>
                    <div class="picker-panel">
                        <p class="picker-title">Translation</p>
                        <form method="get" action="<?= e(u('surah.php')) ?>" data-translation-form>
                            <input type="hidden" name="n" value="<?= (int) $chapter['id'] ?>">
                            <?php foreach ($translations as $item): ?>
                                <label class="radio-row">
                                    <input type="radio" name="translation" value="<?= e($item['id']) ?>"
                                        <?= ($translation['id'] ?? '') === $item['id'] ? 'checked' : '' ?>>
                                    <span>
                                        <strong lang="<?= e($item['language_code'] ?? 'en') ?>"><?= e($item['english_name']) ?></strong>
                                        <small class="muted"><?= e($item['language_name']) ?> · <?= e($item['author_name']) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <button class="btn btn-primary btn-sm btn-block" type="submit">Apply translation</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <div class="container">
        <header class="surah-header">
            <div class="surah-header-top">
                <span class="surah-badge" aria-hidden="true"><?= (int) $chapter['id'] ?></span>
                <div class="surah-titles">
                    <h1 class="surah-title-ar" lang="ar" dir="rtl"><?= e($chapter['name_arabic']) ?></h1>
                    <p class="surah-title-en">
                        <?= (int) $chapter['id'] ?>. <?= e($chapter['name_simple']) ?>
                        <span class="dot">·</span>
                        <span class="muted"><?= e($chapter['translated_name']) ?></span>
                    </p>
                </div>
            </div>

            <ul class="surah-meta-list">
                <li><span class="meta-label">Revelation</span><?= $chapter['revelation_place'] === 'madinah' ? 'Madinah' : 'Makkah' ?></li>
                <li><span class="meta-label">Verses</span><?= (int) $chapter['verses_count'] ?></li>
                <?php if ((int) $chapter['pages_from'] > 0): ?>
                    <li><span class="meta-label">Pages</span><?= (int) $chapter['pages_from'] ?>–<?= (int) $chapter['pages_to'] ?></li>
                <?php endif; ?>
                <?php if ((int) $chapter['revelation_order'] > 0): ?>
                    <li><span class="meta-label">Order</span><?= (int) $chapter['revelation_order'] ?></li>
                <?php endif; ?>
                <?php if ($translation): ?>
                    <li class="meta-translation">
                        <span class="meta-label">Translation</span>
                        <span lang="<?= e($translation['language_code'] ?? 'en') ?>"><?= e($translation['english_name']) ?></span>
                        <?php if (!empty($translation['author_name'])): ?>
                            <span class="muted">by <?= e($translation['author_name']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="surah-header-actions">
                <button type="button" class="btn btn-primary" data-play-surah aria-pressed="false" title="Play this surah">
                    <?= icon('play', 'ico ico--filled ico-play', 18) ?>
                    <?= icon('pause', 'ico ico--filled ico-pause', 18) ?>
                    <span data-play-surah-label>Play surah</span>
                </button>
                <a class="btn btn-ghost" href="<?= e(u('page.php?n=' . max(1, (int) $chapter['pages_from']))) ?>">Mushaf view</a>
            </div>
        </header>

        <?php if ($basmala !== ''): ?>
            <p class="basmala" lang="ar" dir="rtl"><?= e($basmala) ?></p>
        <?php endif; ?>

        <?php if ($verses === []): ?>
            <div class="empty-state card">
                <h2>Content is not ready yet</h2>
                <p>The Quran text has not been imported into this installation. Run the importer described in the README:</p>
                <pre class="code-block">php bin/import-quran.php</pre>
            </div>
        <?php else: ?>
            <ol class="verse-list" data-verse-list>
                <?php foreach ($verses as $verse):
                    $key       = (int) $verse['surah_number'] . ':' . (int) $verse['verse_number'];
                    $isSaved   = isset($savedLookup[$key]);
                    $audioUrl  = $audioBase !== '' ? $audioBase . '/' . (int) $verse['id'] . '.mp3' : '';
                    $mark      = $marks[(int) $verse['verse_number']] ?? null;
                    $markColor = (string) ($mark['color'] ?? '');
                    $markNote  = (string) ($mark['note'] ?? '');
                    ?>
                    <li class="verse <?= !empty($verse['sajda']) ? 'verse-sajda' : '' ?>"
                        id="verse-<?= (int) $verse['verse_number'] ?>"
                        data-verse-item
                        data-surah="<?= (int) $verse['surah_number'] ?>"
                        data-verse="<?= (int) $verse['verse_number'] ?>"
                        data-global="<?= (int) $verse['id'] ?>"
                        data-audio="<?= e($audioUrl) ?>"
                        data-saved="<?= $isSaved ? '1' : '0' ?>"
                        <?= $markColor !== '' ? 'data-highlight="' . e($markColor) . '"' : '' ?>>

                        <div class="verse-main">
                            <p class="verse-arabic" lang="ar" dir="rtl"><?= e($verse['text_uthmani']) ?><span class="ayah-marker" aria-label="Verse <?= (int) $verse['verse_number'] ?>"><?= e(\Quran\Support\Text::toArabicDigits((int) $verse['verse_number'])) ?></span></p>

                            <?php if (!empty($verse['translation_text'])): ?>
                                <p class="verse-translation"
                                   lang="<?= e($translation['language_code'] ?? 'en') ?>"
                                   dir="<?= e($translation['direction'] ?? 'ltr') ?>"><?= e($verse['translation_text']) ?></p>
                            <?php elseif ($translation): ?>
                                <p class="verse-translation muted">No text available in <?= e($translation['english_name']) ?> for this verse.</p>
                            <?php endif; ?>

                            <?php if ($markNote !== ''): ?>
                                <div class="verse-note" data-verse-note>
                                    <p class="verse-note-text"><?= e($markNote) ?></p>
                                    <button type="button" class="link-btn" data-action="note-edit">Edit note</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="verse-actions">
                            <a class="verse-ref" href="<?= e(surah_url((int) $verse['surah_number'], (int) $verse['verse_number'])) ?>"
                               title="Link to this verse"><?= (int) $verse['surah_number'] ?>:<?= (int) $verse['verse_number'] ?></a>
                            <button type="button" class="icon-btn sm" data-action="bookmark"
                                    aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                                    title="<?= $isSaved ? 'Remove bookmark' : 'Bookmark this verse' ?>">
                                <?= icon('bookmark', 'ico' . ($isSaved ? ' ico--filled' : ''), 17) ?>
                                <span class="visually-hidden">Bookmark verse <?= (int) $verse['verse_number'] ?></span>
                            </button>
                            <button type="button" class="icon-btn sm" data-action="highlight"
                                    aria-pressed="<?= $markColor !== '' ? 'true' : 'false' ?>"
                                    data-color="<?= e($markColor) ?>"
                                    aria-haspopup="true"
                                    title="<?= $markColor !== '' ? 'Change highlight' : 'Highlight this verse' ?>">
                                <?= icon('highlight', 'ico', 17) ?>
                                <span class="visually-hidden">Highlight verse <?= (int) $verse['verse_number'] ?></span>
                            </button>
                            <button type="button" class="icon-btn sm" data-action="note"
                                    aria-pressed="<?= $markNote !== '' ? 'true' : 'false' ?>"
                                    title="<?= $markNote !== '' ? 'Edit your note' : 'Add a note' ?>">
                                <?= icon('note', 'ico', 17) ?>
                                <span class="visually-hidden">Note on verse <?= (int) $verse['verse_number'] ?></span>
                            </button>
                            <button type="button" class="icon-btn sm" data-action="play"
                                    aria-pressed="false"
                                    title="Play verse <?= (int) $verse['verse_number'] ?>">
                                <?= icon('play', 'ico ico--filled ico-play', 17) ?>
                                <?= icon('pause', 'ico ico--filled ico-pause', 17) ?>
                                <span class="visually-hidden" data-play-label>Play verse <?= (int) $verse['verse_number'] ?></span>
                            </button>
                            <button type="button" class="icon-btn sm" data-action="copy" title="Copy verse with reference">
                                <?= icon('copy', 'ico', 17) ?>
                                <span class="visually-hidden">Copy verse <?= (int) $verse['verse_number'] ?></span>
                            </button>
                            <button type="button" class="icon-btn sm" data-action="share" title="Share this verse">
                                <?= icon('share', 'ico', 17) ?>
                                <span class="visually-hidden">Share verse <?= (int) $verse['verse_number'] ?></span>
                            </button>
                        </div>

                        <?php if (!empty($verse['sajda'])): ?>
                            <p class="sajda-note">۩ Sajda verse — prostration is recommended when reciting</p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <nav class="surah-pagination" aria-label="Surah navigation">
            <?php if ($prevChapter): ?>
                <a class="pagination-link prev" href="<?= e(surah_url((int) $prevChapter['id'])) ?>">
                    <span class="pagination-label">← Previous surah</span>
                    <span class="pagination-title"><?= (int) $prevChapter['id'] ?>. <?= e($prevChapter['name_simple']) ?></span>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <?php if ($nextChapter): ?>
                <a class="pagination-link next" href="<?= e(surah_url((int) $nextChapter['id'])) ?>">
                    <span class="pagination-label">Next surah →</span>
                    <span class="pagination-title"><?= (int) $nextChapter['id'] ?>. <?= e($nextChapter['name_simple']) ?></span>
                </a>
            <?php endif; ?>
        </nav>

        <p class="reader-attribution muted small">
            Arabic text: Uthmani script. Translation: <?= e($translation['english_name'] ?? '—') ?>
            <?php if (!empty($translation['author_name'])): ?>by <?= e($translation['author_name']) ?><?php endif; ?>.
            Recitation: <?= e($reciter['english_name'] ?? '—') ?>.
            <a href="<?= e(u('about.php')) ?>">Data sources and attribution</a>.
        </p>
    </div>

    <template data-highlight-template>
        <div class="highlight-pop" data-highlight-pop role="dialog" aria-label="Highlight colour">
            <div class="highlight-swatches">
                <?php foreach ($markColors as $color): ?>
                    <button type="button" class="swatch" data-color="<?= e($color) ?>"
                            title="<?= e(ucfirst($color)) ?>"
                            aria-label="Highlight in <?= e($color) ?>"></button>
                <?php endforeach; ?>
                <button type="button" class="swatch swatch-none" data-color=""
                        title="Remove highlight" aria-label="Remove highlight">
                    <?= icon('close', 'ico', 14) ?>
                </button>
            </div>
        </div>
    </template>

    <template data-note-template>
        <form class="verse-note-editor" data-note-editor>
            <label class="visually-hidden" data-note-label>Your note</label>
            <textarea rows="3" maxlength="<?= (int) \Quran\Repositories\MarkRepository::MAX_NOTE_LENGTH ?>" data-note-input
                      placeholder="Write a private note about this verse…"></textarea>
            <div class="note-editor-actions">
                <button type="submit" class="btn btn-primary btn-sm">Save note</button>
                <button type="button" class="btn btn-ghost btn-sm" data-note-cancel>Cancel</button>
                <button type="button" class="btn btn-ghost btn-sm note-delete" data-note-delete hidden>
                    <?= icon('trash', 'ico', 15) ?> Delete
                </button>
            </div>
        </form>
    </template>

    <div class="player" data-player hidden aria-label="Recitation player">
        <div class="container player-inner">
            <div class="player-main">
                <div class="player-controls">
                    <button type="button" class="icon-btn" data-player-prev aria-label="Previous verse">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                    </button>
                    <button type="button" class="player-play" data-player-toggle aria-label="Play">
                        <svg class="icon-play" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l10-6.5z"/></svg>
                        <svg class="icon-pause" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M7.5 5.5h3.2v13H7.5zM13.3 5.5h3.2v13h-3.2z"/></svg>
                    </button>
                    <button type="button" class="icon-btn" data-player-next aria-label="Next verse">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </div>

                <div class="player-track">
                    <div class="player-meta">
                        <span class="player-verse" data-player-label>Verse</span>
                        <span class="player-remaining" data-player-remaining hidden></span>
                        <span class="player-reciter muted small"><?= e($reciter['english_name'] ?? '') ?></span>
                    </div>
                    <div class="player-scrub" data-player-scrub role="slider" tabindex="0" aria-label="Recitation progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="player-bar"><span class="player-progress" data-player-progress></span></div>
                    </div>
                </div>

                <div class="player-time small muted"><span data-player-time>0:00</span></div>
            </div>

            <div class="player-side">
                <label class="visually-hidden" for="reciter-select">Reciter</label>
                <select id="reciter-select" data-reciter-select>
                    <?php foreach ($reciters as $item): ?>
                        <option value="<?= e($item['id']) ?>" <?= ($reciter['id'] ?? '') === $item['id'] ? 'selected' : '' ?>>
                            <?= e($item['english_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="icon-btn sm" data-player-close aria-label="Stop recitation" title="Stop recitation">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </div>
        <audio data-audio-element preload="auto" playsinline></audio>
    </div>
</div>
