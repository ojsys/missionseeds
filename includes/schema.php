<?php
/**
 * Schema introspection and the pending-migration check.
 *
 * Migrations are imported by hand through phpMyAdmin, so it is entirely normal
 * for someone to upload new code and forget one. Without this, the symptom is a
 * blank page or a 500 with no explanation. With it, the admin says plainly which
 * file still needs importing.
 */

/** Tables in the current database. One query, cached per request. */
function schema_tables(): array {
    static $tables = null;
    if ($tables === null) {
        try {
            $stmt = db()->prepare(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
            );
            $stmt->execute();
            $tables = array_map('strtolower', array_column($stmt->fetchAll(), 'TABLE_NAME'));
        } catch (Throwable $e) {
            error_log('[Seedlings schema] ' . $e->getMessage());
            $tables = [];
        }
    }
    return $tables;
}

function schema_has_table(string $table): bool {
    return in_array(strtolower($table), schema_tables(), true);
}

function schema_has_column(string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (!array_key_exists($key, $cache)) {
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            $cache[$key] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            $cache[$key] = false;
        }
    }
    return $cache[$key];
}

/**
 * What each migration is expected to have created. Add a line when you add a
 * migration — one representative table or column is enough to detect it.
 */
function migration_checks(): array {
    return [
        [
            'file'  => 'migrations/002_platform.sql',
            'what'  => 'churches, pathway, indicators, stories, resources, and the four user roles',
            'ok'    => schema_has_table('churches') && schema_has_table('indicators')
                       && schema_has_table('users'),
        ],
        [
            'file'  => 'migrations/003_email.sql',
            'what'  => 'email invitations, SMTP settings, and the delivery log',
            'ok'    => schema_has_table('email_log') && schema_has_column('password_resets', 'purpose'),
        ],
        [
            'file'  => 'migrations/004_enquiry_notifications.sql',
            'what'  => 'enquiry notification settings',
            'ok'    => setting_exists('notify_enquiries'),
        ],
    ];
}

/** True when a settings row is present (not merely empty). */
function setting_exists(string $key): bool {
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** @return array<int, array{file:string, what:string}> migrations still to import */
function pending_migrations(): array {
    static $pending = null;
    if ($pending === null) {
        $pending = [];
        foreach (migration_checks() as $check) {
            if (!$check['ok']) {
                $pending[] = ['file' => $check['file'], 'what' => $check['what']];
            }
        }
    }
    return $pending;
}

/** Is a specific migration in place? Used to disable features that need it. */
function migration_applied(string $file): bool {
    foreach (migration_checks() as $check) {
        if ($check['file'] === $file) return $check['ok'];
    }
    return true;
}
