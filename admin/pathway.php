<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_project');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';
$editingMilestone = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'save_stage':
                update_pathway_stage((string) $_POST['stage_key'], $_POST);
                $flash = 'Stage updated.';
                break;

            case 'save_milestone':
                save_milestone($_POST);
                $flash = 'Milestone saved.';
                break;

            case 'delete_milestone':
                delete_milestone((int) $_POST['id']);
                $flash = 'Milestone removed.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
        if (($_POST['action'] ?? '') === 'save_milestone') {
            $editingMilestone = $_POST;
        }
    }
}

if (!$editingMilestone && !empty($_GET['milestone'])) {
    $editingMilestone = get_milestone((int) $_GET['milestone']);
}
$isNewMilestone = empty($editingMilestone['id']);

// get_pathway_stages() caches per request; re-read after an update so the page
// reflects what was just saved.
$stages = db()->query('SELECT * FROM pathway_stages ORDER BY sort_order ASC')->fetchAll();
$byStage = milestones_by_stage(true);

$pageTitle = 'Pathway';
$active = 'pathway';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Project</div>
  <h1>Pathway &amp; milestones</h1>
  <p>The seven stages are fixed. Edit each one's description and status, and record the milestones underneath it.</p>
</div>

<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<!-- ============ MILESTONE FORM ============ -->
<div class="panel">
  <div class="panel-head">
    <h2><?= $isNewMilestone ? 'Add a milestone' : 'Edit milestone' ?></h2>
    <?php if (!$isNewMilestone): ?><a class="btn secondary small" href="pathway.php">Cancel edit</a><?php endif; ?>
  </div>

  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_milestone">
    <?php if (!$isNewMilestone): ?><input type="hidden" name="id" value="<?= (int) $editingMilestone['id'] ?>"><?php endif; ?>

    <?php field_text('title', 'Milestone', $editingMilestone['title'] ?? '', [
        'required' => true, 'maxlength' => 200,
        'placeholder' => 'e.g. First 500 seedlings distributed',
    ]); ?>

    <?php field_textarea('description', 'Description', $editingMilestone['description'] ?? '', ['rows' => 3]); ?>

    <div class="three-col">
      <?php
        field_select('stage_key', 'Stage', stage_options(), $editingMilestone['stage_key'] ?? 'discover');
        field_select('status', 'Status', MILESTONE_STATUSES, $editingMilestone['status'] ?? 'planned');
        field_text('target_date', 'Target date', $editingMilestone['target_date'] ?? '', ['type' => 'date']);
      ?>
    </div>

    <?php field_text('completed_on', 'Completed on', $editingMilestone['completed_on'] ?? '', [
        'type' => 'date',
        'help' => 'Only used when the status is Complete. Left blank, it defaults to today.',
    ]); ?>

    <?php field_checkbox('is_public', 'Show this milestone on the public website',
        $isNewMilestone ? true : !empty($editingMilestone['is_public']),
        'Uncheck for internal milestones you want to track but not publish.'); ?>

    <button type="submit" class="btn" style="justify-self:start;"><?= $isNewMilestone ? 'Add milestone' : 'Save milestone' ?></button>
  </form>
</div>

<!-- ============ STAGES ============ -->
<?php foreach ($stages as $i => $stage): ?>
<div class="panel">
  <div class="panel-head">
    <h2><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?> · <?= e($stage['name']) ?></h2>
    <span class="pill <?= $stage['status'] === 'complete' ? 'ok' : ($stage['status'] === 'in_progress' ? 'warn' : 'muted') ?>">
      <?= e(STAGE_STATUSES[$stage['status']] ?? '') ?>
    </span>
  </div>

  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_stage">
    <input type="hidden" name="stage_key" value="<?= e($stage['stage_key']) ?>">

    <div class="two-col">
      <?php
        field_text('name', 'Stage name', $stage['name'], ['required' => true]);
        field_select('status', 'Status', STAGE_STATUSES, $stage['status']);
      ?>
    </div>

    <?php
      field_textarea('short_description', 'Description', $stage['short_description'], [
          'rows' => 3, 'help' => 'Shown under the stage on the public Pathway page.',
      ]);
    ?>

    <div class="two-col">
      <?php
        field_text('status_note', 'Status note', $stage['status_note'] ?? '', [
            'maxlength' => 255,
            'help' => 'Optional one-liner, e.g. "Training rounds underway".',
        ]);
        field_icon('icon_key', 'Icon', $stage['icon_key']);
      ?>
    </div>

    <button type="submit" class="btn small" style="justify-self:start;">Save stage</button>
  </form>

  <?php $ms = $byStage[$stage['stage_key']] ?? []; ?>
  <?php if ($ms): ?>
  <div class="table-scroll" style="margin-top:24px;">
    <table class="data-table">
      <thead><tr><th>Milestone</th><th>Status</th><th>Date</th><th>Public</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($ms as $m): ?>
        <tr class="<?= $m['is_public'] ? '' : 'muted-row' ?>">
          <td><strong><?= e($m['title']) ?></strong></td>
          <td><span class="pill <?= $m['status'] === 'complete' ? 'ok' : ($m['status'] === 'blocked' ? 'alert' : ($m['status'] === 'in_progress' ? 'warn' : 'muted')) ?>">
            <?= e(MILESTONE_STATUSES[$m['status']]) ?></span></td>
          <td><?= e($m['completed_on'] ? format_date($m['completed_on'], 'M Y') : ($m['target_date'] ? 'target ' . format_date($m['target_date'], 'M Y') : '—')) ?></td>
          <td><span class="pill <?= $m['is_public'] ? 'ok' : 'muted' ?>"><?= $m['is_public'] ? 'Yes' : 'Internal' ?></span></td>
          <td class="actions">
            <a href="pathway.php?milestone=<?= (int) $m['id'] ?>" title="Edit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
            </a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this milestone?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_milestone">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <?= action_button(SVG_TRASH, 'Delete', ['class' => 'del']) ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="hint" style="margin-top:20px; margin-bottom:0;">No milestones recorded for this stage yet.</p>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
