<?php
/**
 * Sent when someone asks to reset their password, or a Super Admin resets it for them.
 * Vars: $name, $resetUrl, $expiresLabel, $byAdmin (bool)
 */
$subject   = 'Reset your ' . get_setting('site_title', 'Mission Seedlings') . ' password';
$preheader = 'A link to choose a new password. It expires shortly.';

$buttonUrl   = $resetUrl;
$buttonLabel = 'Choose a new password';
?>
<h1 style="margin:0 0 16px; font-family:Georgia,'Times New Roman',serif; font-size:23px; font-weight:600; color:#122519;">
  Reset your password
</h1>

<p style="margin:0 0 14px;">Hello <?= e($name) ?>,</p>

<p style="margin:0 0 14px;">
  <?php if (!empty($byAdmin)): ?>
    A Super Admin has started a password reset for your account. Use the link below to choose a new
    password — your old one no longer works.
  <?php else: ?>
    We received a request to reset the password on your account. Use the link below to choose a new one.
  <?php endif; ?>
</p>

<?php include __DIR__ . '/_button.php'; ?>

<p style="margin:0 0 6px; font-size:13px; color:#57584a;">
  This link works once and expires <?= e($expiresLabel) ?>.
</p>
<p style="margin:0 0 14px; font-size:13px; color:#57584a; word-break:break-all;">
  If the button does not work, copy this address into your browser:<br>
  <a href="<?= e($resetUrl) ?>" style="color:#1f3b2c;"><?= e($resetUrl) ?></a>
</p>

<?php if (empty($byAdmin)): ?>
<p style="margin:18px 0 0; padding:14px 16px; background:#f7f2e4; border-left:3px solid #7fa653;
          border-radius:0 8px 8px 0; font-size:13.5px; color:#57584a;">
  If you did not ask for this, you can ignore this email — your password has not changed. If it keeps
  happening, tell the project team.
</p>
<?php endif; ?>
