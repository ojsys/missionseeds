<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_resources');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';
$editing = null;
$editingForm = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'save_resource':
                $data = $_POST;
                if (!empty($_FILES['file']['name'])) {
                    $upload = save_uploaded_document($_FILES['file']);
                    $data['file_path']  = $upload['path'];
                    $data['file_bytes'] = $upload['bytes'];
                    $data['resource_type'] = 'file';
                }
                save_resource($data);
                $flash = 'Resource saved.';
                break;

            case 'delete_resource':
                delete_resource((int) $_POST['id']);
                $flash = 'Resource removed.';
                break;

            case 'save_kobo':
                save_kobo_form($_POST);
                $flash = 'KoboToolbox form saved.';
                break;

            case 'delete_kobo':
                delete_kobo_form((int) $_POST['id']);
                $flash = 'Form removed.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
        if (($_POST['action'] ?? '') === 'save_resource') $editing = $_POST;
        if (($_POST['action'] ?? '') === 'save_kobo')     $editingForm = $_POST;
    }
}

if (!$editing && !empty($_GET['edit']))      $editing     = get_resource((int) $_GET['edit']);
if (!$editingForm && !empty($_GET['kobo']))  $editingForm = get_kobo_form((int) $_GET['kobo']);
$isNew     = empty($editing['id']);
$isNewForm = empty($editingForm['id']);

$resources = get_hub_resources(true);
$koboForms = get_kobo_forms(true);

$pageTitle = 'Resources';
$active = 'resources';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Content</div>
  <h1>Resource hub</h1>
  <p>Public documents, restricted field materials, and the KoboToolbox form links.</p>
</div>

<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<!-- ============ RESOURCE FORM ============ -->
<div class="panel">
  <div class="panel-head">
    <h2><?= $isNew ? 'Add a resource' : 'Edit resource' ?></h2>
    <?php if (!$isNew): ?><a class="btn secondary small" href="resources.php">Cancel edit</a><?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_resource">
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

    <?php field_text('title', 'Title', $editing['title'] ?? '', ['required' => true, 'maxlength' => 200]); ?>
    <?php field_textarea('description', 'Description', $editing['description'] ?? '', ['rows' => 2]); ?>

    <div class="two-col">
      <?php
        field_text('category', 'Category', $editing['category'] ?? 'General', [
            'help' => 'Resources are grouped under this heading. Reuse the same wording to group them together.',
        ]);
        field_select('visibility', 'Who can see it', RESOURCE_VISIBILITY, $editing['visibility'] ?? 'public');
      ?>
    </div>

    <div class="two-col">
      <?php field_select('resource_type', 'Type', ['link' => 'Link to a web page', 'file' => 'Uploaded file'], $editing['resource_type'] ?? 'link'); ?>
      <?php field_icon('icon_key', 'Icon', $editing['icon_key'] ?? 'book'); ?>
    </div>

    <?php field_text('url', 'Link URL', $editing['url'] ?? '', [
        'type' => 'url', 'placeholder' => 'https://…',
        'help' => 'Used when the type is "Link to a web page".',
    ]); ?>

    <div class="form-row">
      <label for="f_file">Upload a file</label>
      <input type="file" id="f_file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,image/*">
      <p class="help">
        PDF, Word, Excel, PowerPoint, CSV, or an image — up to 20MB. Uploading a file sets the type to "Uploaded file".
        <?= !empty($editing['file_path']) ? ' Current file: ' . e(basename($editing['file_path'])) : '' ?>
      </p>
    </div>

    <?php field_checkbox('is_visible', 'Show this resource', $isNew ? true : !empty($editing['is_visible'])); ?>

    <button type="submit" class="btn" style="justify-self:start;"><?= $isNew ? 'Add resource' : 'Save resource' ?></button>
  </form>
</div>

<!-- ============ RESOURCE LIST ============ -->
<div class="panel">
  <h2><?= count($resources) ?> <?= count($resources) === 1 ? 'resource' : 'resources' ?></h2>
  <?php if (!$resources): ?>
    <p class="hint">No resources yet.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th></th><th>Resource</th><th>Category</th><th>Visibility</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($resources as $r): ?>
        <tr class="<?= $r['is_visible'] ? '' : 'muted-row' ?>">
          <td class="icon-cell"><div class="badge"><?= icon_svg($r['icon_key']) ?></div></td>
          <td>
            <strong><?= e($r['title']) ?></strong>
            <?php if ($r['resource_type'] === 'file' && $r['file_path']): ?>
              <br><span class="help"><?= e(basename($r['file_path'])) ?> · <?= e(human_bytes($r['file_bytes'] ? (int) $r['file_bytes'] : null)) ?></span>
            <?php elseif ($r['url']): ?>
              <br><span class="help"><?= e(mb_substr($r['url'], 0, 60)) ?></span>
            <?php endif; ?>
          </td>
          <td><span class="pill"><?= e($r['category']) ?></span></td>
          <td>
            <span class="pill <?= $r['visibility'] === 'public' ? 'ok' : 'warn' ?>">
              <?= e(['public' => 'Public', 'coordinator' => 'Coordinators', 'staff' => 'Staff only'][$r['visibility']]) ?>
            </span>
          </td>
          <td class="actions">
            <a href="resources.php?edit=<?= (int) $r['id'] ?>" title="Edit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
            </a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this resource?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_resource">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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

<!-- ============ KOBOTOOLBOX ============ -->
<div class="panel">
  <div class="panel-head">
    <h2><?= $isNewForm ? 'Add a KoboToolbox form' : 'Edit form' ?></h2>
    <?php if (!$isNewForm): ?><a class="btn secondary small" href="resources.php">Cancel edit</a><?php endif; ?>
  </div>
  <p class="hint">
    Paste the shareable form link from KoboToolbox. Submissions stay in Kobo — this site only
    links to the form and never stores the responses.
  </p>

  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_kobo">
    <?php if (!$isNewForm): ?><input type="hidden" name="id" value="<?= (int) $editingForm['id'] ?>"><?php endif; ?>

    <div class="two-col">
      <?php
        field_text('name', 'Form name', $editingForm['name'] ?? '', ['required' => true]);
        field_select('purpose', 'Purpose', KOBO_PURPOSES, $editingForm['purpose'] ?? 'other');
      ?>
    </div>

    <?php field_textarea('description', 'Description', $editingForm['description'] ?? '', ['rows' => 2]); ?>

    <?php field_text('enketo_url', 'Form link', $editingForm['enketo_url'] ?? '', [
        'type' => 'url', 'placeholder' => 'https://ee.kobotoolbox.org/x/…',
    ]); ?>

    <div class="two-col">
      <?php
        field_text('form_uid', 'Kobo asset UID (optional)', $editingForm['form_uid'] ?? '', [
            'help' => 'Needed later for automatic indicator sync. Safe to leave blank now.',
        ]);
        field_select('visibility', 'Who can see it', [
            'staff'       => 'Project staff only',
            'coordinator' => 'Church coordinators and staff',
        ], $editingForm['visibility'] ?? 'staff');
      ?>
    </div>

    <?php field_checkbox('is_active', 'Show this form in the Resource Hub', $isNewForm ? true : !empty($editingForm['is_active'])); ?>

    <button type="submit" class="btn" style="justify-self:start;"><?= $isNewForm ? 'Add form' : 'Save form' ?></button>
  </form>

  <?php if ($koboForms): ?>
  <div class="table-scroll" style="margin-top:24px;">
    <table class="data-table">
      <thead><tr><th>Form</th><th>Purpose</th><th>Visibility</th><th>Link</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($koboForms as $f): ?>
        <tr class="<?= $f['is_active'] ? '' : 'muted-row' ?>">
          <td><strong><?= e($f['name']) ?></strong></td>
          <td><span class="pill"><?= e(KOBO_PURPOSES[$f['purpose']]) ?></span></td>
          <td><span class="pill warn"><?= $f['visibility'] === 'staff' ? 'Staff' : 'Coordinators' ?></span></td>
          <td><?= $f['enketo_url'] ? '<span class="pill ok">Set</span>' : '<span class="pill alert">Missing</span>' ?></td>
          <td class="actions">
            <a href="resources.php?kobo=<?= (int) $f['id'] ?>" title="Edit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
            </a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this form link?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_kobo">
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
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
