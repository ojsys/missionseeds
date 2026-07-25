<?php
require_once __DIR__ . '/db.php';

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

function admin_logged_in(): bool {
    start_session_safe();
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void {
    start_session_safe();
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . rtrim(SITE_URL, '/') . '/admin/login.php');
        exit;
    }
}

function attempt_login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT id, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        start_session_safe();
        session_regenerate_id(true);
        $_SESSION['admin_id']       = $user['id'];
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

function do_logout(): void {
    start_session_safe();
    $_SESSION = [];
    session_destroy();
}

/** CSRF token helpers */
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
