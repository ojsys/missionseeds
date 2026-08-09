<?php
/**
 * Shared chrome for every public page: <head>, site navigation, footer, and
 * the logged-in admin bar. Templates in pages/ render only their own content;
 * the router buffers that and hands it to layout_render().
 */

/** Primary navigation. Order here is the order on screen. */
function nav_items(): array {
    return [
        ['path' => '/about',       'label' => 'About',       'key' => 'about'],
        ['path' => '/pathway',     'label' => 'Pathway',     'key' => 'pathway'],
        ['path' => '/cooperative', 'label' => 'Cooperative',  'key' => 'cooperative'],
        ['path' => '/tracker',     'label' => 'Tracker',      'key' => 'tracker'],
        ['path' => '/churches',    'label' => 'Churches',     'key' => 'churches'],
        ['path' => '/stories',     'label' => 'Stories',      'key' => 'stories'],
        ['path' => '/resources',   'label' => 'Resources',    'key' => 'resources'],
    ];
}

/**
 * The header call-to-action. While an application cycle is open this points at
 * the call page; the rest of the time it points at the contact / expression of
 * interest page, so the button is never a dead end.
 */
function nav_cta(): array {
    if (get_setting('call_status', 'open') === 'open') {
        return ['path' => '/call', 'label' => get_setting('eoi_button_label', 'Apply now')];
    }
    return ['path' => '/contact', 'label' => 'Express interest'];
}

function seedlings_mark(string $ring = '#1f3b2c'): string {
    return '<svg viewBox="0 0 32 32" fill="none" aria-hidden="true">'
        . '<circle cx="16" cy="16" r="15" stroke="' . e($ring) . '" stroke-width="1.4"/>'
        . '<path d="M16 22V13" stroke="#7fa653" stroke-width="1.6" stroke-linecap="round"/>'
        . '<path d="M16 13c0-3.5-3-4.5-5.5-4C11 12 13.5 13.3 16 13Z" fill="#7fa653"/>'
        . '<path d="M16 15c0-3 3-4 5.5-3.6C21 15 18.5 16 16 15Z" fill="#c69447"/></svg>';
}

function layout_render(array $meta, string $content): void {
    $isAdmin  = admin_logged_in();
    $user     = current_user();
    $siteName = get_setting('site_title', 'Mission Seedlings');
    $tagline  = get_setting('tagline', 'Growing Local. Flourishing Together.');
    $cta      = nav_cta();
    $active   = $meta['nav_active'] ?? '';

    $title = $meta['title'] ?? $siteName;
    if (stripos($title, $siteName) === false) {
        $title .= ' — ' . $siteName;
    }
    $shareRel   = image_url(get_setting('share_image'), get_setting('hero_image', 'assets/uploads/hero.jpg'));
    // og:image must be absolute — social scrapers fetch it from outside the site.
    $shareImage = $shareRel ? (strpos($shareRel, 'http') === 0 ? $shareRel : rtrim(SITE_URL, '/') . $shareRel) : null;
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(mb_substr(strip_tags($meta['description'] ?? ''), 0, 300)) ?>">
<link rel="canonical" href="<?= e($meta['canonical'] ?? url('/')) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e(mb_substr(strip_tags($meta['description'] ?? ''), 0, 300)) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($meta['canonical'] ?? url('/')) ?>">
<?php if ($shareImage): ?><meta property="og:image" content="<?= e($shareImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#1f3b2c">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body class="<?= e($meta['body_class'] ?? '') ?>" data-admin="<?= $isAdmin ? '1' : '0' ?>">

<a class="skip-link" href="#main">Skip to content</a>

<header class="site">
  <div class="navbar">
    <a class="brand" href="<?= e(url('/')) ?>">
      <?= seedlings_mark() ?>
      <span class="brand-text"><b><?= e($siteName) ?></b><small><?= e($tagline) ?></small></span>
    </a>

    <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="site-nav">
      <span class="nav-toggle-bars" aria-hidden="true"><i></i><i></i><i></i></span>
      <span class="sr-only">Menu</span>
    </button>

    <nav class="nav-links" id="site-nav" aria-label="Main">
      <?php foreach (nav_items() as $item): ?>
        <a href="<?= e(url($item['path'])) ?>"<?= $active === $item['key'] ? ' class="active" aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <a class="nav-links-contact" href="<?= e(url('/contact')) ?>"<?= $active === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a>
      <a class="nav-cta" href="<?= e(url($cta['path'])) ?>"><?= e($cta['label']) ?> &rarr;</a>
    </nav>
  </div>
</header>

<main id="main"><?= $content ?></main>

<footer>
  <div class="footer-inner">
    <div class="footer-brand">
      <div class="brand-mark"><?= seedlings_mark('#f7f2e4') ?><span><?= e($siteName) ?></span></div>
      <div class="footer-tagline"><?= editable('footer_tagline', $isAdmin) ?></div>
      <?php if ($addr = get_setting('footer_address')): ?>
        <div class="footer-address"><?= editable('footer_address', $isAdmin, true) ?></div>
      <?php endif; ?>
    </div>

    <nav class="footer-nav" aria-label="Footer">
      <div class="footer-col">
        <h3>The project</h3>
        <a href="<?= e(url('/about')) ?>">About</a>
        <a href="<?= e(url('/pathway')) ?>">Seedlings Pathway</a>
        <a href="<?= e(url('/cooperative')) ?>">Cooperative Model</a>
        <a href="<?= e(url('/tracker')) ?>">Growth Tracker</a>
      </div>
      <div class="footer-col">
        <h3>Community</h3>
        <a href="<?= e(url('/churches')) ?>">Participating Churches</a>
        <a href="<?= e(url('/stories')) ?>">Growth Stories</a>
        <a href="<?= e(url('/resources')) ?>">Resource Hub</a>
        <?php if (get_setting('call_status', 'open') === 'open'): ?>
          <a href="<?= e(url('/call')) ?>">Call for applications</a>
        <?php endif; ?>
      </div>
      <div class="footer-col">
        <h3>Get in touch</h3>
        <a href="<?= e(url('/contact')) ?>">Contact us</a>
        <?php if ($wa = get_setting('whatsapp_number')): ?>
          <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <?php endif; ?>
        <?php if ($em = get_setting('contact_email')): ?>
          <a href="mailto:<?= e($em) ?>"><?= e($em) ?></a>
        <?php endif; ?>
        <a href="<?= e(url('/admin/login.php')) ?>">Staff login</a>
      </div>
    </nav>
  </div>

  <div class="footer-meta">© <?= date('Y') ?> <?= e($siteName) ?> · Jos, Plateau State, Nigeria</div>
</footer>

<?php if ($isAdmin): ?>
<div class="admin-bar">
  <span>🌱 <strong><?= e($user['full_name'] ?: $user['username']) ?></strong> · <?= e(role_label($user['role'])) ?></span>
  <div class="sep"></div>
  <a href="<?= e(url('/admin/index.php')) ?>">Dashboard</a>
  <?php if (user_can('manage_content')): ?>
    <a href="<?= e(url('/admin/settings.php')) ?>">Settings</a>
  <?php endif; ?>
  <div class="sep"></div>
  <a href="<?= e(url('/admin/logout.php')) ?>" class="danger">Log out</a>
</div>
<?php endif; ?>

<script>
  window.SEEDLINGS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
  window.SEEDLINGS_CSRF  = "<?= $isAdmin ? e(csrf_token()) : '' ?>";
  window.SEEDLINGS_API   = "<?= e(url('/admin/api.php')) ?>";
  <?php if ($isAdmin): ?>
  window.SEEDLINGS_ICONS = <?= json_encode(array_map(
      fn($k, $label) => ['key' => $k, 'label' => $label],
      array_keys(ICON_LIBRARY),
      array_values(ICON_LIBRARY)
  )) ?>;
  <?php endif; ?>
</script>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</body>
</html>
<?php
}
