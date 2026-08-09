<?php
// Visibility is resolved inside the model from the signed-in user's
// capabilities. A restricted resource is filtered out of the query for
// visitors who may not see it — it never reaches the HTML.
$grouped   = resources_by_category();
$koboForms = get_kobo_forms();
$hasRestricted = user_can('view_resources_coordinator') || user_can('view_resources_staff');

$meta['title']       = 'Resource Hub';
$meta['description'] = get_setting('resources_intro');
$meta['nav_active']  = 'resources';

function resource_href(array $r): string {
    return $r['resource_type'] === 'file' && $r['file_path']
        ? url($r['file_path'])
        : (string) $r['url'];
}
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('resources_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('resources_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('resources_intro', $isAdmin, true) ?></p>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">
    <?php if ($grouped): ?>
      <?php foreach ($grouped as $category => $items): ?>
      <div class="resource-group reveal">
        <h2 class="resource-group-title"><?= e($category) ?></h2>
        <ul class="resource-list">
          <?php foreach ($items as $r): ?>
          <li class="resource-row">
            <div class="resource-icon"><?= icon_svg($r['icon_key']) ?></div>
            <div class="resource-main">
              <a href="<?= e(resource_href($r)) ?>"<?= $r['resource_type'] === 'link' ? ' target="_blank" rel="noopener"' : ' download' ?>>
                <?= e($r['title']) ?>
              </a>
              <?php if ($r['description']): ?><p><?= e($r['description']) ?></p><?php endif; ?>
            </div>
            <div class="resource-tags">
              <?php if ($r['visibility'] !== 'public'): ?>
                <span class="lock-tag"><?= icon_svg('shield') ?><?= $r['visibility'] === 'staff' ? 'Staff' : 'Coordinators' ?></span>
              <?php endif; ?>
              <?php if ($r['resource_type'] === 'file' && $r['file_bytes']): ?>
                <span class="size-tag"><?= e(human_bytes((int) $r['file_bytes'])) ?></span>
              <?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty-note">Resources will be published here shortly.</p>
    <?php endif; ?>
  </div>
</section>

<!-- ============ KOBOTOOLBOX FORMS (restricted) ============ -->
<section>
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow">Field data collection</div>
      <h2>KoboToolbox forms</h2>
      <p><?= editable('resources_staff_note', $isAdmin, true) ?></p>
    </div>

    <?php if ($koboForms): ?>
    <div class="kobo-grid">
      <?php foreach ($koboForms as $form): ?>
      <div class="kobo-card">
        <div class="kobo-card-head">
          <div class="resource-icon"><?= icon_svg('checklist') ?></div>
          <span class="lock-tag"><?= icon_svg('shield') ?><?= $form['visibility'] === 'staff' ? 'Staff' : 'Coordinators' ?></span>
        </div>
        <h3><?= e($form['name']) ?></h3>
        <div class="kobo-purpose"><?= e(KOBO_PURPOSES[$form['purpose']] ?? 'Form') ?></div>
        <?php if ($form['description']): ?><p><?= e($form['description']) ?></p><?php endif; ?>
        <?php if ($form['enketo_url']): ?>
          <a class="btn-inline" href="<?= e($form['enketo_url']) ?>" target="_blank" rel="noopener">
            Open form
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        <?php else: ?>
          <span class="kobo-pending">Link not configured yet</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php elseif ($hasRestricted): ?>
      <p class="empty-note">No forms have been added for your role yet.</p>
    <?php else: ?>
      <div class="locked-panel">
        <div class="resource-icon"><?= icon_svg('shield') ?></div>
        <div>
          <h3>These resources are restricted</h3>
          <p>Registration, monthly reporting, and tree-monitoring forms are available to project staff and church coordinators. Sign in to see the ones for your role.</p>
          <a class="btn-inline" href="<?= e(url('/admin/login.php?next=/resources')) ?>">
            Sign in
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
