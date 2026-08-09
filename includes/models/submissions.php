<?php
/**
 * Contact, expression-of-interest, and partnership enquiries.
 *
 * These rows contain personal contact details supplied voluntarily by the
 * enquirer. They are only ever read by staff with the view_submissions
 * capability and are never rendered on a public page.
 */

const SUBMISSION_TYPES = [
    'contact'     => 'General enquiry',
    'interest'    => 'Church expression of interest',
    'partnership' => 'Partnership enquiry',
];

const SUBMISSION_STATUSES = [
    'new'       => 'New',
    'read'      => 'Read',
    'responded' => 'Responded',
    'archived'  => 'Archived',
];

/** Simple abuse brake: how many enquiries this address has sent in an hour. */
function submissions_from_ip_recently(int $minutes = 60): int {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM submissions WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([client_ip_hash(), $minutes]);
    return (int) $stmt->fetchColumn();
}

/**
 * Validates and stores a public enquiry.
 * @return array{ok: bool, errors: string[], id: ?int}
 */
function create_submission(array $data): array {
    $errors = [];

    $type = array_key_exists($data['type'] ?? '', SUBMISSION_TYPES) ? $data['type'] : 'contact';
    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));

    if ($name === '' || mb_strlen($name) > 160) {
        $errors[] = 'Please tell us your name.';
    }
    if ($email === '' && $phone === '') {
        $errors[] = 'Please give us either an email address or a phone number so we can reply.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address does not look right.';
    }
    if (mb_strlen($message) < 10) {
        $errors[] = 'Please tell us a little more — at least a sentence.';
    }
    if (mb_strlen($message) > 5000) {
        $errors[] = 'That message is too long. Please keep it under 5000 characters.';
    }
    if (submissions_from_ip_recently() >= 5) {
        $errors[] = 'You have sent several messages already. Please give us a chance to reply before sending more.';
    }

    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'id' => null];
    }

    db()->prepare(
        'INSERT INTO submissions (type, name, email, phone, organisation, church_name, community, message, ip_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $type,
        $name,
        $email ?: null,
        $phone ?: null,
        trim((string) ($data['organisation'] ?? '')) ?: null,
        trim((string) ($data['church_name'] ?? '')) ?: null,
        trim((string) ($data['community'] ?? '')) ?: null,
        $message,
        client_ip_hash(),
    ]);

    return ['ok' => true, 'errors' => [], 'id' => (int) db()->lastInsertId()];
}

/* ===========================================================================
   Notifications
   =========================================================================== */

/** Addresses to notify, falling back to the site contact address. */
function enquiry_notify_recipients(): array {
    $raw = trim(get_setting('notify_enquiries_to'));
    if ($raw === '') {
        $raw = trim(get_setting('contact_email'));
    }
    $out = [];
    foreach (preg_split('/[,;\s]+/', $raw) ?: [] as $addr) {
        $addr = trim($addr);
        if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            $out[] = $addr;
        }
    }
    return array_values(array_unique($out));
}

function enquiry_notifications_enabled(): bool {
    return get_setting('notify_enquiries', '0') === '1' && enquiry_notify_recipients() !== [];
}

/**
 * Emails the team about a new enquiry.
 *
 * Called after the response has been sent to the visitor (see pages/contact.php),
 * so a slow or unreachable mail server never makes the contact form feel broken.
 * Never throws — a failed notification must not affect the enquirer.
 */
function notify_new_submission(int $submissionId): void {
    try {
        if (!enquiry_notifications_enabled()) {
            return;
        }
        $s = get_submission($submissionId);
        if (!$s) return;

        foreach (enquiry_notify_recipients() as $to) {
            send_email($to, 'new-enquiry', [
                'enquiryName'  => $s['name'],
                'enquiryType'  => SUBMISSION_TYPES[$s['type']] ?? 'Enquiry',
                // Truncated on purpose: this is a nudge to go and read it, not a copy.
                'excerpt'      => excerpt_from($s['message'], 400),
                'churchName'   => $s['church_name'],
                'community'    => $s['community'],
                'organisation' => $s['organisation'],
                'receivedAt'   => format_date($s['created_at'], 'j F Y, H:i'),
                'inboxUrl'     => url('/admin/submissions.php?status=new'),
            ]);
        }
    } catch (Throwable $e) {
        error_log('[Seedlings enquiry notify] ' . $e->getMessage());
    }
}

function get_submissions(?string $status = null, ?string $type = null, int $limit = 100): array {
    $sql    = 'SELECT * FROM submissions WHERE 1=1';
    $params = [];
    if ($status && array_key_exists($status, SUBMISSION_STATUSES)) {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($type && array_key_exists($type, SUBMISSION_TYPES)) {
        $sql .= ' AND type = ?';
        $params[] = $type;
    }
    $limit = max(1, min(500, $limit));
    $stmt  = db()->prepare($sql . " ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_submission(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM submissions WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function count_new_submissions(): int {
    return (int) db()->query("SELECT COUNT(*) FROM submissions WHERE status = 'new'")->fetchColumn();
}

function update_submission(int $id, string $status, ?string $note = null): void {
    if (!array_key_exists($status, SUBMISSION_STATUSES)) {
        throw new RuntimeException('Unknown status.');
    }
    $user = current_user();
    db()->prepare(
        'UPDATE submissions SET status = ?, internal_note = ?, handled_by = ?, handled_at = NOW() WHERE id = ?'
    )->execute([$status, $note ?: null, $user['id'] ?? null, $id]);
    // The summary deliberately records the id only — never the enquirer's details.
    audit('update_submission', 'submission', $id, 'status → ' . $status);
}

function delete_submission(int $id): void {
    db()->prepare('DELETE FROM submissions WHERE id = ?')->execute([$id]);
    audit('delete_submission', 'submission', $id);
}
