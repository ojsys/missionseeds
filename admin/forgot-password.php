<?php
/**
 * "Forgotten your password?" — emails a reset link.
 *
 * The response is identical whether or not the address belongs to an account,
 * so this form cannot be used to discover who has one.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
start_session_safe();

if ($user = current_user()) {
    header('Location: ' . home_for_role($user['role']));
    exit;
}

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'This page was open too long. Please try again.';
    } elseif ($email === '') {
        $error = 'Please enter the email address on your account.';
    } elseif (login_is_locked('pwreset:' . $email)) {
        // Reuses the login throttle table: five requests per address or address
        // family in fifteen minutes is plenty, and stops this becoming a way to
        // spam someone's inbox.
        $error = 'Too many reset requests. Please wait 15 minutes and try again.';
    } else {
        record_login_attempt('pwreset:' . $email, false);
        request_password_reset($email);
        $sent = true;
    }
}

$siteName = get_setting('site_title', 'Mission Seedlings');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Forgotten password — <?= e($siteName) ?></title>
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <a class="brand" href="<?= e(url('/')) ?>">
      <svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="15" stroke="#1f3b2c" stroke-width="1.4"/><path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/><path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/><path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>
      <b><?= e($siteName) ?></b>
    </a>

    <?php if ($sent): ?>
      <h1>Check your email</h1>
      <p class="sub">
        If that address belongs to an account, a link to choose a new password is on its way.
        It expires in two hours.
      </p>
      <div class="admin-flash" style="margin-bottom:22px;">
        Nothing arrived? Check the spam folder, then ask a Super Admin to send you a new invitation —
        they can pass the link on by WhatsApp if email is not getting through.
      </div>
      <a class="btn secondary" style="width:100%; display:block; text-align:center; text-decoration:none;"
         href="<?= e(url('/admin/login.php')) ?>">Back to sign in</a>

    <?php else: ?>
      <h1>Forgotten your password?</h1>
      <p class="sub">Enter the email address on your account and we will send you a link.</p>

      <?php if ($error): ?><div class="admin-flash error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-row">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" required autofocus autocomplete="email">
        </div>
        <button type="submit" class="btn" style="width:100%;">Send me a link</button>
      </form>

      <p class="hint" style="margin-top:18px; text-align:center;">
        <a href="<?= e(url('/admin/login.php')) ?>">Back to sign in</a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
