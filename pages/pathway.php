<?php
$stages     = get_pathway_stages();
$milestones = milestones_by_stage(false);

$meta['title']       = 'The Seedlings Pathway';
$meta['description'] = get_setting('pathway_intro');
$meta['nav_active']  = 'pathway';
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('pathway_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('pathway_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('pathway_intro', $isAdmin, true) ?></p>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">
    <ol class="stepper">
      <?php foreach ($stages as $i => $stage): ?>
      <li class="stepper-item status-<?= e($stage['status']) ?> reveal">
        <div class="stepper-marker">
          <span class="stepper-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <span class="stepper-icon"><?= icon_svg($stage['icon_key']) ?></span>
        </div>
        <div class="stepper-body">
          <div class="stepper-head">
            <h2><?= e($stage['name']) ?></h2>
            <span class="status-badge status-<?= e($stage['status']) ?>">
              <?= e(STAGE_STATUSES[$stage['status']] ?? '') ?>
            </span>
          </div>
          <p><?= e($stage['short_description']) ?></p>

          <?php if (!empty($stage['status_note'])): ?>
            <p class="stepper-note"><?= icon_svg('chat') ?><?= e($stage['status_note']) ?></p>
          <?php endif; ?>

          <?php if (!empty($milestones[$stage['stage_key']])): ?>
          <ul class="milestone-list">
            <?php foreach ($milestones[$stage['stage_key']] as $m): ?>
            <li class="milestone milestone-<?= e($m['status']) ?>">
              <span class="milestone-dot" aria-hidden="true"></span>
              <div>
                <strong><?= e($m['title']) ?></strong>
                <?php if (!empty($m['description'])): ?><span class="milestone-desc"><?= e($m['description']) ?></span><?php endif; ?>
                <span class="milestone-meta">
                  <?= e(MILESTONE_STATUSES[$m['status']] ?? '') ?>
                  <?php if ($m['status'] === 'complete' && $m['completed_on']): ?>
                    · <?= e(format_date($m['completed_on'], 'M Y')) ?>
                  <?php elseif ($m['target_date']): ?>
                    · target <?= e(format_date($m['target_date'], 'M Y')) ?>
                  <?php endif; ?>
                </span>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>

    <p class="pathway-outro"><?= editable('pathway_outro', $isAdmin, true) ?></p>
  </div>
</section>

<section>
  <div class="section-inner reveal">
    <div class="cta-band">
      <div>
        <h2>Follow the numbers</h2>
        <p>The Growth Tracker shows how far churches have come across every stage.</p>
      </div>
      <div class="cta-band-actions">
        <a class="btn-primary" style="background:var(--forest); color:var(--cream); box-shadow:var(--shadow-sm);" href="<?= e(url('/tracker')) ?>">
          View project progress
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </div>
  </div>
</section>
