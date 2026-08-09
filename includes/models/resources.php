<?php
/**
 * Resource Hub and KoboToolbox form links.
 *
 * Visibility is resolved server-side from the signed-in user's capabilities.
 * A restricted resource is never rendered into the HTML for someone who may
 * not see it — it is filtered out of the query, not hidden with CSS.
 */

const RESOURCE_VISIBILITY = [
    'public'      => 'Public — anyone can see this',
    'coordinator' => 'Church coordinators and staff',
    'staff'       => 'Project staff only',
];

const KOBO_PURPOSES = [
    'registration'    => 'Participant registration',
    'monthly_report'  => 'Monthly cooperative report',
    'tree_monitoring' => 'Tree monitoring',
    'other'           => 'Other',
];

/** Visibility levels the signed-in user (or guest) is allowed to see. */
function visible_resource_levels(?array $user = null): array {
    $user   = $user ?? current_user();
    $levels = ['public'];
    if (user_can('view_resources_coordinator', $user)) $levels[] = 'coordinator';
    if (user_can('view_resources_staff', $user))       $levels[] = 'staff';
    return $levels;
}

/**
 * Resources the current viewer may see.
 * Pass $allLevels = true only from admin screens that manage the library.
 */
function get_hub_resources(bool $allLevels = false, ?array $user = null): array {
    if ($allLevels) {
        return db()->query('SELECT * FROM resources ORDER BY category ASC, sort_order ASC, id ASC')->fetchAll();
    }
    $levels = visible_resource_levels($user);
    $in     = implode(',', array_fill(0, count($levels), '?'));
    $stmt   = db()->prepare(
        "SELECT * FROM resources
         WHERE is_visible = 1 AND visibility IN ($in)
         ORDER BY category ASC, sort_order ASC, id ASC"
    );
    $stmt->execute($levels);
    return $stmt->fetchAll();
}

/** Same as get_hub_resources(), grouped by category for rendering. */
function resources_by_category(bool $allLevels = false, ?array $user = null): array {
    $out = [];
    foreach (get_hub_resources($allLevels, $user) as $r) {
        $out[$r['category']][] = $r;
    }
    return $out;
}

function get_resource(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM resources WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function save_resource(array $data): int {
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('A resource needs a title.');
    }

    $type = ($data['resource_type'] ?? 'link') === 'file' ? 'file' : 'link';
    $url  = trim((string) ($data['url'] ?? ''));
    if ($type === 'link') {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Please give a valid link starting with https://');
        }
        if (!preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('Links must start with http:// or https://');
        }
    }

    $user   = current_user();
    $fields = [
        'title'         => $title,
        'description'   => trim((string) ($data['description'] ?? '')) ?: null,
        'resource_type' => $type,
        'url'           => $type === 'link' ? $url : null,
        'category'      => trim((string) ($data['category'] ?? '')) ?: 'General',
        'visibility'    => array_key_exists($data['visibility'] ?? '', RESOURCE_VISIBILITY) ? $data['visibility'] : 'public',
        'icon_key'      => array_key_exists($data['icon_key'] ?? '', ICON_LIBRARY) ? $data['icon_key'] : 'book',
        'is_visible'    => !empty($data['is_visible']) ? 1 : 0,
    ];
    if (!empty($data['file_path'])) {
        $fields['file_path']  = $data['file_path'];
        $fields['file_bytes'] = $data['file_bytes'] ?? null;
    }

    if (!empty($data['id'])) {
        $id       = (int) $data['id'];
        $existing = get_resource($id);
        if (!$existing) throw new RuntimeException('Resource not found.');
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
        db()->prepare("UPDATE resources SET $set WHERE id = :id")->execute($fields + ['id' => $id]);
        if (!empty($data['file_path']) && $data['file_path'] !== $existing['file_path']) {
            delete_upload($existing['file_path']);
        }
        audit('update_resource', 'resource', $id, $title);
        return $id;
    }

    if ($type === 'file' && empty($fields['file_path'])) {
        throw new RuntimeException('Please choose a file to upload.');
    }
    $fields['created_by'] = $user['id'] ?? null;
    $fields['sort_order'] = 1 + (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM resources')->fetchColumn();
    $cols = implode(', ', array_keys($fields));
    $vals = ':' . implode(', :', array_keys($fields));
    db()->prepare("INSERT INTO resources ($cols) VALUES ($vals)")->execute($fields);
    $newId = (int) db()->lastInsertId();
    audit('create_resource', 'resource', $newId, $title);
    return $newId;
}

function delete_resource(int $id): void {
    $r = get_resource($id);
    if (!$r) return;
    db()->prepare('DELETE FROM resources WHERE id = ?')->execute([$id]);
    delete_upload($r['file_path']);
    audit('delete_resource', 'resource', $id, $r['title']);
}

/* ===========================================================================
   KoboToolbox forms
   =========================================================================== */

/** Kobo forms the current viewer may open. Guests get none — all are restricted. */
function get_kobo_forms(bool $allLevels = false, ?array $user = null): array {
    if ($allLevels) {
        return db()->query('SELECT * FROM kobo_forms ORDER BY sort_order ASC, id ASC')->fetchAll();
    }
    $levels = [];
    if (user_can('view_resources_coordinator', $user)) $levels[] = 'coordinator';
    if (user_can('view_resources_staff', $user))       $levels[] = 'staff';
    if (!$levels) return [];

    $in   = implode(',', array_fill(0, count($levels), '?'));
    $stmt = db()->prepare(
        "SELECT * FROM kobo_forms WHERE is_active = 1 AND visibility IN ($in) ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute($levels);
    return $stmt->fetchAll();
}

function get_kobo_form(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM kobo_forms WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function save_kobo_form(array $data): int {
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('The form needs a name.');
    }
    $enketo = trim((string) ($data['enketo_url'] ?? ''));
    if ($enketo !== '' && !filter_var($enketo, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('That form link is not a valid URL.');
    }

    $fields = [
        'name'        => $name,
        'description' => trim((string) ($data['description'] ?? '')) ?: null,
        'purpose'     => array_key_exists($data['purpose'] ?? '', KOBO_PURPOSES) ? $data['purpose'] : 'other',
        'form_uid'    => trim((string) ($data['form_uid'] ?? '')) ?: null,
        'enketo_url'  => $enketo ?: null,
        'visibility'  => ($data['visibility'] ?? 'staff') === 'coordinator' ? 'coordinator' : 'staff',
        'is_active'   => !empty($data['is_active']) ? 1 : 0,
    ];

    if (!empty($data['id'])) {
        $id  = (int) $data['id'];
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
        db()->prepare("UPDATE kobo_forms SET $set WHERE id = :id")->execute($fields + ['id' => $id]);
        audit('update_kobo_form', 'kobo_form', $id, $name);
        return $id;
    }

    $fields['sort_order'] = 1 + (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM kobo_forms')->fetchColumn();
    $cols = implode(', ', array_keys($fields));
    $vals = ':' . implode(', :', array_keys($fields));
    db()->prepare("INSERT INTO kobo_forms ($cols) VALUES ($vals)")->execute($fields);
    $newId = (int) db()->lastInsertId();
    audit('create_kobo_form', 'kobo_form', $newId, $name);
    return $newId;
}

function delete_kobo_form(int $id): void {
    db()->prepare('DELETE FROM kobo_forms WHERE id = ?')->execute([$id]);
    audit('delete_kobo_form', 'kobo_form', $id);
}
