<?php
/**
 * Shared HTML email shell.
 *
 * Email clients are twenty years behind browsers: no external stylesheets, no
 * flexbox or grid in Outlook, and <style> blocks stripped by several webmail
 * clients. So this is table-based with inline styles, on purpose. Please keep
 * it that way when editing.
 *
 * Expects $body (the rendered template) and $subject. $cfg is the mail config.
 */
$siteName = get_setting('site_title', 'Mission Seedlings');
$signoff  = $cfg['signoff'] ?? 'The Mission Seedlings team';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($subject) ?></title>
</head>
<body style="margin:0; padding:0; background:#eee4cd; -webkit-font-smoothing:antialiased;">

<!-- Preheader: the grey preview line in an inbox list. Hidden in the message. -->
<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; height:0; width:0;">
  <?= e($preheader ?? $subject) ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eee4cd; padding:28px 12px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="max-width:560px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid rgba(31,59,44,0.14);">

        <!-- header -->
        <tr>
          <td style="background:#122519; padding:22px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding-right:10px; vertical-align:middle;">
                  <div style="width:30px; height:30px; border-radius:50%; background:#7fa653; text-align:center; line-height:30px; font-size:16px;">🌱</div>
                </td>
                <td style="vertical-align:middle;">
                  <div style="font-family:Georgia,'Times New Roman',serif; font-size:17px; font-weight:600; color:#f7f2e4; letter-spacing:0.01em;">
                    <?= e($siteName) ?>
                  </div>
                  <div style="font-family:Arial,Helvetica,sans-serif; font-size:10px; letter-spacing:0.12em; text-transform:uppercase; color:#c69447; padding-top:2px;">
                    <?= e(get_setting('tagline', 'Growing Local. Flourishing Together.')) ?>
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- body -->
        <tr>
          <td style="padding:32px 30px 26px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#20241c;">
            <?= $body ?>

            <p style="margin:26px 0 0; font-size:15px; color:#20241c;">
              — <?= e($signoff) ?>
            </p>
          </td>
        </tr>

        <!-- footer -->
        <tr>
          <td style="background:#f7f2e4; padding:18px 30px; border-top:1px solid rgba(31,59,44,0.14);
                     font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:1.55; color:#57584a;">
            <?= e($siteName) ?> · <?= e(get_setting('footer_address', 'Jos, Plateau State, Nigeria')) ?><br>
            <a href="<?= e(url('/')) ?>" style="color:#1f3b2c;"><?= e(preg_replace('#^https?://#', '', rtrim(SITE_URL, '/'))) ?></a>
            <?php if ($wa = get_setting('whatsapp_display')): ?>
              · WhatsApp <?= e($wa) ?>
            <?php endif; ?>
            <div style="margin-top:10px; color:#8a8a7a;">
              You are receiving this because someone on the Mission Seedlings team set up or changed
              an account for you. If that was not expected, please reply and let us know.
            </div>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
