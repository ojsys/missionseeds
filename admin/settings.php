<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_admin();

$SETTABLE = [
    'site_title', 'tagline',
    'deadline_date', 'eoi_form_url', 'eoi_button_label',
    'whatsapp_number', 'whatsapp_display',
];

$flash = '';
$flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = 'Session expired — please try again.';
        $flashType = 'error';
    } else {
        foreach ($SETTABLE as $key) {
            if (isset($_POST[$key])) {
                set_setting($key, trim((string) $_POST[$key]));
            }
        }
        $flash = 'Settings saved.';
    }
}

$pageTitle = 'Settings';
$active = 'settings';
include __DIR__ . '/partials/header.php';
?>

<div class="admin-header">
  <div class="eyebrow">Site settings</div>
  <h1>Settings</h1>
  <p>These fields power buttons, dates, and contact details across the page — they're structured data rather than free text, so they live here instead of the inline editor.</p>
</div>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="panel">
    <h2>Application</h2>
    <p class="hint">Where the "Apply now" / "Express interest" buttons send people, and the deadline shown on the page.</p>
    <div class="form-grid">
      <div class="form-row">
        <label for="eoi_form_url">Expression of interest form URL</label>
        <input type="url" id="eoi_form_url" name="eoi_form_url" value="<?= e(get_setting('eoi_form_url')) ?>" placeholder="https://forms.gle/...">
        <p class="help">Paste the link to your Google Form, Typeform, or any external application form. Every "Apply" button on the site opens this link in a new tab.</p>
      </div>
      <div class="two-col">
        <div class="form-row">
          <label for="eoi_button_label">Apply button label</label>
          <input type="text" id="eoi_button_label" name="eoi_button_label" value="<?= e(get_setting('eoi_button_label')) ?>">
        </div>
        <div class="form-row">
          <label for="deadline_date">Expression of interest deadline</label>
          <input type="date" id="deadline_date" name="deadline_date" value="<?= e(get_setting('deadline_date')) ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Contact</h2>
    <p class="hint">Used by the WhatsApp button in the "Join the pilot" section.</p>
    <div class="two-col">
      <div class="form-row">
        <label for="whatsapp_number">WhatsApp number (digits only, with country code)</label>
        <input type="tel" id="whatsapp_number" name="whatsapp_number" value="<?= e(get_setting('whatsapp_number')) ?>" placeholder="2348137912872">
      </div>
      <div class="form-row">
        <label for="whatsapp_display">WhatsApp number (as displayed)</label>
        <input type="text" id="whatsapp_display" name="whatsapp_display" value="<?= e(get_setting('whatsapp_display')) ?>" placeholder="+234 813 791 2872">
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Site identity</h2>
    <p class="hint">Shown in the navigation bar, browser tab, and footer.</p>
    <div class="two-col">
      <div class="form-row">
        <label for="site_title">Site title</label>
        <input type="text" id="site_title" name="site_title" value="<?= e(get_setting('site_title')) ?>">
      </div>
      <div class="form-row">
        <label for="tagline">Tagline</label>
        <input type="text" id="tagline" name="tagline" value="<?= e(get_setting('tagline')) ?>">
      </div>
    </div>
  </div>

  <button type="submit" class="btn">Save settings</button>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
