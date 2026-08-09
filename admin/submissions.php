<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('view_submissions');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        if (($_POST['action'] ?? '') === 'delete') {
            delete_submission((int) $_POST['id']);
            $flash = 'Enquiry deleted.';
        } else {
            update_submission((int) $_POST['id'], (string) $_POST['status'], $_POST['internal_note'] ?? null);
            $flash = 'Enquiry updated.';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$statusFilter = $_GET['status'] ?? '';
$typeFilter   = $_GET['type'] ?? '';
$items = get_submissions(
    array_key_exists($statusFilter, SUBMISSION_STATUSES) ? $statusFilter : null,
    array_key_exists($typeFilter, SUBMISSION_TYPES) ? $typeFilter : null
);
$newCount = count_new_submissions();

$pageEyebrow = 'Inbox';
$pageTitle   = 'Contact and interest enquiries';
$pageIntro   = 'Messages sent through the website\'s contact form.';
$active = 'submissions';
include __DIR__ . '/partials/header.php';
?>


<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<div class="admin-note">
  <strong>This page holds personal data.</strong> Names, emails, and phone numbers people gave us
  so we could reply. Use them for that and nothing else, do not copy them elsewhere, and archive
  or delete enquiries once they are dealt with.
</div>

<div class="filter-row">
  <a class="<?= $statusFilter === '' && $typeFilter === '' ? 'active' : '' ?>" href="submissions.php">All</a>
  <?php foreach (SUBMISSION_STATUSES as $k => $label): ?>
    <a class="<?= $statusFilter === $k ? 'active' : '' ?>" href="submissions.php?status=<?= e($k) ?>">
      <?= e($label) ?><?= $k === 'new' && $newCount ? ' (' . $newCount . ')' : '' ?>
    </a>
  <?php endforeach; ?>
  <?php foreach (SUBMISSION_TYPES as $k => $label): ?>
    <a class="<?= $typeFilter === $k ? 'active' : '' ?>" href="submissions.php?type=<?= e($k) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$items): ?>
  <div class="panel"><p class="hint" style="margin:0;">Nothing here.</p></div>
<?php endif; ?>

<?php foreach ($items as $s): ?>
<div class="panel">
  <div class="panel-head">
    <h2><?= e($s['name']) ?></h2>
    <div style="display:flex; gap:8px; align-items:center;">
      <span class="pill"><?= e(SUBMISSION_TYPES[$s['type']]) ?></span>
      <span class="pill <?= $s['status'] === 'new' ? 'warn' : ($s['status'] === 'responded' ? 'ok' : 'muted') ?>">
        <?= e(SUBMISSION_STATUSES[$s['status']]) ?>
      </span>
    </div>
  </div>

  <p class="hint" style="margin-bottom:14px;">
    <?= e(format_date($s['created_at'], 'j M Y, H:i')) ?> · <?= e(time_ago($s['created_at'])) ?>
    <?php if ($s['church_name']): ?> · <?= e($s['church_name']) ?><?php endif; ?>
    <?php if ($s['community']): ?> · <?= e($s['community']) ?><?php endif; ?>
    <?php if ($s['organisation']): ?> · <?= e($s['organisation']) ?><?php endif; ?>
  </p>

  <div style="display:flex; gap:18px; flex-wrap:wrap; margin-bottom:16px;">
    <?php if ($s['email']): ?>
      <a class="btn secondary small" href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a>
    <?php endif; ?>
    <?php if ($s['phone']): ?>
      <a class="btn secondary small" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $s['phone'])) ?>"><?= e($s['phone']) ?></a>
    <?php endif; ?>
  </div>

  <div style="background:var(--cream); border-radius:8px; padding:18px 20px; font-size:15px; white-space:pre-wrap;"><?= e($s['message']) ?></div>

  <form method="post" class="form-grid" style="margin-top:20px; gap:12px;">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
    <div class="two-col">
      <?php field_select('status', 'Status', SUBMISSION_STATUSES, $s['status']); ?>
      <?php field_text('internal_note', 'Internal note', $s['internal_note'] ?? '', [
          'help' => 'Visible only to staff on this page.',
      ]); ?>
    </div>
    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn small">Save</button>
    </div>
  </form>

  <form method="post" style="margin-top:12px;"
        onsubmit="return confirm('Permanently delete this enquiry and the personal details in it?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
    <button type="submit" class="btn small danger">Delete permanently</button>
  </form>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
