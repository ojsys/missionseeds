<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_content');

$flash = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = 'Session expired — please try again.';
        $flashType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $group = $_POST['group_key'] === 'receive' ? 'receive' : 'criteria';
            $icon  = array_key_exists($_POST['icon_key'] ?? '', ICON_LIBRARY) ? $_POST['icon_key'] : 'seedling';
            save_list_item([
                'group_key'  => $group,
                'icon_key'   => $icon,
                'title'      => trim($_POST['title'] ?? ''),
                'body'       => trim($_POST['body'] ?? ''),
                'is_visible' => 1,
            ]);
            $flash = 'Item added.';
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $icon = array_key_exists($_POST['icon_key'] ?? '', ICON_LIBRARY) ? $_POST['icon_key'] : 'seedling';
            save_list_item([
                'id'         => $id,
                'icon_key'   => $icon,
                'title'      => trim($_POST['title'] ?? ''),
                'body'       => trim($_POST['body'] ?? ''),
                'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
            ]);
            $flash = 'Item updated.';
        }

        if ($action === 'delete') {
            delete_list_item((int) ($_POST['id'] ?? 0));
            $flash = 'Item removed.';
        }

        if ($action === 'move') {
            move_list_item((int) ($_POST['id'] ?? 0), $_POST['direction'] === 'down' ? 'down' : 'up');
            $flash = 'Order updated.';
        }
    }

    header('Location: lists.php?flash=' . urlencode($flash) . '&flashType=' . urlencode($flashType));
    exit;
}

if (isset($_GET['flash'])) {
    $flash = $_GET['flash'];
    $flashType = $_GET['flashType'] ?? '';
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editItem = $editId ? get_list_item($editId) : null;

$criteria = get_list_items('criteria', false);
$receive  = get_list_items('receive', false);

function icon_select(string $selected = 'seedling'): string {
    $html = '<select name="icon_key">';
    foreach (ICON_LIBRARY as $key => $label) {
        $sel = $key === $selected ? ' selected' : '';
        $html .= '<option value="' . e($key) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $html . '</select>';
}

function render_group_table(string $group, array $items, bool $hasTitle, ?array $editItem): void {
    ?>
    <table class="data-table">
      <thead>
        <tr>
          <th class="icon-cell"></th>
          <th><?= $hasTitle ? 'Title' : 'Text' ?></th>
          <th style="width:90px;">Visible</th>
          <th class="actions" style="width:170px;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="4" style="color:var(--ink-muted);">No items yet — add one below.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $i => $item): ?>
        <?php if ($editItem && $editItem['id'] === $item['id']): ?>
        <tr>
          <td colspan="4">
            <form method="post" class="form-grid" style="gap:12px;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <div class="two-col">
                <div class="form-row">
                  <label>Icon</label>
                  <?= icon_select($item['icon_key']) ?>
                </div>
                <?php if ($hasTitle): ?>
                <div class="form-row">
                  <label>Title</label>
                  <input type="text" name="title" value="<?= e($item['title']) ?>">
                </div>
                <?php endif; ?>
              </div>
              <div class="form-row">
                <label><?= $hasTitle ? 'Description (optional)' : 'Text' ?></label>
                <textarea name="body"><?= e($item['body']) ?></textarea>
              </div>
              <label style="display:flex; align-items:center; gap:8px; font-family:var(--body); font-size:14px; text-transform:none; letter-spacing:0;">
                <input type="checkbox" name="is_visible" <?= $item['is_visible'] ? 'checked' : '' ?> style="width:auto;"> Visible on the page
              </label>
              <div style="display:flex; gap:10px;">
                <button type="submit" class="btn">Save changes</button>
                <a href="lists.php" class="btn secondary" style="text-decoration:none; display:inline-flex; align-items:center;">Cancel</a>
              </div>
            </form>
          </td>
        </tr>
        <?php continue; endif; ?>

        <tr class="<?= $item['is_visible'] ? '' : 'muted-row' ?>">
          <td class="icon-cell"><span class="badge"><?= icon_svg($item['icon_key']) ?></span></td>
          <td><?= e($hasTitle ? $item['title'] : $item['body']) ?><?php if ($hasTitle && $item['body']): ?><div style="color:var(--ink-muted); font-size:13px; margin-top:2px;"><?= e($item['body']) ?></div><?php endif; ?></td>
          <td><?= $item['is_visible'] ? 'Yes' : 'Hidden' ?></td>
          <td class="actions">
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <input type="hidden" name="direction" value="up">
              <button type="submit" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
              </button>
            </form>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <input type="hidden" name="direction" value="down">
              <button type="submit" title="Move down" <?= $i === count($items) - 1 ? 'disabled' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
              </button>
            </form>
            <a href="lists.php?edit=<?= (int) $item['id'] ?>#edit-<?= (int) $item['id'] ?>" title="Edit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
            </a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this item?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <button type="submit" class="del" title="Delete">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"/></svg>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php
}

$pageTitle = 'Manage lists';
$active = 'lists';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Content lists</div>
  <h1>Manage lists</h1>
  <p>These power the "Who should apply" and "What participating churches receive" sections. Reorder with the arrows, hide an item without deleting it, or add a new one.</p>
</div>

<?php if ($flash): ?>
  <div class="admin-flash <?= e($flashType) ?>"><?= e($flash) ?></div>
<?php endif; ?>

<div class="panel">
  <h2>Who should apply</h2>
  <p class="hint">A short checklist of criteria — icon + one line of text each.</p>
  <?php render_group_table('criteria', $criteria, false, $editItem && $editItem['group_key'] === 'criteria' ? $editItem : null); ?>

  <details style="margin-top:20px;">
    <summary style="cursor:pointer; font-weight:600; color:var(--forest); font-size:14.5px;">+ Add a new criterion</summary>
    <form method="post" class="form-grid" style="margin-top:16px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="group_key" value="criteria">
      <div class="form-row">
        <label>Icon</label>
        <?= icon_select() ?>
      </div>
      <div class="form-row">
        <label>Text</label>
        <textarea name="body" placeholder="e.g. Have access to reliable water for irrigation."></textarea>
      </div>
      <button type="submit" class="btn" style="justify-self:start;">Add criterion</button>
    </form>
  </details>
</div>

<div class="panel">
  <h2>What participating churches receive</h2>
  <p class="hint">Displayed as a card grid — a short title, and an optional one-line description.</p>
  <?php render_group_table('receive', $receive, true, $editItem && $editItem['group_key'] === 'receive' ? $editItem : null); ?>

  <details style="margin-top:20px;">
    <summary style="cursor:pointer; font-weight:600; color:var(--forest); font-size:14.5px;">+ Add a new item</summary>
    <form method="post" class="form-grid" style="margin-top:16px;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="group_key" value="receive">
      <div class="two-col">
        <div class="form-row">
          <label>Icon</label>
          <?= icon_select() ?>
        </div>
        <div class="form-row">
          <label>Title</label>
          <input type="text" name="title" placeholder="e.g. Access to irrigation equipment">
        </div>
      </div>
      <div class="form-row">
        <label>Description (optional)</label>
        <textarea name="body"></textarea>
      </div>
      <button type="submit" class="btn" style="justify-self:start;">Add item</button>
    </form>
  </details>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
