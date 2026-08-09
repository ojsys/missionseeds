<?php
// $params['slug'] comes from the router. Unpublished profiles are visible to
// staff who can manage the project, so they can preview before publishing.
$canPreview = user_can('manage_project');
$church     = get_church_by_slug($params['slug'] ?? '', $canPreview);
if (!$church) {
    not_found();
}

$latest = latest_church_update((int) $church['id']);
$more   = get_stories(['church_id' => (int) $church['id'], 'limit' => 4]);
$photo  = image_url($church['photo_path']);

$meta['title']       = $church['name'];
$meta['description'] = $church['summary'] ?: ($church['name'] . ' — a participating church in the Mission Seedlings programme.');
$meta['nav_active']  = 'churches';
?>
<section class="page-intro church-hero">
  <div class="section-inner">
    <a class="back-link" href="<?= e(url('/churches')) ?>">&larr; All participating churches</a>

    <?php if (!$church['is_published']): ?>
      <div class="draft-banner">Not published — only project staff can see this page.</div>
    <?php endif; ?>

    <div class="church-hero-grid">
      <div>
        <div class="eyebrow">Participating church</div>
        <h1><?= e($church['name']) ?></h1>
        <div class="church-hero-meta">
          <?php if ($church['community']): ?>
            <span><?= icon_svg('land') ?><?= e($church['community']) ?><?= $church['lga'] ? ', ' . e($church['lga']) : '' ?>, <?= e($church['state']) ?></span>
          <?php endif; ?>
          <?php if ($church['denomination']): ?>
            <span><?= icon_svg('shield') ?><?= e($church['denomination']) ?></span>
          <?php endif; ?>
          <?php if ($church['joined_on']): ?>
            <span><?= icon_svg('calendar') ?>Joined <?= e(format_date($church['joined_on'], 'F Y')) ?></span>
          <?php endif; ?>
        </div>
        <span class="stage-tag stage-<?= e($church['stage_key']) ?>">Stage: <?= e(stage_name($church['stage_key'])) ?></span>
        <?php if ($church['summary']): ?>
          <p class="lede-dark" style="margin-top:22px;"><?= nl2br(e($church['summary'])) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($photo): ?>
      <div class="church-hero-photo">
        <img src="<?= e($photo) ?>" alt="<?= e($church['name']) ?>">
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow">Progress</div>
      <h2>Where this church stands</h2>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon"><?= icon_svg('people') ?></div>
        <div class="stat-value"><?= number_format($church['participant_count']) ?></div>
        <div class="stat-label">Participants</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= icon_svg('seedling') ?></div>
        <div class="stat-value"><?= number_format($church['seedlings_allocated']) ?></div>
        <div class="stat-label">Seedlings allocated</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= icon_svg('land') ?></div>
        <div class="stat-value"><?= number_format($church['trees_planted']) ?></div>
        <div class="stat-label">Trees planted</div>
      </div>
      <?php if ($church['survival_rate'] !== null): ?>
      <div class="stat-card">
        <div class="stat-icon"><?= icon_svg('sun') ?></div>
        <div class="stat-value"><?= e(rtrim(rtrim(number_format((float) $church['survival_rate'], 1), '0'), '.')) ?>%</div>
        <div class="stat-label">Seedling survival</div>
      </div>
      <?php endif; ?>
    </div>

    <p class="privacy-note">
      <?= icon_svg('shield') ?>
      <span>Participant names, phone numbers, household information, and cooperative financial records are held privately by the project team and are never shown here.</span>
    </p>
  </div>
</section>

<section>
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow">Latest</div>
      <h2>Updates from <?= e($church['name']) ?></h2>
    </div>

    <?php if ($latest): ?>
      <article class="latest-update">
        <div class="latest-update-meta">
          <span class="tag"><?= e(STORY_CATEGORIES[$latest['category']] ?? 'Update') ?></span>
          <time datetime="<?= e($latest['published_at']) ?>"><?= e(format_date($latest['published_at'])) ?></time>
        </div>
        <h3><a href="<?= e(url('/stories/' . $latest['slug'])) ?>"><?= e($latest['title']) ?></a></h3>
        <?php if ($latest['excerpt']): ?><p><?= e($latest['excerpt']) ?></p><?php endif; ?>
        <a class="link-arrow" href="<?= e(url('/stories/' . $latest['slug'])) ?>">Read the full update <span aria-hidden="true">&rarr;</span></a>
      </article>

      <?php $others = array_slice($more, 1); ?>
      <?php if ($others): ?>
      <div class="story-grid" style="margin-top:40px;">
        <?php foreach ($others as $story): ?>
          <?php include __DIR__ . '/partials/story-card.php'; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <p class="empty-note">No approved updates from this church yet.</p>
    <?php endif; ?>
  </div>
</section>
