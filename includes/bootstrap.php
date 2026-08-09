<?php
/**
 * Single entry point for shared includes. Every front-controller request and
 * every admin/portal page starts here, so there is exactly one place to add a
 * new model or helper.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/content_keys.php';

require_once __DIR__ . '/models/project.php';
require_once __DIR__ . '/models/stories.php';
require_once __DIR__ . '/models/resources.php';
require_once __DIR__ . '/models/kobo.php';
require_once __DIR__ . '/models/submissions.php';
require_once __DIR__ . '/models/users.php';

/**
 * The site's path prefix, empty for a domain root install and "/seedlings"
 * for a subfolder one. Derived from SITE_URL so there is one place to change it.
 */
function base_path_prefix(): string {
    static $prefix = null;
    if ($prefix === null) {
        $prefix = rtrim((string) parse_url(SITE_URL, PHP_URL_PATH), '/');
    }
    return $prefix;
}

/** Absolute URL for a site path. url('/about') → https://missionseedlings.com/about */
function url(string $path = '/'): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Root-relative URL for an asset, cache-busted by file mtime.
 *
 * Deliberately not absolute: an absolute URL built from SITE_URL breaks every
 * stylesheet, script, and image the moment the site is reached on any other
 * hostname — a staging domain, a bare IP, or a temporary Hostinger preview URL.
 * A root-relative path is correct on all of them.
 */
function asset(string $relativePath): string {
    $rel  = ltrim($relativePath, '/');
    $full = BASE_PATH . '/' . $rel;
    $ver  = is_file($full) ? @filemtime($full) : null;
    return base_path_prefix() . '/' . $rel . ($ver ? '?v=' . $ver : '');
}

/**
 * URL for an uploaded/stored image, falling back to a default when the file is
 * missing (common when a database is copied between environments without the
 * uploads folder). Returns null when neither exists, so callers can omit the
 * <img> entirely rather than render a broken one.
 */
function image_url(?string $storedPath, ?string $fallback = null): ?string {
    $path = $storedPath ?: $fallback;
    if (!$path) return null;
    if (strpos($path, 'http') === 0) return $path;
    $rel = ltrim($path, '/');
    if (!is_file(BASE_PATH . '/' . $rel)) {
        if (!$fallback || $path === $fallback) return null;
        $rel = ltrim($fallback, '/');
        if (!is_file(BASE_PATH . '/' . $rel)) return null;
    }
    $ver = @filemtime(BASE_PATH . '/' . $rel);
    return base_path_prefix() . '/' . $rel . ($ver ? '?v=' . $ver : '');
}
