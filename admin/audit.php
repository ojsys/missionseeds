<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('view_audit');

$entries = get_audit_log(300, $_GET['entity'] ?? null);

// Turn action slugs into something a non-developer can read at a glance.
$labels = [
    'login'                => 'Signed in',
    'change_password'      => 'Changed their password',
    'reset_password'       => 'Reset a password',
    'create_user'          => 'Created an account',
    'update_user'          => 'Updated an account',
    'delete_user'          => 'Deleted an account',
    'edit_copy'            => 'Edited page copy',
    'create_church'        => 'Added a church',
    'update_church'        => 'Updated a church',
    'delete_church'        => 'Removed a church',
    'update_stage'         => 'Updated a pathway stage',
    'create_milestone'     => 'Added a milestone',
    'update_milestone'     => 'Updated a milestone',
    'delete_milestone'     => 'Removed a milestone',
    'create_indicator'     => 'Added an indicator',
    'update_indicator'     => 'Updated an indicator',
    'update_indicators'    => 'Updated the figures',
    'rollup_indicators'    => 'Filled indicators from church records',
    'delete_indicator'     => 'Removed an indicator',
    'create_story'         => 'Wrote a story',
    'update_story'         => 'Edited a story',
    'review_story'         => 'Reviewed a story',
    'delete_story'         => 'Deleted a story',
    'upload_media'         => 'Uploaded an image',
    'update_media'         => 'Updated image details',
    'delete_media'         => 'Deleted an image',
    'create_resource'      => 'Added a resource',
    'update_resource'      => 'Updated a resource',
    'delete_resource'      => 'Removed a resource',
    'create_kobo_form'     => 'Added a Kobo form link',
    'update_kobo_form'     => 'Updated a Kobo form link',
    'delete_kobo_form'     => 'Removed a Kobo form link',
    'contact_submission'   => 'Website enquiry received',
    'update_submission'    => 'Updated an enquiry',
    'delete_submission'    => 'Deleted an enquiry',
    'kobo_sync'            => 'KoboToolbox sync ran',
];

$pageEyebrow = 'Accounts';
$pageTitle   = 'Activity log';
$pageIntro   = 'Every change made through the admin area, newest first. Useful for answering "who changed this, and when".';
$active = 'audit';
include __DIR__ . '/partials/header.php';
?>


<div class="admin-note">
  IP addresses are stored as one-way hashes, so the log can group repeated activity without
  keeping anyone's actual address.
</div>

<div class="panel">
  <?php if (!$entries): ?>
    <p class="hint" style="margin:0;">Nothing recorded yet.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>When</th><th>Who</th><th>Did what</th><th>Detail</th></tr></thead>
      <tbody>
        <?php foreach ($entries as $row): ?>
        <tr>
          <td style="white-space:nowrap;">
            <?= e(format_date($row['created_at'], 'j M, H:i')) ?><br>
            <span class="help"><?= e(time_ago($row['created_at'])) ?></span>
          </td>
          <td><?= e($row['username'] ?? 'system') ?></td>
          <td><?= e($labels[$row['action']] ?? $row['action']) ?></td>
          <td><span class="help"><?= e($row['summary'] ?? '—') ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
