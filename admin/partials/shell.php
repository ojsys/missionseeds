<?php
/**
 * The admin/portal shell: sidebar, topbar, and the opening of the content area.
 *
 * Both admin/partials/header.php and portal/partials/header.php set a few
 * variables and include this, so the two areas stay visually identical and
 * there is one place to change the chrome.
 *
 * Expects:
 *   $shellGroups  array   navigation groups (see admin/partials/nav.php)
 *   $shellLabel   string  wordmark suffix — "Admin" or "Church portal"
 *   $pageTitle    string  page name, used in <title> and the heading
 *   $active       string  key of the current nav item
 * Optional:
 *   $pageEyebrow  string  small label above the page heading
 *   $pageIntro    string  one-line description under the heading
 *   $pageActions  string  raw HTML for buttons on the right of the page header
 */

$active     = $active ?? '';
$shellUser  = current_user();
$siteName   = get_setting('site_title', 'Mission Seedlings');
$crumbs     = nav_breadcrumb($shellGroups, $active, $pageTitle ?? 'Admin');
$totalBadge = 0;
foreach ($shellGroups as $g) {
    foreach ($g['items'] as $i) { $totalBadge += (int) ($i['badge'] ?? 0); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e($siteName) ?></title>
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">

<a class="skip-link" href="#admin-content">Skip to content</a>

<div class="shell">

  <!-- ============ SIDEBAR ============ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <a href="<?= e(url(is_staff_role($shellUser['role'] ?? '') ? '/admin/index.php' : '/portal/index.php')) ?>">
        <svg viewBox="0 0 32 32" fill="none" aria-hidden="true">
          <circle cx="16" cy="16" r="15" stroke="#f7f2e4" stroke-width="1.2" opacity="0.45"/>
          <path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/>
          <path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/>
          <path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/>
        </svg>
        <span><b><?= e($siteName) ?></b><small><?= e($shellLabel) ?></small></span>
      </a>
      <button type="button" class="sidebar-close" id="sidebar-close" aria-label="Close menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <nav class="sidebar-nav" aria-label="Admin">
      <?php foreach ($shellGroups as $group): ?>
        <div class="nav-group">
          <div class="nav-group-label"><?= e($group['label']) ?></div>
          <?php foreach ($group['items'] as $item): ?>
            <a class="nav-item<?= $active === $item['key'] ? ' active' : '' ?>"
               href="<?= e($item['href']) ?>"
               <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
              <span class="nav-item-icon"><?= nav_icon($item['icon']) ?></span>
              <span class="nav-item-label"><?= e($item['label']) ?></span>
              <?php if (!empty($item['badge'])): ?>
                <span class="nav-badge"><?= (int) $item['badge'] ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <a class="sidebar-viewsite" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
        <?= nav_icon('external') ?><span>View the website</span>
      </a>
      <?php if ($shellUser): ?>
      <div class="sidebar-user">
        <span class="avatar" aria-hidden="true"><?= e(user_initials($shellUser)) ?></span>
        <span class="sidebar-user-meta">
          <strong><?= e($shellUser['full_name'] ?: $shellUser['username']) ?></strong>
          <span class="role-chip role-<?= e($shellUser['role']) ?>"><?= e(role_label($shellUser['role'])) ?></span>
        </span>
        <a class="sidebar-logout" href="<?= e(url('/admin/logout.php')) ?>" title="Log out" aria-label="Log out">
          <?= nav_icon('logout') ?>
        </a>
      </div>
      <?php endif; ?>
    </div>
  </aside>

  <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>

  <!-- ============ MAIN COLUMN ============ -->
  <div class="shell-main">
    <header class="topbar">
      <button type="button" class="topbar-burger" id="sidebar-open" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        <?php if ($totalBadge): ?><span class="burger-dot" aria-hidden="true"></span><?php endif; ?>
      </button>

      <nav class="crumbs" aria-label="Breadcrumb">
        <?php foreach ($crumbs as $i => $c): ?>
          <?php if ($i > 0): ?><span class="crumb-sep" aria-hidden="true">/</span><?php endif; ?>
          <span<?= $i === count($crumbs) - 1 ? ' class="crumb-current"' : '' ?>><?= e($c) ?></span>
        <?php endforeach; ?>
      </nav>

      <div class="topbar-right">
        <a class="topbar-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
          <?= nav_icon('external') ?><span>View site</span>
        </a>
        <?php if ($shellUser): ?>
          <span class="avatar avatar-sm" title="<?= e($shellUser['full_name'] ?: $shellUser['username']) ?>"><?= e(user_initials($shellUser)) ?></span>
        <?php endif; ?>
      </div>
    </header>

    <main class="content" id="admin-content">
      <div class="content-inner">
        <div class="page-head">
          <div>
            <?php if (!empty($pageEyebrow)): ?><div class="eyebrow"><?= e($pageEyebrow) ?></div><?php endif; ?>
            <h1><?= e($pageTitle ?? 'Admin') ?></h1>
            <?php if (!empty($pageIntro)): ?><p class="page-intro-text"><?= e($pageIntro) ?></p><?php endif; ?>
          </div>
          <?php if (!empty($pageActions)): ?>
            <div class="page-head-actions"><?= $pageActions ?></div>
          <?php endif; ?>
        </div>
