<?php
$featured  = get_indicators(false, true);
$stages    = get_pathway_stages();
$stories   = get_stories(['limit' => 3]);
$churches  = get_churches();
$heroSrc   = image_url(get_setting('hero_image'), 'assets/uploads/hero.jpg');
$callOpen  = get_setting('call_status', 'open') === 'open';

$meta['title']       = get_setting('tagline', 'Growing Local. Flourishing Together.');
$meta['description'] = get_setting('home_subheading');
$meta['body_class']  = 'page-home';
$meta['nav_active']  = 'home';
?>

<!-- ============ HERO ============ -->
<section class="hero" style="padding:0;">
  <div class="hero-grid">
    <div class="hero-copy">
      <div class="eyebrow"><?= editable('home_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('home_heading', $isAdmin) ?></h1>
      <p class="lede"><?= editable('home_subheading', $isAdmin, true) ?></p>
      <div class="hero-actions">
        <a class="btn-primary" href="<?= e(url('/pathway')) ?>">
          Explore the Pathway
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a class="btn-ghost" href="<?= e(url('/tracker')) ?>">View project progress</a>
        <a class="btn-ghost" href="<?= e($callOpen ? url('/call') : url('/contact')) ?>">Express interest</a>
      </div>
    </div>
    <div class="hero-photo">
      <?php if ($heroSrc): ?>
      <img src="<?= e($heroSrc) ?>" alt="Church members in Jos, Plateau State, standing together beside young coffee seedlings" fetchpriority="high">
      <?php endif; ?>
      <div class="hero-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.4"/></svg>
        Pilot · Jos, Plateau State
      </div>
    </div>
  </div>
</section>

<?= divider('divider-to-cream', '#f7f2e4') ?>

<!-- ============ CURRENT PILOT ============ -->
<section id="pilot">
  <div class="section-inner reveal">
    <div class="spotlight">
      <div class="spotlight-icon"><?= icon_svg('coffee') ?></div>
      <div>
        <div class="eyebrow" style="color:var(--gold-soft);"><?= editable('home_pilot_eyebrow', $isAdmin) ?></div>
        <h3><?= editable('home_pilot_heading', $isAdmin) ?></h3>
        <p><?= editable('home_pilot_body', $isAdmin, true) ?></p>
        <div class="spotlight-meta">
          <span><?= icon_svg('land') ?> Jos, Plateau State</span>
          <span><?= icon_svg('growth') ?> Current phase: <strong><?= e(current_phase_name()) ?></strong></span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ IMPACT INDICATORS ============ -->
<?php if ($featured): ?>
<section id="impact" style="background:var(--cream-soft);">
  <div class="section-inner reveal">
    <div class="section-head">
      <div class="eyebrow">Growth tracker</div>
      <h2><?= editable('home_stats_heading', $isAdmin) ?></h2>
      <p><?= editable('home_stats_intro', $isAdmin, true) ?></p>
    </div>
    <div class="stat-grid">
      <?php foreach ($featured as $ind): ?>
      <div class="stat-card">
        <div class="stat-icon"><?= icon_svg($ind['icon_key']) ?></div>
        <div class="stat-value"><?= e(format_indicator($ind)) ?></div>
        <div class="stat-label"><?= e($ind['label']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:28px;">
      <a class="link-arrow" href="<?= e(url('/tracker')) ?>">See the full Growth Tracker <span aria-hidden="true">&rarr;</span></a>
    </p>
  </div>
</section>
<?php endif; ?>

<!-- ============ PATHWAY TEASER ============ -->
<section id="pathway-teaser">
  <div class="section-inner reveal">
    <div class="section-head">
      <div class="eyebrow">The pathway</div>
      <h2><?= editable('home_pathway_heading', $isAdmin) ?></h2>
      <p><?= editable('home_pathway_intro', $isAdmin, true) ?></p>
    </div>
    <ol class="stage-strip">
      <?php foreach ($stages as $i => $stage): ?>
      <li class="stage-chip status-<?= e($stage['status']) ?>">
        <span class="stage-chip-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <span class="stage-chip-name"><?= e($stage['name']) ?></span>
      </li>
      <?php endforeach; ?>
    </ol>
    <p style="margin-top:28px;">
      <a class="link-arrow" href="<?= e(url('/pathway')) ?>">Walk through all seven stages <span aria-hidden="true">&rarr;</span></a>
    </p>
  </div>
</section>

<!-- ============ CHURCHES + STORIES ============ -->
<?php if ($stories || $churches): ?>
<section id="field" style="background:var(--cream-soft);">
  <div class="section-inner reveal">
    <div class="section-head">
      <div class="eyebrow">From the field</div>
      <h2><?= editable('home_stories_heading', $isAdmin) ?></h2>
      <p><?= editable('home_stories_intro', $isAdmin, true) ?></p>
    </div>

    <?php if ($stories): ?>
    <div class="story-grid">
      <?php foreach ($stories as $story): ?>
        <?php include __DIR__ . '/partials/story-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:28px;">
      <a class="link-arrow" href="<?= e(url('/stories')) ?>">Read all growth stories <span aria-hidden="true">&rarr;</span></a>
    </p>
    <?php else: ?>
    <p class="empty-note">The first stories from the field will appear here as churches begin reporting.</p>
    <?php endif; ?>

    <?php if ($churches): ?>
    <p style="margin-top:14px;">
      <a class="link-arrow" href="<?= e(url('/churches')) ?>">
        Meet the <?= count($churches) ?> participating <?= count($churches) === 1 ? 'church' : 'churches' ?>
        <span aria-hidden="true">&rarr;</span>
      </a>
    </p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?= divider('divider-to-soil', '#332217', '#eee4cd') ?>

<!-- ============ CLOSING CTA ============ -->
<section class="harvest">
  <div class="section-inner reveal">
    <div class="eyebrow" style="color:var(--sprout-soft);">Get involved</div>
    <h2 style="color:var(--cream); margin-top:10px; font-size:clamp(28px,3.2vw,36px);"><?= editable('home_cta_heading', $isAdmin) ?></h2>
    <p style="color:rgba(247,242,228,0.75); max-width:52ch; margin-top:14px; font-size:16.5px;"><?= editable('home_cta_body', $isAdmin, true) ?></p>
    <div class="harvest-actions">
      <a class="btn-apply" href="<?= e($callOpen ? url('/call') : url('/contact')) ?>">
        <?= $callOpen ? e(get_setting('eoi_button_label', 'Apply now')) : 'Express interest' ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
      <?php if ($wa = get_setting('whatsapp_number')): ?>
      <a class="btn-whatsapp" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 00-8.6 15L2 22l5.2-1.4A10 10 0 1012 2zm0 18a8 8 0 01-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1120 12a8 8 0 01-8 8zm4.4-5.9c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.4.1-.5.1-.1.2-.3.4-.4.1-.2.2-.3.2-.5.1-.2 0-.4 0-.5-.1-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.2-.9.9-.9 2.2s1 2.5 1.1 2.7c.1.2 2 3 4.8 4.2.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.2-.4-.3z"/></svg>
        <?= e(get_setting('whatsapp_display', 'WhatsApp us')) ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>
