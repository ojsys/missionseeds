<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_admin();

$pageTitle = 'Dashboard';
$active = 'dashboard';
$deadline = get_setting('deadline_date', '');
$days = $deadline ? days_until($deadline) : null;
$criteriaCount = count(get_list_items('criteria', false));
$receiveCount  = count(get_list_items('receive', false));
$partnerCount  = count(get_partners(false));

include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Welcome back</div>
  <h1>Hi, <?= e($_SESSION['admin_username'] ?? 'there') ?> 🌱</h1>
  <p>Edit most of the page copy directly on the live site — just log in and click any headline or paragraph. Use the tools below for photos, structured settings, and the criteria / benefits lists.</p>
</div>

<div class="admin-grid" style="margin-bottom:32px;">
  <div class="panel" style="margin-bottom:0;">
    <div class="eyebrow">Deadline</div>
    <h2 style="margin-top:8px;"><?= $deadline ? e(format_deadline($deadline)) : '—' ?></h2>
    <p class="hint" style="margin-bottom:0;"><?= $days !== null ? ($days >= 0 ? $days . ' days remaining' : 'Deadline has passed') : 'Not set' ?></p>
  </div>
  <div class="panel" style="margin-bottom:0;">
    <div class="eyebrow">Who should apply</div>
    <h2 style="margin-top:8px;"><?= $criteriaCount ?> criteria</h2>
    <p class="hint" style="margin-bottom:0;">Shown in the "Who should apply" section</p>
  </div>
  <div class="panel" style="margin-bottom:0;">
    <div class="eyebrow">What churches receive</div>
    <h2 style="margin-top:8px;"><?= $receiveCount ?> items</h2>
    <p class="hint" style="margin-bottom:0;">Shown in the benefits grid</p>
  </div>
  <div class="panel" style="margin-bottom:0;">
    <div class="eyebrow">Partners</div>
    <h2 style="margin-top:8px;"><?= $partnerCount ?> <?= $partnerCount === 1 ? 'logo' : 'logos' ?></h2>
    <p class="hint" style="margin-bottom:0;">Shown in the partners strip</p>
  </div>
</div>

<div class="admin-grid">
  <a class="admin-card" href="../index.php" target="_blank">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h16M4 12h16M4 18h10"/></svg></div>
    <h3>Edit page copy</h3>
    <p>Open the live site and click any headline or paragraph to edit it in place, WordPress-style.</p>
  </a>
  <a class="admin-card" href="settings.php#appearance">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-8 8"/></svg></div>
    <h3>Hero photo</h3>
    <p>Upload, preview, or reset the large photo at the top of the landing page.</p>
  </a>
  <a class="admin-card" href="lists.php">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 10h16M10 4v16"/></svg></div>
    <h3>Manage lists</h3>
    <p>Add, reorder, hide, or remove items in "Who should apply" and "What churches receive".</p>
  </a>
  <a class="admin-card" href="partners.php">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M17 20h5v-2a3 3 0 00-4-2.8M9 20H2v-2a5 5 0 016.6-4.7"/><circle cx="9" cy="7" r="4"/><path d="M16 3.1a4 4 0 010 7.8"/></svg></div>
    <h3>Partners</h3>
    <p>Upload partner logos, reorder them, and choose which appear in the partners strip.</p>
  </a>
  <a class="admin-card" href="settings.php">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.6V21a2 2 0 11-4 0v-.2a1.7 1.7 0 00-1-1.5 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.6-1H3a2 2 0 110-4h.2a1.7 1.7 0 001.5-1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H9a1.7 1.7 0 001-1.6V3a2 2 0 114 0v.2a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V9a1.7 1.7 0 001.6 1H21a2 2 0 110 4h-.2a1.7 1.7 0 00-1.6 1z"/></svg></div>
    <h3>Site settings</h3>
    <p>Deadline date, expression-of-interest link, WhatsApp number, and site title.</p>
  </a>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
