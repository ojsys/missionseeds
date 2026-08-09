<?php
$indicators = get_indicators(false);
$milestones = get_milestones(false);
$stages     = get_pathway_stages();
$phase      = current_phase_name();

$meta['title']       = 'Growth Tracker';
$meta['description'] = get_setting('tracker_intro');
$meta['nav_active']  = 'tracker';

/**
 * Sparkline for an indicator's recent history. Returns '' when there are fewer
 * than two data points — a single dot would imply a trend that isn't there.
 */
function sparkline_path(array $history, int $w = 120, int $h = 32): string {
    if (count($history) < 2) return '';
    $values = array_map(fn($r) => (float) $r['value_num'], $history);
    $min = min($values);
    $max = max($values);
    $range = ($max - $min) ?: 1;
    $step  = $w / (count($values) - 1);

    $points = [];
    foreach ($values as $i => $v) {
        $x = round($i * $step, 1);
        $y = round($h - (($v - $min) / $range) * ($h - 4) - 2, 1);
        $points[] = ($i === 0 ? 'M' : 'L') . $x . ' ' . $y;
    }
    return implode(' ', $points);
}
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('tracker_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('tracker_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('tracker_intro', $isAdmin, true) ?></p>
    </div>
    <div class="phase-pill">
      <?= icon_svg('growth') ?>
      <span>Current project phase</span>
      <strong><?= e($phase) ?></strong>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">
    <?php if ($indicators): ?>
    <div class="stat-grid stat-grid--wide">
      <?php foreach ($indicators as $ind): ?>
        <?php $spark = sparkline_path(indicator_history((int) $ind['id'], 12)); ?>
        <div class="stat-card reveal">
          <div class="stat-icon"><?= icon_svg($ind['icon_key']) ?></div>
          <div class="stat-value"><?= e(format_indicator($ind)) ?></div>
          <div class="stat-label"><?= e($ind['label']) ?></div>
          <?php if ($spark): ?>
            <svg class="sparkline" viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true" focusable="false">
              <path d="<?= e($spark) ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="empty-note">Indicators will appear here once the project team publishes the first figures.</p>
    <?php endif; ?>

    <p class="privacy-note">
      <?= icon_svg('shield') ?>
      <span><?= editable('tracker_privacy_note', $isAdmin, true) ?></span>
    </p>
  </div>
</section>

<!-- ============ MILESTONE TIMELINE ============ -->
<section>
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow">Implementation</div>
      <h2><?= editable('tracker_milestones_heading', $isAdmin) ?></h2>
    </div>

    <?php if ($milestones): ?>
    <ol class="timeline">
      <?php foreach ($milestones as $m): ?>
      <li class="timeline-item milestone-<?= e($m['status']) ?> reveal">
        <span class="timeline-dot" aria-hidden="true"></span>
        <div class="timeline-body">
          <div class="timeline-head">
            <h3><?= e($m['title']) ?></h3>
            <span class="status-badge status-<?= e($m['status']) ?>"><?= e(MILESTONE_STATUSES[$m['status']] ?? '') ?></span>
          </div>
          <div class="timeline-meta">
            <?= e(stage_name($m['stage_key'])) ?> stage
            <?php if ($m['status'] === 'complete' && $m['completed_on']): ?>
              · completed <?= e(format_date($m['completed_on'])) ?>
            <?php elseif ($m['target_date']): ?>
              · target <?= e(format_date($m['target_date'])) ?>
            <?php endif; ?>
          </div>
          <?php if (!empty($m['description'])): ?><p><?= e($m['description']) ?></p><?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php else: ?>
      <p class="empty-note">Milestones will be published here as the project team records them.</p>
    <?php endif; ?>

    <!-- Stage progress summary -->
    <div class="stage-summary">
      <?php foreach ($stages as $stage): ?>
      <div class="stage-summary-item status-<?= e($stage['status']) ?>">
        <span class="stage-summary-name"><?= e($stage['name']) ?></span>
        <span class="stage-summary-status"><?= e(STAGE_STATUSES[$stage['status']] ?? '') ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
