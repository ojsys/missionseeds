<?php
/**
 * Small rendering helpers shared by the admin CRUD screens.
 * They exist to keep the individual pages readable, not to be a form library —
 * anything unusual is written out longhand on the page that needs it.
 */

function field_text(string $name, string $label, $value = '', array $opts = []): void {
    $type = $opts['type'] ?? 'text';
    $id   = 'f_' . $name;
    echo '<div class="form-row">';
    echo '<label for="' . e($id) . '">' . e($label) . '</label>';
    echo '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '"'
       . ' value="' . e((string) $value) . '"'
       . (isset($opts['placeholder']) ? ' placeholder="' . e($opts['placeholder']) . '"' : '')
       . (isset($opts['step'])        ? ' step="' . e((string) $opts['step']) . '"' : '')
       . (isset($opts['min'])         ? ' min="' . e((string) $opts['min']) . '"' : '')
       . (isset($opts['max'])         ? ' max="' . e((string) $opts['max']) . '"' : '')
       . (isset($opts['maxlength'])   ? ' maxlength="' . e((string) $opts['maxlength']) . '"' : '')
       . (!empty($opts['required'])   ? ' required' : '')
       . '>';
    if (!empty($opts['help'])) echo '<p class="help">' . e($opts['help']) . '</p>';
    echo '</div>';
}

function field_textarea(string $name, string $label, $value = '', array $opts = []): void {
    $id = 'f_' . $name;
    echo '<div class="form-row">';
    echo '<label for="' . e($id) . '">' . e($label) . '</label>';
    echo '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="' . (int) ($opts['rows'] ?? 4) . '"'
       . (isset($opts['maxlength']) ? ' maxlength="' . e((string) $opts['maxlength']) . '"' : '')
       . '>' . e((string) $value) . '</textarea>';
    if (!empty($opts['help'])) echo '<p class="help">' . e($opts['help']) . '</p>';
    echo '</div>';
}

/** @param array $choices value => label */
function field_select(string $name, string $label, array $choices, $value = '', array $opts = []): void {
    $id = 'f_' . $name;
    echo '<div class="form-row">';
    echo '<label for="' . e($id) . '">' . e($label) . '</label>';
    echo '<select id="' . e($id) . '" name="' . e($name) . '">';
    if (isset($opts['blank'])) {
        echo '<option value="">' . e($opts['blank']) . '</option>';
    }
    foreach ($choices as $val => $text) {
        echo '<option value="' . e((string) $val) . '"' . ((string) $val === (string) $value ? ' selected' : '') . '>'
           . e((string) $text) . '</option>';
    }
    echo '</select>';
    if (!empty($opts['help'])) echo '<p class="help">' . e($opts['help']) . '</p>';
    echo '</div>';
}

function field_checkbox(string $name, string $label, bool $checked, ?string $help = null): void {
    $id = 'f_' . $name;
    echo '<div class="form-row"><div class="checkbox-row">';
    echo '<input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>';
    echo '<div><label for="' . e($id) . '">' . e($label) . '</label>';
    if ($help) echo '<p class="help">' . e($help) . '</p>';
    echo '</div></div></div>';
}

/** The icon dropdown, shared by every screen that stores an icon_key. */
function field_icon(string $name, string $label, string $value): void {
    field_select($name, $label, ICON_LIBRARY, $value ?: 'seedling');
}

/** Small round action button used in .data-table .actions cells. */
function action_button(string $svgPath, string $title, array $attrs = []): string {
    $attrString = '';
    foreach ($attrs as $k => $v) {
        $attrString .= ' ' . $k . '="' . e((string) $v) . '"';
    }
    return '<button type="submit" title="' . e($title) . '"' . $attrString . '>'
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' . $svgPath . '</svg>'
         . '<span class="sr-only">' . e($title) . '</span></button>';
}

const SVG_EDIT   = '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>';
const SVG_TRASH  = '<path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"/>';
const SVG_UP     = '<path d="M12 19V5M5 12l7-7 7 7"/>';
const SVG_DOWN   = '<path d="M12 5v14M19 12l-7 7-7-7"/>';
const SVG_EYE    = '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
const SVG_CHECK  = '<path d="M20 6L9 17l-5-5"/>';
const SVG_KEY    = '<path d="M15 7a4 4 0 11-4 4h-1l-2 2-2-2-2 2v3h3l6-6a4 4 0 012-3z"/>';
