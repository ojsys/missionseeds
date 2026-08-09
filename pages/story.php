<?php
// Staff who can manage stories may preview one before it is published.
$canPreview = user_can('manage_stories');
$story      = get_story_by_slug($params['slug'] ?? '', $canPreview);
if (!$story) {
    not_found();
}

$gallery = story_gallery((int) $story['id']);
$cover   = image_url($story['cover_path'] ?? null);
$related = array_values(array_filter(
    get_stories(['limit' => 4, 'category' => $story['category']]),
    fn($s) => (int) $s['id'] !== (int) $story['id']
));

$meta['title']       = $story['title'];
$meta['description'] = $story['excerpt'] ?: excerpt_from($story['body']);
$meta['nav_active']  = 'stories';
?>
<article>
  <section class="page-intro story-hero">
    <div class="section-inner">
      <a class="back-link" href="<?= e(url('/stories')) ?>">&larr; All growth stories</a>

      <?php if ($story['status'] !== 'published'): ?>
        <div class="draft-banner">
          <?= e(STORY_STATUSES[$story['status']] ?? 'Not published') ?> — only project staff can see this page.
        </div>
      <?php endif; ?>

      <div class="story-hero-meta">
        <span class="tag"><?= e(STORY_CATEGORIES[$story['category']] ?? 'Update') ?></span>
        <?php if ($story['published_at']): ?>
          <time datetime="<?= e($story['published_at']) ?>"><?= e(format_date($story['published_at'])) ?></time>
        <?php endif; ?>
        <?php if ($story['church_name']): ?>
          · <a href="<?= e(url('/churches/' . $story['church_slug'])) ?>"><?= e($story['church_name']) ?></a>
        <?php endif; ?>
      </div>

      <h1><?= e($story['title']) ?></h1>
      <?php if ($story['excerpt']): ?>
        <p class="lede-dark"><?= e($story['excerpt']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($cover): ?>
  <div class="story-cover">
    <img src="<?= e($cover) ?>" alt="<?= e($story['cover_alt'] ?? '') ?>">
  </div>
  <?php endif; ?>

  <section>
    <div class="section-inner">
      <div class="story-body">
        <?= rich_text($story['body']) ?>
      </div>

      <?php if ($gallery): ?>
      <div class="story-gallery">
        <?php foreach ($gallery as $img): ?>
          <?php $src = image_url($img['path']); if (!$src) continue; ?>
          <figure>
            <img src="<?= e($src) ?>" alt="<?= e($img['alt_text'] ?? '') ?>" loading="lazy">
            <?php if ($img['caption']): ?><figcaption><?= e($img['caption']) ?></figcaption><?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
</article>

<?php if ($related): ?>
<section style="background:var(--cream-soft);">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow">More like this</div>
      <h2>Related stories</h2>
    </div>
    <div class="story-grid">
      <?php foreach (array_slice($related, 0, 3) as $story): ?>
        <?php include __DIR__ . '/partials/story-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
