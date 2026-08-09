<?php
/**
 * Sent when a Super Admin creates an account.
 * Vars: $name, $roleLabel, $inviteUrl, $expiresLabel, $invitedBy, $churchName
 */
$subject   = 'Set up your ' . get_setting('site_title', 'Mission Seedlings') . ' account';
$preheader = 'Choose your password and sign in — your invitation link is inside.';

$buttonUrl   = $inviteUrl;
$buttonLabel = 'Choose your password';
?>
<h1 style="margin:0 0 16px; font-family:Georgia,'Times New Roman',serif; font-size:23px; font-weight:600; color:#122519;">
  Welcome, <?= e($name) ?>
</h1>

<p style="margin:0 0 14px;">
  <?= e($invitedBy) ?> has set up an account for you on the
  <strong><?= e(get_setting('site_title', 'Mission Seedlings')) ?></strong> website.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background:#f7f2e4; border-radius:10px; margin:0 0 6px;">
  <tr>
    <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#20241c;">
      <strong>Username:</strong> <?= e($username) ?><br>
      <strong>Your role:</strong> <?= e($roleLabel) ?>
      <?php if (!empty($churchName)): ?><br><strong>Your church:</strong> <?= e($churchName) ?><?php endif; ?>
    </td>
  </tr>
</table>

<p style="margin:18px 0 0;">
  Click below to choose your own password. Nobody else — including the person who created your
  account — will ever see it.
</p>

<?php include __DIR__ . '/_button.php'; ?>

<p style="margin:0 0 6px; font-size:13px; color:#57584a;">
  This link works once and expires <?= e($expiresLabel) ?>. If it has expired, ask a Super Admin
  to send you a new one.
</p>
<p style="margin:0; font-size:13px; color:#57584a; word-break:break-all;">
  If the button does not work, copy this address into your browser:<br>
  <a href="<?= e($inviteUrl) ?>" style="color:#1f3b2c;"><?= e($inviteUrl) ?></a>
</p>
