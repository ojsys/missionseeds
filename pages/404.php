<?php
$meta['title']       = 'Page not found';
$meta['description'] = 'The page you were looking for does not exist.';
$meta['nav_active']  = '';
?>
<section>
  <div class="section-inner">
    <div class="section-head">
      <div class="eyebrow">404</div>
      <h2>We couldn't find that page</h2>
      <p>It may have moved, or the link may have a typo. Here are the places people usually want.</p>
    </div>
    <div class="hero-actions" style="margin-top:8px;">
      <a class="btn-primary" style="background:var(--forest); color:var(--cream); box-shadow:var(--shadow-sm);" href="<?= e(url('/')) ?>">
        Back to the homepage
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
      <a class="btn-ghost" style="color:var(--forest-deep); border-color:var(--cream-line);" href="<?= e(url('/contact')) ?>">Contact the team</a>
    </div>
  </div>
</section>
