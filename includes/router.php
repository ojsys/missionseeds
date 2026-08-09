<?php
/**
 * Tiny front-controller router.
 *
 * Clean URLs are produced by the .htaccess rewrite: any request that is not a
 * real file or directory is sent to /index.php, which asks this file which
 * template in pages/ should render.
 */

/** Static one-to-one routes: url segment => pages/<template>.php */
const ROUTES = [
    ''            => 'home',
    'about'       => 'about',
    'pathway'     => 'pathway',
    'tracker'     => 'tracker',
    'cooperative' => 'cooperative',
    'churches'    => 'churches',
    'stories'     => 'stories',
    'resources'   => 'resources',
    'contact'     => 'contact',
    'call'        => 'call',
];

/** Routes with one captured segment: pattern => [template, param name] */
const ROUTE_PATTERNS = [
    '#^churches/([a-z0-9][a-z0-9\-]{0,120})$#' => ['church', 'slug'],
    '#^stories/([a-z0-9][a-z0-9\-]{0,120})$#'  => ['story',  'slug'],
];

/**
 * The requested path, normalised: no leading/trailing slash, no query string,
 * and with any subfolder install prefix removed.
 */
function current_path(): string {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);

    // Support installs in a subfolder (SITE_URL = https://host/seedlings).
    $base = rtrim((string) parse_url(SITE_URL, PHP_URL_PATH), '/');
    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }

    // /index.php and /index.php/about both resolve like the clean URL would.
    $uri = preg_replace('#^/index\.php#', '', $uri) ?? $uri;

    return trim($uri, '/');
}

/**
 * Resolves a path to ['template' => string, 'params' => array], or null when
 * nothing matches.
 */
function match_route(string $path): ?array {
    $path = strtolower($path);

    if (array_key_exists($path, ROUTES)) {
        return ['template' => ROUTES[$path], 'params' => []];
    }

    foreach (ROUTE_PATTERNS as $pattern => [$template, $paramName]) {
        if (preg_match($pattern, $path, $m)) {
            return ['template' => $template, 'params' => [$paramName => $m[1]]];
        }
    }

    return null;
}

/**
 * Renders a page template inside the site layout.
 *
 * Templates are buffered first so they can set $meta (title, description,
 * canonical, body class) before the <head> is written, and can call
 * not_found() to bail out mid-render.
 */
function render(string $template, array $params = []): void {
    $file = BASE_PATH . '/pages/' . $template . '.php';
    if (!is_file($file)) {
        $template = '404';
        $file = BASE_PATH . '/pages/404.php';
        http_response_code(404);
    }

    // Defaults a template may override.
    $meta = [
        'title'       => get_setting('site_title', 'Mission Seedlings'),
        'description' => get_setting('home_subheading'),
        'canonical'   => url(current_path()),
        'body_class'  => 'page-' . $template,
        'nav_active'  => $template,
        'full_bleed'  => false, // true = template supplies its own <main> wrapper
    ];

    // Available to every template.
    $isAdmin = admin_logged_in();
    $user    = current_user();

    ob_start();
    try {
        include $file;
    } catch (NotFoundException $e) {
        ob_end_clean();
        http_response_code(404);
        render('404');
        return;
    } catch (Throwable $e) {
        ob_end_clean();
        error_log('[Seedlings] Render error in ' . $template . ': ' . $e->getMessage());
        http_response_code(500);
        render('500');
        return;
    }
    $content = ob_get_clean();

    require_once BASE_PATH . '/includes/layout.php';
    layout_render($meta, $content);
}

/** Thrown by a template when its record does not exist (e.g. unknown slug). */
class NotFoundException extends RuntimeException {}

function not_found(string $message = 'Not found'): void {
    throw new NotFoundException($message);
}

/** Dispatches the current request. */
function dispatch(): void {
    $path = current_path();

    // The sitemap writes its own XML response and never uses the HTML layout.
    if ($path === 'sitemap.xml') {
        include BASE_PATH . '/pages/sitemap.php';
        return;
    }

    $route = match_route($path);

    if ($route === null) {
        http_response_code(404);
        render('404');
        return;
    }

    // The call page can be taken offline between application cycles.
    if ($route['template'] === 'call' && get_setting('call_status', 'open') === 'hidden' && !admin_logged_in()) {
        http_response_code(404);
        render('404');
        return;
    }

    render($route['template'], $route['params']);
}
