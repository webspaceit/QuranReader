<?php
/**
 * @var string $query
 * @var array{total:int,results:array<int,array<string,mixed>>} $result
 * @var array $chapterMatches
 * @var array|null $translation
 * @var array $translations
 * @var array $chapters
 * @var int $surahFilter
 * @var int $juzFilter
 */
$hasQuery = $query !== '';
?>
<section class="section">
    <div class="container container-narrow">
        <header class="page-head">
            <h1>Search the Quran</h1>
            <p class="muted">Search Arabic text, or the selected translation. Diacritics are ignored, so
                <span lang="ar" dir="rtl">الرحمن</span> finds <span lang="ar" dir="rtl">ٱلرَّحْمَٰن</span>.</p>
        </header>

        <form class="search-panel" method="get" action="<?= e(u('search.php')) ?>" role="search"
              data-search-page data-api-base="<?= e(u('api/quran/search.php')) ?>">
            <div class="search-panel-row">
                <label class="visually-hidden" for="q">Search terms</label>
                <div class="search-input-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.2 4.2"/>
                    </svg>
                    <input id="q" type="search" name="q" value="<?= e($query) ?>" placeholder="e.g. mercy, patience, الرحمن"
                           autocomplete="off" enterkeyhint="search" data-search-input autofocus>
                </div>
                <button class="btn btn-primary" type="submit">Search</button>
            </div>

            <details class="search-filters" <?= ($surahFilter > 0 || $juzFilter > 0) ? 'open' : '' ?>>
                <summary>Filters</summary>
                <div class="filter-grid">
                    <div class="field">
                        <label for="filter-surah">Surah</label>
                        <select id="filter-surah" name="surah" data-filter-surah>
                            <option value="0">All surahs</option>
                            <?php foreach ($chapters as $chapter): ?>
                                <option value="<?= (int) $chapter['id'] ?>" <?= (int) $chapter['id'] === $surahFilter ? 'selected' : '' ?>>
                                    <?= (int) $chapter['id'] ?>. <?= e($chapter['name_simple']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filter-juz">Juz</label>
                        <select id="filter-juz" name="juz">
                            <option value="0">All juz</option>
                            <?php for ($i = 1; $i <= 30; $i++): ?>
                                <option value="<?= $i ?>" <?= $i === $juzFilter ? 'selected' : '' ?>>Juz <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filter-translation">Translation</label>
                        <select id="filter-translation" name="translation">
                            <?php foreach ($translations as $item): ?>
                                <option value="<?= e($item['id']) ?>" <?= ($translation['id'] ?? '') === $item['id'] ? 'selected' : '' ?>>
                                    <?= e($item['english_name']) ?> (<?= e($item['language_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </details>
        </form>

        <div data-search-status class="search-status" aria-live="polite">
            <?php if ($hasQuery && mb_strlen($query, 'UTF-8') >= 2): ?>
                <p class="muted">
                    <?php if ($result['total'] === 0): ?>
                        No verses matched “<?= e($query) ?>”.
                    <?php else: ?>
                        <strong><?= number_format($result['total']) ?></strong>
                        verse<?= $result['total'] === 1 ? '' : 's' ?> matched “<?= e($query) ?>”
                        <?= $translation ? 'in ' . e($translation['english_name']) : '' ?>.
                        <?= $result['total'] > count($result['results']) ? 'Showing the first ' . count($result['results']) . '.' : '' ?>
                    <?php endif; ?>
                </p>
            <?php elseif ($hasQuery): ?>
                <p class="muted">Type at least two characters to search.</p>
            <?php endif; ?>
        </div>

        <?php if ($chapterMatches !== []): ?>
            <div class="chapter-matches">
                <?php foreach ($chapterMatches as $chapter): ?>
                    <a class="chapter-match" href="<?= e(surah_url((int) $chapter['id'])) ?>">
                        <span class="chapter-match-num"><?= (int) $chapter['id'] ?></span>
                        <span>
                            <strong><?= e($chapter['name_simple']) ?></strong>
                            <small class="muted"><?= e($chapter['translated_name']) ?> · <?= (int) $chapter['verses_count'] ?> verses</small>
                        </span>
                        <span class="chapter-match-ar" lang="ar" dir="rtl"><?= e($chapter['name_arabic']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <ol class="result-list" data-results>
            <?php foreach ($result['results'] as $row): ?>
                <?php
                $surahNumber = (int) $row['surah_number'];
                $verseNumber = (int) $row['verse_number'];
                ?>
                <li class="result">
                    <div class="result-head">
                        <a class="result-ref" href="<?= e(surah_url($surahNumber, $verseNumber)) ?>">
                            <?= \Quran\Support\Text::highlight((string) $row['name_simple'], $query) ?> <span class="muted"><?= $surahNumber ?>:<?= $verseNumber ?></span>
                        </a>
                        <span class="pill"><?= e($row['name_arabic']) ?></span>
                    </div>
                    <p class="result-arabic" lang="ar" dir="rtl"><?= \Quran\Support\Text::highlight((string) $row['text_uthmani'], $query) ?></p>
                    <?php if (!empty($row['translation_text'])): ?>
                        <p class="result-translation"><?= \Quran\Support\Text::highlight(\Quran\Support\Text::excerpt((string) $row['translation_text'], 240), $query) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if ($hasQuery && $result['total'] === 0 && mb_strlen($query, 'UTF-8') >= 2): ?>
            <div class="empty-state card">
                <h2>No results</h2>
                <p>Try a shorter word, check the spelling, or switch the translation. Arabic search ignores
                    diacritics but is otherwise exact.</p>
                <p class="muted small">Tip: searching inside a specific surah is often faster than searching everything.</p>
            </div>
        <?php endif; ?>

        <?php if (!$hasQuery): ?>
            <div class="search-suggestions">
                <h2 class="small-heading">Try a search</h2>
                <div class="chip-row">
                    <?php foreach (['mercy', 'patience', 'light', 'garden', 'الرحمن', 'الصلاة'] as $suggestion): ?>
                        <a class="chip chip-sm" href="<?= e(u('search.php?q=' . urlencode($suggestion))) ?>"><?= e($suggestion) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
