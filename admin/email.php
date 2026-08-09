<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_capability('manage_users');
require_once __DIR__ . '/partials/form.php';

$flash = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'save':
                foreach (['smtp_host', 'smtp_username', 'mail_from_email', 'mail_from_name',
                          'mail_reply_to', 'mail_signoff'] as $key) {
                    set_setting($key, trim((string) ($_POST[$key] ?? '')));
                }
                set_setting('smtp_port', (string) max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587))));
                set_setting('smtp_encryption',
                    in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls');
                set_setting('smtp_enabled', !empty($_POST['smtp_enabled']) ? '1' : '0');

                // Write-only: a blank field means "leave the stored password alone",
                // so the page never has to render the secret in order to keep it.
                $newPass = (string) ($_POST['smtp_password'] ?? '');
                if ($newPass !== '') {
                    $enc = encrypt_secret($newPass);
                    if ($enc === null) {
                        throw new RuntimeException('This server has no OpenSSL support, so the password cannot be stored securely. Set SMTP_PASSWORD in config.php instead.');
                    }
                    set_setting('smtp_password_enc', $enc);
                }
                if (!empty($_POST['clear_password'])) {
                    set_setting('smtp_password_enc', '');
                }

                audit('update_email_settings', 'setting', null, 'SMTP configuration');
                // Redirect so mail_config()'s per-request cache is rebuilt fresh.
                header('Location: email.php?saved=1');
                exit;

            case 'save_notifications':
                set_setting('notify_enquiries', !empty($_POST['notify_enquiries']) ? '1' : '0');
                set_setting('notify_enquiries_to', trim((string) ($_POST['notify_enquiries_to'] ?? '')));
                audit('update_email_settings', 'setting', null, 'enquiry notifications');
                header('Location: email.php?saved=1');
                exit;

            case 'test':
                $to = trim((string) ($_POST['test_to'] ?? ''));
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please give a valid address to send the test to.');
                }
                $result = send_email($to, 'test', [
                    'sentBy' => $user['full_name'] ?: $user['username'],
                ], (int) $user['id']);

                if ($result['ok']) {
                    $flash = 'Test email sent to ' . $to . '. If it does not arrive within a few minutes, check the spam folder.';
                } else {
                    $flash = 'Could not send: ' . $result['error'];
                    $flashType = 'error';
                }
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

if (!empty($_GET['saved'])) {
    if (mail_is_configured()) {
        $flash = 'Email settings saved. Send a test below to confirm they work.';
    } else {
        // "Saved" on its own reads like success when nothing will actually send.
        $problems  = mail_config_problems();
        $flash     = 'Settings saved — but email still will not send: ' . $problems[0]['title'] . '. '
                   . $problems[0]['fix'];
        $flashType = 'error';
    }
}

$emailReady   = migration_applied('migrations/003_email.sql');
$cfg          = mail_config();
$hasPassword  = get_setting('smtp_password_enc') !== '' || defined('SMTP_PASSWORD');
$lockedByFile = defined('SMTP_HOST');
$log          = get_email_log(25);
$failures     = count(array_filter($log, fn($r) => $r['status'] === 'failed'));

$pageEyebrow = 'System';
$pageTitle   = 'Email';
$pageIntro   = 'How the site sends invitations, password resets, and security notices.';
$active = 'email';
include __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?><div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div><?php endif; ?>

<?php if (!$emailReady): ?>
  <div class="panel" style="border-color:var(--cherry);">
    <h2>The email update has not been applied yet</h2>
    <p class="hint">
      These settings need one more database import before they will work. In phpMyAdmin, select
      the site database, go to <strong>Import</strong>, and upload these files in order:
    </p>
    <ul style="margin:0 0 16px 18px; font-size:14.5px; color:var(--ink-muted);">
      <li><code>migrations/003_email.sql</code></li>
      <li><code>migrations/004_enquiry_notifications.sql</code></li>
    </ul>
    <p class="hint" style="margin-bottom:0;">
      Until then, accounts can still be created — the invitation link is shown on screen to pass on
      by WhatsApp — but nothing is emailed.
    </p>
  </div>
<?php else: ?>

<!-- ============ STATUS ============ -->
<div class="panel">
  <div class="panel-head">
    <h2>Status</h2>
    <span class="pill <?= mail_is_configured() ? 'ok' : 'warn' ?>">
      <?= mail_is_configured() ? 'SMTP configured' : 'Not configured' ?>
    </span>
  </div>

  <?php if (mail_is_configured()): ?>
    <p class="hint" style="margin-bottom:0;">
      Sending through <strong><?= e($cfg['host']) ?>:<?= (int) $cfg['port'] ?></strong>
      (<?= e(strtoupper($cfg['encryption'])) ?>) as <strong><?= e($cfg['from_email']) ?></strong>.
      Send a test below to confirm it really works.
    </p>
  <?php else: ?>
    <p class="hint">
      Nothing is being emailed yet. Accounts can still be created — the invitation link is shown on
      screen to pass on by WhatsApp — but here is what still needs doing:
    </p>
    <ul class="fix-list">
      <?php foreach (mail_config_problems() as $problem): ?>
        <li>
          <strong><?= e($problem['title']) ?></strong>
          <span><?= e($problem['fix']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<!-- ============ SMTP SETTINGS ============ -->
<div class="panel">
  <h2>SMTP settings</h2>
  <p class="hint">
    Get these from your mailbox provider. On Hostinger: hPanel → Emails → Email Accounts →
    <em>Connect Devices &amp; Apps</em>, which lists the outgoing server, port, and username.
    The username is normally the full email address.
  </p>

  <?php if ($lockedByFile): ?>
    <div class="admin-note">
      These values are currently set in <code>config.php</code>, which overrides anything saved here.
      Remove the <code>SMTP_*</code> constants from that file if you would rather manage them on this page.
    </div>
  <?php endif; ?>

  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <?php field_checkbox('smtp_enabled', 'Use SMTP to send email',
        get_setting('smtp_enabled', '0') === '1',
        'This is the master switch — fill in the details below, tick this, and save. Leaving it '
        . "unticked means the details are stored but ignored, and the site falls back to the server's "
        . 'basic mail function, which most shared hosts silently drop.'); ?>

    <div class="two-col">
      <?php
        field_text('smtp_host', 'Outgoing server (SMTP host)', get_setting('smtp_host'), [
            'placeholder' => 'smtp.hostinger.com',
        ]);
        field_text('smtp_port', 'Port', get_setting('smtp_port', '587'), [
            'type' => 'number', 'min' => 1, 'max' => 65535,
            'help' => '587 for TLS (usual), 465 for SSL.',
        ]);
      ?>
    </div>

    <div class="two-col">
      <?php
        field_select('smtp_encryption', 'Security', [
            'tls'  => 'STARTTLS — port 587 (recommended)',
            'ssl'  => 'SSL/TLS — port 465',
            'none' => 'None (not recommended)',
        ], get_setting('smtp_encryption', 'tls'));
        field_text('smtp_username', 'Username', get_setting('smtp_username'), [
            'placeholder' => 'noreply@missionseedlings.com',
        ]);
      ?>
    </div>

    <div class="form-row">
      <label for="f_smtp_password">Password</label>
      <input type="password" id="f_smtp_password" name="smtp_password" autocomplete="new-password"
             placeholder="<?= $hasPassword ? '•••••••••• (already saved)' : 'Mailbox password' ?>">
      <p class="help">
        <?= $hasPassword
            ? 'A password is saved. Leave this blank to keep it, or type a new one to replace it.'
            : 'Stored encrypted, and never shown again after saving.' ?>
      </p>
    </div>

    <?php if ($hasPassword && !$lockedByFile): ?>
      <?php field_checkbox('clear_password', 'Delete the saved password', false,
          'Tick this to remove it entirely — email will stop working until a new one is set.'); ?>
    <?php endif; ?>

    <h3 style="font-family:var(--display); font-size:16px; margin-top:10px;">How messages appear</h3>
    <div class="two-col">
      <?php
        field_text('mail_from_email', 'From address', get_setting('mail_from_email'), [
            'type' => 'email', 'placeholder' => 'noreply@missionseedlings.com',
            'help' => 'Must be a mailbox on your own domain, or messages will be treated as spam.',
        ]);
        field_text('mail_from_name', 'From name', get_setting('mail_from_name', 'Mission Seedlings'));
      ?>
    </div>
    <div class="two-col">
      <?php
        field_text('mail_reply_to', 'Reply-to address', get_setting('mail_reply_to'), [
            'type' => 'email',
            'help' => 'Where replies should go. Leave blank to use the From address.',
        ]);
        field_text('mail_signoff', 'Sign-off', get_setting('mail_signoff', 'The Mission Seedlings team'), [
            'help' => 'The line at the end of every email.',
        ]);
      ?>
    </div>

    <button type="submit" class="btn" style="justify-self:start;">Save email settings</button>
  </form>
</div>

<!-- ============ NOTIFICATIONS ============ -->
<div class="panel">
  <div class="panel-head">
    <h2>Enquiry notifications</h2>
    <span class="pill <?= enquiry_notifications_enabled() ? 'ok' : 'muted' ?>">
      <?= enquiry_notifications_enabled() ? 'On' : 'Off' ?>
    </span>
  </div>
  <p class="hint">
    Emails the team whenever someone uses the contact form, so the inbox does not have to be
    checked on the off-chance.
  </p>

  <div class="admin-note">
    The notification gives the sender's name, what it is about, and the opening of their message —
    but <strong>not their email address or phone number</strong>. Those stay on the enquiry itself,
    behind a sign-in, rather than being copied into staff mailboxes and forwarded threads.
    The email links straight to it.
  </div>

  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_notifications">

    <?php field_checkbox('notify_enquiries', 'Email the team about new enquiries',
        get_setting('notify_enquiries', '0') === '1'); ?>

    <?php field_text('notify_enquiries_to', 'Send notifications to', get_setting('notify_enquiries_to'), [
        'placeholder' => 'you@missionseedlings.com, someone-else@missionseedlings.com',
        'help' => 'One or more addresses, separated by commas. Leave blank to use the site contact address ('
                . (get_setting('contact_email') ?: 'not set yet') . ').',
    ]); ?>

    <button type="submit" class="btn" style="justify-self:start;">Save notification settings</button>
  </form>
</div>

<!-- ============ TEST ============ -->
<div class="panel">
  <h2>Send a test email</h2>
  <p class="hint">Proves the settings above actually work before you rely on them for an invitation.</p>
  <form method="post" class="form-grid" data-no-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test">
    <?php field_text('test_to', 'Send to', $user['email'] ?? '', [
        'type' => 'email', 'required' => true, 'placeholder' => 'you@example.com',
    ]); ?>
    <button type="submit" class="btn secondary" style="justify-self:start;">Send test</button>
  </form>
</div>

<!-- ============ LOG ============ -->
<div class="panel">
  <div class="panel-head">
    <h2>Recent email</h2>
    <?php if ($failures): ?><span class="pill alert"><?= $failures ?> failed</span><?php endif; ?>
  </div>
  <p class="hint">
    What the site has tried to send. Message contents are deliberately not stored — invitations
    contain single-use links that must not be readable here.
  </p>

  <?php if (!$log): ?>
    <p class="hint" style="margin:0;">Nothing sent yet.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>When</th><th>To</th><th>Message</th><th>Result</th></tr></thead>
      <tbody>
        <?php foreach ($log as $row): ?>
        <tr class="<?= $row['status'] === 'sent' ? '' : 'muted-row' ?>">
          <td style="white-space:nowrap;">
            <?= e(format_date($row['created_at'], 'j M, H:i')) ?><br>
            <span class="help"><?= e(time_ago($row['created_at'])) ?></span>
          </td>
          <td><?= e($row['to_email']) ?></td>
          <td>
            <?= e($row['subject']) ?>
            <br><span class="help"><?= e($row['template'] ?? '—') ?> · via <?= e($row['transport']) ?></span>
          </td>
          <td>
            <span class="pill <?= $row['status'] === 'sent' ? 'ok' : 'alert' ?>"><?= e($row['status']) ?></span>
            <?php if ($row['error']): ?><br><span class="help"><?= e($row['error']) ?></span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; // $emailReady ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
