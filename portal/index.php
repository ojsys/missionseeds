<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['church_coordinator']);

// Every query on this page is scoped by $user['church_id'] from the session,
// never by anything the browser sends.
$church = $user['church_id'] ? get_church((int) $user['church_id']) : null;
$mine   = $church ? get_stories([
    'church_id' => (int) $church['id'],
    'status'    => array_keys(STORY_STATUSES),
    'limit'     => 10,
]) : [];

$pending   = count(array_filter($mine, fn($s) => $s['status'] === 'pending'));
$published = count(array_filter($mine, fn($s) => $s['status'] === 'published'));

$firstName   = explode(' ', trim((string) ($user['full_name'] ?: $user['username'])))[0];
$pageEyebrow = 'Church portal';
$pageTitle   = 'Hi, ' . $firstName . ' 🌱';
$pageIntro   = 'Share what is happening at your church, and keep an eye on your progress.';
$pageActions = '<a class="btn" href="updates.php">Write an update</a>';
$active = 'home';
include __DIR__ . '/partials/header.php';
?>

<?php if (!$church): ?>
  <div class="admin-flash error">
    Your account is not linked to a church yet. Ask a project manager to connect it, then sign in again.
  </div>
<?php else: ?>

<div class="stat-strip">
  <div class="panel"><div class="eyebrow">Stage</div><h2 style="font-size:19px;"><?= e(stage_name($church['stage_key'])) ?></h2></div>
  <div class="panel"><div class="eyebrow">Participants</div><h2><?= number_format($church['participant_count']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Seedlings</div><h2><?= number_format($church['seedlings_allocated']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Trees planted</div><h2><?= number_format($church['trees_planted']) ?></h2></div>
  <?php if ($church['survival_rate'] !== null): ?>
  <div class="panel"><div class="eyebrow">Survival</div><h2><?= e(rtrim(rtrim(number_format((float) $church['survival_rate'], 1), '0'), '.')) ?>%</h2></div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head">
    <h2><?= e($church['name']) ?></h2>
    <span class="pill <?= $church['is_published'] ? 'ok' : 'muted' ?>"><?= $church['is_published'] ? 'Published' : 'Not yet published' ?></span>
  </div>
  <p class="hint">
    <?= e($church['community'] ? $church['community'] . ', ' : '') ?><?= e($church['state']) ?>
    <?php if ($church['joined_on']): ?> · joined <?= e(format_date($church['joined_on'], 'F Y')) ?><?php endif; ?>
  </p>
  <?php if ($church['summary']): ?><p style="font-size:15px;"><?= nl2br(e($church['summary'])) ?></p><?php endif; ?>
  <p class="hint" style="margin-top:16px; margin-bottom:0;">
    These figures are maintained by the project team from your KoboToolbox reports.
    If something looks wrong, let your project manager know.
  </p>
  <?php if ($church['is_published']): ?>
    <p style="margin-top:16px;"><a class="btn secondary small" href="<?= e(url('/churches/' . $church['slug'])) ?>" target="_blank">See your public profile ↗</a></p>
  <?php endif; ?>
</div>

<div class="admin-grid">
  <a class="admin-card" href="updates.php">
    <div class="icon"><?= icon_svg('chat') ?></div>
    <h3>Write an update</h3>
    <p>Share a story, a training day, a planting session, or a meeting — with photos.</p>
  </a>
  <a class="admin-card" href="<?= e(url('/resources')) ?>">
    <div class="icon"><?= icon_svg('checklist') ?></div>
    <h3>Forms and resources</h3>
    <p>Your monthly cooperative report, tree monitoring form, and field materials.</p>
  </a>
  <a class="admin-card" href="../admin/change-password.php">
    <div class="icon"><?= icon_svg('shield') ?></div>
    <h3>Change your password</h3>
    <p>Keep your account secure. Never share your sign-in details with anyone.</p>
  </a>
</div>

<div class="panel" style="margin-top:32px;">
  <div class="panel-head">
    <h2>Your updates</h2>
    <a class="btn small" href="updates.php">Write a new one</a>
  </div>
  <p class="hint">
    <?= $published ?> published · <?= $pending ?> waiting for review.
    A project manager checks every update before it appears on the website.
  </p>

  <?php if (!$mine): ?>
    <p class="hint" style="margin-bottom:0;">You have not written anything yet.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Update</th><th>Status</th><th>When</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($mine as $s): ?>
        <tr class="<?= $s['status'] === 'published' ? '' : 'muted-row' ?>">
          <td><strong><?= e($s['title']) ?></strong></td>
          <td><span class="pill <?= $s['status'] === 'published' ? 'ok' : ($s['status'] === 'pending' ? 'warn' : ($s['status'] === 'rejected' ? 'alert' : 'muted')) ?>">
            <?= e(STORY_STATUSES[$s['status']]) ?></span>
            <?php if ($s['status'] === 'rejected' && $s['review_note']): ?>
              <br><span class="help"><?= e($s['review_note']) ?></span>
            <?php endif; ?>
          </td>
          <td><span class="help"><?= e(time_ago($s['updated_at'])) ?></span></td>
          <td class="actions">
            <?php if (can_edit_story($s, $user)): ?>
              <a href="updates.php?edit=<?= (int) $s['id'] ?>" title="Edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
              </a>
            <?php endif; ?>
            <?php if ($s['status'] === 'published'): ?>
              <a href="<?= e(url('/stories/' . $s['slug'])) ?>" target="_blank" title="View">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
