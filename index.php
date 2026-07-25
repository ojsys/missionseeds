<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php';

start_session_safe();
$isAdmin = admin_logged_in();

$criteria = get_list_items('criteria', !$isAdmin);
$receive  = get_list_items('receive', !$isAdmin);

$deadlineDate = get_setting('deadline_date', '2026-07-31');
$daysLeft     = days_until($deadlineDate);
$heroImage    = get_setting('hero_image', 'assets/uploads/hero.jpg');
$whatsapp     = get_setting('whatsapp_number', '');
$whatsappDisp = get_setting('whatsapp_display', '');
$eoiUrl       = get_setting('eoi_form_url', '#');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(get_setting('site_title', 'Seedlings')) ?> — <?= e(get_setting('tagline')) ?></title>
<meta name="description" content="<?= e(get_setting('hero_subheading')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-admin="<?= $isAdmin ? '1' : '0' ?>">

<header class="site">
  <div class="navbar">
    <a class="brand" href="#top">
      <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#1f3b2c" stroke-width="1.4"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/><path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>
      <span class="brand-text"><b><?= e(get_setting('site_title', 'Seedlings')) ?></b><small><?= e(get_setting('tagline')) ?></small></span>
    </a>
    <a class="nav-cta" href="<?= e($eoiUrl) ?>" target="_blank" rel="noopener">Express interest →</a>
  </div>
</header>

<div id="top" style="position:relative;">
  <div id="vine-wrap">
    <svg class="vine-track" preserveAspectRatio="none">
      <path id="vine-path-bg" d=""></path>
      <path id="vine-path-fill" d=""></path>
    </svg>
  </div>

  <!-- ============ HERO ============ -->
  <section class="hero" style="padding:0;">
    <div class="hero-grid">
      <div class="hero-copy">
        <div class="eyebrow"><?= editable('hero_eyebrow', $isAdmin) ?></div>
        <h1><?= editable('hero_heading', $isAdmin) ?></h1>
        <p class="lede"><?= editable('hero_subheading', $isAdmin, true) ?></p>
        <div class="hero-actions">
          <a class="btn-primary" href="<?= e($eoiUrl) ?>" target="_blank" rel="noopener">
            <?= e(get_setting('eoi_button_label', 'Apply now')) ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <a class="btn-ghost" href="#who">See what's involved ↓</a>
        </div>
      </div>
      <div class="hero-photo">
        <img id="hero-img" src="<?= e($heroImage) ?>" alt="Church members standing together in Jos, with a young coffee seedling planted in the foreground">
        <div class="hero-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.4"/></svg>
          Pilot · Jos, Plateau State
        </div>
        <div class="hero-photo-controls">
          <button type="button" class="change-photo-btn" id="change-photo-btn">Change photo</button>
          <input type="file" id="hero-photo-input" accept="image/*" style="display:none;">
        </div>
      </div>
    </div>
  </section>

  <?= divider('divider-to-cream', '#f7f2e4') ?>

  <!-- ============ SEED / INTRO ============ -->
  <section id="seed">
    <div class="section-inner reveal">
      <div class="leaf-node" data-vine-node><?= leaf_svg(0) ?></div>
      <div class="section-head">
        <div class="eyebrow"><?= editable('seed_eyebrow', $isAdmin) ?></div>
        <h2><?= editable('seed_heading', $isAdmin) ?></h2>
      </div>
      <div class="intro-body">
        <p><?= editable('seed_body', $isAdmin, true) ?></p>
      </div>
    </div>
  </section>

  <!-- ============ ROOT / WHO SHOULD APPLY ============ -->
  <section id="who" style="background:var(--cream-soft);">
    <div class="section-inner reveal">
      <div class="leaf-node" data-vine-node><?= leaf_svg(1) ?></div>
      <div class="section-head">
        <div class="eyebrow"><?= editable('who_eyebrow', $isAdmin) ?></div>
        <h2><?= editable('who_heading', $isAdmin) ?></h2>
        <p><?= editable('who_intro', $isAdmin, true) ?></p>
      </div>

      <div class="criteria-list" id="criteria-list">
        <?php foreach ($criteria as $item): ?>
        <div class="criteria-item" data-item-id="<?= (int)$item['id'] ?>">
          <?php if ($isAdmin): ?>
          <div class="admin-item-controls">
            <button type="button" class="btn-toggle-visible" title="<?= $item['is_visible'] ? 'Hide' : 'Show' ?>">
              <?php if ($item['is_visible']): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M6.6 6.7C4.5 8 3 12 3 12s4 7 10 7c1.7 0 3.2-.5 4.5-1.2M17.9 17.9C20 16.5 21 12 21 12s-1.6-2.8-4.1-4.7"/></svg>
              <?php endif; ?>
            </button>
            <button type="button" class="btn-delete-item" title="Delete">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"/></svg>
            </button>
          </div>
          <?php endif; ?>
          <div class="criteria-icon"><?= icon_svg($item['icon_key']) ?></div>
          <p class="editable" contenteditable="<?= $isAdmin ? 'true' : 'false' ?>" data-item-id="<?= (int)$item['id'] ?>" data-item-field="body"><?= e($item['body']) ?></p>
        </div>
        <?php endforeach; ?>

        <?php if ($isAdmin): ?>
        <div class="add-item-card" data-group="criteria" data-has-title="0">+ Add a criterion</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ============ PILOT SPOTLIGHT ============ -->
  <section id="pilot">
    <div class="section-inner reveal">
      <div class="leaf-node" data-vine-node><?= leaf_svg(2, '#c69447') ?></div>
      <div class="spotlight">
        <div class="spotlight-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 21h16M6 21V9a2 2 0 012-2h8a2 2 0 012 2v12M10 7V4h4v3"/><circle cx="12" cy="13" r="2.4"/></svg>
        </div>
        <div>
          <div class="eyebrow" style="color:var(--gold-soft);"><?= editable('pilot_eyebrow', $isAdmin) ?></div>
          <h3><?= editable('pilot_heading', $isAdmin) ?></h3>
          <p><?= editable('pilot_body', $isAdmin, true) ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ WHAT CHURCHES RECEIVE ============ -->
  <section id="receive" style="background:var(--cream-soft);">
    <div class="section-inner reveal">
      <div class="leaf-node" data-vine-node><?= leaf_svg(0) ?></div>
      <div class="section-head">
        <div class="eyebrow"><?= editable('receive_eyebrow', $isAdmin) ?></div>
        <h2><?= editable('receive_heading', $isAdmin) ?></h2>
      </div>
      <div class="receive-grid" id="receive-list">
        <?php foreach ($receive as $item): ?>
        <div class="receive-card" data-item-id="<?= (int)$item['id'] ?>">
          <?php if ($isAdmin): ?>
          <div class="admin-item-controls">
            <button type="button" class="btn-toggle-visible" title="<?= $item['is_visible'] ? 'Hide' : 'Show' ?>">
              <?php if ($item['is_visible']): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M6.6 6.7C4.5 8 3 12 3 12s4 7 10 7c1.7 0 3.2-.5 4.5-1.2M17.9 17.9C20 16.5 21 12 21 12s-1.6-2.8-4.1-4.7"/></svg>
              <?php endif; ?>
            </button>
            <button type="button" class="btn-delete-item" title="Delete">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"/></svg>
            </button>
          </div>
          <?php endif; ?>
          <div class="receive-icon"><?= icon_svg($item['icon_key']) ?></div>
          <h4 class="editable" contenteditable="<?= $isAdmin ? 'true' : 'false' ?>" data-item-id="<?= (int)$item['id'] ?>" data-item-field="title"><?= e($item['title']) ?></h4>
        </div>
        <?php endforeach; ?>

        <?php if ($isAdmin): ?>
        <div class="add-item-card" data-group="receive" data-has-title="1">+ Add an item</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?= divider('divider-to-soil', '#332217') ?>

  <!-- ============ HARVEST / CTA ============ -->
  <section id="interest" class="harvest">
    <div class="section-inner reveal">
      <div class="leaf-node" data-vine-node style="filter:brightness(1.4);"><?= leaf_svg(1, '#c69447') ?></div>

      <div class="eyebrow" style="color:var(--sprout-soft);"><?= editable('harvest_eyebrow', $isAdmin) ?></div>
      <h2 style="color:var(--cream); margin-top:10px; font-size:clamp(28px,3.2vw,36px);"><?= editable('harvest_heading', $isAdmin) ?></h2>
      <p style="color:rgba(247,242,228,0.75); max-width:48ch; margin-top:14px; font-size:16.5px;"><?= editable('harvest_subheading', $isAdmin, true) ?></p>

      <div class="deadline-card" style="margin-top:32px;">
        <div class="eyebrow">Expression of interest deadline</div>
        <div class="deadline-date"><?= e(format_deadline($deadlineDate)) ?></div>
        <?php if ($daysLeft !== null): ?>
          <div class="deadline-days"><?= $daysLeft >= 0 ? $daysLeft . ' days remaining' : 'Deadline passed — enquire for the next cycle' ?></div>
        <?php endif; ?>
        <p class="note"><?= editable('deadline_note', $isAdmin, true) ?></p>

        <div class="harvest-actions">
          <a class="btn-apply" href="<?= e($eoiUrl) ?>" target="_blank" rel="noopener">
            <?= e(get_setting('eoi_button_label', 'Apply now')) ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <?php if ($whatsapp): ?>
          <a class="btn-whatsapp" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 00-8.6 15L2 22l5.2-1.4A10 10 0 1012 2zm0 18a8 8 0 01-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1120 12a8 8 0 01-8 8zm4.4-5.9c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.4.1-.5.1-.1.2-.3.4-.4.1-.2.2-.3.2-.5.1-.2 0-.4 0-.5-.1-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.2-.9.9-.9 2.2s1 2.5 1.1 2.7c.1.2 2 3 4.8 4.2.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.2-.4-.3z"/></svg>
            <?= e($whatsappDisp) ?>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</div>

<footer>
  <div class="brand-mark">
    <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#f7f2e4" stroke-width="1.2" opacity="0.5"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/></svg>
  </div>
  <div class="footer-tagline"><?= editable('footer_tagline', $isAdmin) ?></div>
  <div class="footer-meta">© <?= date('Y') ?> Seedlings</div>
</footer>

<?php if ($isAdmin): ?>
<div class="admin-bar">
  <span>🌱 Editing as <strong><?= e($_SESSION['admin_username'] ?? 'admin') ?></strong></span>
  <div class="sep"></div>
  <a href="admin/index.php">Dashboard</a>
  <a href="admin/lists.php">Manage lists</a>
  <a href="admin/settings.php">Settings</a>
  <div class="sep"></div>
  <a href="admin/logout.php" class="danger">Log out</a>
</div>
<?php endif; ?>

<script>
  window.SEEDLINGS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
  window.SEEDLINGS_CSRF  = "<?= $isAdmin ? e(csrf_token()) : '' ?>";
  window.SEEDLINGS_API   = "admin/api.php";
  <?php if ($isAdmin): ?>
  window.SEEDLINGS_ICONS = <?= json_encode(array_map(function ($k, $label) {
      return ['key' => $k, 'label' => $label];
  }, array_keys(ICON_LIBRARY), array_values(ICON_LIBRARY))) ?>;
  <?php endif; ?>
</script>
<script src="assets/js/site.js"></script>
</body>
</html>
