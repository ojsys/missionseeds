<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_users');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';
$invite = null;      // ['kind','name','sent','error','link'] after a create/invite/reset
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'create': {
                $result  = create_user($_POST);
                $created = get_user($result['id']);
                $invite  = [
                    'kind'  => 'invite',
                    'name'  => $created['full_name'] ?: $created['username'],
                    'email' => $created['email'],
                ] + $result;
                $flash = 'Account created.';
                break;
            }

            case 'resend': {
                $id     = (int) $_POST['id'];
                $target = get_user($id);
                $result = send_invitation($id, false);
                $invite = [
                    'kind'  => 'invite',
                    'name'  => $target['full_name'] ?: $target['username'],
                    'email' => $target['email'],
                ] + $result;
                $flash = 'Invitation re-sent.';
                break;
            }

            case 'update':
                update_user((int) $_POST['id'], $_POST);
                $flash = 'Account updated.';
                break;

            case 'reset': {
                $id     = (int) $_POST['id'];
                $target = get_user($id);
                $result = reset_user_password($id);
                $invite = [
                    'kind'  => 'reset',
                    'name'  => $target['full_name'] ?: $target['username'],
                    'email' => $target['email'],
                ] + $result;
                $flash = 'Password reset started.';
                break;
            }

            case 'delete':
                delete_user((int) $_POST['id']);
                $flash = 'Account deleted.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

if (!empty($_GET['edit'])) {
    $editing = get_user((int) $_GET['edit']);
}
$users    = get_users();
$churches = get_churches(true);
$me       = current_user();

$pageEyebrow = 'Accounts';
$pageTitle   = 'Users and roles';
$pageIntro   = 'Accounts are created here — there is no public sign-up. New users are emailed an invitation and choose their own password; nobody else ever sees it.';
$active = 'users';
include __DIR__ . '/partials/header.php';
?>


<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<?php if ($invite): ?>
<div class="panel" style="border-color:<?= $invite['sent'] ? 'var(--sprout)' : 'var(--gold)' ?>;">
  <?php if ($invite['sent']): ?>
    <div class="panel-head">
      <h2><?= $invite['kind'] === 'invite' ? 'Invitation sent' : 'Reset link sent' ?></h2>
      <span class="pill ok">Emailed</span>
    </div>
    <p class="hint">
      Sent to <strong><?= e($invite['email']) ?></strong>.
      <?= $invite['kind'] === 'invite'
          ? 'They have 7 days to choose their password.'
          : 'The link expires in 2 hours, and their old password no longer works.' ?>
      Nobody but <?= e($invite['name']) ?> will ever know the password.
    </p>
    <details style="margin-top:6px;">
      <summary class="help" style="cursor:pointer;">Need to send it another way as well?</summary>
      <p class="hint" style="margin:10px 0 6px;">The same link — safe to send over WhatsApp:</p>
      <div class="temp-password" style="font-size:12.5px; word-break:break-all;"><?= e($invite['link']) ?></div>
    </details>
  <?php else: ?>
    <div class="panel-head">
      <h2>Account ready — but the email did not send</h2>
      <span class="pill warn">Not emailed</span>
    </div>
    <p class="hint">
      <?= e($invite['error'] ?? 'Email is not configured.') ?>
      Send <strong><?= e($invite['name']) ?></strong> the link below instead — WhatsApp is fine.
      It works once and expires, so it is safe to pass on.
    </p>
    <div class="temp-password" style="font-size:12.5px; word-break:break-all;"><?= e($invite['link']) ?></div>
    <p class="hint" style="margin-bottom:0;">
      <a href="email.php">Set up email</a> so this happens automatically next time.
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============ ROLE REFERENCE ============ -->
<div class="panel">
  <h2>What each role can do</h2>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Role</th><th>Can do</th></tr></thead>
      <tbody>
        <tr><td><span class="role-chip role-super_admin">Super Admin</span></td>
            <td>Everything, including creating accounts and changing roles.</td></tr>
        <tr><td><span class="role-chip role-project_manager">Project Manager</span></td>
            <td>Churches, pathway, milestones, indicators, stories, resources, enquiries, and site settings. Cannot manage accounts.</td></tr>
        <tr><td><span class="role-chip role-editor">Editor</span></td>
            <td>Website copy and stories, including publishing them. No access to churches, indicators, or enquiries.</td></tr>
        <tr><td><span class="role-chip role-church_coordinator">Church Coordinator</span></td>
            <td>Signs in to the church portal. Submits updates and photos for their own church only, which a manager reviews before publication.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ CREATE / EDIT ============ -->
<?php if ($editing): ?>
<div class="panel">
  <div class="panel-head">
    <h2>Edit <?= e($editing['username']) ?></h2>
    <a class="btn secondary small" href="users.php">Cancel edit</a>
  </div>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">

    <div class="two-col">
      <?php
        field_text('full_name', 'Full name', $editing['full_name'] ?? '');
        field_text('email', 'Email', $editing['email'] ?? '', ['type' => 'email']);
      ?>
    </div>
    <div class="two-col">
      <?php
        field_select('role', 'Role', ROLES, $editing['role']);
        field_select('church_id', 'Church (coordinators only)', array_column($churches, 'name', 'id'),
            $editing['church_id'] ?? '', ['blank' => 'Not linked to a church']);
      ?>
    </div>
    <?php field_checkbox('is_active', 'Account is active', (bool) $editing['is_active'],
        'Deactivating blocks sign-in immediately without deleting anything.'); ?>

    <button type="submit" class="btn" style="justify-self:start;">Save changes</button>
  </form>
</div>
<?php else: ?>
<div class="panel">
  <h2>Create an account</h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div class="two-col">
      <?php
        field_text('username', 'Username', '', [
            'required' => true, 'maxlength' => 60,
            'help' => 'Lowercase letters, numbers, dots, dashes, underscores. This is what they type to sign in.',
        ]);
        field_text('full_name', 'Full name', '');
      ?>
    </div>
    <div class="two-col">
      <?php
        field_text('email', 'Email address', '', [
            'type' => 'email',
            'help' => 'The invitation is sent here. Without it you will have to pass the link on yourself.',
        ]);
        field_select('role', 'Role', ROLES, 'editor');
      ?>
    </div>
    <?php field_select('church_id', 'Church', array_column($churches, 'name', 'id'), '', [
        'blank' => 'Not linked to a church',
        'help'  => 'Required for Church Coordinators — it is the only church they will be able to post about.',
    ]); ?>

    <button type="submit" class="btn" style="justify-self:start;">Create account</button>
  </form>
</div>
<?php endif; ?>

<!-- ============ LIST ============ -->
<div class="panel">
  <h2><?= count($users) ?> <?= count($users) === 1 ? 'account' : 'accounts' ?></h2>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Account</th><th>Role</th><th>Church</th><th>Last sign-in</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr class="<?= $u['is_active'] ? '' : 'muted-row' ?>">
          <td>
            <strong><?= e($u['full_name'] ?: $u['username']) ?></strong>
            <?php if ((int) $u['id'] === (int) $me['id']): ?><span class="pill">You</span><?php endif; ?>
            <br><span class="help"><?= e($u['username']) ?><?= $u['email'] ? ' · ' . e($u['email']) : '' ?></span>
            <?php if ($u['must_change_password']): ?>
              <br><span class="pill warn"><?= $u['last_login_at'] ? 'Reset pending' : 'Invitation pending' ?></span>
            <?php endif; ?>
            <?php if (!$u['is_active']): ?><br><span class="pill muted">Deactivated</span><?php endif; ?>
          </td>
          <td><span class="role-chip role-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></td>
          <td><?= e($u['church_name'] ?? '—') ?></td>
          <td><span class="help"><?= e($u['last_login_at'] ? time_ago($u['last_login_at']) : 'Never') ?></span></td>
          <td class="actions">
            <a href="users.php?edit=<?= (int) $u['id'] ?>" title="Edit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= SVG_EDIT ?></svg>
            </a>
            <?php if ($u['must_change_password']): ?>
            <form method="post" style="display:inline;"
                  onsubmit="return confirm('Send <?= e(addslashes($u['username'])) ?> a fresh invitation link? Any earlier link stops working.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="resend">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <?= action_button(SVG_SEND, 'Re-send invitation') ?>
            </form>
            <?php endif; ?>
            <form method="post" style="display:inline;"
                  onsubmit="return confirm('Start a password reset for <?= e(addslashes($u['username'])) ?>? Their current password stops working immediately and they are emailed a link.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="reset">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <?= action_button(SVG_KEY, 'Reset password') ?>
            </form>
            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
            <form method="post" style="display:inline;"
                  onsubmit="return confirm('Permanently delete <?= e(addslashes($u['username'])) ?>? Consider deactivating instead — it keeps the record.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <?= action_button(SVG_TRASH, 'Delete', ['class' => 'del']) ?>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
