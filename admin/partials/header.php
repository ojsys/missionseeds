<?php
/**
 * Admin chrome. Expects $pageTitle and optional $active to be set before
 * including this file.
 *
 * The navigation is built from capabilities, so a role never sees a link to a
 * page it would be refused on. The capability check on the page itself is what
 * actually enforces access — this just keeps the UI honest.
 */
$active   = $active ?? '';
$navUser  = current_user();
$siteName = get_setting('site_title', 'Mission Seedlings');

$adminNav = [];
$adminNav[] = ['href' => 'index.php', 'key' => 'dashboard', 'label' => 'Dashboard'];

if (user_can('manage_project')) {
    $adminNav[] = ['href' => 'churches.php',   'key' => 'churches',   'label' => 'Churches'];
    $adminNav[] = ['href' => 'pathway.php',    'key' => 'pathway',    'label' => 'Pathway'];
    $adminNav[] = ['href' => 'indicators.php', 'key' => 'indicators', 'label' => 'Indicators'];
}
if (user_can('manage_stories')) {
    $adminNav[] = ['href' => 'stories.php', 'key' => 'stories', 'label' => 'Stories'];
}
if (user_can('manage_resources')) {
    $adminNav[] = ['href' => 'resources.php', 'key' => 'resources', 'label' => 'Resources'];
}
if (user_can('view_submissions')) {
    $adminNav[] = ['href' => 'submissions.php', 'key' => 'submissions', 'label' => 'Enquiries'];
}
if (user_can('manage_content')) {
    $adminNav[] = ['href' => 'lists.php',    'key' => 'lists',    'label' => 'Lists'];
    $adminNav[] = ['href' => 'partners.php', 'key' => 'partners', 'label' => 'Partners'];
}
if (user_can('manage_settings')) {
    $adminNav[] = ['href' => 'settings.php', 'key' => 'settings', 'label' => 'Settings'];
}
if (user_can('manage_users')) {
    $adminNav[] = ['href' => 'users.php', 'key' => 'users', 'label' => 'Users'];
}
if (user_can('view_audit')) {
    $adminNav[] = ['href' => 'audit.php', 'key' => 'audit', 'label' => 'Activity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e($siteName) ?> Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin">
<div class="admin-topbar">
  <a class="brand" href="index.php">
    <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#f7f2e4" stroke-width="1.2" opacity="0.5"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/><path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>
    <b><?= e($siteName) ?></b><small>&nbsp;Admin</small>
  </a>
  <div class="admin-nav">
    <?php foreach ($adminNav as $item): ?>
      <a href="<?= e($item['href']) ?>" class="<?= $active === $item['key'] ? 'active' : '' ?>"><?= e($item['label']) ?></a>
    <?php endforeach; ?>
    <a href="change-password.php" class="<?= $active === 'password' ? 'active' : '' ?>">Password</a>
    <a href="<?= e(url('/')) ?>" class="view-site" target="_blank">View site ↗</a>
    <a href="logout.php" class="logout">Log out</a>
  </div>
</div>
<?php if ($navUser): ?>
<div class="admin-whoami">
  Signed in as <strong><?= e($navUser['full_name'] ?: $navUser['username']) ?></strong>
  <span class="role-chip role-<?= e($navUser['role']) ?>"><?= e(role_label($navUser['role'])) ?></span>
</div>
<?php endif; ?>
<div class="admin-main">
