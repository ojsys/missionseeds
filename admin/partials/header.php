<?php
// Expects $pageTitle and optional $active to be set before including this file.
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Admin') ?> — Seedlings Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin">
<div class="admin-topbar">
  <a class="brand" href="index.php">
    <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#f7f2e4" stroke-width="1.2" opacity="0.5"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/><path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>
    <b>Seedlings</b><small>&nbsp;Admin</small>
  </a>
  <div class="admin-nav">
    <a href="index.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="lists.php" class="<?= $active === 'lists' ? 'active' : '' ?>">Lists</a>
    <a href="partners.php" class="<?= $active === 'partners' ? 'active' : '' ?>">Partners</a>
    <a href="settings.php" class="<?= $active === 'settings' ? 'active' : '' ?>">Settings</a>
    <a href="change-password.php" class="<?= $active === 'password' ? 'active' : '' ?>">Password</a>
    <a href="../index.php" class="view-site" target="_blank">View site ↗</a>
    <a href="logout.php" class="logout">Log out</a>
  </div>
</div>
<div class="admin-main">
