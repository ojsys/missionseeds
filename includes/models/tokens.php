<?php
/**
 * Single-use invitation and password-reset tokens.
 *
 * Only a SHA-256 hash of the token is stored, so a database dump does not let
 * anyone take over accounts. The raw token exists once, in the email (or in the
 * fallback link an admin copies), and never again.
 */

const INVITE_TTL_HOURS = 168;  // 7 days — an invite may sit unread over a weekend
const RESET_TTL_HOURS  = 2;    // short: a reset is acted on immediately or not at all

/**
 * Issues a token for a user, invalidating any earlier one of the same purpose.
 * @return string the raw token — show or email it, then forget it
 */
function create_token(int $userId, string $purpose = 'reset'): string {
    if (!schema_has_column('password_resets', 'purpose')) {
        throw new RuntimeException(
            'The email/invitation update has not been applied to the database yet. '
            . 'Import migrations/003_email.sql, then try again.'
        );
    }
    $purpose = $purpose === 'invite' ? 'invite' : 'reset';
    $ttl     = $purpose === 'invite' ? INVITE_TTL_HOURS : RESET_TTL_HOURS;

    // One live token per purpose: issuing a new one must retire the old.
    db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND purpose = ?')
        ->execute([$userId, $purpose]);

    $raw = bin2hex(random_bytes(32));
    db()->prepare(
        'INSERT INTO password_resets (user_id, purpose, token_hash, expires_at, requested_ip)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?)'
    )->execute([$userId, $purpose, hash('sha256', $raw), $ttl, client_ip_hash()]);

    // Opportunistic cleanup so the table cannot grow without bound.
    if (random_int(1, 20) === 1) {
        db()->exec('DELETE FROM password_resets WHERE expires_at < (NOW() - INTERVAL 30 DAY)');
    }
    return $raw;
}

/** The absolute URL a token holder should open. */
function token_url(string $rawToken): string {
    return url('/admin/set-password.php') . '?token=' . urlencode($rawToken);
}

/**
 * Looks up a live token and its user.
 * @return array|null ['token' => row, 'user' => row] or null when invalid,
 *                    expired, already used, or the account is deactivated
 */
function find_valid_token(?string $rawToken): ?array {
    if (!$rawToken || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT pr.*, u.id AS uid, u.username, u.full_name, u.email, u.role, u.is_active
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $rawToken)]);
    $row = $stmt->fetch();
    if (!$row || !$row['is_active']) {
        return null;
    }
    return [
        'token' => ['id' => $row['id'], 'purpose' => $row['purpose'], 'expires_at' => $row['expires_at']],
        'user'  => [
            'id'        => (int) $row['uid'],
            'username'  => $row['username'],
            'full_name' => $row['full_name'],
            'email'     => $row['email'],
            'role'      => $row['role'],
        ],
    ];
}

/**
 * Completes a token: sets the password, retires every token for that user, and
 * clears the must-change flag. Wrapped in a transaction so a half-applied
 * change cannot leave someone locked out.
 */
function consume_token(int $tokenId, int $userId, string $newPassword): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW()
             WHERE id = ?'
        )->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$tokenId]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
        // Any lockout from failed guesses is irrelevant now.
        $pdo->prepare('DELETE FROM login_attempts WHERE identifier = (SELECT username FROM users WHERE id = ?)')
            ->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** "in 7 days" / "in 2 hours" — for email copy. */
function token_expiry_label(string $purpose): string {
    return $purpose === 'invite' ? 'in 7 days' : 'in 2 hours';
}

/**
 * Password rules, in one place so the invite screen, the reset screen, and the
 * change-password screen cannot drift apart.
 * @return string[] list of problems, empty when acceptable
 */
function password_problems(string $password, string $confirm, array $user = []): array {
    $problems = [];
    if (mb_strlen($password) < 10) {
        $problems[] = 'Use at least 10 characters.';
    }
    if ($password !== $confirm) {
        $problems[] = 'The two passwords do not match.';
    }
    $lower = mb_strtolower($password);
    foreach (['password', '12345678', 'qwerty', 'seedlings', 'missionseedlings'] as $common) {
        if ($lower === $common || $lower === $common . '1') {
            $problems[] = 'That password is too easy to guess. Please choose something else.';
            break;
        }
    }
    foreach (['username', 'email'] as $field) {
        $value = trim((string) ($user[$field] ?? ''));
        if ($value !== '' && $lower === mb_strtolower($value)) {
            $problems[] = 'Your password cannot be the same as your ' . $field . '.';
        }
    }
    return $problems;
}
