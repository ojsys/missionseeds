<?php
/**
 * Growth Stories and the media library.
 *
 * Church coordinators write stories that land in a `pending` queue; staff with
 * the publish_stories capability approve them. A coordinator can never set the
 * church a story belongs to or its published state — the model decides both
 * from the signed-in user, so a tampered form field changes nothing.
 */

const STORY_CATEGORIES = [
    'update'       => 'Project update',
    'church_story' => 'Church story',
    'training'     => 'Training activity',
    'planting'     => 'Tree planting',
    'meeting'      => 'Cooperative meeting',
    'testimony'    => 'Participant testimony',
];

const STORY_STATUSES = [
    'draft'     => 'Draft',
    'pending'   => 'Awaiting review',
    'published' => 'Published',
    'rejected'  => 'Needs changes',
];

/* ===========================================================================
   Reading
   =========================================================================== */

/**
 * @param array $filters category, church_id, status, limit, offset, search
 *                       Omit 'status' for the public feed (published only).
 */
function get_stories(array $filters = []): array {
    $sql = 'SELECT s.*, c.name AS church_name, c.slug AS church_slug, m.path AS cover_path, m.alt_text AS cover_alt
            FROM stories s
            LEFT JOIN churches c ON c.id = s.church_id
            LEFT JOIN media m    ON m.id = s.cover_media_id
            WHERE 1=1';
    $params = [];

    if (!empty($filters['status'])) {
        if (is_array($filters['status'])) {
            $sql .= ' AND s.status IN (' . implode(',', array_fill(0, count($filters['status']), '?')) . ')';
            array_push($params, ...$filters['status']);
        } else {
            $sql .= ' AND s.status = ?';
            $params[] = $filters['status'];
        }
    } else {
        $sql .= " AND s.status = 'published' AND s.published_at IS NOT NULL AND s.published_at <= NOW()";
    }

    if (!empty($filters['category'])) {
        $sql .= ' AND s.category = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['church_id'])) {
        $sql .= ' AND s.church_id = ?';
        $params[] = (int) $filters['church_id'];
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (s.title LIKE ? OR s.excerpt LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY COALESCE(s.published_at, s.updated_at) DESC, s.id DESC';

    // LIMIT/OFFSET are cast to int and interpolated: MySQL will not accept them
    // as bound parameters when emulated prepares are off.
    $limit  = isset($filters['limit'])  ? max(1, (int) $filters['limit'])  : null;
    $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
    if ($limit !== null) {
        $sql .= " LIMIT $limit OFFSET $offset";
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_stories(array $filters = []): int {
    $sql = 'SELECT COUNT(*) FROM stories s WHERE 1=1';
    $params = [];
    if (!empty($filters['status'])) {
        $sql .= ' AND s.status = ?';
        $params[] = $filters['status'];
    } else {
        $sql .= " AND s.status = 'published' AND s.published_at IS NOT NULL AND s.published_at <= NOW()";
    }
    if (!empty($filters['category'])) {
        $sql .= ' AND s.category = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['church_id'])) {
        $sql .= ' AND s.church_id = ?';
        $params[] = (int) $filters['church_id'];
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function get_story(int $id): ?array {
    $stmt = db()->prepare(
        'SELECT s.*, c.name AS church_name, c.slug AS church_slug, m.path AS cover_path, m.alt_text AS cover_alt
         FROM stories s
         LEFT JOIN churches c ON c.id = s.church_id
         LEFT JOIN media m    ON m.id = s.cover_media_id
         WHERE s.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_story_by_slug(string $slug, bool $allowUnpublished = false): ?array {
    $sql = 'SELECT s.*, c.name AS church_name, c.slug AS church_slug, m.path AS cover_path, m.alt_text AS cover_alt
            FROM stories s
            LEFT JOIN churches c ON c.id = s.church_id
            LEFT JOIN media m    ON m.id = s.cover_media_id
            WHERE s.slug = ?';
    if (!$allowUnpublished) {
        $sql .= " AND s.status = 'published' AND s.published_at IS NOT NULL AND s.published_at <= NOW()";
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** The most recent published story for a church — shown on its profile. */
function latest_church_update(int $churchId): ?array {
    $rows = get_stories(['church_id' => $churchId, 'limit' => 1]);
    return $rows[0] ?? null;
}

function count_pending_stories(): int {
    return (int) db()->query("SELECT COUNT(*) FROM stories WHERE status = 'pending'")->fetchColumn();
}

/* ===========================================================================
   Writing
   =========================================================================== */

/**
 * Creates or updates a story.
 *
 * The caller passes the raw form data; this decides what the signed-in user is
 * actually allowed to set:
 *   - a coordinator's story is always attributed to their own church, and can
 *     only ever reach 'draft' or 'pending'
 *   - only a user with publish_stories may set 'published' or 'rejected'
 */
function save_story(array $data): int {
    $user = current_user();
    if (!$user) throw new RuntimeException('You must be signed in.');

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('A story needs a title.');
    }

    $id       = !empty($data['id']) ? (int) $data['id'] : null;
    $existing = $id ? get_story($id) : null;
    if ($id && !$existing) {
        throw new RuntimeException('Story not found.');
    }
    if ($existing && !can_edit_story($existing, $user)) {
        throw new RuntimeException('You cannot edit that story.');
    }

    // --- who the story belongs to -------------------------------------------
    if ($user['role'] === 'church_coordinator') {
        $churchId = $user['church_id'] ? (int) $user['church_id'] : null;
    } else {
        $churchId = !empty($data['church_id']) ? (int) $data['church_id'] : null;
    }

    // --- what state it may be in --------------------------------------------
    $requested = $data['status'] ?? 'draft';
    if (!array_key_exists($requested, STORY_STATUSES)) {
        $requested = 'draft';
    }
    if (!user_can('publish_stories', $user) && in_array($requested, ['published', 'rejected'], true)) {
        $requested = 'pending';
    }

    $body     = trim((string) ($data['body'] ?? ''));
    $excerpt  = trim((string) ($data['excerpt'] ?? '')) ?: excerpt_from($body, 180);
    $category = array_key_exists($data['category'] ?? '', STORY_CATEGORIES) ? $data['category'] : 'update';

    $fields = [
        'title'          => $title,
        'excerpt'        => mb_substr($excerpt, 0, 400),
        'body'           => $body,
        'category'       => $category,
        'church_id'      => $churchId,
        'cover_media_id' => !empty($data['cover_media_id']) ? (int) $data['cover_media_id'] : null,
        'status'         => $requested,
    ];

    // published_at is set once, the first time a story goes live, so republishing
    // an edit does not reshuffle the feed.
    if ($requested === 'published') {
        $fields['published_at'] = $existing['published_at'] ?? date('Y-m-d H:i:s');
    }
    if (in_array($requested, ['published', 'rejected'], true) && user_can('publish_stories', $user)) {
        $fields['reviewed_by'] = $user['id'];
        $fields['reviewed_at'] = date('Y-m-d H:i:s');
        $fields['review_note'] = trim((string) ($data['review_note'] ?? '')) ?: null;
    }

    if ($id) {
        $fields['slug'] = unique_slug(slugify($data['slug'] ?? $title), 'stories', $id);
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
        db()->prepare("UPDATE stories SET $set WHERE id = :id")->execute($fields + ['id' => $id]);
        audit('update_story', 'story', $id, $title . ' → ' . $requested);
        return $id;
    }

    $fields['slug']      = unique_slug(slugify($title), 'stories');
    $fields['author_id'] = $user['id'];
    $cols = implode(', ', array_keys($fields));
    $vals = ':' . implode(', :', array_keys($fields));
    db()->prepare("INSERT INTO stories ($cols) VALUES ($vals)")->execute($fields);
    $newId = (int) db()->lastInsertId();
    audit('create_story', 'story', $newId, $title . ' → ' . $requested);
    return $newId;
}

/**
 * Ownership rule for stories. Staff who can manage stories may edit any;
 * a coordinator may edit only their own church's, and only while it is still
 * a draft, pending, or sent back for changes.
 */
function can_edit_story(array $story, ?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) return false;
    if (user_can('manage_stories', $user)) return true;
    if ($user['role'] !== 'church_coordinator') return false;
    if (!owns_church($story['church_id'] ? (int) $story['church_id'] : null, $user)) return false;
    return in_array($story['status'], ['draft', 'pending', 'rejected'], true);
}

function set_story_status(int $id, string $status, ?string $note = null): void {
    if (!array_key_exists($status, STORY_STATUSES)) {
        throw new RuntimeException('Unknown status.');
    }
    $user  = current_user();
    $story = get_story($id);
    if (!$story) throw new RuntimeException('Story not found.');

    $publishedAt = $story['published_at'];
    if ($status === 'published' && !$publishedAt) {
        $publishedAt = date('Y-m-d H:i:s');
    }

    db()->prepare(
        'UPDATE stories SET status = ?, published_at = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE id = ?'
    )->execute([$status, $publishedAt, $note ?: null, $user['id'] ?? null, $id]);

    audit('review_story', 'story', $id, $story['title'] . ' → ' . $status);
}

function delete_story(int $id): void {
    $story = get_story($id);
    if (!$story) return;
    db()->prepare('DELETE FROM story_media WHERE story_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM stories WHERE id = ?')->execute([$id]);
    audit('delete_story', 'story', $id, $story['title']);
}

/* ===========================================================================
   Media
   =========================================================================== */

function get_media(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM media WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function list_media(int $limit = 60, bool $consentedOnly = false): array {
    $sql = 'SELECT * FROM media';
    if ($consentedOnly) $sql .= ' WHERE has_consent = 1';
    $limit = max(1, min(200, $limit));
    return db()->query($sql . " ORDER BY created_at DESC, id DESC LIMIT $limit")->fetchAll();
}

/** Stores an already-validated upload path as a media record. */
function create_media(string $path, array $meta = []): int {
    $user = current_user();
    $full = BASE_PATH . '/' . ltrim($path, '/');
    $size = @getimagesize($full) ?: [null, null];

    db()->prepare(
        'INSERT INTO media (path, alt_text, caption, mime, bytes, width, height, has_consent, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $path,
        trim((string) ($meta['alt_text'] ?? '')) ?: null,
        trim((string) ($meta['caption'] ?? '')) ?: null,
        $size['mime'] ?? null,
        is_file($full) ? filesize($full) : null,
        $size[0] ?: null,
        $size[1] ?: null,
        !empty($meta['has_consent']) ? 1 : 0,
        $user['id'] ?? null,
    ]);
    $id = (int) db()->lastInsertId();
    audit('upload_media', 'media', $id, basename($path));
    return $id;
}

function update_media(int $id, array $meta): void {
    db()->prepare('UPDATE media SET alt_text = ?, caption = ?, has_consent = ? WHERE id = ?')->execute([
        trim((string) ($meta['alt_text'] ?? '')) ?: null,
        trim((string) ($meta['caption'] ?? '')) ?: null,
        !empty($meta['has_consent']) ? 1 : 0,
        $id,
    ]);
    audit('update_media', 'media', $id);
}

function delete_media(int $id): void {
    $item = get_media($id);
    if (!$item) return;
    db()->prepare('DELETE FROM story_media WHERE media_id = ?')->execute([$id]);
    db()->prepare('UPDATE stories SET cover_media_id = NULL WHERE cover_media_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    delete_upload($item['path']);
    audit('delete_media', 'media', $id, basename($item['path']));
}

/** Gallery images attached to a story, in order. */
function story_gallery(int $storyId): array {
    $stmt = db()->prepare(
        'SELECT m.* FROM story_media sm
         JOIN media m ON m.id = sm.media_id
         WHERE sm.story_id = ? ORDER BY sm.sort_order ASC, sm.id ASC'
    );
    $stmt->execute([$storyId]);
    return $stmt->fetchAll();
}

function set_story_gallery(int $storyId, array $mediaIds): void {
    db()->prepare('DELETE FROM story_media WHERE story_id = ?')->execute([$storyId]);
    $stmt = db()->prepare('INSERT INTO story_media (story_id, media_id, sort_order) VALUES (?, ?, ?)');
    $order = 0;
    foreach (array_unique(array_map('intval', $mediaIds)) as $mediaId) {
        if ($mediaId > 0) $stmt->execute([$storyId, $mediaId, $order++]);
    }
}
