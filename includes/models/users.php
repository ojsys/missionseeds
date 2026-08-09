<?php
/**
 * Account management. Only a Super Admin reaches these functions.
 *
 * Accounts are invite-only: an admin creates one with a generated temporary
 * password, and the account holder must set their own on first sign-in. There
 * is no public registration anywhere in the application.
 */

function get_users(bool $includeInactive = true): array {
    $sql = 'SELECT u.*, c.name AS church_name
            FROM users u
            LEFT JOIN churches c ON c.id = u.church_id';
    if (!$includeInactive) $sql .= ' WHERE u.is_active = 1';
    return db()->query($sql . ' ORDER BY u.is_active DESC, u.role ASC, u.username ASC')->fetchAll();
}

function get_user(int $id): ?array {
    $stmt = db()->prepare(
        'SELECT u.*, c.name AS church_name FROM users u
         LEFT JOIN churches c ON c.id = u.church_id WHERE u.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** A readable temporary password: easy to relay by phone, still ~62 bits. */
function generate_temp_password(): string {
    $words = ['coffee', 'harvest', 'seedling', 'plateau', 'cluster', 'orchard', 'sunrise',
              'terrace', 'lantern', 'compass', 'meadow', 'summit', 'cascade', 'thicket'];
    return $words[random_int(0, count($words) - 1)] . '-'
         . $words[random_int(0, count($words) - 1)] . '-'
         . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Creates an account.
 * @return array{id:int, temp_password:string}
 */
function create_user(array $data): array {
    $username = strtolower(trim((string) ($data['username'] ?? '')));
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
        throw new RuntimeException('Username must be 3–60 characters: letters, numbers, dot, dash, or underscore.');
    }
    if (!array_key_exists($data['role'] ?? '', ROLES)) {
        throw new RuntimeException('Please choose a role.');
    }
    $role     = $data['role'];
    $email    = trim((string) ($data['email'] ?? ''));
    $churchId = !empty($data['church_id']) ? (int) $data['church_id'] : null;

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('That email address does not look right.');
    }
    // A coordinator with no church could see nothing and submit nothing.
    if ($role === 'church_coordinator' && !$churchId) {
        throw new RuntimeException('A church coordinator must be linked to a church.');
    }
    if ($role !== 'church_coordinator') {
        $churchId = null;
    }

    $exists = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $exists->execute([$username]);
    if ((int) $exists->fetchColumn() > 0) {
        throw new RuntimeException('That username is already taken.');
    }
    if ($email !== '') {
        $e = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $e->execute([$email]);
        if ((int) $e->fetchColumn() > 0) {
            throw new RuntimeException('That email address is already on another account.');
        }
    }

    $temp    = generate_temp_password();
    $creator = current_user();

    db()->prepare(
        'INSERT INTO users (username, password_hash, role, full_name, email, church_id, is_active,
                            must_change_password, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?)'
    )->execute([
        $username,
        password_hash($temp, PASSWORD_DEFAULT),
        $role,
        trim((string) ($data['full_name'] ?? '')) ?: null,
        $email ?: null,
        $churchId,
        $creator['id'] ?? null,
    ]);

    $id = (int) db()->lastInsertId();
    audit('create_user', 'user', $id, $username . ' (' . role_label($role) . ')');
    return ['id' => $id, 'temp_password' => $temp];
}

/** Updates profile fields and role. Never touches the password. */
function update_user(int $id, array $data): void {
    $target = get_user($id);
    if (!$target) throw new RuntimeException('Account not found.');

    if (!array_key_exists($data['role'] ?? '', ROLES)) {
        throw new RuntimeException('Please choose a role.');
    }
    $role     = $data['role'];
    $email    = trim((string) ($data['email'] ?? ''));
    $churchId = !empty($data['church_id']) ? (int) $data['church_id'] : null;
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('That email address does not look right.');
    }
    if ($role === 'church_coordinator' && !$churchId) {
        throw new RuntimeException('A church coordinator must be linked to a church.');
    }
    if ($role !== 'church_coordinator') {
        $churchId = null;
    }

    // Guard rails against locking the project out of its own site.
    $me = current_user();
    if ($me && (int) $me['id'] === $id) {
        if ($role !== 'super_admin') {
            throw new RuntimeException('You cannot change your own role. Ask another Super Admin.');
        }
        if (!$isActive) {
            throw new RuntimeException('You cannot deactivate your own account.');
        }
    }
    if ($target['role'] === 'super_admin' && ($role !== 'super_admin' || !$isActive)) {
        if (count_active_super_admins() <= 1) {
            throw new RuntimeException('This is the last active Super Admin. Promote someone else first.');
        }
    }

    if ($email !== '') {
        $e = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
        $e->execute([$email, $id]);
        if ((int) $e->fetchColumn() > 0) {
            throw new RuntimeException('That email address is already on another account.');
        }
    }

    db()->prepare(
        'UPDATE users SET role = ?, full_name = ?, email = ?, church_id = ?, is_active = ? WHERE id = ?'
    )->execute([
        $role,
        trim((string) ($data['full_name'] ?? '')) ?: null,
        $email ?: null,
        $churchId,
        $isActive,
        $id,
    ]);
    audit('update_user', 'user', $id, $target['username'] . ' → ' . role_label($role) . ($isActive ? '' : ', deactivated'));
}

function count_active_super_admins(): int {
    return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1")->fetchColumn();
}

/** Issues a new temporary password and forces a change at next sign-in. */
function reset_user_password(int $id): string {
    $target = get_user($id);
    if (!$target) throw new RuntimeException('Account not found.');

    $temp = generate_temp_password();
    db()->prepare(
        'UPDATE users SET password_hash = ?, must_change_password = 1, password_changed_at = NULL WHERE id = ?'
    )->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);

    // Any outstanding reset links for this account stop working.
    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$id]);
    audit('reset_password', 'user', $id, $target['username']);
    return $temp;
}

function delete_user(int $id): void {
    $target = get_user($id);
    if (!$target) return;

    $me = current_user();
    if ($me && (int) $me['id'] === $id) {
        throw new RuntimeException('You cannot delete your own account.');
    }
    if ($target['role'] === 'super_admin' && count_active_super_admins() <= 1) {
        throw new RuntimeException('This is the last active Super Admin.');
    }

    // Stories keep their text and history; they just lose the author link.
    db()->prepare('UPDATE stories SET author_id = NULL WHERE author_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    audit('delete_user', 'user', $id, $target['username']);
}

/* ===========================================================================
   Audit log reading
   =========================================================================== */

function get_audit_log(int $limit = 200, ?string $entityType = null): array {
    $sql    = 'SELECT * FROM audit_log WHERE 1=1';
    $params = [];
    if ($entityType) {
        $sql .= ' AND entity_type = ?';
        $params[] = $entityType;
    }
    $limit = max(1, min(1000, $limit));
    $stmt  = db()->prepare($sql . " ORDER BY created_at DESC, id DESC LIMIT $limit");
    $stmt->execute($params);
    return $stmt->fetchAll();
}
