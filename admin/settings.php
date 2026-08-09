<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_capability('manage_settings');

$SETTABLE = [
    'site_title', 'tagline', 'footer_address', 'contact_email',
    'call_status', 'deadline_date', 'eoi_form_url', 'eoi_button_label',
    'whatsapp_number', 'whatsapp_display',
];

const CALL_STATUSES = [
    'open'   => 'Open — accepting applications',
    'closed' => 'Closed — page stays up with a closed notice',
    'hidden' => 'Hidden — page returns "not found" to the public',
];
// hero_image is set via file upload handler below, not $_POST text.

$flash = '';
$flashType = '';
$DEFAULT_HERO = 'assets/uploads/hero.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = 'Session expired — please try again.';
        $flashType = 'error';
    } else {
        // --- (1) File upload: hero image -----------------------------------------
        if (isset($_FILES['hero_image_upload']) && is_uploaded_file($_FILES['hero_image_upload']['tmp_name'])) {
            try {
                $file = $_FILES['hero_image_upload'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Hero image upload error code ' . $file['error']);
                }
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!isset($allowed[$mime])) {
                    throw new RuntimeException('Hero image must be a JPG, PNG, or WEBP file.');
                }
                if ($file['size'] > 8 * 1024 * 1024) {
                    throw new RuntimeException('Hero image must be under 8MB (for fast page load — try compressing with squoosh.app first).');
                }
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                $filename = 'hero-image-' . time() . '.' . $allowed[$mime];
                $dest     = UPLOAD_DIR . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    throw new RuntimeException('Could not save the uploaded hero image (check assets/uploads/ permissions).');
                }
                set_setting('hero_image', 'assets/uploads/' . $filename);
                $flash = 'Hero image updated.';
                $flashType = '';
            } catch (Throwable $e) {
                $flash = $e->getMessage();
                $flashType = 'error';
            }
        }

        // --- (2) Action: reset hero to default ------------------------------------
        if (isset($_POST['reset_hero']) && $_POST['reset_hero'] === '1') {
            set_setting('hero_image', $DEFAULT_HERO);
            $flash = 'Hero image reset to default.';
            $flashType = '';
        }

        // --- (3) Normal text settings ---------------------------------------------
        foreach ($SETTABLE as $key) {
            if (isset($_POST[$key])) {
                set_setting($key, trim((string) $_POST[$key]));
            }
        }

        if ($flash === '') {
            $flash = 'Settings saved.';
        }
    }
}

$heroImage    = get_setting('hero_image', $DEFAULT_HERO);
$heroImageUrl = (strpos($heroImage, 'http') === 0) ? $heroImage : rtrim(SITE_URL, '/') . '/' . ltrim($heroImage, '/');
$isDefaultHero = ($heroImage === $DEFAULT_HERO);

$pageEyebrow = 'Site settings';
$pageTitle   = 'Settings';
$pageIntro   = 'These fields power buttons, dates, and contact details across the page — they\'re structured data rather than free text, so they live here instead of the inline editor.';
$active = 'settings';
include __DIR__ . '/partials/header.php';
?>


<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= e($flash) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="panel" id="appearance">
    <h2>Appearance — Hero photo</h2>
    <p class="hint">The large photo shown at the top of the page next to the headline. Recommendation: 1600×1100px JPG, under 600KB, with people on the right side of the image (copy sits on the left).</p>

    <div class="hero-preview-wrap">
      <div class="hero-preview-box">
        <img id="hero-preview-img" src="<?= e($heroImageUrl) ?>" alt="Current hero photo preview"
             onerror="this.onerror=null;this.src='<?= e(rtrim(SITE_URL, '/')) ?>/<?= e($DEFAULT_HERO) ?>';">
        <div class="hero-preview-badge"><?= $isDefaultHero ? 'Default photo' : 'Custom photo' ?></div>
      </div>
      <div class="hero-upload-controls">
        <label for="hero_image_upload" class="btn">Choose new photo…</label>
        <input type="file" id="hero_image_upload" name="hero_image_upload"
               accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" style="display:none;">
        <button type="submit" name="reset_hero" value="1" class="btn secondary"
                onclick="return confirm('Reset hero photo back to the default?')">Reset to default</button>
        <div class="form-row" style="margin-top:16px; margin-bottom:0;">
          <label style="margin-bottom:6px;">File format</label>
          <p class="help" style="margin:0;">JPG, PNG, or WEBP — max 8MB. Smaller = faster landing page.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Call for applications</h2>
    <p class="hint">
      The call page at <code><?= e(url('/call')) ?></code> stays in place between cycles.
      When a new call opens, update the deadline and form link below and set the status back to open.
    </p>
    <div class="form-grid">
      <div class="form-row">
        <label for="call_status">Call status</label>
        <select id="call_status" name="call_status">
          <?php foreach (CALL_STATUSES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= get_setting('call_status', 'open') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="help">
          While the call is open, the main navigation button points at the call page. Otherwise it
          points at the contact page so nobody hits a dead end.
        </p>
      </div>
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
    <p class="hint">Used on the contact page, in the footer, and by the WhatsApp buttons.</p>
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
    <div class="two-col">
      <div class="form-row">
        <label for="contact_email">Contact email</label>
        <input type="email" id="contact_email" name="contact_email" value="<?= e(get_setting('contact_email')) ?>" placeholder="hello@missionseedlings.com">
        <p class="help">Shown on the contact page and in the footer. Leave blank to hide it.</p>
      </div>
      <div class="form-row">
        <label for="footer_address">Address line</label>
        <input type="text" id="footer_address" name="footer_address" value="<?= e(get_setting('footer_address')) ?>" placeholder="Jos, Plateau State, Nigeria">
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

<script>
  // Live preview before upload (no round-trip to server)
  (function(){
    var input = document.getElementById('hero_image_upload');
    var preview = document.getElementById('hero-preview-img');
    if(!input || !preview) return;
    input.addEventListener('change', function(){
      var file = input.files && input.files[0];
      if(!file) return;
      if(!/image\/(jpeg|png|webp)/.test(file.type)) return;
      var reader = new FileReader();
      reader.onload = function(e){ preview.src = e.target.result; };
      reader.readAsDataURL(file);
    });
  })();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
