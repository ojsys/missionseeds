<?php
$errors = [];
$sent   = false;
$form   = ['type' => 'contact', 'name' => '', 'email' => '', 'phone' => '',
           'organisation' => '', 'church_name' => '', 'community' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $k => $_) {
        $form[$k] = trim((string) ($_POST[$k] ?? ''));
    }

    // Two silent bot checks, so genuine visitors on slow connections never meet
    // a captcha: a field humans cannot see, and a form that was filled in
    // impossibly fast.
    $trap    = trim((string) ($_POST['website'] ?? ''));
    $started = (int) ($_POST['started_at'] ?? 0);
    $tooFast = $started > 0 && (time() - $started) < 3;

    if ($trap !== '' || $tooFast) {
        // Behave exactly like success — never tell a bot which check caught it.
        $sent = true;
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try sending that again.';
    } else {
        $result = create_submission($form);
        if ($result['ok']) {
            $sent = true;
            audit('contact_submission', 'submission', $result['id'], $form['type']);
        } else {
            $errors = $result['errors'];
        }
    }
}

$eoiUrl   = get_setting('eoi_form_url', '');
$whatsapp = get_setting('whatsapp_number', '');
$email    = get_setting('contact_email', '');
$callOpen = get_setting('call_status', 'open') === 'open';

$meta['title']       = 'Contact and Expression of Interest';
$meta['description'] = get_setting('contact_intro');
$meta['nav_active']  = 'contact';
?>
<section class="page-intro">
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow"><?= editable('contact_eyebrow', $isAdmin) ?></div>
      <h1><?= editable('contact_heading', $isAdmin) ?></h1>
      <p class="lede-dark"><?= editable('contact_intro', $isAdmin, true) ?></p>
    </div>
  </div>
</section>

<section style="background:var(--cream-soft);">
  <div class="section-inner">
    <div class="contact-grid">

      <!-- ---------- form ---------- -->
      <div class="contact-form-wrap">
        <?php if ($sent): ?>
          <div class="form-success">
            <div class="resource-icon"><?= icon_svg('seedling') ?></div>
            <h2>Thank you — we have your message</h2>
            <p>Someone from the Mission Seedlings team will be in touch. If it is urgent, WhatsApp is usually fastest.</p>
          </div>
        <?php else: ?>
          <h2>Send us a message</h2>

          <?php if ($errors): ?>
            <div class="form-errors">
              <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>

          <form method="post" class="contact-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="started_at" value="<?= time() ?>">
            <!-- Honeypot: hidden from people, irresistible to bots. -->
            <div class="hp-field" aria-hidden="true">
              <label for="website">Website</label>
              <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-row">
              <label for="type">What is this about?</label>
              <select id="type" name="type">
                <?php foreach (SUBMISSION_TYPES as $k => $label): ?>
                  <option value="<?= e($k) ?>" <?= $form['type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row">
              <label for="name">Your name <span class="req">*</span></label>
              <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="160" autocomplete="name">
            </div>

            <div class="two-col">
              <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="190" autocomplete="email">
              </div>
              <div class="form-row">
                <label for="phone">Phone / WhatsApp</label>
                <input type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="40" autocomplete="tel">
              </div>
            </div>
            <p class="help">Give us at least one of these so we can reply.</p>

            <div class="two-col">
              <div class="form-row">
                <label for="church_name">Church (if applicable)</label>
                <input type="text" id="church_name" name="church_name" value="<?= e($form['church_name']) ?>" maxlength="180">
              </div>
              <div class="form-row">
                <label for="community">Community / town</label>
                <input type="text" id="community" name="community" value="<?= e($form['community']) ?>" maxlength="140">
              </div>
            </div>

            <div class="form-row">
              <label for="organisation">Organisation (if applicable)</label>
              <input type="text" id="organisation" name="organisation" value="<?= e($form['organisation']) ?>" maxlength="180">
            </div>

            <div class="form-row">
              <label for="message">Your message <span class="req">*</span></label>
              <textarea id="message" name="message" rows="6" required maxlength="5000"><?= e($form['message']) ?></textarea>
            </div>

            <p class="help">
              We use what you send here only to reply to you and to plan the programme.
              We never publish it or pass it to anyone outside the project team.
            </p>

            <button type="submit" class="btn-primary" style="background:var(--forest); color:var(--cream); box-shadow:var(--shadow-sm);">
              Send message
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
          </form>
        <?php endif; ?>
      </div>

      <!-- ---------- side panels ---------- -->
      <aside class="contact-aside">
        <div class="aside-card">
          <div class="resource-icon"><?= icon_svg('shield') ?></div>
          <h3><?= editable('contact_eoi_heading', $isAdmin) ?></h3>
          <p><?= editable('contact_eoi_body', $isAdmin, true) ?></p>
          <?php if ($callOpen): ?>
            <a class="btn-inline" href="<?= e(url('/call')) ?>">
              See the current call
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
          <?php endif; ?>
          <?php if ($eoiUrl): ?>
            <a class="btn-inline" href="<?= e($eoiUrl) ?>" target="_blank" rel="noopener">
              Expression of interest form
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
          <?php endif; ?>
        </div>

        <div class="aside-card">
          <div class="resource-icon"><?= icon_svg('handshake') ?></div>
          <h3><?= editable('contact_partner_heading', $isAdmin) ?></h3>
          <p><?= editable('contact_partner_body', $isAdmin, true) ?></p>
        </div>

        <div class="aside-card aside-card--contact">
          <h3>Reach us directly</h3>
          <?php if ($whatsapp): ?>
            <a class="contact-line" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">
              <?= icon_svg('chat') ?><?= e(get_setting('whatsapp_display', 'WhatsApp')) ?>
            </a>
          <?php endif; ?>
          <?php if ($email): ?>
            <a class="contact-line" href="mailto:<?= e($email) ?>"><?= icon_svg('book') ?><?= e($email) ?></a>
          <?php endif; ?>
          <div class="contact-line contact-line--static">
            <?= icon_svg('land') ?><?= e(get_setting('footer_address', 'Jos, Plateau State, Nigeria')) ?>
          </div>
        </div>
      </aside>

    </div>
  </div>
</section>
