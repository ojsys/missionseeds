<?php
/**
 * A small, curated line-icon set (24x24, 1.6px stroke) so the design stays
 * consistent no matter what an editor picks in the admin panel. Add new
 * keys here if you need more — the admin dropdown reads from ICON_LIBRARY.
 */
const ICON_LIBRARY = [
    'land'      => 'Land',
    'people'    => 'People',
    'target'    => 'Target / impact',
    'handshake' => 'Partnership',
    'book'      => 'Training',
    'network'   => 'Cooperative network',
    'seedling'  => 'Seedling',
    'mentor'    => 'Mentorship',
    'checklist' => 'Monitoring / checklist',
    'market'    => 'Market access',
    'coffee'    => 'Coffee',
    'calendar'  => 'Calendar',
    'chat'      => 'Conversation',
    'growth'    => 'Growth chart',
    'sun'       => 'Sun / season',
    'shield'    => 'Stewardship',
];

function icon_path(string $key): string {
    $paths = [
        'land'      => '<path d="M3 12h18M3 12l4-4M3 12l4 4M21 12l-4-4M21 12l-4 4"/>',
        'people'    => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6M14 20c0-2.6 1.8-4.6 4-5"/>',
        'target'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="0.8" fill="currentColor"/>',
        'handshake' => '<path d="M7 12a5 5 0 010-7M17 5a5 5 0 010 7M8.5 15.5l1.8-1.8a1.6 1.6 0 012.3 0l.8.8a1.6 1.6 0 002.3 0l1.8-1.8M4 20l3-3M20 20l-3-3"/>',
        'book'      => '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M8 20h8M12 16v4"/>',
        'network'   => '<circle cx="12" cy="6" r="2.2"/><circle cx="5" cy="17" r="2.2"/><circle cx="19" cy="17" r="2.2"/><path d="M12 8.2v3M9.5 12.5L7 15M14.5 12.5L17 15"/>',
        'seedling'  => '<path d="M3 12c3-1 5-1 8 1s5 2 8 1"/><path d="M12 5v3"/><circle cx="12" cy="3.4" r="1.2" fill="currentColor" stroke="none"/>',
        'mentor'    => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/><path d="M17 9h3M18.5 7.5v3"/>',
        'checklist' => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 8h6M9 12l2 2 4-4"/>',
        'market'    => '<path d="M4 21h16M6 21V9a2 2 0 012-2h8a2 2 0 012 2v12M10 7V4h4v3"/><circle cx="12" cy="13" r="2.4"/>',
        'coffee'    => '<path d="M4 9h13v5a5 5 0 01-5 5H9a5 5 0 01-5-5V9z"/><path d="M17 10.5h1.5a2.5 2.5 0 010 5H17"/><path d="M8 3c0 1.2-1 1.5-1 2.6S8 7.2 8 8M12 3c0 1.2-1 1.5-1 2.6S12 7.2 12 8"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="1.5"/><path d="M3 9.5h18M8 3v4M16 3v4"/>',
        'chat'      => '<path d="M4 5h16v11H9l-4 4V5z"/>',
        'growth'    => '<path d="M4 19h16M6 19V9l4 3 4-6 4 4v9"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M4 12H2M22 12h-2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4"/>',
        'shield'    => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/>',
    ];
    return $paths[$key] ?? $paths['seedling'];
}

function icon_svg(string $key, string $class = ''): string {
    return '<svg class="' . htmlspecialchars($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
        . icon_path($key) . '</svg>';
}

/** Leaf markers used along the growth-vine spine; cycles through 3 variants. */
function leaf_svg(int $index, string $color = '#7fa653'): string {
    $variants = [
        '<path d="M17 30V10" stroke="%COLOR%" stroke-width="2" stroke-linecap="round"/><path d="M17 10C17 3 11 1 5 2c1 8 6 10.5 12 8Z" fill="%COLOR%"/>',
        '<path d="M17 30V10" stroke="%COLOR%" stroke-width="2" stroke-linecap="round"/><path d="M17 12C17 5 23 3 29 4c-1 8-6 10-12 8Z" fill="%COLOR%"/>',
        '<path d="M17 30V10" stroke="%COLOR%" stroke-width="2" stroke-linecap="round"/><path d="M17 11C17 4 12 1 6 1c0 8 5 11 11 10Z" fill="%COLOR%"/>',
    ];
    $svg = $variants[$index % 3];
    $svg = str_replace('%COLOR%', $color, $svg);
    return '<svg viewBox="0 0 34 34">' . $svg . '</svg>';
}
