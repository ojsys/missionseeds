<?php
require_once __DIR__ . '/db.php';

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Settings that may be edited inline (contenteditable) directly on the public page. */
const INLINE_EDITABLE_KEYS = [
    'hero_eyebrow', 'hero_heading', 'hero_subheading',
    'seed_eyebrow', 'seed_heading', 'seed_body',
    'who_eyebrow', 'who_heading', 'who_intro',
    'pilot_eyebrow', 'pilot_heading', 'pilot_body',
    'receive_eyebrow', 'receive_heading',
    'harvest_eyebrow', 'harvest_heading', 'harvest_subheading',
    'deadline_note', 'eoi_button_label', 'footer_tagline',
];

/**
 * Renders a piece of settings text. When an admin is logged in, wraps it in
 * a contenteditable span the front-end JS can save via admin/api.php.
 */
function editable(string $key, bool $isAdmin, bool $multiline = false): string {
    $value = get_setting($key);
    $html  = $multiline
        ? nl2br(e($value))
        : e($value);

    // Support a lightweight {{highlight}} syntax for an italic accent word/phrase.
    $html = preg_replace('/\{\{(.+?)\}\}/', '<em>$1</em>', $html);

    if (!$isAdmin) {
        return $html;
    }
    return '<span class="editable" contenteditable="true" data-setting-key="' . e($key) . '">' . $html . '</span>';
}

/* ---------------- settings (key/value) ---------------- */

function get_all_settings(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

function get_setting(string $key, string $default = ''): string {
    $all = get_all_settings();
    return $all[$key] ?? $default;
}

function set_setting(string $key, string $value): void {
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = :v2'
    );
    $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
}

/* ---------------- list items (criteria / receive grids) ---------------- */

function get_list_items(string $groupKey, bool $onlyVisible = true): array {
    $sql = 'SELECT * FROM list_items WHERE group_key = ?';
    if ($onlyVisible) $sql .= ' AND is_visible = 1';
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$groupKey]);
    return $stmt->fetchAll();
}

function get_list_item(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM list_items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function save_list_item(array $data): int {
    if (!empty($data['id'])) {
        $stmt = db()->prepare(
            'UPDATE list_items SET icon_key=:icon_key, title=:title, body=:body, is_visible=:is_visible WHERE id=:id'
        );
        $stmt->execute([
            'icon_key'   => $data['icon_key'],
            'title'      => $data['title'],
            'body'       => $data['body'],
            'is_visible' => $data['is_visible'] ?? 1,
            'id'         => $data['id'],
        ]);
        return (int) $data['id'];
    }

    $maxOrder = (int) db()->query(
        "SELECT COALESCE(MAX(sort_order),0) FROM list_items WHERE group_key = " . db()->quote($data['group_key'])
    )->fetchColumn();

    $stmt = db()->prepare(
        'INSERT INTO list_items (group_key, icon_key, title, body, sort_order, is_visible)
         VALUES (:group_key, :icon_key, :title, :body, :sort_order, :is_visible)'
    );
    $stmt->execute([
        'group_key'  => $data['group_key'],
        'icon_key'   => $data['icon_key'],
        'title'      => $data['title'],
        'body'       => $data['body'],
        'sort_order' => $maxOrder + 1,
        'is_visible' => $data['is_visible'] ?? 1,
    ]);
    return (int) db()->lastInsertId();
}

function delete_list_item(int $id): void {
    $stmt = db()->prepare('DELETE FROM list_items WHERE id = ?');
    $stmt->execute([$id]);
}

function move_list_item(int $id, string $direction): void {
    $item = get_list_item($id);
    if (!$item) return;

    $cmp = $direction === 'up' ? '<' : '>';
    $ord = $direction === 'up' ? 'DESC' : 'ASC';

    $stmt = db()->prepare(
        "SELECT id, sort_order FROM list_items
         WHERE group_key = :g AND sort_order $cmp :o
         ORDER BY sort_order $ord LIMIT 1"
    );
    $stmt->execute(['g' => $item['group_key'], 'o' => $item['sort_order']]);
    $neighbor = $stmt->fetch();
    if (!$neighbor) return;

    $upd = db()->prepare('UPDATE list_items SET sort_order = ? WHERE id = ?');
    $upd->execute([$neighbor['sort_order'], $item['id']]);
    $upd->execute([$item['sort_order'], $neighbor['id']]);
}

/* ---------------- misc ---------------- */

function format_deadline(string $ymd): string {
    $ts = strtotime($ymd);
    if (!$ts) return e($ymd);
    return strtoupper(date('d · m · Y', $ts));
}

function divider(string $class, string $fillColor): string {
    return '<div class="section-divider ' . e($class) . '"><svg viewBox="0 0 1440 60" preserveAspectRatio="none">'
        . '<path d="M0,38 C220,8 380,58 620,34 C860,10 1040,58 1260,30 C1340,20 1400,26 1440,32 L1440,60 L0,60 Z" fill="' . e($fillColor) . '"/>'
        . '</svg></div>';
}

function days_until(string $ymd): ?int {
    $ts = strtotime($ymd . ' 23:59:59');
    if (!$ts) return null;
    $diff = $ts - time();
    return (int) ceil($diff / 86400);
}
