<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$forced = !empty($_GET['forced']) || $user['must_change_password'];
$flash = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = 'Session expired — please try again.';
        $flashType = 'error';
    } else {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $flash = 'Current password is incorrect.';
            $flashType = 'error';
        } elseif (strlen($new) < 10) {
            $flash = 'New password must be at least 10 characters.';
            $flashType = 'error';
        } elseif ($new === $current) {
            $flash = 'Please choose a password different from your current one.';
            $flashType = 'error';
        } elseif ($new !== $confirm) {
            $flash = 'New password and confirmation do not match.';
            $flashType = 'error';
        } else {
            $upd = db()->prepare(
                'UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?'
            );
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            audit('change_password', 'user', (int) $user['id'], $user['username']);

            if ($forced) {
                header('Location: ' . home_for_role($user['role']));
                exit;
            }
            $flash  = 'Password updated.';
            $forced = false;
        }
    }
}

$pageEyebrow = 'Account';
$pageTitle   = 'Change password';
$pageIntro   = $forced
    ? 'Your account was created with a temporary password. Please choose your own before continuing.'
    : "Use at least 10 characters. A short phrase you will remember beats a short scramble you won't.";
$active = 'password';
include __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div>
<?php endif; ?>

<div class="panel">
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <div class="form-row">
      <label for="current_password"><?= $forced ? 'Temporary password' : 'Current password' ?></label>
      <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>
    <div class="two-col">
      <div class="form-row">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="10" autocomplete="new-password">
      </div>
      <div class="form-row">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="10" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn" style="justify-self:start;">Update password</button>
  </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
