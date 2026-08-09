<?php
/**
 * Completes an invitation or a password reset.
 *
 * Reached only with a valid single-use token, so it is deliberately outside the
 * login gate. Nothing here reveals whether a username or email exists.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
start_session_safe();

$raw   = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$found = find_valid_token($raw);
$errors = [];
$done   = false;

if ($found && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'This page was open too long. Please reload and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm'] ?? '');
        $errors   = password_problems($password, $confirm, $found['user']);

        if (!$errors) {
            consume_token((int) $found['token']['id'], (int) $found['user']['id'], $password);
            audit(
                $found['token']['purpose'] === 'invite' ? 'complete_invite' : 'complete_reset',
                'user',
                (int) $found['user']['id'],
                $found['user']['username']
            );
            notify_password_changed((int) $found['user']['id']);

            // Signed out deliberately: proving they can sign in with the new
            // password is a better ending than being dropped straight inside.
            $done = true;
        }
    }
}

$isInvite = $found && $found['token']['purpose'] === 'invite';
$siteName = get_setting('site_title', 'Mission Seedlings');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= $isInvite ? 'Set up your account' : 'Choose a new password' ?> — <?= e($siteName) ?></title>
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

    <?php if ($done): ?>
      <h1>Your password is set</h1>
      <p class="sub">You can now sign in with your username and the password you just chose.</p>
      <a class="btn" style="width:100%; display:block; text-align:center; text-decoration:none;"
         href="<?= e(url('/admin/login.php')) ?>">Go to sign in</a>

    <?php elseif (!$found): ?>
      <h1>This link is no longer valid</h1>
      <p class="sub">
        Invitation and reset links work once and then expire — this one has already been used,
        or it has run out of time.
      </p>
      <div class="admin-flash error" style="margin-bottom:22px;">
        Ask a Super Admin to send you a new invitation, or use "Forgotten your password?" on the
        sign-in page.
      </div>
      <a class="btn secondary" style="width:100%; display:block; text-align:center; text-decoration:none;"
         href="<?= e(url('/admin/login.php')) ?>">Back to sign in</a>

    <?php else: ?>
      <h1><?= $isInvite ? 'Welcome — choose a password' : 'Choose a new password' ?></h1>
      <p class="sub">
        <?php if ($isInvite): ?>
          You are setting up the account <strong><?= e($found['user']['username']) ?></strong>
          (<?= e(role_label($found['user']['role'])) ?>).
        <?php else: ?>
          Setting a new password for <strong><?= e($found['user']['username']) ?></strong>.
        <?php endif; ?>
      </p>

      <?php if ($errors): ?>
        <div class="admin-flash error">
          <?php foreach ($errors as $i => $err): ?>
            <?= $i ? '<br>' : '' ?><?= e($err) ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($raw) ?>">
        <input type="hidden" name="username" value="<?= e($found['user']['username']) ?>" autocomplete="username">
        <div class="form-row">
          <label for="password">Your new password</label>
          <input type="password" id="password" name="password" required minlength="10" autofocus autocomplete="new-password">
          <p class="help">At least 10 characters. A short phrase you will remember beats a short scramble you won't.</p>
        </div>
        <div class="form-row">
          <label for="confirm">Type it again</label>
          <input type="password" id="confirm" name="confirm" required minlength="10" autocomplete="new-password">
        </div>
        <button type="submit" class="btn" style="width:100%;">
          <?= $isInvite ? 'Set my password' : 'Save new password' ?>
        </button>
      </form>

      <p class="hint" style="margin-top:18px; text-align:center;">
        Nobody else can see this password — not even the person who created your account.
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
