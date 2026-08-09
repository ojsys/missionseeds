<?php
/**
 * Staff admin chrome. Pages set $pageTitle and $active (plus optional
 * $pageEyebrow, $pageIntro, $pageActions) and include this.
 */
require_once __DIR__ . '/nav.php';

$shellGroups = admin_nav_groups();
$shellLabel  = 'Admin';

include __DIR__ . '/shell.php';
