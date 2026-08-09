<?php
/**
 * Mission Seedlings — front controller.
 *
 * Every public request lands here (see the rewrite rule in .htaccess) and is
 * routed to a template in pages/. Admin and portal pages are real files and
 * are served directly, bypassing this file.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/router.php';

start_session_safe();

dispatch();
