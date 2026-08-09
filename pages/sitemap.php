<?php
/**
 * XML sitemap. Rendered outside the layout — the router hands /sitemap.xml
 * straight here and this file writes the whole response itself.
 *
 * Only published, publicly visible content is listed.
 */
header('Content-Type: application/xml; charset=UTF-8');

$urls = [
    ['loc' => url('/'),            'priority' => '1.0', 'freq' => 'weekly'],
    ['loc' => url('/about'),       'priority' => '0.8', 'freq' => 'monthly'],
    ['loc' => url('/pathway'),     'priority' => '0.8', 'freq' => 'monthly'],
    ['loc' => url('/cooperative'), 'priority' => '0.7', 'freq' => 'monthly'],
    ['loc' => url('/tracker'),     'priority' => '0.9', 'freq' => 'weekly'],
    ['loc' => url('/churches'),    'priority' => '0.8', 'freq' => 'weekly'],
    ['loc' => url('/stories'),     'priority' => '0.8', 'freq' => 'weekly'],
    ['loc' => url('/resources'),   'priority' => '0.6', 'freq' => 'monthly'],
    ['loc' => url('/contact'),     'priority' => '0.7', 'freq' => 'monthly'],
];

if (get_setting('call_status', 'open') !== 'hidden') {
    $urls[] = ['loc' => url('/call'), 'priority' => '0.9', 'freq' => 'weekly'];
}

foreach (get_churches() as $c) {
    $urls[] = [
        'loc'      => url('/churches/' . $c['slug']),
        'priority' => '0.6',
        'freq'     => 'monthly',
        'lastmod'  => $c['updated_at'],
    ];
}
foreach (get_stories(['limit' => 500]) as $s) {
    $urls[] = [
        'loc'      => url('/stories/' . $s['slug']),
        'priority' => '0.5',
        'freq'     => 'yearly',
        'lastmod'  => $s['updated_at'],
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= e($u['loc']) ?></loc>
<?php if (!empty($u['lastmod'])): ?>
    <lastmod><?= e(date('Y-m-d', strtotime($u['lastmod']))) ?></lastmod>
<?php endif; ?>
    <changefreq><?= e($u['freq']) ?></changefreq>
    <priority><?= e($u['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
