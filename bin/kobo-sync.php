<?php
/**
 * KoboToolbox indicator sync — for a scheduled cron job.
 *
 * Does nothing until KOBO_API_TOKEN is filled in in config.php, so it is safe
 * to schedule now and switch on later.
 *
 * Hostinger hPanel → Advanced → Cron Jobs, once a day:
 *   /usr/bin/php /home/USERNAME/public_html/bin/kobo-sync.php
 *
 * Refuses to run from a browser — this is a command-line tool.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (!kobo_is_configured()) {
    fwrite(STDOUT, "KoboToolbox sync is not configured (KOBO_API_TOKEN is empty). Nothing to do.\n");
    exit(0);
}

$updated = kobo_sync_all();
fwrite(STDOUT, date('c') . " — KoboToolbox sync complete: {$updated} indicator(s) updated.\n");
exit(0);
