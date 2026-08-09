<?php
require_once __DIR__ . '/db.php';

/* ===========================================================================
   Roles and capabilities
   ---------------------------------------------------------------------------
   Four roles. Everything the application gates on is expressed as a named
   capability, never as a role check scattered through page code — so changing
   what a Project Manager may do is a one-line edit here.
   =========================================================================== */

const ROLES = [
    'super_admin'       => 'Super Admin',
    'project_manager'   => 'Project Manager',
    'editor'            => 'Editor',
    'church_coordinator'=> 'Church Coordinator',
];

const ROLE_CAPABILITIES = [
    'super_admin' => [
        'manage_users', 'view_audit', 'manage_settings', 'manage_content',
        'manage_project', 'manage_stories', 'publish_stories', 'submit_stories',
        'manage_resources', 'view_resources_staff', 'view_resources_coordinator',
        'view_submissions', 'manage_media', 'view_progress_all',
    ],
    'project_manager' => [
        'view_audit', 'manage_settings', 'manage_content',
        'manage_project', 'manage_stories', 'publish_stories', 'submit_stories',
        'manage_resources', 'view_resources_staff', 'view_resources_coordinator',
        'view_submissions', 'manage_media', 'view_progress_all',
    ],
    'editor' => [
        'manage_settings', 'manage_content',
        'manage_stories', 'publish_stories', 'submit_stories',
        'view_resources_staff', 'manage_media', 'view_progress_all',
    ],
    'church_coordinator' => [
        'submit_stories', 'manage_media',
        'view_resources_coordinator', 'view_progress_own',
    ],
];

/** Staff roles use the /admin area; everyone else uses /portal. */
const STAFF_ROLES = ['super_admin', 'project_manager', 'editor'];

function role_label(?string $role): string {
    return ROLES[$role ?? ''] ?? 'Unknown';
}

function is_staff_role(?string $role): bool {
    return in_array($role, STAFF_ROLES, true);
}

/* ===========================================================================
   Sessions
   =========================================================================== */

const SESSION_IDLE_TIMEOUT     = 1800;   // 30 minutes of inactivity
const SESSION_ABSOLUTE_TIMEOUT = 43200;  // 12 hours, then re-authenticate

function start_session_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** A stable fingerprint of the browser, so a stolen cookie alone is not enough. */
function session_fingerprint(): string {
    return hash('sha256', APP_KEY . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/**
 * Validates the current session's age and fingerprint. Returns false (and
 * clears the session) when it has expired or looks hijacked.
 */
function session_is_valid(): bool {
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    $now = time();
    if (($_SESSION['fingerprint'] ?? '') !== session_fingerprint()) {
        do_logout();
        return false;
    }
    if ($now - (int) ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_TIMEOUT) {
        do_logout();
        return false;
    }
    if ($now - (int) ($_SESSION['logged_in_at'] ?? 0) > SESSION_ABSOLUTE_TIMEOUT) {
        do_logout();
        return false;
    }
    $_SESSION['last_seen'] = $now;
    return true;
}

/* ===========================================================================
   Current user
   =========================================================================== */

/**
 * The signed-in user row, or null. Cached per request.
 *
 * Reads the database rather than trusting session data, so deactivating an
 * account or changing its role takes effect on the very next page load.
 */
function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }

    start_session_safe();
    if (!session_is_valid()) {
        return $cache = null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, role, full_name, email, church_id, is_active, must_change_password
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        do_logout();
        return $cache = null;
    }
    return $cache = $user;
}

function admin_logged_in(): bool {
    return current_user() !== null;
}

function user_can(string $capability, ?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) return false;
    return in_array($capability, ROLE_CAPABILITIES[$user['role']] ?? [], true);
}

/** True when the signed-in user is a coordinator for this church. */
function owns_church(?int $churchId, ?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user || !$churchId) return false;
    return (int) $user['church_id'] === (int) $churchId;
}

/* ===========================================================================
   Gates
   =========================================================================== */

/** Where a user belongs after signing in, based on their role. */
function home_for_role(?string $role): string {
    return is_staff_role($role)
        ? rtrim(SITE_URL, '/') . '/admin/index.php'
        : rtrim(SITE_URL, '/') . '/portal/index.php';
}

/** Requires any signed-in account. Sends guests to the login page. */
function require_login(): array {
    $user = current_user();
    if (!$user) {
        $target = rtrim(SITE_URL, '/') . '/admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $target);
        exit;
    }
    // A freshly issued account must set its own password before doing anything.
    if ($user['must_change_password'] && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'change-password.php') {
        header('Location: ' . rtrim(SITE_URL, '/') . '/admin/change-password.php?forced=1');
        exit;
    }
    return $user;
}

/** Requires a specific capability, otherwise 403. */
function require_capability(string $capability): array {
    $user = require_login();
    if (!user_can($capability, $user)) {
        deny_access();
    }
    return $user;
}

/** Requires one of the given roles, otherwise 403. */
function require_role(array $roles): array {
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        deny_access();
    }
    return $user;
}

/** Requires a staff account (the /admin area). */
function require_staff(): array {
    return require_role(STAFF_ROLES);
}

/**
 * The 403 page. Rendered standalone rather than through the admin shell — the
 * shell's sidebar is built from capabilities the visitor has just been refused,
 * so drawing it here would be both pointless and confusing.
 */
function deny_access(): void {
    http_response_code(403);
    $user = current_user();
    $back = $user ? home_for_role($user['role']) : rtrim(SITE_URL, '/') . '/';
    $css  = htmlspecialchars(rtrim(SITE_URL, '/'), ENT_QUOTES);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow">'
       . '<title>Not permitted</title>'
       . '<link rel="stylesheet" href="' . $css . '/assets/css/style.css">'
       . '<link rel="stylesheet" href="' . $css . '/assets/css/admin.css">'
       . '</head><body class="denied"><div class="denied-shell"><div class="panel">'
       . '<h2>You do not have access to that page</h2>'
       . '<p class="hint">Your account role does not include this area. If you think it should, '
       . 'ask a Super Admin to update your role.</p>'
       . '<p><a class="btn" href="' . htmlspecialchars($back, ENT_QUOTES) . '">Back to your dashboard</a></p>'
       . '</div></div></body></html>';
    exit;
}

/* ===========================================================================
   Login, throttling, logout
   =========================================================================== */

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_WINDOW = 900;  // 15 minutes

function client_ip_hash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', APP_KEY . '|' . $ip);
}

/** Failed attempts for this username+IP inside the lockout window. */
function login_attempts_recent(string $username): int {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ? SECOND)
           AND (identifier = ? OR ip_hash = ?)'
    );
    $stmt->execute([LOGIN_LOCKOUT_WINDOW, $username, client_ip_hash()]);
    return (int) $stmt->fetchColumn();
}

function login_is_locked(string $username): bool {
    return login_attempts_recent($username) >= MAX_LOGIN_ATTEMPTS;
}

function record_login_attempt(string $username, bool $success): void {
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (identifier, ip_hash, success) VALUES (?, ?, ?)'
    );
    $stmt->execute([mb_substr($username, 0, 190), client_ip_hash(), $success ? 1 : 0]);

    if ($success) {
        // A clean login clears the slate for this account and address.
        $clear = db()->prepare('DELETE FROM login_attempts WHERE success = 0 AND (identifier = ? OR ip_hash = ?)');
        $clear->execute([$username, client_ip_hash()]);
    }

    // Opportunistic cleanup so the table cannot grow without bound.
    if (random_int(1, 50) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)');
    }
}

/**
 * Verifies credentials and starts an authenticated session.
 * Returns the user row on success, or null. Throttling is the caller's job
 * (see login_is_locked) so it can show a useful message.
 */
function attempt_login(string $username, string $password): ?array {
    $stmt = db()->prepare(
        'SELECT id, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Always run a hash comparison, even for an unknown username, so response
    // time does not reveal whether the account exists.
    $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
    $ok   = password_verify($password, $hash);

    if (!$user || !$ok || !$user['is_active']) {
        record_login_attempt($username, false);
        return null;
    }

    // Transparently upgrade older hashes on successful login.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    start_session_safe();
    session_regenerate_id(true);
    $_SESSION['admin_id']       = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['logged_in_at']   = time();
    $_SESSION['last_seen']      = time();
    $_SESSION['fingerprint']    = session_fingerprint();

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    record_login_attempt($username, true);

    return $user;
}

function do_logout(): void {
    start_session_safe();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ===========================================================================
   CSRF
   =========================================================================== */

function csrf_token(): string {
    start_session_safe();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool {
    start_session_safe();
    return !empty($_SESSION['csrf']) && $token !== null && hash_equals($_SESSION['csrf'], $token);
}

/** Hidden CSRF input for forms. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Rejects the request outright when the CSRF token is missing or stale. */
function require_csrf(): void {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Your session expired. Please go back, refresh the page, and try again.');
    }
}

/* ===========================================================================
   Audit trail
   =========================================================================== */

/** Records a change made by a signed-in user. Never throws — logging must not break a save. */
function audit(string $action, ?string $entityType = null, ?int $entityId = null, ?string $summary = null): void {
    try {
        $user = current_user();
        $stmt = db()->prepare(
            'INSERT INTO audit_log (user_id, username, action, entity_type, entity_id, summary, ip_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'] ?? null,
            $user['username'] ?? null,
            $action,
            $entityType,
            $entityId,
            $summary !== null ? mb_substr($summary, 0, 255) : null,
            client_ip_hash(),
        ]);
    } catch (Throwable $e) {
        error_log('[Seedlings audit] ' . $e->getMessage());
    }
}
