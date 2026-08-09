<?php
$category = $_GET['category'] ?? '';
if (!array_key_exists($category, STORY_CATEGORIES)) {
    $category = '';
}
$perPage = 9;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$filters = ['limit' => $perPage, 'offset' => ($page - 1) * $perPage];
if ($category !== '') $filters['category'] = $category;

$stories = get_stories($filters);
$total   = count_stories($category !== '' ? ['category' => $category] : []);
$pages   = max(1, (int) ceil($total / $perPage));

$meta['title']       = 'Growth Stories';
$meta['description'] = get_setting('stories_intro');
$meta['nav_active']  = 'stories';

/** Keeps the active category when paging. */
function stories_url(int $page, string $category): string {
    $q = array_filter(['category' => $category, 'page' => $page > 1 ? $page : null]);
    return url('/stories') . ($q ? '?' . http_build_query($q) : '');
}
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('stories_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('stories_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('stories_intro', $isAdmin, true) ?></p>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">

    <nav class="chip-filters" aria-label="Filter stories by category">
      <a class="chip <?= $category === '' ? 'active' : '' ?>" href="<?= e(url('/stories')) ?>">All</a>
      <?php foreach (STORY_CATEGORIES as $key => $label): ?>
        <a class="chip <?= $category === $key ? 'active' : '' ?>" href="<?= e(stories_url(1, $key)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($stories): ?>
      <div class="story-grid">
        <?php foreach ($stories as $story): ?>
          <?php include __DIR__ . '/partials/story-card.php'; ?>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
      <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?>
          <a href="<?= e(stories_url($page - 1, $category)) ?>">&larr; Newer</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
          <a href="<?= e(stories_url($page + 1, $category)) ?>">Older &rarr;</a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    <?php else: ?>
      <p class="empty-note">
        <?= $category !== ''
            ? 'No stories in that category yet. <a href="' . e(url('/stories')) . '">See all stories</a>.'
            : 'The first stories from the field will be published here soon.' ?>
      </p>
    <?php endif; ?>
  </div>
</section>
