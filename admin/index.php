<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

// Coordinators belong in the portal, not here.
if (!is_staff_role($user['role'])) {
    header('Location: ' . home_for_role($user['role']));
    exit;
}

$rollup = church_rollup();
$phase  = current_phase_name();
$stages = get_pathway_stages();

// "Needs attention" is the actual work queue, not a set of counters — the
// sidebar already carries the counts.
$pendingStories = user_can('manage_stories')   ? get_stories(['status' => 'pending', 'limit' => 5]) : [];
$newEnquiries   = user_can('view_submissions') ? get_submissions('new', null, 5) : [];

$staleIndicator = null;
if (user_can('manage_project')) {
    $row = db()->query(
        'SELECT label, updated_at FROM indicators WHERE is_public = 1 ORDER BY updated_at ASC LIMIT 1'
    )->fetch();
    // Nudge if the public tracker has not been touched in a month.
    if ($row && strtotime($row['updated_at']) < strtotime('-30 days')) {
        $staleIndicator = $row;
    }
}
$attention = count($pendingStories) + count($newEnquiries) + ($staleIndicator ? 1 : 0);

$recent = user_can('view_audit') ? get_audit_log(6) : [];

$firstName   = explode(' ', trim((string) ($user['full_name'] ?: $user['username'])))[0];
$pageEyebrow = 'Welcome back';
$pageTitle   = 'Hi, ' . $firstName . ' 🌱';
$pageIntro   = 'Edit most page copy directly on the live site — open any page while signed in and click '
             . 'a headline or paragraph. Use the tools here for churches, figures, stories, and settings.';
$pageActions = '<a class="btn secondary" href="' . e(url('/')) . '" target="_blank" rel="noopener">Open the website</a>';
$active = 'dashboard';
include __DIR__ . '/partials/header.php';
?>

<!-- ============ HEADLINE FIGURES ============ -->
<div class="stat-strip">
  <div class="panel"><div class="eyebrow">Current phase</div><h2 style="font-size:20px;"><?= e($phase) ?></h2></div>
  <div class="panel"><div class="eyebrow">Churches</div><h2><?= number_format($rollup['churches_onboarded']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Participants</div><h2><?= number_format($rollup['participants']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Seedlings</div><h2><?= number_format($rollup['seedlings_distributed']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Trees planted</div><h2><?= number_format($rollup['trees_planted']) ?></h2></div>
</div>

<div class="dash-grid">
  <div class="dash-col">

    <!-- ============ NEEDS ATTENTION ============ -->
    <div class="panel">
      <div class="panel-head">
        <h2>Needs your attention</h2>
        <?php if ($attention): ?><span class="pill alert"><?= $attention ?></span><?php endif; ?>
      </div>

      <?php if (!$attention): ?>
        <div class="dash-empty">
          <?= nav_icon('grid') ?>
          <p>Nothing waiting — the queue is clear.</p>
        </div>
      <?php endif; ?>

      <?php if ($pendingStories): ?>
      <div class="queue">
        <div class="queue-label">Stories awaiting review</div>
        <?php foreach ($pendingStories as $s): ?>
        <div class="queue-row">
          <div class="queue-main">
            <a href="stories.php?edit=<?= (int) $s['id'] ?>"><?= e($s['title']) ?></a>
            <span class="help"><?= e($s['church_name'] ?? 'No church') ?> · <?= e(time_ago($s['updated_at'])) ?></span>
          </div>
          <?php if (user_can('publish_stories')): ?>
          <form method="post" action="stories.php" data-no-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <input type="hidden" name="status" value="published">
            <button type="submit" class="btn small">Publish</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($newEnquiries): ?>
      <div class="queue">
        <div class="queue-label">New enquiries</div>
        <?php foreach ($newEnquiries as $q): ?>
        <div class="queue-row">
          <div class="queue-main">
            <a href="submissions.php?status=new"><?= e($q['name']) ?></a>
            <span class="help"><?= e(SUBMISSION_TYPES[$q['type']]) ?> · <?= e(time_ago($q['created_at'])) ?></span>
          </div>
          <a class="btn small secondary" href="submissions.php?status=new">Read</a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($staleIndicator): ?>
      <div class="queue">
        <div class="queue-label">Growth Tracker</div>
        <div class="queue-row">
          <div class="queue-main">
            <a href="indicators.php">The public figures haven't changed in over a month</a>
            <span class="help">Oldest: <?= e($staleIndicator['label']) ?>, <?= e(time_ago($staleIndicator['updated_at'])) ?></span>
          </div>
          <a class="btn small secondary" href="indicators.php">Update</a>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ============ RECENT ACTIVITY ============ -->
    <?php if ($recent): ?>
    <div class="panel">
      <div class="panel-head">
        <h2>Recent activity</h2>
        <a class="btn secondary small" href="audit.php">See all</a>
      </div>
      <ul class="activity-list">
        <?php foreach ($recent as $row): ?>
        <li>
          <span class="activity-dot" aria-hidden="true"></span>
          <div>
            <strong><?= e($row['username'] ?? 'system') ?></strong>
            <?= e(str_replace('_', ' ', $row['action'])) ?>
            <?php if ($row['summary']): ?><span class="help">— <?= e($row['summary']) ?></span><?php endif; ?>
            <span class="activity-when"><?= e(time_ago($row['created_at'])) ?></span>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <div class="dash-col">

    <!-- ============ QUICK ACTIONS ============ -->
    <div class="panel">
      <h2>Quick actions</h2>
      <div class="quick-actions">
        <?php if (user_can('manage_project')): ?>
          <a href="churches.php"><?= nav_icon('church') ?><span>Add a church</span></a>
          <a href="indicators.php"><?= nav_icon('chart') ?><span>Update the figures</span></a>
        <?php endif; ?>
        <?php if (user_can('manage_stories')): ?>
          <a href="stories.php"><?= nav_icon('file') ?><span>Write a story</span></a>
          <a href="media.php"><?= nav_icon('image') ?><span>Upload photos</span></a>
        <?php endif; ?>
        <?php if (user_can('manage_resources')): ?>
          <a href="resources.php"><?= nav_icon('folder') ?><span>Add a resource</span></a>
        <?php endif; ?>
        <?php if (user_can('manage_users')): ?>
          <a href="users.php"><?= nav_icon('users') ?><span>Create an account</span></a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============ PATHWAY AT A GLANCE ============ -->
    <div class="panel">
      <div class="panel-head">
        <h2>Pathway</h2>
        <?php if (user_can('manage_project')): ?>
          <a class="btn secondary small" href="pathway.php">Edit</a>
        <?php endif; ?>
      </div>
      <ol class="mini-stages">
        <?php foreach ($stages as $i => $s): ?>
        <li class="status-<?= e($s['status']) ?>">
          <span class="mini-stage-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <span class="mini-stage-name"><?= e($s['name']) ?></span>
          <span class="mini-stage-status"><?= e(STAGE_STATUSES[$s['status']] ?? '') ?></span>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <!-- ============ CALL STATUS ============ -->
    <?php if (user_can('manage_settings')): ?>
      <?php
        $callStatus = get_setting('call_status', 'open');
        $callLabels = ['open' => 'Open', 'closed' => 'Closed', 'hidden' => 'Hidden'];
        $deadline   = get_setting('deadline_date');
        $daysLeft   = $deadline ? days_until($deadline) : null;
      ?>
      <div class="panel">
        <div class="panel-head">
          <h2>Call for applications</h2>
          <span class="pill <?= $callStatus === 'open' ? 'ok' : 'muted' ?>"><?= e($callLabels[$callStatus] ?? $callStatus) ?></span>
        </div>
        <p class="hint" style="margin-bottom:14px;">
          <?php if ($callStatus === 'open' && $daysLeft !== null): ?>
            Deadline <?= e(format_date($deadline)) ?> —
            <?= $daysLeft >= 0 ? $daysLeft . ' days left' : 'the date has passed' ?>.
          <?php elseif ($callStatus === 'open'): ?>
            The call page is live and accepting applications.
          <?php else: ?>
            Not accepting applications. Reopen it from Settings for the next cycle.
          <?php endif; ?>
        </p>
        <a class="btn secondary small" href="settings.php">Call settings</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
