<?php
/**
 * Tells the team that someone has used the contact form.
 *
 * DELIBERATELY WITHHOLDS THE ENQUIRER'S CONTACT DETAILS. Their email address
 * and phone number stay in the admin inbox, behind a login, where access is
 * controlled and logged — rather than being scattered across staff mailboxes
 * and forwarded threads. This email says enough to judge urgency, and links to
 * the place where the rest lives.
 *
 * Vars: $enquiryName, $enquiryType, $excerpt, $churchName, $community,
 *       $organisation, $inboxUrl, $receivedAt
 */
$subject   = 'New ' . lcfirst($enquiryType) . ' — ' . $enquiryName;
$preheader = $enquiryName . ' sent a message through the website.';

$buttonUrl   = $inboxUrl;
$buttonLabel = 'Open the enquiry';
?>
<h1 style="margin:0 0 16px; font-family:Georgia,'Times New Roman',serif; font-size:23px; font-weight:600; color:#122519;">
  New enquiry from the website
</h1>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background:#f7f2e4; border-radius:10px; margin:0 0 18px;">
  <tr>
    <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.7; color:#20241c;">
      <strong>From:</strong> <?= e($enquiryName) ?><br>
      <strong>About:</strong> <?= e($enquiryType) ?><br>
      <?php if (!empty($churchName)): ?><strong>Church:</strong> <?= e($churchName) ?><br><?php endif; ?>
      <?php if (!empty($community)): ?><strong>Community:</strong> <?= e($community) ?><br><?php endif; ?>
      <?php if (!empty($organisation)): ?><strong>Organisation:</strong> <?= e($organisation) ?><br><?php endif; ?>
      <strong>Received:</strong> <?= e($receivedAt) ?>
    </td>
  </tr>
</table>

<p style="margin:0 0 6px; font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.08em;
          text-transform:uppercase; color:#57584a;">
  What they said
</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="border-left:3px solid #7fa653; background:#ffffff;">
  <tr>
    <td style="padding:4px 0 4px 16px; font-family:Arial,Helvetica,sans-serif; font-size:14.5px;
               line-height:1.6; color:#3a3a2e;">
      <?= nl2br(e($excerpt)) ?>
    </td>
  </tr>
</table>

<?php include __DIR__ . '/_button.php'; ?>

<p style="margin:0; font-size:13px; color:#57584a;">
  Their email address and phone number are on the enquiry itself — kept behind a sign-in rather
  than sent out by email. Reply from there, and mark it handled so nobody duplicates the work.
</p>
