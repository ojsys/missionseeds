<?php
require_once __DIR__ . '/db.php';

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// The list of inline-editable settings keys now lives in includes/content_keys.php,
// grouped by page. Use is_inline_editable($key) to test one.

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

/* ---------------- partners (logo strip) ---------------- */

function get_partners(bool $onlyVisible = true): array {
    $sql = 'SELECT * FROM partners';
    if ($onlyVisible) $sql .= ' WHERE is_visible = 1';
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function get_partner(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM partners WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Insert or update a partner. Pass 'id' to update. 'logo_path' is optional on
 * update — omit it to keep the existing logo.
 */
function save_partner(array $data): int {
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Partner name is required.');
    }
    $link = trim((string) ($data['link_url'] ?? ''));
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('That website link is not a valid URL.');
    }
    if ($link === '') $link = null;
    $visible = !empty($data['is_visible']) ? 1 : 0;

    if (!empty($data['id'])) {
        $id      = (int) $data['id'];
        $current = get_partner($id);
        if (!$current) throw new RuntimeException('Partner not found.');

        // A blank logo_path on update means "keep the logo you already have".
        $logo = !empty($data['logo_path']) ? $data['logo_path'] : $current['logo_path'];

        $stmt = db()->prepare(
            'UPDATE partners SET name=:name, logo_path=:logo_path, link_url=:link_url, is_visible=:is_visible
             WHERE id=:id'
        );
        $stmt->execute([
            'name'       => $name,
            'logo_path'  => $logo,
            'link_url'   => $link,
            'is_visible' => $visible,
            'id'         => $id,
        ]);

        // Replacing the logo orphans the old file — clean it up.
        if (!empty($data['logo_path']) && $data['logo_path'] !== $current['logo_path']) {
            delete_upload($current['logo_path']);
        }
        return $id;
    }

    if (empty($data['logo_path'])) {
        throw new RuntimeException('A logo image is required.');
    }

    $maxOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM partners')->fetchColumn();
    $stmt = db()->prepare(
        'INSERT INTO partners (name, logo_path, link_url, sort_order, is_visible)
         VALUES (:name, :logo_path, :link_url, :sort_order, :is_visible)'
    );
    $stmt->execute([
        'name'       => $name,
        'logo_path'  => $data['logo_path'],
        'link_url'   => $link,
        'sort_order' => $maxOrder + 1,
        'is_visible' => $visible,
    ]);
    return (int) db()->lastInsertId();
}

function delete_partner(int $id): void {
    $partner = get_partner($id);
    if (!$partner) return;
    $stmt = db()->prepare('DELETE FROM partners WHERE id = ?');
    $stmt->execute([$id]);
    delete_upload($partner['logo_path']);
}

function move_partner(int $id, string $direction): void {
    $partner = get_partner($id);
    if (!$partner) return;

    $cmp = $direction === 'up' ? '<' : '>';
    $ord = $direction === 'up' ? 'DESC' : 'ASC';

    $stmt = db()->prepare(
        "SELECT id, sort_order FROM partners
         WHERE sort_order $cmp :o ORDER BY sort_order $ord, id $ord LIMIT 1"
    );
    $stmt->execute(['o' => $partner['sort_order']]);
    $neighbor = $stmt->fetch();
    if (!$neighbor) return;

    $upd = db()->prepare('UPDATE partners SET sort_order = ? WHERE id = ?');
    $upd->execute([$neighbor['sort_order'], $partner['id']]);
    $upd->execute([$partner['sort_order'], $neighbor['id']]);
}

/* ---------------- uploads ---------------- */

/**
 * Validates an uploaded image by sniffing its real MIME type (never trusting
 * the browser-supplied name or type) and moves it into assets/uploads.
 * Returns the site-relative path to store in the database.
 */
function save_uploaded_image(array $file, string $prefix, int $maxBytes = 4194304): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            ($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE
                ? 'That file is larger than the server allows.'
                : 'No file was received.'
        );
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image must be under ' . round($maxBytes / 1048576) . 'MB.');
    }

    // SVG is deliberately not allowed: it can carry scripts, and these files are
    // served from our own origin.
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Please upload a PNG, JPG, or WEBP image.');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Could not create the uploads folder.');
    }

    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '', $prefix) ?: 'upload';
    $filename   = $safePrefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return 'assets/uploads/' . $filename;
}

/**
 * Validates and stores an uploaded document for the Resource Hub.
 *
 * Like save_uploaded_image() this sniffs the real MIME type rather than
 * trusting the filename. The allow-list is deliberately short — anything that
 * a browser might execute, or that Apache might hand to the PHP handler, is
 * absent. Uploads also land in assets/uploads, where the folder's own
 * .htaccess disables script execution.
 */
function save_uploaded_document(array $file, string $prefix = 'resource', int $maxBytes = 20971520): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            ($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE
                ? 'That file is larger than the server allows.'
                : 'No file was received.'
        );
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File must be under ' . round($maxBytes / 1048576) . 'MB.');
    }

    $allowed = [
        'application/pdf'  => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
        'text/plain'       => 'txt',
        'text/csv'         => 'csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        // Office files are ZIP containers, so finfo often reports them as such.
        'application/zip'  => null,
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $allowed)) {
        throw new RuntimeException('Please upload a PDF, Word, Excel, PowerPoint, CSV, or image file.');
    }

    $ext = $allowed[$mime];
    if ($ext === null) {
        // A ZIP-detected file is only accepted if its name claims an Office type.
        $claimed = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($claimed, ['docx', 'xlsx', 'pptx'], true)) {
            throw new RuntimeException('Please upload a PDF, Word, Excel, PowerPoint, CSV, or image file.');
        }
        $ext = $claimed;
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Could not create the uploads folder.');
    }

    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '', $prefix) ?: 'file';
    $filename   = $safePrefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return ['path' => 'assets/uploads/' . $filename, 'bytes' => (int) $file['size']];
}

/** "2.4 MB" — for resource download links. */
function human_bytes(?int $bytes): string {
    if (!$bytes) return '';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float) $bytes;
    while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
    return ($n < 10 && $i > 0 ? round($n, 1) : round($n)) . ' ' . $units[$i];
}

/** Deletes an uploaded file, refusing to touch anything outside assets/uploads. */
function delete_upload(?string $relativePath): void {
    if (!$relativePath) return;
    $full = realpath(BASE_PATH . '/' . ltrim($relativePath, '/'));
    $root = realpath(UPLOAD_DIR);
    if (!$full || !$root || strpos($full, $root . DIRECTORY_SEPARATOR) !== 0) return;
    if (basename($full) === '.htaccess' || basename($full) === 'hero.jpg') return;
    @unlink($full);
}

/* ---------------- misc ---------------- */

function format_deadline(string $ymd): string {
    $ts = strtotime($ymd);
    if (!$ts) return e($ymd);
    return strtoupper(date('d · m · Y', $ts));
}

/**
 * Organic wave between two sections. $fillColor is the section BELOW; the
 * divider's own background must match the section ABOVE, which the CSS class
 * normally handles — pass $bgColor to override it when the preceding section
 * is conditional (e.g. Partners).
 */
function divider(string $class, string $fillColor, ?string $bgColor = null): string {
    $style = $bgColor ? ' style="background:' . e($bgColor) . ';"' : '';
    return '<div class="section-divider ' . e($class) . '"' . $style . '><svg viewBox="0 0 1440 60" preserveAspectRatio="none">'
        . '<path d="M0,38 C220,8 380,58 620,34 C860,10 1040,58 1260,30 C1340,20 1400,26 1440,32 L1440,60 L0,60 Z" fill="' . e($fillColor) . '"/>'
        . '</svg></div>';
}

function days_until(string $ymd): ?int {
    $ts = strtotime($ymd . ' 23:59:59');
    if (!$ts) return null;
    $diff = $ts - time();
    return (int) ceil($diff / 86400);
}

/** "12 March 2026", or '' when the date is empty/unparseable. */
function format_date(?string $ymd, string $format = 'j F Y'): string {
    if (!$ymd) return '';
    $ts = strtotime($ymd);
    return $ts ? date($format, $ts) : '';
}

/** Human-friendly age for feeds: "3 days ago", "2 months ago". */
function time_ago(?string $datetime): string {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400)   return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800)  return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

/**
 * URL-safe slug. Always returns something — falls back to a short random
 * token so a title of only punctuation cannot produce an empty slug.
 */
function slugify(string $text, int $maxLength = 120): string {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    $slug = mb_substr($slug, 0, $maxLength);
    return $slug !== '' ? $slug : 'item-' . bin2hex(random_bytes(3));
}

/**
 * Makes a slug unique within a table by appending -2, -3, … when needed.
 * $table and $column are developer-supplied identifiers, never user input.
 */
function unique_slug(string $base, string $table, ?int $ignoreId = null, string $column = 'slug'): string {
    $slug = $base;
    $n    = 1;
    $sql  = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?" . ($ignoreId ? ' AND id <> ?' : '');
    $stmt = db()->prepare($sql);

    while (true) {
        $params = $ignoreId ? [$slug, $ignoreId] : [$slug];
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $n++;
        $slug = mb_substr($base, 0, 115) . '-' . $n;
    }
}

/** Formats an indicator for display according to its display_format. */
function format_indicator(array $indicator): string {
    switch ($indicator['display_format']) {
        case 'text':
            return $indicator['value_text'] ?? '—';
        case 'percent':
            return rtrim(rtrim(number_format((float) $indicator['value_num'], 1), '0'), '.') . '%';
        case 'decimal':
            return number_format((float) $indicator['value_num'], 1);
        case 'integer':
        default:
            return number_format((float) $indicator['value_num']);
    }
}

/**
 * Renders admin-authored body text as safe HTML.
 *
 * Story bodies are written by trusted-but-not-infallible staff and church
 * coordinators, so rather than allowing raw HTML we accept plain paragraphs
 * plus a tiny markdown subset (**bold**, *italic*, [text](url)) and escape
 * everything else. Nothing an author types can introduce script.
 */
function rich_text(?string $text): string {
    if (!$text) return '';
    $out = '';
    foreach (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", trim($text))) as $para) {
        $safe = e($para);
        $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe);
        $safe = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $safe);
        $safe = preg_replace_callback(
            '/\[([^\]]{1,120})\]\((https?:\/\/[^\s)]{1,400})\)/',
            fn($m) => '<a href="' . e($m[2]) . '" target="_blank" rel="noopener nofollow">' . $m[1] . '</a>',
            $safe
        );
        $out .= '<p>' . nl2br($safe) . '</p>';
    }
    return $out;
}

/** First N characters of body text, for cards and meta descriptions. */
function excerpt_from(?string $text, int $length = 180): string {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');
    if (mb_strlen($plain) <= $length) return $plain;
    return mb_substr($plain, 0, $length - 1) . '…';
}
