<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_media');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'upload':
                if (empty($_FILES['image']['name'])) {
                    throw new RuntimeException('Please choose an image to upload.');
                }
                $path = save_uploaded_image($_FILES['image'], 'media');
                create_media($path, [
                    'alt_text'    => $_POST['alt_text'] ?? '',
                    'caption'     => $_POST['caption'] ?? '',
                    'has_consent' => !empty($_POST['has_consent']),
                ]);
                $flash = 'Image uploaded.';
                break;

            case 'update':
                update_media((int) $_POST['id'], $_POST);
                $flash = 'Image details saved.';
                break;

            case 'delete':
                delete_media((int) $_POST['id']);
                $flash = 'Image deleted.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$items = list_media(120);

$pageTitle = 'Media';
$active = 'stories';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Content</div>
  <h1>Media library</h1>
  <p>Photos used as story covers and galleries across the site.</p>
</div>

<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<div class="admin-note">
  <strong>Consent matters.</strong> Before publishing a photo where someone is recognisable,
  make sure they have agreed to it being used. Tick the consent box only when they have.
  Images without consent stay in the library but are flagged everywhere they appear.
</div>

<div class="panel">
  <h2>Upload a photo</h2>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <div class="form-row">
      <label for="f_image">Image file</label>
      <input type="file" id="f_image" name="image" accept="image/jpeg,image/png,image/webp" required>
      <p class="help">JPG, PNG, or WEBP under 4MB. Compress large photos first — the site has to load on slow connections.</p>
    </div>
    <div class="two-col">
      <?php
        field_text('alt_text', 'Description for screen readers', '', [
            'help' => 'What is happening in the photo, in a short sentence.',
        ]);
        field_text('caption', 'Caption (optional)', '');
      ?>
    </div>
    <?php field_checkbox('has_consent', 'Everyone recognisable in this photo has consented to publication', false); ?>
    <button type="submit" class="btn" style="justify-self:start;">Upload</button>
  </form>
</div>

<div class="panel">
  <h2><?= count($items) ?> <?= count($items) === 1 ? 'image' : 'images' ?></h2>
  <?php if (!$items): ?>
    <p class="hint">Nothing uploaded yet.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th style="width:110px;">Image</th><th>Details</th><th>Consent</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $m): ?>
        <?php $src = image_url($m['path']); ?>
        <tr>
          <td>
            <?php if ($src): ?>
              <img src="<?= e($src) ?>" alt="" style="width:96px; height:72px; object-fit:cover; border-radius:6px;">
            <?php else: ?>
              <span class="pill alert">File missing</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" class="form-grid" style="gap:10px;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <input type="text" name="alt_text" value="<?= e($m['alt_text'] ?? '') ?>" placeholder="Description for screen readers"
                     style="padding:9px 12px; border:1px solid var(--cream-line); border-radius:6px;">
              <input type="text" name="caption" value="<?= e($m['caption'] ?? '') ?>" placeholder="Caption"
                     style="padding:9px 12px; border:1px solid var(--cream-line); border-radius:6px;">
              <div class="checkbox-row">
                <input type="checkbox" id="c<?= (int) $m['id'] ?>" name="has_consent" value="1" <?= $m['has_consent'] ? 'checked' : '' ?>>
                <label for="c<?= (int) $m['id'] ?>">Consent recorded</label>
              </div>
              <button type="submit" class="btn small secondary" style="justify-self:start;">Save details</button>
            </form>
            <span class="help">
              <?= e($m['width'] ? $m['width'] . '×' . $m['height'] : '') ?>
              <?= e(human_bytes($m['bytes'] ? (int) $m['bytes'] : null)) ?>
              · uploaded <?= e(time_ago($m['created_at'])) ?>
            </span>
          </td>
          <td><span class="pill <?= $m['has_consent'] ? 'ok' : 'alert' ?>"><?= $m['has_consent'] ? 'Yes' : 'Not recorded' ?></span></td>
          <td class="actions">
            <form method="post" onsubmit="return confirm('Delete this image? It will be removed from any story using it.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
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
