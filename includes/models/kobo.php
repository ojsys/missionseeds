<?php
/**
 * KoboToolbox integration.
 *
 * PHASE ONE — the website links to Kobo forms; it does not read from them.
 * Growth Tracker indicators are entered by project managers.
 *
 * PHASE TWO — set KOBO_API_TOKEN in config.php, point an indicator's
 * source/kobo_form_id/kobo_field at a form, and schedule kobo_sync_all() from
 * a cron job. The schema already carries every field this needs, so switching
 * it on requires no migration.
 *
 * PRIVACY: only aggregate counts ever cross this boundary. Raw submissions —
 * which contain participant names, phone numbers, and household data — must
 * stay inside KoboToolbox. Do not add a function here that stores them.
 */

function kobo_is_configured(): bool {
    return defined('KOBO_API_TOKEN') && KOBO_API_TOKEN !== '';
}

/**
 * GETs a KoboToolbox API endpoint and returns the decoded body, or null on
 * any failure. Never throws — a sync problem must not take the site down.
 */
function kobo_api_get(string $path): ?array {
    if (!kobo_is_configured() || !function_exists('curl_init')) {
        return null;
    }
    $url = rtrim(KOBO_API_BASE, '/') . '/' . ltrim($path, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Token ' . KOBO_API_TOKEN,
            'Accept: application/json',
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        error_log('[Seedlings Kobo] ' . $path . ' failed: HTTP ' . $status . ' ' . $err);
        return null;
    }
    $decoded = json_decode((string) $body, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * The submission count for a Kobo form — the summary figure most indicators
 * need (participants registered, reports submitted, and so on).
 */
function kobo_submission_count(string $formUid): ?int {
    $data = kobo_api_get('/api/v2/assets/' . rawurlencode($formUid) . '/?format=json');
    if ($data === null) return null;
    return isset($data['deployment__submission_count']) ? (int) $data['deployment__submission_count'] : null;
}

/**
 * Resolves the current value for one Kobo-sourced indicator.
 *
 * Returns null when sync is not configured, the form is unreachable, or the
 * indicator names a field this function does not know how to summarise —
 * in which case the manually entered value is simply left alone.
 */
function kobo_sync_indicator(array $indicator): ?float {
    if (($indicator['source'] ?? 'manual') !== 'kobo' || empty($indicator['kobo_form_id'])) {
        return null;
    }
    // The only summary we can derive without reading raw submissions.
    if (in_array($indicator['kobo_field'] ?? '', ['', 'submission_count'], true)) {
        $count = kobo_submission_count($indicator['kobo_form_id']);
        return $count === null ? null : (float) $count;
    }
    // Per-field aggregation needs Kobo's reporting endpoints; wire it up here
    // when the client's forms and field names are finalised.
    return null;
}

/**
 * Syncs every indicator marked source='kobo'. Safe to call from cron:
 *   php /home/USER/public_html/bin/kobo-sync.php
 * Returns the number of indicators actually updated.
 */
function kobo_sync_all(): int {
    if (!kobo_is_configured()) return 0;

    $updated = 0;
    foreach (db()->query("SELECT * FROM indicators WHERE source = 'kobo'") as $indicator) {
        $value = kobo_sync_indicator($indicator);
        if ($value === null) continue;

        set_indicator_value((int) $indicator['id'], $value);
        db()->prepare('UPDATE indicators SET last_synced_at = NOW() WHERE id = ?')
            ->execute([$indicator['id']]);
        $updated++;
    }
    if ($updated) {
        audit('kobo_sync', 'indicator', null, $updated . ' indicator(s) updated');
    }
    return $updated;
}
