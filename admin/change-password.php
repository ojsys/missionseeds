<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_admin();

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

        $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $flash = 'Current password is incorrect.';
            $flashType = 'error';
        } elseif (strlen($new) < 8) {
            $flash = 'New password must be at least 8 characters.';
            $flashType = 'error';
        } elseif ($new !== $confirm) {
            $flash = 'New password and confirmation do not match.';
            $flashType = 'error';
        } else {
            $upd = db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            $flash = 'Password updated.';
        }
    }
}

$pageTitle = 'Change password';
$active = 'password';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Account</div>
  <h1>Change password</h1>
  <p>If you're still using the default password from install.sql, change it now.</p>
</div>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div>
<?php endif; ?>

<div class="panel">
  <form method="post" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-row">
      <label for="current_password">Current password</label>
      <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>
    <div class="two-col">
      <div class="form-row">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="form-row">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn" style="justify-self:start;">Update password</button>
  </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
