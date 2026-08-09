<?php
/**
 * Sent by Settings → Email → "Send a test email", to prove the SMTP details work.
 * Vars: $sentBy
 */
$subject   = get_setting('site_title', 'Mission Seedlings') . ' — test email';
$preheader = 'Your SMTP settings are working.';
?>
<h1 style="margin:0 0 16px; font-family:Georgia,'Times New Roman',serif; font-size:23px; font-weight:600; color:#122519;">
  Email is working
</h1>

<p style="margin:0 0 14px;">
  If you are reading this, the website can send email. Invitations, password resets, and security
  notices will reach people.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background:#f7f2e4; border-radius:10px;">
  <tr>
    <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif; font-size:13.5px; color:#57584a;">
      Sent by <strong><?= e($sentBy) ?></strong> on <?= e(date('j F Y, H:i')) ?>.
    </td>
  </tr>
</table>
