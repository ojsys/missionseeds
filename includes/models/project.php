<?php
/**
 * Project data: participating churches, the seven-stage pathway, milestones,
 * and impact indicators.
 *
 * Read functions take an $includeUnpublished flag that defaults to false, so
 * the safe behaviour is the default and exposing draft data has to be asked
 * for explicitly.
 */

/* ===========================================================================
   Pathway stages
   =========================================================================== */

const STAGE_STATUSES = [
    'not_started' => 'Not started',
    'in_progress' => 'In progress',
    'complete'    => 'Complete',
];

function get_pathway_stages(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT * FROM pathway_stages ORDER BY sort_order ASC, id ASC')->fetchAll();
    }
    return $cache;
}

function get_pathway_stage(string $stageKey): ?array {
    foreach (get_pathway_stages() as $stage) {
        if ($stage['stage_key'] === $stageKey) return $stage;
    }
    return null;
}

/** stage_key => name, for dropdowns and labels. */
function stage_options(): array {
    $out = [];
    foreach (get_pathway_stages() as $stage) {
        $out[$stage['stage_key']] = $stage['name'];
    }
    return $out;
}

function stage_name(?string $stageKey): string {
    return stage_options()[$stageKey ?? ''] ?? 'Not set';
}

function update_pathway_stage(string $stageKey, array $data): void {
    $stmt = db()->prepare(
        'UPDATE pathway_stages SET name = ?, short_description = ?, icon_key = ?, status = ?, status_note = ?
         WHERE stage_key = ?'
    );
    $stmt->execute([
        trim($data['name']),
        trim((string) $data['short_description']),
        array_key_exists($data['icon_key'], ICON_LIBRARY) ? $data['icon_key'] : 'seedling',
        array_key_exists($data['status'], STAGE_STATUSES) ? $data['status'] : 'not_started',
        trim((string) $data['status_note']) ?: null,
        $stageKey,
    ]);
    audit('update_stage', 'pathway_stage', null, $stageKey);
}

/** The furthest stage that is in progress or complete — the project's headline phase. */
function current_phase_name(): string {
    $current = null;
    foreach (get_pathway_stages() as $stage) {
        if ($stage['status'] !== 'not_started') {
            $current = $stage;
        }
    }
    return $current['name'] ?? (get_pathway_stages()[0]['name'] ?? '—');
}

/* ===========================================================================
   Milestones
   =========================================================================== */

const MILESTONE_STATUSES = [
    'planned'     => 'Planned',
    'in_progress' => 'In progress',
    'complete'    => 'Complete',
    'blocked'     => 'Blocked',
];

function get_milestones(bool $includePrivate = false, ?string $stageKey = null): array {
    $sql    = 'SELECT * FROM milestones WHERE 1=1';
    $params = [];
    if (!$includePrivate) {
        $sql .= ' AND is_public = 1';
    }
    if ($stageKey !== null) {
        $sql .= ' AND stage_key = ?';
        $params[] = $stageKey;
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Milestones bucketed by stage_key, for the pathway and tracker pages. */
function milestones_by_stage(bool $includePrivate = false): array {
    $out = [];
    foreach (get_milestones($includePrivate) as $m) {
        $out[$m['stage_key']][] = $m;
    }
    return $out;
}

function get_milestone(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM milestones WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function save_milestone(array $data): int {
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('A milestone needs a title.');
    }
    $stageKey = array_key_exists($data['stage_key'] ?? '', stage_options()) ? $data['stage_key'] : 'discover';
    $status   = array_key_exists($data['status'] ?? '', MILESTONE_STATUSES) ? $data['status'] : 'planned';
    $user     = current_user();

    $fields = [
        'stage_key'    => $stageKey,
        'title'        => $title,
        'description'  => trim((string) ($data['description'] ?? '')) ?: null,
        'status'       => $status,
        'target_date'  => ($data['target_date'] ?? '') ?: null,
        // A milestone marked complete without a date defaults to today, so the
        // public timeline never shows "complete" with no when.
        'completed_on' => $status === 'complete' ? (($data['completed_on'] ?? '') ?: date('Y-m-d')) : null,
        'is_public'    => !empty($data['is_public']) ? 1 : 0,
        'updated_by'   => $user['id'] ?? null,
    ];

    if (!empty($data['id'])) {
        $id  = (int) $data['id'];
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
        $stmt = db()->prepare("UPDATE milestones SET $set WHERE id = :id");
        $stmt->execute($fields + ['id' => $id]);
        audit('update_milestone', 'milestone', $id, $title);
        return $id;
    }

    $fields['sort_order'] = 1 + (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM milestones')->fetchColumn();
    $cols = implode(', ', array_keys($fields));
    $vals = ':' . implode(', :', array_keys($fields));
    db()->prepare("INSERT INTO milestones ($cols) VALUES ($vals)")->execute($fields);
    $id = (int) db()->lastInsertId();
    audit('create_milestone', 'milestone', $id, $title);
    return $id;
}

function delete_milestone(int $id): void {
    db()->prepare('DELETE FROM milestones WHERE id = ?')->execute([$id]);
    audit('delete_milestone', 'milestone', $id);
}

/* ===========================================================================
   Indicators
   =========================================================================== */

function get_indicators(bool $includePrivate = false, bool $featuredOnly = false): array {
    $sql = 'SELECT * FROM indicators WHERE 1=1';
    if (!$includePrivate) $sql .= ' AND is_public = 1';
    if ($featuredOnly)    $sql .= ' AND is_featured = 1';
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function get_indicator(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM indicators WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Updates an indicator's value and records a dated snapshot, so the tracker
 * can show movement over time. One snapshot per indicator per day — a second
 * edit on the same day overwrites it rather than creating noise.
 */
function set_indicator_value(int $id, float $value, ?string $valueText = null): void {
    $user = current_user();
    db()->prepare('UPDATE indicators SET value_num = ?, value_text = ?, updated_by = ? WHERE id = ?')
        ->execute([$value, $valueText, $user['id'] ?? null, $id]);

    db()->prepare(
        'INSERT INTO indicator_history (indicator_id, value_num, recorded_on, recorded_by)
         VALUES (?, ?, CURDATE(), ?)
         ON DUPLICATE KEY UPDATE value_num = VALUES(value_num), recorded_by = VALUES(recorded_by)'
    )->execute([$id, $value, $user['id'] ?? null]);
}

function save_indicator(array $data): int {
    $label = trim((string) ($data['label'] ?? ''));
    if ($label === '') {
        throw new RuntimeException('An indicator needs a label.');
    }
    $formats = ['integer', 'decimal', 'percent', 'text'];

    $fields = [
        'label'          => $label,
        'unit'           => trim((string) ($data['unit'] ?? '')) ?: null,
        'icon_key'       => array_key_exists($data['icon_key'] ?? '', ICON_LIBRARY) ? $data['icon_key'] : 'growth',
        'display_format' => in_array($data['display_format'] ?? '', $formats, true) ? $data['display_format'] : 'integer',
        'is_public'      => !empty($data['is_public']) ? 1 : 0,
        'is_featured'    => !empty($data['is_featured']) ? 1 : 0,
        'source'         => ($data['source'] ?? 'manual') === 'kobo' ? 'kobo' : 'manual',
        'kobo_form_id'   => trim((string) ($data['kobo_form_id'] ?? '')) ?: null,
        'kobo_field'     => trim((string) ($data['kobo_field'] ?? '')) ?: null,
    ];

    if (!empty($data['id'])) {
        $id  = (int) $data['id'];
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
        db()->prepare("UPDATE indicators SET $set WHERE id = :id")->execute($fields + ['id' => $id]);
    } else {
        $fields['indicator_key'] = unique_slug(slugify($label, 55), 'indicators', null, 'indicator_key');
        $fields['sort_order']    = 1 + (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM indicators')->fetchColumn();
        $cols = implode(', ', array_keys($fields));
        $vals = ':' . implode(', :', array_keys($fields));
        db()->prepare("INSERT INTO indicators ($cols) VALUES ($vals)")->execute($fields);
        $id = (int) db()->lastInsertId();
    }

    set_indicator_value($id, (float) ($data['value_num'] ?? 0), trim((string) ($data['value_text'] ?? '')) ?: null);
    audit(empty($data['id']) ? 'create_indicator' : 'update_indicator', 'indicator', $id, $label);
    return $id;
}

function delete_indicator(int $id): void {
    db()->prepare('DELETE FROM indicator_history WHERE indicator_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM indicators WHERE id = ?')->execute([$id]);
    audit('delete_indicator', 'indicator', $id);
}

/** Dated values for one indicator, oldest first — used to draw the trend line. */
function indicator_history(int $indicatorId, int $limit = 24): array {
    $stmt = db()->prepare(
        'SELECT value_num, recorded_on FROM indicator_history
         WHERE indicator_id = ? ORDER BY recorded_on DESC LIMIT ?'
    );
    $stmt->bindValue(1, $indicatorId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return array_reverse($stmt->fetchAll());
}

/* ===========================================================================
   Churches
   =========================================================================== */

function get_churches(bool $includeUnpublished = false, ?string $stageKey = null): array {
    $sql    = 'SELECT * FROM churches WHERE 1=1';
    $params = [];
    if (!$includeUnpublished) {
        $sql .= ' AND is_published = 1';
    }
    if ($stageKey) {
        $sql .= ' AND stage_key = ?';
        $params[] = $stageKey;
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_church(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM churches WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_church_by_slug(string $slug, bool $includeUnpublished = false): ?array {
    $sql = 'SELECT * FROM churches WHERE slug = ?';
    if (!$includeUnpublished) $sql .= ' AND is_published = 1';
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** Distinct communities across published churches, for the filter control. */
function church_communities(bool $includeUnpublished = false): array {
    $sql = "SELECT DISTINCT community FROM churches WHERE community IS NOT NULL AND community <> ''";
    if (!$includeUnpublished) $sql .= ' AND is_published = 1';
    return array_column(db()->query($sql . ' ORDER BY community ASC')->fetchAll(), 'community');
}

function save_church(array $data): int {
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('A church needs a name.');
    }

    $id     = !empty($data['id']) ? (int) $data['id'] : null;
    $slugIn = trim((string) ($data['slug'] ?? ''));
    $slug   = unique_slug(slugify($slugIn !== '' ? $slugIn : $name), 'churches', $id);

    $fields = [
        'slug'                => $slug,
        'name'                => $name,
        'community'           => trim((string) ($data['community'] ?? '')) ?: null,
        'lga'                 => trim((string) ($data['lga'] ?? '')) ?: null,
        'state'               => trim((string) ($data['state'] ?? '')) ?: 'Plateau',
        'denomination'        => trim((string) ($data['denomination'] ?? '')) ?: null,
        'stage_key'           => array_key_exists($data['stage_key'] ?? '', stage_options()) ? $data['stage_key'] : 'discover',
        'participant_count'   => max(0, (int) ($data['participant_count'] ?? 0)),
        'seedlings_allocated' => max(0, (int) ($data['seedlings_allocated'] ?? 0)),
        'trees_planted'       => max(0, (int) ($data['trees_planted'] ?? 0)),
        'survival_rate'       => (($data['survival_rate'] ?? '') === '' || ($data['survival_rate'] ?? null) === null)
                                    ? null : max(0, min(100, (float) $data['survival_rate'])),
        'meetings_held'       => max(0, (int) ($data['meetings_held'] ?? 0)),
        'reports_submitted'   => max(0, (int) ($data['reports_submitted'] ?? 0)),
        'joined_on'           => ($data['joined_on'] ?? '') ?: null,
        'summary'             => trim((string) ($data['summary'] ?? '')) ?: null,
        'latitude'            => ($data['latitude'] ?? '') === '' ? null : (float) $data['latitude'],
        'longitude'           => ($data['longitude'] ?? '') === '' ? null : (float) $data['longitude'],
        'is_published'        => !empty($data['is_published']) ? 1 : 0,
    ];
    if (!empty($data['photo_path'])) {
        $fields['photo_path'] = $data['photo_path'];
    }

    if ($id) {
        $existing = get_church($id);
        if (!$existing) throw new RuntimeException('Church not found.');
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
        db()->prepare("UPDATE churches SET $set WHERE id = :id")->execute($fields + ['id' => $id]);
        if (!empty($data['photo_path']) && $data['photo_path'] !== $existing['photo_path']) {
            delete_upload($existing['photo_path']);
        }
        audit('update_church', 'church', $id, $name);
        return $id;
    }

    $fields['sort_order'] = 1 + (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM churches')->fetchColumn();
    $cols = implode(', ', array_keys($fields));
    $vals = ':' . implode(', :', array_keys($fields));
    db()->prepare("INSERT INTO churches ($cols) VALUES ($vals)")->execute($fields);
    $newId = (int) db()->lastInsertId();
    audit('create_church', 'church', $newId, $name);
    return $newId;
}

function delete_church(int $id): void {
    $church = get_church($id);
    if (!$church) return;
    // Stories and coordinator accounts keep working: they just lose the link.
    db()->prepare('UPDATE stories SET church_id = NULL WHERE church_id = ?')->execute([$id]);
    db()->prepare('UPDATE users SET church_id = NULL WHERE church_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM churches WHERE id = ?')->execute([$id]);
    delete_upload($church['photo_path']);
    audit('delete_church', 'church', $id, $church['name']);
}

/**
 * Totals rolled up from church records. Offered to admins as a one-click way
 * to keep the public indicators honest without retyping them.
 */
function church_rollup(): array {
    $row = db()->query(
        'SELECT COUNT(*)                    AS churches,
                COALESCE(SUM(participant_count),0)   AS participants,
                COALESCE(SUM(seedlings_allocated),0) AS seedlings,
                COALESCE(SUM(trees_planted),0)       AS trees,
                COALESCE(SUM(meetings_held),0)       AS meetings,
                COALESCE(SUM(reports_submitted),0)   AS reports,
                AVG(survival_rate)                   AS survival
         FROM churches WHERE is_published = 1'
    )->fetch();

    return [
        'churches_onboarded'    => (int) $row['churches'],
        'participants'          => (int) $row['participants'],
        'seedlings_distributed' => (int) $row['seedlings'],
        'trees_planted'         => (int) $row['trees'],
        'meetings_completed'    => (int) $row['meetings'],
        'reports_submitted'     => (int) $row['reports'],
        'survival_rate'         => $row['survival'] === null ? null : round((float) $row['survival'], 1),
    ];
}
