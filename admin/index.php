<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

// Coordinators belong in the portal, not here.
if (!is_staff_role($user['role'])) {
    header('Location: ' . home_for_role($user['role']));
    exit;
}

$rollup       = church_rollup();
$pendingCount = user_can('manage_stories') ? count_pending_stories() : 0;
$newEnquiries = user_can('view_submissions') ? count_new_submissions() : 0;
$phase        = current_phase_name();
$recent       = user_can('view_audit') ? get_audit_log(8) : [];

$pageTitle = 'Dashboard';
$active = 'dashboard';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Welcome back</div>
  <h1>Hi, <?= e(explode(' ', trim((string) ($user['full_name'] ?: $user['username'])))[0]) ?> 🌱</h1>
  <p>
    You can edit most page copy directly on the live site — open any page while signed in and click a
    headline or paragraph. Use the tools here for churches, progress figures, stories, and settings.
  </p>
</div>

<?php if ($pendingCount || $newEnquiries): ?>
<div class="admin-flash">
  <?php if ($pendingCount): ?>
    <strong><?= $pendingCount ?></strong> <?= $pendingCount === 1 ? 'story is' : 'stories are' ?> waiting for review.
    <a href="stories.php?status=pending">Review →</a>
  <?php endif; ?>
  <?php if ($pendingCount && $newEnquiries): ?> &nbsp;·&nbsp; <?php endif; ?>
  <?php if ($newEnquiries): ?>
    <strong><?= $newEnquiries ?></strong> new <?= $newEnquiries === 1 ? 'enquiry' : 'enquiries' ?>.
    <a href="submissions.php?status=new">Open the inbox →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="stat-strip">
  <div class="panel"><div class="eyebrow">Current phase</div><h2 style="font-size:20px;"><?= e($phase) ?></h2></div>
  <div class="panel"><div class="eyebrow">Churches</div><h2><?= number_format($rollup['churches_onboarded']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Participants</div><h2><?= number_format($rollup['participants']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Seedlings</div><h2><?= number_format($rollup['seedlings_distributed']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Trees planted</div><h2><?= number_format($rollup['trees_planted']) ?></h2></div>
</div>

<div class="admin-grid">
  <?php if (user_can('manage_project')): ?>
  <a class="admin-card" href="churches.php">
    <div class="icon"><?= icon_svg('shield') ?></div>
    <h3>Participating churches</h3>
    <p>Add churches, update their stage, participant counts, seedlings, and photos.</p>
  </a>
  <a class="admin-card" href="indicators.php">
    <div class="icon"><?= icon_svg('growth') ?></div>
    <h3>Impact indicators</h3>
    <p>Update the figures on the public Growth Tracker. Every change is dated automatically.</p>
  </a>
  <a class="admin-card" href="pathway.php">
    <div class="icon"><?= icon_svg('target') ?></div>
    <h3>Pathway &amp; milestones</h3>
    <p>Move stages forward and record what has been achieved at each one.</p>
  </a>
  <?php endif; ?>

  <?php if (user_can('manage_stories')): ?>
  <a class="admin-card" href="stories.php">
    <div class="icon"><?= icon_svg('chat') ?></div>
    <h3>Growth stories</h3>
    <p>Write updates, approve what coordinators send in, and publish to the website.</p>
  </a>
  <a class="admin-card" href="media.php">
    <div class="icon"><?= icon_svg('people') ?></div>
    <h3>Media library</h3>
    <p>Upload photos, add descriptions, and record consent before publishing.</p>
  </a>
  <?php endif; ?>

  <?php if (user_can('manage_resources')): ?>
  <a class="admin-card" href="resources.php">
    <div class="icon"><?= icon_svg('book') ?></div>
    <h3>Resource hub</h3>
    <p>Public documents, restricted field materials, and KoboToolbox form links.</p>
  </a>
  <?php endif; ?>

  <?php if (user_can('view_submissions')): ?>
  <a class="admin-card" href="submissions.php">
    <div class="icon"><?= icon_svg('handshake') ?></div>
    <h3>Enquiries</h3>
    <p>Messages from the contact form — churches, partners, and general questions.</p>
  </a>
  <?php endif; ?>

  <a class="admin-card" href="<?= e(url('/')) ?>" target="_blank">
    <div class="icon"><?= icon_svg('seedling') ?></div>
    <h3>Edit page copy</h3>
    <p>Open the live site and click any headline or paragraph to edit it in place.</p>
  </a>

  <?php if (user_can('manage_content')): ?>
  <a class="admin-card" href="lists.php">
    <div class="icon"><?= icon_svg('checklist') ?></div>
    <h3>Call page lists</h3>
    <p>"Who should apply" and "What churches receive" on the call-for-applications page.</p>
  </a>
  <a class="admin-card" href="partners.php">
    <div class="icon"><?= icon_svg('handshake') ?></div>
    <h3>Partners</h3>
    <p>Partner logos shown on the call page.</p>
  </a>
  <?php endif; ?>

  <?php if (user_can('manage_settings')): ?>
  <a class="admin-card" href="settings.php">
    <div class="icon"><?= icon_svg('sun') ?></div>
    <h3>Site settings</h3>
    <p>Call status, deadline, application link, WhatsApp number, and site identity.</p>
  </a>
  <?php endif; ?>

  <?php if (user_can('manage_users')): ?>
  <a class="admin-card" href="users.php">
    <div class="icon"><?= icon_svg('network') ?></div>
    <h3>Users and roles</h3>
    <p>Create accounts for staff and church coordinators, and set what each can do.</p>
  </a>
  <?php endif; ?>
</div>

<?php if ($recent): ?>
<div class="panel" style="margin-top:32px;">
  <div class="panel-head">
    <h2>Recent activity</h2>
    <a class="btn secondary small" href="audit.php">See all</a>
  </div>
  <table class="data-table">
    <tbody>
      <?php foreach ($recent as $row): ?>
      <tr>
        <td style="white-space:nowrap;"><span class="help"><?= e(time_ago($row['created_at'])) ?></span></td>
        <td><strong><?= e($row['username'] ?? 'system') ?></strong></td>
        <td>
          <?= e(str_replace('_', ' ', $row['action'])) ?>
          <?php if ($row['summary']): ?> — <span class="help"><?= e($row['summary']) ?></span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
