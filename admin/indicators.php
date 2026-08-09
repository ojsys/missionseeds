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
        switch ($_POST['action'] ?? '') {
            case 'save':
                save_indicator($_POST);
                $flash = 'Indicator saved.';
                break;

            case 'delete':
                delete_indicator((int) $_POST['id']);
                $flash = 'Indicator removed.';
                break;

            case 'quick_update':
                // The everyday case: type new numbers, press one button.
                foreach ($_POST['value'] ?? [] as $id => $value) {
                    if ($value === '') continue;
                    set_indicator_value((int) $id, (float) $value);
                }
                audit('update_indicators', 'indicator', null, 'bulk value update');
                $flash = 'Figures updated.';
                break;

            case 'rollup':
                // Copy the totals computed from published church records.
                $rollup = church_rollup();
                $stmt = db()->prepare('SELECT id, indicator_key FROM indicators WHERE indicator_key = ?');
                $applied = 0;
                foreach ($rollup as $key => $value) {
                    if ($value === null) continue;
                    $stmt->execute([$key]);
                    if ($row = $stmt->fetch()) {
                        set_indicator_value((int) $row['id'], (float) $value);
                        $applied++;
                    }
                }
                audit('rollup_indicators', 'indicator', null, $applied . ' from church records');
                $flash = $applied . ' indicator(s) updated from church records.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
        if (($_POST['action'] ?? '') === 'save') $editing = $_POST;
    }
}

if (!$editing && !empty($_GET['edit'])) {
    $editing = get_indicator((int) $_GET['edit']);
}
$isNew = empty($editing['id']);

$indicators = get_indicators(true);
$rollup     = church_rollup();

$pageTitle = 'Indicators';
$active = 'indicators';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Project</div>
  <h1>Impact indicators</h1>
  <p>These are the numbers on the public Growth Tracker. Every change is dated, so the tracker can show movement over time.</p>
</div>

<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<div class="admin-note">
  <strong>Public figures only.</strong> Never add an indicator for budget, seed-fund balances,
  allowances, or anything else financial — the brief requires those stay private, and this
  table feeds the public page.
</div>

<!-- ============ QUICK UPDATE ============ -->
<div class="panel">
  <div class="panel-head">
    <h2>Update the figures</h2>
    <form method="post" onsubmit="return confirm('Overwrite the matching indicators with totals from published church records?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rollup">
      <button type="submit" class="btn secondary small">Fill from church records</button>
    </form>
  </div>
  <p class="hint">
    Type the current value for anything that has changed and save once.
    Church records currently total: <?= number_format($rollup['churches_onboarded']) ?> churches,
    <?= number_format($rollup['participants']) ?> participants,
    <?= number_format($rollup['seedlings_distributed']) ?> seedlings,
    <?= number_format($rollup['trees_planted']) ?> trees.
  </p>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="quick_update">
    <div class="table-scroll">
      <table class="data-table">
        <thead><tr><th></th><th>Indicator</th><th style="width:170px;">Current value</th><th>Shown</th><th>Source</th></tr></thead>
        <tbody>
          <?php foreach ($indicators as $ind): ?>
          <tr class="<?= $ind['is_public'] ? '' : 'muted-row' ?>">
            <td class="icon-cell"><div class="badge"><?= icon_svg($ind['icon_key']) ?></div></td>
            <td>
              <strong><?= e($ind['label']) ?></strong>
              <?php if ($ind['is_featured']): ?><br><span class="pill">Homepage</span><?php endif; ?>
            </td>
            <td>
              <?php if ($ind['display_format'] === 'text'): ?>
                <span class="help"><?= e($ind['value_text'] ?: '—') ?> (edit below)</span>
              <?php else: ?>
                <input type="number" step="0.01" min="0" name="value[<?= (int) $ind['id'] ?>]"
                       value="<?= e(rtrim(rtrim(number_format((float) $ind['value_num'], 2, '.', ''), '0'), '.')) ?>">
              <?php endif; ?>
            </td>
            <td><span class="pill <?= $ind['is_public'] ? 'ok' : 'muted' ?>"><?= $ind['is_public'] ? 'Public' : 'Hidden' ?></span></td>
            <td>
              <span class="pill <?= $ind['source'] === 'kobo' ? 'warn' : 'muted' ?>"><?= $ind['source'] === 'kobo' ? 'Kobo' : 'Manual' ?></span>
              <div class="actions" style="text-align:left; margin-top:6px;">
                <a href="indicators.php?edit=<?= (int) $ind['id'] ?>" title="Edit settings">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button type="submit" class="btn" style="margin-top:22px;">Save figures</button>
  </form>
</div>

<!-- ============ ADD / EDIT ============ -->
<div class="panel">
  <div class="panel-head">
    <h2><?= $isNew ? 'Add an indicator' : 'Edit ' . e($editing['label']) ?></h2>
    <?php if (!$isNew): ?><a class="btn secondary small" href="indicators.php">Cancel edit</a><?php endif; ?>
  </div>

  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

    <div class="two-col">
      <?php
        field_text('label', 'Label', $editing['label'] ?? '', ['required' => true, 'maxlength' => 120]);
        field_select('display_format', 'Format', [
            'integer' => 'Whole number (1,240)',
            'decimal' => 'One decimal (12.4)',
            'percent' => 'Percentage (87.5%)',
            'text'    => 'Free text',
        ], $editing['display_format'] ?? 'integer');
      ?>
    </div>

    <div class="two-col">
      <?php
        field_text('value_num', 'Value', $editing['value_num'] ?? 0, ['type' => 'number', 'step' => '0.01', 'min' => 0]);
        field_text('value_text', 'Text value', $editing['value_text'] ?? '', [
            'help' => 'Only used when the format is Free text.',
        ]);
      ?>
    </div>

    <div class="two-col">
      <?php
        field_text('unit', 'Unit', $editing['unit'] ?? '', ['placeholder' => 'e.g. %, kg, ha']);
        field_icon('icon_key', 'Icon', $editing['icon_key'] ?? 'growth');
      ?>
    </div>

    <div class="two-col">
      <?php
        field_checkbox('is_public', 'Show on the public Growth Tracker', $isNew ? true : !empty($editing['is_public']));
        field_checkbox('is_featured', 'Also show in the homepage highlights', !empty($editing['is_featured']),
            'Four featured indicators fit the homepage strip neatly.');
      ?>
    </div>

    <h3 style="font-family:var(--display); font-size:16px; margin-top:8px;">KoboToolbox sync (optional)</h3>
    <p class="hint" style="margin-top:-8px;">
      Leave as Manual for now. When the Kobo API token is configured in <code>config.php</code>,
      switching an indicator to Kobo lets a scheduled sync keep it up to date automatically.
    </p>
    <div class="three-col">
      <?php
        field_select('source', 'Source', ['manual' => 'Entered manually', 'kobo' => 'KoboToolbox'], $editing['source'] ?? 'manual');
        field_text('kobo_form_id', 'Kobo form UID', $editing['kobo_form_id'] ?? '');
        field_text('kobo_field', 'Kobo field', $editing['kobo_field'] ?? '', [
            'placeholder' => 'submission_count',
        ]);
      ?>
    </div>

    <button type="submit" class="btn" style="justify-self:start;"><?= $isNew ? 'Add indicator' : 'Save indicator' ?></button>
  </form>

  <?php if (!$isNew): ?>
  <form method="post" style="margin-top:18px; padding-top:18px; border-top:1px solid var(--cream-line);"
        onsubmit="return confirm('Delete this indicator and its recorded history?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <button type="submit" class="btn danger">Delete indicator</button>
  </form>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
