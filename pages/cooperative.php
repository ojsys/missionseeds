<?php
$meta['title']       = 'The Cooperative Model';
$meta['description'] = get_setting('coop_intro');
$meta['nav_active']  = 'cooperative';

// The structural facts of the model. These are deliberately hard-coded rather
// than editable copy: they are the terms every cooperative agrees to, and a
// stray edit here would misrepresent the agreement to churches.
$facts = [
    ['people',    'Ten participants',        'Each participating church mobilises ten members into one cooperative.'],
    ['seedling',  '10–20 seedlings each',    'Every participant receives ten to twenty coffee seedlings to plant and care for.'],
    ['shield',    'Individual seed funds',   'Each member holds their own seed fund within the cooperative — pooled for safekeeping, owned individually.'],
    ['calendar',  '18-month fund lock',      'Contributions are locked for eighteen months so the fund can mature before it is drawn on.'],
    ['growth',    '₦100 – ₦2,000 monthly',   'Members choose a monthly savings amount within this range and keep to it.'],
    ['chat',      'Monthly meetings',        'The cooperative meets every month to review tree care, record savings, and resolve issues together.'],
    ['network',   'Three signatories',       'Every cooperative account requires three signatories — no one person can move funds alone.'],
    ['checklist', 'Tree care and reporting', 'Members care for their trees and submit monthly progress and tree-monitoring reports.'],
];

$blocks = [
    ['coop_savings_heading',    'coop_savings_body',    'growth'],
    ['coop_governance_heading', 'coop_governance_body', 'shield'],
    ['coop_reporting_heading',  'coop_reporting_body',  'checklist'],
    ['coop_maturity_heading',   'coop_maturity_body',   'sun'],
];
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('coop_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('coop_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('coop_intro', $isAdmin, true) ?></p>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow">At a glance</div>
      <h2>How a Seedlings cooperative is put together</h2>
    </div>
    <div class="fact-grid">
      <?php foreach ($facts as [$icon, $title, $body]): ?>
      <div class="fact-card reveal">
        <div class="fact-icon"><?= icon_svg($icon) ?></div>
        <h3><?= e($title) ?></h3>
        <p><?= e($body) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section>
  <div class="section-inner">
    <div class="prose-grid">
      <?php foreach ($blocks as [$headingKey, $bodyKey, $icon]): ?>
      <article class="prose-block reveal">
        <div class="prose-icon"><?= icon_svg($icon) ?></div>
        <h2><?= editable($headingKey, $isAdmin) ?></h2>
        <p><?= editable($bodyKey, $isAdmin, true) ?></p>
      </article>
      <?php endforeach; ?>
    </div>

    <p class="privacy-note">
      <?= icon_svg('shield') ?>
      <span>Cooperative balances, bank details, and individual savings records belong to the cooperative and its members. They are never published on this website.</span>
    </p>
  </div>
</section>
