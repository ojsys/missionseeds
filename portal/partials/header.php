<?php
/**
 * Church portal chrome — the same shell as the admin area with a much shorter
 * navigation, so a coordinator who has been shown the admin once still
 * recognises where they are.
 */
require_once __DIR__ . '/../../admin/partials/nav.php';

$shellGroups = portal_nav_groups();
$shellLabel  = 'Church portal';

include __DIR__ . '/../../admin/partials/shell.php';
