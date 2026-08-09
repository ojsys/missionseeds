<?php
/**
 * Security notice after a successful password change. Not optional politeness:
 * it is how someone finds out if their account was taken over.
 * Vars: $name, $whenLabel
 */
$subject   = 'Your ' . get_setting('site_title', 'Mission Seedlings') . ' password was changed';
$preheader = 'A confirmation that your password was updated.';
?>
<h1 style="margin:0 0 16px; font-family:Georgia,'Times New Roman',serif; font-size:23px; font-weight:600; color:#122519;">
  Your password was changed
</h1>

<p style="margin:0 0 14px;">Hello <?= e($name) ?>,</p>

<p style="margin:0 0 14px;">
  The password on your account was changed on <strong><?= e($whenLabel) ?></strong>.
  If that was you, nothing further is needed.
</p>

<p style="margin:18px 0 0; padding:14px 16px; background:#fbe7e3; border-left:3px solid #a8382a;
          border-radius:0 8px 8px 0; font-size:14px; color:#7e2a1f;">
  <strong>If this was not you</strong>, contact the project team straight away so your account can
  be secured.
</p>
