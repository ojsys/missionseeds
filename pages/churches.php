<?php
$stageFilter     = $_GET['stage'] ?? '';
$communityFilter = trim((string) ($_GET['community'] ?? ''));

$all = get_churches();

// Filtering in PHP rather than SQL: the pilot has a handful of churches, and
// this keeps the community filter a single query instead of two.
$churches = array_values(array_filter($all, function ($c) use ($stageFilter, $communityFilter) {
    if ($stageFilter !== '' && $c['stage_key'] !== $stageFilter) return false;
    if ($communityFilter !== '' && $c['community'] !== $communityFilter) return false;
    return true;
}));

$communities = church_communities();
$stages      = get_pathway_stages();

$meta['title']       = 'Participating Churches';
$meta['description'] = get_setting('churches_intro');
$meta['nav_active']  = 'churches';
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('churches_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('churches_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('churches_intro', $isAdmin, true) ?></p>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">

    <?php if ($all): ?>
    <form class="filter-bar" method="get" action="<?= e(url('/churches')) ?>">
      <div class="filter-field">
        <label for="stage">Stage</label>
        <select name="stage" id="stage">
          <option value="">All stages</option>
          <?php foreach ($stages as $s): ?>
            <option value="<?= e($s['stage_key']) ?>" <?= $stageFilter === $s['stage_key'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($communities): ?>
      <div class="filter-field">
        <label for="community">Community</label>
        <select name="community" id="community">
          <option value="">All communities</option>
          <?php foreach ($communities as $c): ?>
            <option value="<?= e($c) ?>" <?= $communityFilter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <button type="submit" class="btn-filter">Apply</button>
      <?php if ($stageFilter !== '' || $communityFilter !== ''): ?>
        <a class="filter-clear" href="<?= e(url('/churches')) ?>">Clear</a>
      <?php endif; ?>
      <span class="filter-count"><?= count($churches) ?> of <?= count($all) ?></span>
    </form>
    <?php endif; ?>

    <?php if ($churches): ?>
    <div class="church-grid">
      <?php foreach ($churches as $church): ?>
        <?php $photo = image_url($church['photo_path']); ?>
        <a class="church-card reveal" href="<?= e(url('/churches/' . $church['slug'])) ?>">
          <?php if ($photo): ?>
            <div class="church-card-media"><img src="<?= e($photo) ?>" alt="<?= e($church['name']) ?>" loading="lazy"></div>
          <?php else: ?>
            <div class="church-card-media church-card-media--empty"><?= icon_svg('shield') ?></div>
          <?php endif; ?>
          <div class="church-card-body">
            <h2><?= e($church['name']) ?></h2>
            <?php if ($church['community']): ?>
              <div class="church-card-place"><?= icon_svg('land') ?><?= e($church['community']) ?><?= $church['lga'] ? ', ' . e($church['lga']) : '' ?></div>
            <?php endif; ?>
            <span class="stage-tag stage-<?= e($church['stage_key']) ?>"><?= e(stage_name($church['stage_key'])) ?></span>
            <dl class="church-card-stats">
              <div><dt>Participants</dt><dd><?= number_format($church['participant_count']) ?></dd></div>
              <div><dt>Seedlings</dt><dd><?= number_format($church['seedlings_allocated']) ?></dd></div>
              <?php if ($church['survival_rate'] !== null): ?>
                <div><dt>Survival</dt><dd><?= e(rtrim(rtrim(number_format((float) $church['survival_rate'], 1), '0'), '.')) ?>%</dd></div>
              <?php endif; ?>
            </dl>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php elseif ($all): ?>
      <p class="empty-note">No churches match those filters. <a href="<?= e(url('/churches')) ?>">Show all</a>.</p>
    <?php else: ?>
      <p class="empty-note">Church profiles will be published here as churches complete onboarding.</p>
    <?php endif; ?>

    <p class="privacy-note">
      <?= icon_svg('shield') ?>
      <span><?= editable('churches_privacy_note', $isAdmin, true) ?></span>
    </p>
  </div>
</section>
