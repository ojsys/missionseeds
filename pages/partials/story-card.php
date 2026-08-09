<?php
/**
 * One story card. Expects $story (a row from get_stories()).
 * Used by the home page, the stories feed, and church profiles.
 */
$cardCover = image_url($story['cover_path'] ?? null);
?>
<a class="story-card" href="<?= e(url('/stories/' . $story['slug'])) ?>">
  <?php if ($cardCover): ?>
    <div class="story-card-media">
      <img src="<?= e($cardCover) ?>" alt="<?= e($story['cover_alt'] ?? '') ?>" loading="lazy">
    </div>
  <?php else: ?>
    <div class="story-card-media story-card-media--empty"><?= icon_svg('seedling') ?></div>
  <?php endif; ?>
  <div class="story-card-body">
    <div class="story-card-meta">
      <span class="tag"><?= e(STORY_CATEGORIES[$story['category']] ?? 'Update') ?></span>
      <?php if (!empty($story['published_at'])): ?>
        <time datetime="<?= e($story['published_at']) ?>"><?= e(format_date($story['published_at'], 'j M Y')) ?></time>
      <?php endif; ?>
    </div>
    <h3><?= e($story['title']) ?></h3>
    <?php if (!empty($story['excerpt'])): ?>
      <p><?= e($story['excerpt']) ?></p>
    <?php endif; ?>
    <?php if (!empty($story['church_name'])): ?>
      <div class="story-card-church"><?= icon_svg('shield') ?><?= e($story['church_name']) ?></div>
    <?php endif; ?>
  </div>
</a>
