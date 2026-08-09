<?php
/**
 * Navigation model for the admin and portal shells.
 *
 * The sidebar is built from capabilities, so a role never sees a link to a
 * page it would be refused on. The capability check on the page itself is what
 * actually enforces access — this only keeps the UI honest.
 *
 * Each item: key, label, href, icon, and an optional badge count.
 */

/**
 * A small set of interface icons, separate from ICON_LIBRARY — that one is the
 * agriculture-themed set editors pick from for content, and it would look odd
 * as navigation furniture.
 */
function nav_icon(string $key): string {
    $paths = [
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'church'    => '<path d="M12 2v4M10 4h4M4 21V11l8-5 8 5v10M4 21h16M10 21v-5h4v5"/>',
        'route'     => '<circle cx="6" cy="19" r="2.5"/><circle cx="18" cy="5" r="2.5"/><path d="M15.5 5H10a3.5 3.5 0 000 7h4a3.5 3.5 0 010 7H8.5"/>',
        'chart'     => '<path d="M4 20h16M7 20v-6M12 20V8M17 20v-9"/>',
        'file'      => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'image'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.6"/><path d="m21 16-5-5-9 8"/>',
        'folder'    => '<path d="M3 7a2 2 0 012-2h4l2 2.5h8a2 2 0 012 2V18a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
        'inbox'     => '<path d="M3 12h5l1.5 3h5L16 12h5"/><path d="M4.5 6.5 3 12v6a2 2 0 002 2h14a2 2 0 002-2v-6l-1.5-5.5A2 2 0 0017.6 5H6.4a2 2 0 00-1.9 1.5z"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.4 2.7-6 6-6s6 2.6 6 6"/><path d="M16.5 5.3a3.2 3.2 0 010 5.9M17 14.4c2.3.6 4 2.6 4 5.1"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.6V21a2 2 0 11-4 0v-.2a1.7 1.7 0 00-1-1.5 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.6-1H3a2 2 0 110-4h.2a1.7 1.7 0 001.5-1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H9a1.7 1.7 0 001-1.6V3a2 2 0 114 0v.2a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V9a1.7 1.7 0 001.6 1H21a2 2 0 110 4h-.2a1.7 1.7 0 00-1.6 1z"/>',
        'activity'  => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'handshake' => '<path d="M7 12a5 5 0 010-7M17 5a5 5 0 010 7M8.5 15.5l1.8-1.8a1.6 1.6 0 012.3 0l.8.8a1.6 1.6 0 002.3 0l1.8-1.8M4 20l3-3M20 20l-3-3"/>',
        'edit'      => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 20 3M17 6l2.5 2.5M14.5 8.5 17 11"/>',
        'external'  => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14 21 3"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>',
        'home'      => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1z"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$key] ?? $paths['grid']) . '</svg>';
}

/** Grouped navigation for the staff admin area. */
function admin_nav_groups(): array {
    $groups = [];

    $groups[] = ['label' => 'Overview', 'items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => url('/admin/index.php'), 'icon' => 'grid'],
    ]];

    if (user_can('manage_project')) {
        $groups[] = ['label' => 'Project', 'items' => [
            ['key' => 'churches',   'label' => 'Churches',   'href' => url('/admin/churches.php'),   'icon' => 'church'],
            ['key' => 'pathway',    'label' => 'Pathway',    'href' => url('/admin/pathway.php'),    'icon' => 'route'],
            ['key' => 'indicators', 'label' => 'Indicators', 'href' => url('/admin/indicators.php'), 'icon' => 'chart'],
        ]];
    }

    $content = [];
    if (user_can('manage_stories')) {
        $content[] = ['key' => 'stories', 'label' => 'Stories', 'href' => url('/admin/stories.php'),
                      'icon' => 'file', 'badge' => count_pending_stories()];
        $content[] = ['key' => 'media', 'label' => 'Media', 'href' => url('/admin/media.php'), 'icon' => 'image'];
    }
    if (user_can('manage_resources')) {
        $content[] = ['key' => 'resources', 'label' => 'Resources', 'href' => url('/admin/resources.php'), 'icon' => 'folder'];
    }
    if (user_can('manage_content')) {
        $content[] = ['key' => 'lists',    'label' => 'Call page lists', 'href' => url('/admin/lists.php'),    'icon' => 'list'];
        $content[] = ['key' => 'partners', 'label' => 'Partners',        'href' => url('/admin/partners.php'), 'icon' => 'handshake'];
    }
    if ($content) {
        $groups[] = ['label' => 'Content', 'items' => $content];
    }

    $people = [];
    if (user_can('view_submissions')) {
        $people[] = ['key' => 'submissions', 'label' => 'Enquiries', 'href' => url('/admin/submissions.php'),
                     'icon' => 'inbox', 'badge' => count_new_submissions()];
    }
    if (user_can('manage_users')) {
        $people[] = ['key' => 'users', 'label' => 'Users & roles', 'href' => url('/admin/users.php'), 'icon' => 'users'];
    }
    if ($people) {
        $groups[] = ['label' => 'People', 'items' => $people];
    }

    $system = [];
    if (user_can('manage_settings')) {
        $system[] = ['key' => 'settings', 'label' => 'Settings', 'href' => url('/admin/settings.php'), 'icon' => 'settings'];
    }
    if (user_can('view_audit')) {
        $system[] = ['key' => 'audit', 'label' => 'Activity log', 'href' => url('/admin/audit.php'), 'icon' => 'activity'];
    }
    $system[] = ['key' => 'password', 'label' => 'My password', 'href' => url('/admin/change-password.php'), 'icon' => 'key'];
    $groups[] = ['label' => 'System', 'items' => $system];

    return $groups;
}

/** Grouped navigation for the church coordinator portal. */
function portal_nav_groups(): array {
    return [
        ['label' => 'My church', 'items' => [
            ['key' => 'home',    'label' => 'Overview',     'href' => url('/portal/index.php'),   'icon' => 'home'],
            ['key' => 'updates', 'label' => 'My updates',   'href' => url('/portal/updates.php'), 'icon' => 'edit'],
        ]],
        ['label' => 'Field tools', 'items' => [
            ['key' => 'resources', 'label' => 'Forms & resources', 'href' => url('/resources'), 'icon' => 'folder'],
        ]],
        ['label' => 'Account', 'items' => [
            ['key' => 'password', 'label' => 'My password', 'href' => url('/admin/change-password.php'), 'icon' => 'key'],
        ]],
    ];
}

/**
 * Breadcrumb trail for the topbar: the group a page sits in, then the page.
 * Falls back to just the page title when the key is not in the navigation
 * (e.g. change-password reached from a forced redirect).
 */
function nav_breadcrumb(array $groups, string $activeKey, string $pageTitle): array {
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            if ($item['key'] === $activeKey) {
                return [$group['label'], $item['label']];
            }
        }
    }
    return [$pageTitle];
}

/** Initials for the sidebar avatar. */
function user_initials(array $user): string {
    $name = trim((string) ($user['full_name'] ?: $user['username']));
    $parts = preg_split('/\s+/', $name) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last) ?: '?';
}
