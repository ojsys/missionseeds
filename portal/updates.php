<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['church_coordinator']);
require_once __DIR__ . '/../admin/partials/form.php';

$church = $user['church_id'] ? get_church((int) $user['church_id']) : null;

$flash = '';
$flashType = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        if (!$church) {
            throw new RuntimeException('Your account is not linked to a church yet.');
        }

        $id       = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $existing = $id ? get_story($id) : null;

        // Ownership check before anything else. save_story() re-checks, but
        // failing here means a tampered id never touches the write path.
        if ($existing && !can_edit_story($existing, $user)) {
            throw new RuntimeException('You can only edit your own church\'s updates.');
        }

        $data = $_POST;
        // save_story() ignores whatever a coordinator sends for these, but
        // being explicit keeps the intent obvious to the next reader.
        unset($data['church_id']);
        $data['status'] = ($_POST['submit_action'] ?? '') === 'submit' ? 'pending' : 'draft';

        if (!empty($_FILES['cover']['name'])) {
            $path = save_uploaded_image($_FILES['cover'], 'story');
            $data['cover_media_id'] = create_media($path, [
                'alt_text'    => $_POST['cover_alt'] ?? '',
                'has_consent' => !empty($_POST['cover_consent']),
            ]);
        }

        $savedId = save_story($data);

        // Extra photos: uploaded here, attached to this story only.
        $galleryIds = [];
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['name'] as $i => $name) {
                if ($name === '' || $_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $one = [
                    'name'     => $name,
                    'type'     => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                ];
                // One consent tick covers every photo attached in this submission.
                $galleryIds[] = create_media(save_uploaded_image($one, 'story'), [
                    'has_consent' => !empty($_POST['cover_consent']),
                ]);
            }
        }
        if ($galleryIds) {
            $existingGallery = array_column(story_gallery($savedId), 'id');
            set_story_gallery($savedId, array_merge($existingGallery, $galleryIds));
        }

        $flash = $data['status'] === 'pending'
            ? 'Thank you — your update has been sent for review. A project manager will publish it shortly.'
            : 'Saved as a draft. It has not been sent for review yet.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
        $editing = $_POST;
    }
}

if (!$editing && !empty($_GET['edit'])) {
    $candidate = get_story((int) $_GET['edit']);
    if ($candidate && can_edit_story($candidate, $user)) {
        $editing = $candidate;
    } else {
        $flash = 'That update is not yours to edit.';
        $flashType = 'error';
    }
}
$isNew = empty($editing['id']);

$mine = $church ? get_stories([
    'church_id' => (int) $church['id'],
    'status'    => array_keys(STORY_STATUSES),
    'limit'     => 20,
]) : [];

$pageEyebrow = 'Church portal';
$pageTitle   = $isNew ? 'Write an update' : 'Edit your update';
$pageIntro   = 'Tell us what has been happening — a training day, a planting session, '
             . "a cooperative meeting, or a member's story.";
$active = 'updates';
include __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<?php if (!$church): ?>
  <div class="panel"><p class="hint" style="margin:0;">Ask a project manager to link your account to your church, then sign in again.</p></div>
<?php else: ?>

<div class="admin-note">
  <strong>Please do not include personal details.</strong> No phone numbers, home addresses, ID numbers,
  income, or savings amounts — even for people who are happy to be named. Those belong in your
  KoboToolbox report. And only send photos of people who have said yes to being published.
</div>

<div class="panel">
  <div class="panel-head">
    <h2><?= $isNew ? 'New update' : e($editing['title']) ?></h2>
    <?php if (!$isNew): ?><a class="btn secondary small" href="updates.php">Start a new one</a><?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

    <?php field_text('title', 'Title', $editing['title'] ?? '', [
        'required' => true, 'maxlength' => 220,
        'placeholder' => 'e.g. Twenty members trained in seedling care',
    ]); ?>

    <?php field_select('category', 'What kind of update is this?', STORY_CATEGORIES, $editing['category'] ?? 'update'); ?>

    <?php field_textarea('body', 'Your update', $editing['body'] ?? '', [
        'rows' => 12,
        'help' => 'Write it as you would tell it. Leave a blank line between paragraphs.',
    ]); ?>

    <div class="two-col">
      <div class="form-row">
        <label for="f_cover">Main photo</label>
        <input type="file" id="f_cover" name="cover" accept="image/jpeg,image/png,image/webp">
        <p class="help"><?= !empty($editing['cover_path']) ? 'A photo is already attached — choose a file only to replace it.' : 'Optional. JPG or PNG under 4MB.' ?></p>
      </div>
      <?php field_text('cover_alt', 'What is in the photo?', '', [
          'help' => 'A short description, so people using a screen reader know what it shows.',
      ]); ?>
    </div>

    <div class="form-row">
      <label for="f_photos">More photos</label>
      <input type="file" id="f_photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
      <p class="help">You can select several at once.</p>
    </div>

    <?php field_checkbox('cover_consent', 'Everyone recognisable in these photos has agreed to them being published', false); ?>

    <div style="display:flex; gap:12px; flex-wrap:wrap;">
      <button type="submit" name="submit_action" value="submit" class="btn">Send for review</button>
      <button type="submit" name="submit_action" value="draft" class="btn secondary">Save as draft</button>
    </div>
    <p class="help" style="margin-top:-6px;">
      Sending for review does not publish it straight away — a project manager reads it first.
    </p>
  </form>
</div>

<div class="panel">
  <h2>Everything you have written</h2>
  <?php if (!$mine): ?>
    <p class="hint" style="margin:0;">Nothing yet.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Update</th><th>Status</th><th>Last change</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($mine as $s): ?>
        <tr class="<?= $s['status'] === 'published' ? '' : 'muted-row' ?>">
          <td>
            <strong><?= e($s['title']) ?></strong><br>
            <span class="help"><?= e(STORY_CATEGORIES[$s['category']] ?? '') ?></span>
          </td>
          <td>
            <span class="pill <?= $s['status'] === 'published' ? 'ok' : ($s['status'] === 'pending' ? 'warn' : ($s['status'] === 'rejected' ? 'alert' : 'muted')) ?>">
              <?= e(STORY_STATUSES[$s['status']]) ?>
            </span>
            <?php if ($s['status'] === 'rejected' && $s['review_note']): ?>
              <br><span class="help">Note: <?= e($s['review_note']) ?></span>
            <?php endif; ?>
          </td>
          <td><span class="help"><?= e(time_ago($s['updated_at'])) ?></span></td>
          <td class="actions">
            <?php if (can_edit_story($s, $user)): ?>
              <a href="updates.php?edit=<?= (int) $s['id'] ?>" title="Edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
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
