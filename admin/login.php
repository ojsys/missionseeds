<?php
require_once __DIR__ . '/../includes/bootstrap.php';

start_session_safe();

if ($user = current_user()) {
    header('Location: ' . home_for_role($user['role']));
    exit;
}

/**
 * Where to send the user after signing in. Only same-site paths are honoured,
 * so a crafted ?next= cannot bounce someone to another domain.
 */
function safe_next(?string $next): ?string {
    if (!$next) return null;
    if (str_starts_with($next, '//') || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $next)) {
        return null;
    }
    return str_starts_with($next, '/') ? $next : null;
}

$error = '';
$next  = safe_next($_GET['next'] ?? $_POST['next'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } elseif (login_is_locked($username)) {
            $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
        } elseif ($account = attempt_login($username, $password)) {
            audit('login', 'user', (int) $account['id'], $account['username']);
            $fresh = current_user();
            if ($fresh && $fresh['must_change_password']) {
                header('Location: ' . rtrim(SITE_URL, '/') . '/admin/change-password.php?forced=1');
                exit;
            }
            header('Location: ' . ($next ? rtrim(SITE_URL, '/') . $next : home_for_role($account['role'])));
            exit;
        } else {
            $remaining = MAX_LOGIN_ATTEMPTS - login_attempts_recent($username);
            $error = 'Incorrect username or password.'
                   . ($remaining > 0 && $remaining <= 2
                        ? ' ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining.'
                        : '');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Log in — <?= e(get_setting('site_title', 'Mission Seedlings')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <a class="brand" href="<?= e(url('/')) ?>">
      <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#1f3b2c" stroke-width="1.4"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/><path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>
      <b><?= e(get_setting('site_title', 'Mission Seedlings')) ?></b>
    </a>
    <h1>Sign in</h1>
    <p class="sub">For project staff and church coordinators.</p>

    <?php if ($error): ?>
      <div class="admin-flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
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

    <p class="hint" style="margin-top:18px; text-align:center;">
      Forgotten your password? Ask a Super Admin to reset it for you.
    </p>
  </div>
</div>
</body>
</html>
