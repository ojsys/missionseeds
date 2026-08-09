<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_stories');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'save':
                $data = $_POST;

                // A cover image uploaded with the story becomes a media record
                // so it carries alt text and a consent flag like any other.
                if (!empty($_FILES['cover']['name'])) {
                    $path = save_uploaded_image($_FILES['cover'], 'story');
                    $data['cover_media_id'] = create_media($path, [
                        'alt_text'    => $_POST['cover_alt'] ?? '',
                        'has_consent' => !empty($_POST['cover_consent']),
                    ]);
                }
                $id = save_story($data);
                set_story_gallery($id, $_POST['gallery'] ?? []);
                $flash = 'Story saved.';
                break;

            case 'review':
                require_capability('publish_stories');
                set_story_status((int) $_POST['id'], (string) $_POST['status'], $_POST['review_note'] ?? null);
                $flash = 'Story updated.';
                break;

            case 'delete':
                delete_story((int) $_POST['id']);
                $flash = 'Story deleted.';
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
    $editing = get_story((int) $_GET['edit']);
}
$isNew = empty($editing['id']);

$filter  = $_GET['status'] ?? '';
$listArg = array_key_exists($filter, STORY_STATUSES) ? ['status' => $filter] : ['status' => array_keys(STORY_STATUSES)];
$stories = get_stories($listArg + ['limit' => 100]);
$pending = count_pending_stories();

$churches   = get_churches(true);
$mediaItems = list_media(48);
$gallerySet = $isNew ? [] : array_column(story_gallery((int) $editing['id']), 'id');

$pageEyebrow = 'Content';
$pageTitle   = 'Growth stories';
$pageIntro   = 'Updates, church stories, training days, planting activity, meetings, and testimonies.';
$active = 'stories';
include __DIR__ . '/partials/header.php';
?>


<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<?php if ($pending > 0 && user_can('publish_stories')): ?>
  <div class="admin-flash">
    <strong><?= $pending ?></strong> <?= $pending === 1 ? 'story is' : 'stories are' ?> waiting for review.
    <a href="stories.php?status=pending">Review <?= $pending === 1 ? 'it' : 'them' ?> →</a>
  </div>
<?php endif; ?>

<!-- ============ WRITE / EDIT ============ -->
<div class="panel">
  <div class="panel-head">
    <h2><?= $isNew ? 'Write a story' : 'Edit story' ?></h2>
    <?php if (!$isNew): ?><a class="btn secondary small" href="stories.php">Cancel edit</a><?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

    <?php field_text('title', 'Title', $editing['title'] ?? '', ['required' => true, 'maxlength' => 220]); ?>

    <div class="three-col">
      <?php
        field_select('category', 'Category', STORY_CATEGORIES, $editing['category'] ?? 'update');
        field_select('church_id', 'Church', array_column($churches, 'name', 'id'),
            $editing['church_id'] ?? '', ['blank' => 'Not church-specific']);
        field_select('status', 'Status', STORY_STATUSES, $editing['status'] ?? 'draft');
      ?>
    </div>

    <?php field_textarea('excerpt', 'Summary', $editing['excerpt'] ?? '', [
        'rows' => 2, 'maxlength' => 400,
        'help' => 'One or two sentences for cards and search results. Left blank, it is taken from the opening of the story.',
    ]); ?>

    <?php field_textarea('body', 'Story', $editing['body'] ?? '', [
        'rows' => 14,
        'help' => 'Leave a blank line between paragraphs. You can use **bold**, *italic*, and [link text](https://example.com).',
    ]); ?>

    <div class="two-col">
      <div class="form-row">
        <label for="f_cover">Cover photo</label>
        <input type="file" id="f_cover" name="cover" accept="image/jpeg,image/png,image/webp">
        <p class="help"><?= !empty($editing['cover_path']) ? 'A cover is already set — choose a file only to replace it.' : 'JPG, PNG, or WEBP under 4MB.' ?></p>
      </div>
      <?php field_text('cover_alt', 'Cover photo description', '', [
          'help' => 'Describe the photo for people using a screen reader.',
      ]); ?>
    </div>

    <?php field_checkbox('cover_consent', 'The people in this photo have given consent to be published', false,
        'Required before a photo of identifiable people goes on the public site.'); ?>

    <?php if ($mediaItems): ?>
    <div class="form-row">
      <label>Gallery images</label>
      <p class="help" style="margin-top:0; margin-bottom:10px;">Tick any images from the media library to show below this story.</p>
      <div class="media-picker">
        <?php foreach ($mediaItems as $m): ?>
          <?php $src = image_url($m['path']); if (!$src) continue; ?>
          <label class="media-tile <?= $m['has_consent'] ? '' : 'no-consent' ?>">
            <input type="checkbox" name="gallery[]" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $gallerySet, true) ? 'checked' : '' ?>>
            <img src="<?= e($src) ?>" alt="<?= e($m['alt_text'] ?? '') ?>" loading="lazy">
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <button type="submit" class="btn" style="justify-self:start;"><?= $isNew ? 'Save story' : 'Save changes' ?></button>
  </form>
</div>

<!-- ============ LIST ============ -->
<div class="panel">
  <h2>All stories</h2>

  <div class="filter-row">
    <a class="<?= $filter === '' ? 'active' : '' ?>" href="stories.php">All</a>
    <?php foreach (STORY_STATUSES as $k => $label): ?>
      <a class="<?= $filter === $k ? 'active' : '' ?>" href="stories.php?status=<?= e($k) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$stories): ?>
    <p class="hint">No stories here yet.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Story</th><th>Category</th><th>Church</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($stories as $s): ?>
        <tr class="<?= $s['status'] === 'published' ? '' : 'muted-row' ?>">
          <td>
            <strong><?= e($s['title']) ?></strong><br>
            <span class="help"><?= e($s['published_at'] ? format_date($s['published_at']) : 'Not published') ?></span>
          </td>
          <td><span class="pill"><?= e(STORY_CATEGORIES[$s['category']] ?? '') ?></span></td>
          <td><?= e($s['church_name'] ?? '—') ?></td>
          <td>
            <span class="pill <?= $s['status'] === 'published' ? 'ok' : ($s['status'] === 'pending' ? 'warn' : ($s['status'] === 'rejected' ? 'alert' : 'muted')) ?>">
              <?= e(STORY_STATUSES[$s['status']]) ?>
            </span>
          </td>
          <td class="actions">
            <a href="<?= e(url('/stories/' . $s['slug'])) ?>" target="_blank" title="Preview">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EYE ?></svg>
            </a>
            <a href="stories.php?edit=<?= (int) $s['id'] ?>" title="Edit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
            </a>
            <?php if (user_can('publish_stories') && $s['status'] !== 'published'): ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="review">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="status" value="published">
              <?= action_button(SVG_CHECK, 'Publish') ?>
            </form>
            <?php endif; ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this story permanently?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <?= action_button(SVG_TRASH, 'Delete', ['class' => 'del']) ?>
            </form>
          </td>
        </tr>
        <?php if ($s['status'] === 'pending' && user_can('publish_stories')): ?>
        <tr>
          <td colspan="5" style="background:rgba(198,148,71,0.08);">
            <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="review">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <input type="text" name="review_note" placeholder="Note for the author (optional)" style="flex:1; min-width:220px; padding:9px 12px; border:1px solid var(--cream-line); border-radius:6px;">
              <button type="submit" name="status" value="published" class="btn small">Approve &amp; publish</button>
              <button type="submit" name="status" value="rejected" class="btn small secondary">Send back</button>
            </form>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<p><a class="btn secondary" href="media.php">Manage the media library →</a></p>

<?php include __DIR__ . '/partials/footer.php'; ?>
