<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_admin();

$flash = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = 'Session expired — please try again.';
        $flashType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // The file lands on disk before the rest of the row is validated, so if
        // validation then fails we must not leave the upload orphaned.
        $uploadedLogo = null;
        try {
            if ($action === 'add') {
                $uploadedLogo = $logo = save_uploaded_image($_FILES['logo'] ?? [], 'partner');
                save_partner([
                    'name'       => $_POST['name'] ?? '',
                    'logo_path'  => $logo,
                    'link_url'   => $_POST['link_url'] ?? '',
                    'is_visible' => 1,
                ]);
                $flash = 'Partner added.';
            }

            if ($action === 'update') {
                // Logo is optional here — only replace it if a new file came through.
                $logo = '';
                if (!empty($_FILES['logo']['name']) && ($_FILES['logo']['error'] ?? 1) === UPLOAD_ERR_OK) {
                    $uploadedLogo = $logo = save_uploaded_image($_FILES['logo'], 'partner');
                }
                save_partner([
                    'id'         => (int) ($_POST['id'] ?? 0),
                    'name'       => $_POST['name'] ?? '',
                    'logo_path'  => $logo,
                    'link_url'   => $_POST['link_url'] ?? '',
                    'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
                ]);
                $flash = 'Partner updated.';
            }

            if ($action === 'delete') {
                delete_partner((int) ($_POST['id'] ?? 0));
                $flash = 'Partner removed.';
            }

            if ($action === 'move') {
                move_partner((int) ($_POST['id'] ?? 0), $_POST['direction'] === 'down' ? 'down' : 'up');
                $flash = 'Order updated.';
            }
        } catch (Throwable $e) {
            // Roll back the just-saved file so a rejected form leaves nothing behind.
            delete_upload($uploadedLogo);
            $flash = $e->getMessage();
            $flashType = 'error';
        }
    }

    header('Location: partners.php?flash=' . urlencode($flash) . '&flashType=' . urlencode($flashType));
    exit;
}

if (isset($_GET['flash'])) {
    $flash = $_GET['flash'];
    $flashType = $_GET['flashType'] ?? '';
}

$editId   = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editItem = $editId ? get_partner($editId) : null;
$partners = get_partners(false);

$pageTitle = 'Partners';
$active = 'partners';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Partner logos</div>
  <h1>Partners</h1>
  <p>Logos shown in the "Partners" strip near the bottom of the landing page. Reorder with the arrows, hide a partner without deleting it, or add a new one. The section is hidden from the site entirely while there are no visible partners.</p>
</div>

<?php if ($flash): ?>
  <div class="admin-flash <?= e($flashType) ?>"><?= e($flash) ?></div>
<?php endif; ?>

<div class="panel">
  <h2>Current partners</h2>
  <p class="hint">Logos look best as transparent PNG or WEBP. They are shown at a uniform height, so wide and square marks both work.</p>

  <table class="data-table">
    <thead>
      <tr>
        <th style="width:110px;">Logo</th>
        <th>Name</th>
        <th style="width:90px;">Visible</th>
        <th class="actions" style="width:170px;">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$partners): ?>
      <tr><td colspan="4" style="color:var(--ink-muted);">No partners yet — add the first one below.</td></tr>
    <?php endif; ?>
    <?php foreach ($partners as $i => $p): ?>
      <?php if ($editItem && (int) $editItem['id'] === (int) $p['id']): ?>
      <tr>
        <td colspan="4">
          <form method="post" enctype="multipart/form-data" class="form-grid" style="gap:12px;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <div class="two-col">
              <div class="form-row">
                <label>Partner name</label>
                <input type="text" name="name" value="<?= e($p['name']) ?>" required>
              </div>
              <div class="form-row">
                <label>Website (optional)</label>
                <input type="url" name="link_url" value="<?= e($p['link_url'] ?? '') ?>" placeholder="https://example.org">
              </div>
            </div>
            <div class="form-row">
              <label>Replace logo (optional)</label>
              <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
              <div class="help">Leave empty to keep the current logo. PNG, JPG, or WEBP — max 4MB.</div>
            </div>
            <label style="display:flex; align-items:center; gap:8px; font-family:var(--body); font-size:14px; text-transform:none; letter-spacing:0;">
              <input type="checkbox" name="is_visible" <?= $p['is_visible'] ? 'checked' : '' ?> style="width:auto;"> Visible on the page
            </label>
            <div style="display:flex; gap:10px;">
              <button type="submit" class="btn">Save changes</button>
              <a href="partners.php" class="btn secondary" style="text-decoration:none; display:inline-flex; align-items:center;">Cancel</a>
            </div>
          </form>
        </td>
      </tr>
      <?php continue; endif; ?>

      <tr class="<?= $p['is_visible'] ? '' : 'muted-row' ?>">
        <td><img src="../<?= e($p['logo_path']) ?>" alt="<?= e($p['name']) ?>" class="partner-thumb"></td>
        <td>
          <?= e($p['name']) ?>
          <?php if (!empty($p['link_url'])): ?>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:2px;"><?= e($p['link_url']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= $p['is_visible'] ? 'Yes' : 'Hidden' ?></td>
        <td class="actions">
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="direction" value="up">
            <button type="submit" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="direction" value="down">
            <button type="submit" title="Move down" <?= $i === count($partners) - 1 ? 'disabled' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
            </button>
          </form>
          <a href="partners.php?edit=<?= (int) $p['id'] ?>" title="Edit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
          </a>
          <form method="post" style="display:inline;" onsubmit="return confirm('Remove this partner? The logo file will be deleted too.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="del" title="Delete">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"/></svg>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <details style="margin-top:20px;" <?= $partners ? '' : 'open' ?>>
    <summary style="cursor:pointer; font-weight:600; color:var(--forest); font-size:14.5px;">+ Add a new partner</summary>
    <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:16px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <div class="two-col">
        <div class="form-row">
          <label>Partner name</label>
          <input type="text" name="name" placeholder="e.g. Plateau Coffee Cooperative" required>
          <div class="help">Used as the logo's alt text for screen readers.</div>
        </div>
        <div class="form-row">
          <label>Website (optional)</label>
          <input type="url" name="link_url" placeholder="https://example.org">
          <div class="help">If set, the logo links here in a new tab.</div>
        </div>
      </div>
      <div class="form-row">
        <label>Logo</label>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
        <div class="help">PNG, JPG, or WEBP — max 4MB. Transparent PNG on a light background works best.</div>
      </div>
      <button type="submit" class="btn" style="justify-self:start;">Add partner</button>
    </form>
  </details>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
