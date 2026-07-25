<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

start_session_safe();
if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (attempt_login($username, $password)) {
            header('Location: index.php');
            exit;
        }
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — Seedlings Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <a class="brand" href="../index.php">
      <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#1f3b2c" stroke-width="1.4"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/><path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>
      <b>Seedlings</b>
    </a>
    <h1>Admin sign in</h1>
    <p class="sub">Edit your page content, lists, and settings.</p>

    <?php if ($error): ?>
      <div class="admin-flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="form-row">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus autocomplete="username">
      </div>
      <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn" style="width:100%;">Log in</button>
    </form>
  </div>
</div>
</body>
</html>
