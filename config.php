<?php
/**
 * Seedlings CMS — database configuration
 * ----------------------------------------------------------
 * Hostinger hPanel: Databases > MySQL Databases > create a
 * database, create a user, add the user to the database with
 * ALL PRIVILEGES.
 *
 * Hostinger PREFIXES your DB name + username with the
 * hPanel hosting account prefix (typically 8 alphanumeric chars,
 * e.g. "u89abcd12").
 *
 *   DB example:  u89abcd12_seedlings
 *   USER example: u89abcd12_seeduser
 *
 * FILL IN DB_NAME, DB_USER, DB_PASS below from your hPanel
 * before uploading this file to public_html on missionseedlings.com
 */

// Local development override. If config.local.php exists next to this file it
// wins, and the production values below are skipped. Never upload that file to
// the live server — it is for a developer's own machine only.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'REPLACE_WITH_HOSTINGER_PREFIX_seedlings');
defined('DB_USER') || define('DB_USER', 'REPLACE_WITH_HOSTINGER_PREFIX_seeduser');
defined('DB_PASS') || define('DB_PASS', 'REPLACE_WITH_A_STRONG_UNIQUE_PASSWORD');

// Absolute base URL (no trailing slash) — PRODUCTION DOMAIN
defined('SITE_URL') || define('SITE_URL', 'https://missionseedlings.com');

// Cryptographic salt for admin session cookies.
// ⚠️  Keep this string secret. Already generated as 256-bit random.
defined('APP_KEY') || define('APP_KEY', '51a7c7955c3a6f144b20be41b5d792ca1a07d232b80b1b5cf4e098f26f73304f');

// KoboToolbox API credentials. Leave blank in phase one — the Resource Hub
// links to forms directly. Filling these in enables live indicator sync.
defined('KOBO_API_BASE')  || define('KOBO_API_BASE', 'https://kf.kobotoolbox.org');
defined('KOBO_API_TOKEN') || define('KOBO_API_TOKEN', '');

// Timezone for the deadline countdown and admin timestamps.
// For Nigeria / West Africa (WAT, UTC+1).
date_default_timezone_set('Africa/Lagos');

// ---- Do not edit below this line ----
define('BASE_PATH', __DIR__);
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads');
define('UPLOAD_URL', SITE_URL . '/assets/uploads');

// ---------------------------------------------------------------------
// Production safety: hide PHP version banner + disable unneeded warnings in
// output. Hostinger php.ini may already do this, belt+suspenders here.
// ---------------------------------------------------------------------
@ini_set('expose_php', '0');
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
