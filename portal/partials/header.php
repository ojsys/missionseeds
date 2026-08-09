<?php
/**
 * Church portal chrome. Deliberately simpler than the admin area: coordinators
 * have two things to do here, and the navigation says so.
 */
$active   = $active ?? '';
$navUser  = current_user();
$siteName = get_setting('site_title', 'Mission Seedlings');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Portal') ?> — <?= e($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin">
<div class="admin-topbar">
  <a class="brand" href="index.php">
    <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#f7f2e4" stroke-width="1.2" opacity="0.5"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/><path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>
    <b><?= e($siteName) ?></b><small>&nbsp;Church portal</small>
  </a>
  <div class="admin-nav">
    <a href="index.php" class="<?= $active === 'home' ? 'active' : '' ?>">My church</a>
    <a href="updates.php" class="<?= $active === 'updates' ? 'active' : '' ?>">My updates</a>
    <a href="<?= e(url('/resources')) ?>" class="<?= $active === 'resources' ? 'active' : '' ?>">Forms &amp; resources</a>
    <a href="../admin/change-password.php">Password</a>
    <a href="<?= e(url('/')) ?>" class="view-site" target="_blank">View site ↗</a>
    <a href="../admin/logout.php" class="logout">Log out</a>
  </div>
</div>
<?php if ($navUser): ?>
<div class="admin-whoami">
  Signed in as <strong><?= e($navUser['full_name'] ?: $navUser['username']) ?></strong>
  <span class="role-chip role-<?= e($navUser['role']) ?>"><?= e(role_label($navUser['role'])) ?></span>
</div>
<?php endif; ?>
<div class="admin-main">
