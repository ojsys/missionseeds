<?php
$meta['title']       = 'About Mission Seedlings';
$meta['description'] = get_setting('about_intro');
$meta['nav_active']  = 'about';

// Each block is a heading + body pair driven by settings, so staff can rewrite
// any of them inline without touching this file.
$blocks = [
    ['about_vision_heading',    'about_vision_body',    'target'],
    ['about_mission_heading',   'about_mission_body',   'seedling'],
    ['about_why_heading',       'about_why_body',       'people'],
    ['about_model_heading',     'about_model_body',     'network'],
    ['about_framework_heading', 'about_framework_body', 'shield'],
    ['about_future_heading',    'about_future_body',    'growth'],
];
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('about_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('about_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('about_intro', $isAdmin, true) ?></p>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft); padding-top:64px;">
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
  </div>
</section>

<section>
  <div class="section-inner reveal">
    <div class="cta-band">
      <div>
        <h2>See how it works in practice</h2>
        <p>The seven-stage pathway shows exactly what a participating church does, and when.</p>
      </div>
      <div class="cta-band-actions">
        <a class="btn-primary" style="background:var(--forest); color:var(--cream); box-shadow:var(--shadow-sm);" href="<?= e(url('/pathway')) ?>">
          Explore the Pathway
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a class="btn-ghost" style="color:var(--forest-deep); border-color:var(--cream-line);" href="<?= e(url('/cooperative')) ?>">The cooperative model</a>
      </div>
    </div>
  </div>
</section>
