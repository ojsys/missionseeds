<?php
/**
 * Bulletproof-ish CTA button. Expects $buttonUrl and $buttonLabel.
 * Table-based because Outlook ignores padding on <a>.
 */
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
  <tr>
    <td align="center" bgcolor="#c69447" style="border-radius:100px;">
      <a href="<?= e($buttonUrl) ?>"
         style="display:inline-block; padding:14px 30px; font-family:Arial,Helvetica,sans-serif;
                font-size:15px; font-weight:bold; color:#122519; text-decoration:none; border-radius:100px;">
        <?= e($buttonLabel) ?>
      </a>
    </td>
  </tr>
</table>
