<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_project');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            delete_church((int) $_POST['id']);
            $flash = 'Church removed.';
        } else {
            $data = $_POST;
            if (!empty($_FILES['photo']['name'])) {
                $data['photo_path'] = save_uploaded_image($_FILES['photo'], 'church');
            }
            save_church($data);
            $flash = 'Church saved.';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
        $editing = $_POST;
    }
}

if (!$editing && !empty($_GET['edit'])) {
    $editing = get_church((int) $_GET['edit']);
}
$isNew    = empty($editing['id']);
$churches = get_churches(true);
$rollup   = church_rollup();

$pageEyebrow = 'Project';
$pageTitle   = 'Participating churches';
$pageIntro   = 'Church profiles power the public Churches page and roll up into the Growth Tracker.';
$active = 'churches';
include __DIR__ . '/partials/header.php';
?>


<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<div class="admin-note">
  <strong>Never enter personal data here.</strong> Participant names, phone numbers, household
  income, ID numbers, and home locations belong in KoboToolbox, not on this page. Everything
  saved here can appear on the public website once the profile is published.
</div>

<div class="stat-strip">
  <div class="panel"><div class="eyebrow">Churches</div><h2><?= number_format($rollup['churches_onboarded']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Participants</div><h2><?= number_format($rollup['participants']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Seedlings</div><h2><?= number_format($rollup['seedlings_distributed']) ?></h2></div>
  <div class="panel"><div class="eyebrow">Trees planted</div><h2><?= number_format($rollup['trees_planted']) ?></h2></div>
</div>

<!-- ============ ADD / EDIT ============ -->
<div class="panel">
  <div class="panel-head">
    <h2><?= $isNew ? 'Add a church' : 'Edit ' . e($editing['name']) ?></h2>
    <?php if (!$isNew): ?><a class="btn secondary small" href="churches.php">Cancel edit</a><?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

    <?php field_text('name', 'Church name', $editing['name'] ?? '', ['required' => true, 'maxlength' => 180]); ?>

    <div class="three-col">
      <?php
        field_text('community', 'Community / town', $editing['community'] ?? '');
        field_text('lga', 'Local government area', $editing['lga'] ?? '');
        field_text('state', 'State', $editing['state'] ?? 'Plateau');
      ?>
    </div>

    <div class="two-col">
      <?php
        field_text('denomination', 'Denomination', $editing['denomination'] ?? '');
        field_select('stage_key', 'Current pathway stage', stage_options(), $editing['stage_key'] ?? 'discover');
      ?>
    </div>

    <?php field_textarea('summary', 'Short description', $editing['summary'] ?? '', [
        'rows' => 3,
        'help' => 'One or two sentences about this church and its role in the programme. Shown on the public profile.',
    ]); ?>

    <div class="three-col">
      <?php
        field_text('participant_count', 'Participants', $editing['participant_count'] ?? 0, ['type' => 'number', 'min' => 0]);
        field_text('seedlings_allocated', 'Seedlings allocated', $editing['seedlings_allocated'] ?? 0, ['type' => 'number', 'min' => 0]);
        field_text('trees_planted', 'Trees planted', $editing['trees_planted'] ?? 0, ['type' => 'number', 'min' => 0]);
      ?>
    </div>

    <div class="three-col">
      <?php
        field_text('survival_rate', 'Seedling survival rate (%)', $editing['survival_rate'] ?? '', [
            'type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.1',
            'help' => 'Leave blank until measured.',
        ]);
        field_text('meetings_held', 'Monthly meetings held', $editing['meetings_held'] ?? 0, ['type' => 'number', 'min' => 0]);
        field_text('reports_submitted', 'Reports submitted', $editing['reports_submitted'] ?? 0, ['type' => 'number', 'min' => 0]);
      ?>
    </div>

    <div class="two-col">
      <?php field_text('joined_on', 'Joined the programme', $editing['joined_on'] ?? '', ['type' => 'date']); ?>
      <div class="form-row">
        <label for="f_photo">Church photo</label>
        <input type="file" id="f_photo" name="photo" accept="image/jpeg,image/png,image/webp">
        <p class="help">JPG, PNG, or WEBP under 4MB. Landscape works best.
        <?= !empty($editing['photo_path']) ? 'A photo is already set — choose a new one only to replace it.' : '' ?></p>
      </div>
    </div>

    <div class="two-col">
      <?php
        field_text('latitude', 'Church latitude', $editing['latitude'] ?? '', [
            'type' => 'number', 'step' => 'any',
            'help' => 'Optional, for the future map. Staff-only — never shown publicly. Use the church site, never a member\'s home.',
        ]);
        field_text('longitude', 'Church longitude', $editing['longitude'] ?? '', ['type' => 'number', 'step' => 'any']);
      ?>
    </div>

    <?php field_checkbox('is_published', 'Publish this profile on the public website',
        !empty($editing['is_published']),
        'Unpublished profiles are visible only to project staff.'); ?>

    <button type="submit" class="btn" style="justify-self:start;"><?= $isNew ? 'Add church' : 'Save changes' ?></button>
  </form>
</div>

<!-- ============ LIST ============ -->
<div class="panel">
  <h2><?= count($churches) ?> <?= count($churches) === 1 ? 'church' : 'churches' ?></h2>
  <?php if (!$churches): ?>
    <p class="hint">No churches yet. Add the first one above.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Church</th><th>Stage</th><th>Participants</th><th>Seedlings</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($churches as $c): ?>
        <tr class="<?= $c['is_published'] ? '' : 'muted-row' ?>">
          <td>
            <strong><?= e($c['name']) ?></strong>
            <?php if ($c['community']): ?><br><span class="help"><?= e($c['community']) ?></span><?php endif; ?>
          </td>
          <td><span class="pill"><?= e(stage_name($c['stage_key'])) ?></span></td>
          <td><?= number_format($c['participant_count']) ?></td>
          <td><?= number_format($c['seedlings_allocated']) ?></td>
          <td><span class="pill <?= $c['is_published'] ? 'ok' : 'muted' ?>"><?= $c['is_published'] ? 'Published' : 'Draft' ?></span></td>
          <td class="actions">
            <a href="<?= e(url('/churches/' . $c['slug'])) ?>" target="_blank" title="View">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EYE ?></svg>
            </a>
            <a href="churches.php?edit=<?= (int) $c['id'] ?>" title="Edit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
            </a>
            <form method="post" style="display:inline;"
                  onsubmit="return confirm('Remove <?= e(addslashes($c['name'])) ?>? Its stories stay but lose the link to this church.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <?= action_button(SVG_TRASH, 'Delete', ['class' => 'del']) ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
