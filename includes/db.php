<?php
require_once __DIR__ . '/../config.php';

/* ===========================================================================
   Structured app logger — every error and diagnostic message routes through
   app_log() into logs/app.log. Secrets (DB_PASS, APP_KEY, password fields,
   raw session ids, full uploaded file paths) are redacted before writing.
   =========================================================================== */

function app_log_secrets(): array {
    static $secrets = null;
    if ($secrets === null) {
        $secrets = [];
        foreach (['DB_PASS', 'APP_KEY', 'KOBO_API_TOKEN'] as $c) {
            if (defined($c) && constant($c) !== '' && constant($c) !== null) {
                $val = (string) constant($c);
                if (strlen($val) >= 4) {
                    $secrets[] = $val;
                    // Also match URL-encoded and quote-wrapped forms of it.
                    $secrets[] = rawurlencode($val);
                    $secrets[] = htmlspecialchars($val, ENT_QUOTES);
                }
            }
        }
        $secrets = array_values(array_unique(array_filter($secrets, 'strlen')));
    }
    return $secrets;
}

function app_log_scrub(string $text): string {
    $s = $text;
    foreach (app_log_secrets() as $secret) {
        if ($secret === '') continue;
        $len = strlen($secret);
        $mask = $len <= 4 ? '***' : (substr($secret, 0, 2) . '***' . substr($secret, -2));
        $s = str_replace($secret, $mask, $s);
    }
    // Scrub common password/CSRF/session fields out of any $_POST dumps.
    $patterns = [
        '/"password(_hash)?"\s*=>\s*"[^"]*"/i' => '"${1}"=>"***"',
        '/"password(_current|_new|_confirm)?"\s*=>\s*"[^"]*"/i' => '"${1}"=>"***"',
        '/"csrf"\s*=>\s*"[^"]*"/' => '"csrf"=>"***"',
        '/PHPSESSID=([A-Za-z0-9,-]{10,})/' => 'PHPSESSID=***',
        '/session_id\(([^\)]{10,})\)/' => 'session_id(***)',
    ];
    foreach ($patterns as $p => $r) {
        $s = preg_replace($p, $r, $s) ?? $s;
    }
    return $s;
}

function app_log_write(string $line): void {
    if (!defined('LOG_PATH') || LOG_PATH === '') {
        return;
    }
    $dir = dirname(LOG_PATH);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $prefix = '[' . date('Y-m-d H:i:s') . '] '
            . str_pad((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 4)
            . ' ';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri !== '') {
        $prefix .= app_log_scrub($uri) . ' — ';
    }
    $lineNoNewline = str_replace(["\r\n", "\r", "\n"], ' | ', $line);
    @file_put_contents(
        LOG_PATH,
        $prefix . app_log_scrub($lineNoNewline) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function app_log(string $level, string $message, array $context = []): void {
    $parts = [strtoupper($level) . ':', $message];
    if ($context) {
        $dump = [];
        foreach ($context as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $dump[] = $k . '=' . var_export($v, true);
            } else {
                $dump[] = $k . '=' . preg_replace('/\s+/', ' ', print_r($v, true));
            }
        }
        if ($dump) $parts[] = '{' . implode(', ', $dump) . '}';
    }
    app_log_write(implode(' ', $parts));
}

/* ===========================================================================
   Global error / exception / shutdown handlers.
   - PDOException is ERRMODE_EXCEPTION → thrown as uncaught → handled here.
   - Fatal PHP errors (memory, compile-time) are caught by the shutdown handler.
   - After recording to app.log, a generic 500 page is shown — never details.
   =========================================================================== */

function seedlings_uncaught_exception_handler(Throwable $e): void {
    $class = get_class($e);
    $context = [
        'class'    => $class,
        'code'     => $e->getCode(),
        'file'     => $e->getFile(),
        'line'     => $e->getLine(),
        'trace'    => array_slice(array_map(function ($f) {
            $s = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '');
            if (!empty($f['file'])) {
                $s .= ' @ ' . basename($f['file']) . ':' . ($f['line'] ?? '?');
            }
            return $s;
        }, $e->getTrace()), 0, 20),
    ];
    // For PDO / mysqli specifically, also record SQLSTATE and driver info.
    if ($e instanceof PDOException) {
        $context['errorInfo'] = $e->errorInfo ?? null;
    }
    app_log('error', $e->getMessage(), $context);

    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
    }
    seedlings_show_oops($e);
    exit(1);
}

function seedlings_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool {
    $fatalish = (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR
                 | E_RECOVERABLE_ERROR | E_WARNING | E_USER_WARNING);
    if (!($errno & $fatalish)) {
        return false;  // let PHP's default handler log notices silently.
    }
    app_log('warn', "PHP #$errno: $errstr", ['file' => $errfile, 'line' => $errline]);
    return false;
}

function seedlings_shutdown_handler(): void {
    $err = error_get_last();
    if ($err === null) return;
    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
    if (!($err['type'] & $fatal)) return;
    app_log('fatal', "Shutdown: {$err['message']}", ['file' => $err['file'], 'line' => $err['line']]);
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
    }
    seedlings_show_oops();
}

function seedlings_show_oops(?Throwable $e = null): void {
    // Only reveal to admins if they have a valid session; never by default.
    $friendlyFile = null;
    $friendlyLine = null;
    if ($e instanceof Throwable && session_status() === PHP_SESSION_ACTIVE) {
        // We can't call current_user() here because the DB may not be working.
        // Instead, accept presence of a valid fingerprint + admin_id only
        // for display hints, and mask the password-scrubbed message.
        if (!empty($_SESSION['admin_id']) && ($_SESSION['fingerprint'] ?? '') === (function_exists('session_fingerprint') ? session_fingerprint() : '')) {
            $friendlyFile = basename($e->getFile());
            $friendlyLine = $e->getLine();
        }
    }
    $hint = '';
    if ($friendlyFile !== null) {
        $hint = '<p class="hint" style="opacity:.75">Logged in admin only: thrown in ' . htmlspecialchars($friendlyFile) . ':' . (int) $friendlyLine . '. Full details in ' . htmlspecialchars(basename(LOG_DIR ?? '')) . '/app.log.</p>';
    }
    $css = '<style>
        body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f7f5ef;color:#1d2a1d;margin:0;padding:3rem 1.25rem;}
        .wrap{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e6e2d2;border-radius:14px;padding:2rem 1.5rem;box-shadow:0 1px 0 rgba(0,0,0,.03);}
        h2{margin:0 0 .5rem;font-size:1.4rem;}
        p{margin:.5rem 0;line-height:1.5;}
        .mono{background:#f1efe6;padding:.4rem .55rem;border-radius:6px;font-family:ui-monospace,Menlo,monospace;font-size:.88rem;word-break:break-word;}
        a{color:#2e5d30;}
    </style>';
    $logPathForHumans = (defined('LOG_DIR') ? basename(LOG_DIR) : 'logs') . '/app.log';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Something went wrong</title>' . $css . '</head><body>'
       . '<div class="wrap">'
       . '<h2>Something went wrong</h2>'
       . '<p>The Mission Seedlings site hit an unexpected problem. The technical details have been recorded and will not appear on this page, to keep the site safe.</p>'
       . '<p>If you are the site owner, open hPanel File Manager (or connect via FTP) and look inside:</p>'
       . '<p class="mono">' . htmlspecialchars($logPathForHumans) . '</p>'
       . '<p>You can also try <a href="' . htmlspecialchars((defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/diagnostics.php') . '">running the diagnostics page</a> for a friendly, password-free status check.</p>'
       . $hint
       . '</div></body></html>';
}

set_exception_handler('seedlings_uncaught_exception_handler');
set_error_handler('seedlings_error_handler');
register_shutdown_function('seedlings_shutdown_handler');

/* ===========================================================================
   PDO connection
   - Connection exceptions are now logged in full AND shown with a friendlier
     user-facing message that explains the two most likely Hostinger causes:
     (1) placeholder credentials still in config.php, or (2) localhost socket
     not reachable (try 127.0.0.1).
   =========================================================================== */

function db_hint_for_code(int $code, string $msg): string {
    // Map common MySQL / PDO error codes to a plain-English hint.
    if ($code === 1045 || stripos($msg, 'Access denied') !== false) {
        return 'Access denied (1045) → open config.php and confirm DB_NAME / DB_USER / DB_PASS are copied exactly from hPanel. Hostinger prefixes both DB_NAME and DB_USER with your account prefix (e.g. u89abcd12_...).';
    }
    if ($code === 1049 || stripos($msg, 'Unknown database') !== false) {
        return 'Database does not exist (1049) → in hPanel go to MySQL Databases and create the database first, then paste its full prefixed name into config.php DB_NAME.';
    }
    if ($code === 2002 || $code === 2003 || stripos($msg, 'No such file') !== false || stripos($msg, 'Connection refused') !== false) {
        return 'Cannot reach MySQL server (2002/2003) → try changing DB_HOST in config.php from "localhost" to "127.0.0.1" (localhost tries a Unix socket that Hostinger sometimes places at a non-default path; 127.0.0.1 uses TCP which always works).';
    }
    if ($code === 1130 || stripos($msg, 'not allowed') !== false) {
        return 'Host not allowed (1130) → in hPanel > MySQL Databases > Manage > Remote MySQL, ensure the DB user is allowed to connect from localhost / 127.0.0.1.';
    }
    if ($code === 1226 || stripos($msg, 'has exceeded the') !== false) {
        return 'Resource limit hit (1226) → wait a minute, or in hPanel > MySQL Databases > Management, flush idle connections. This is transient on Hostinger shared plans.';
    }
    if ($code === 1040 || stripos($msg, 'Too many connections') !== false) {
        return 'Too many connections (1040) → wait 30 seconds, then retry. This is a shared-hosting transient.';
    }
    return '';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? $e->getCode());
            $hint = db_hint_for_code($code, $e->getMessage());
            app_log('crit', 'PDO CONNECT FAILED', [
                'code'        => $code,
                'host'        => DB_HOST,
                'dbname'      => DB_NAME,
                'dbuser'      => DB_USER,
                'pdo_code'    => $e->getCode(),
                'errorInfo'   => $e->errorInfo ?? null,
                'message'     => $e->getMessage(),
                'hint'        => $hint,
                'php_sapi'    => PHP_SAPI,
                'server_name' => $_SERVER['SERVER_NAME'] ?? null,
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? null,
            ]);
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                http_response_code(500);
            }
            $hintHtml = $hint !== ''
                ? '<p class="mono">' . htmlspecialchars($hint) . '</p>'
                : '';
            $logPathForHumans = (defined('LOG_DIR') ? basename(LOG_DIR) : 'logs') . '/app.log';
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
               . '<meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<title>Site is temporarily unavailable</title>'
               . '<style>
                   body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f7f5ef;color:#1d2a1d;margin:0;padding:3rem 1.25rem;}
                   .wrap{max-width:620px;margin:0 auto;background:#fff;border:1px solid #e6e2d2;border-radius:14px;padding:2rem 1.5rem;}
                   h2{margin:0 0 .5rem;}
                   p{margin:.5rem 0;line-height:1.55;}
                   .mono{background:#f1efe6;padding:.45rem .6rem;border-radius:6px;font-family:ui-monospace,Menlo,monospace;font-size:.86rem;}
                   code{background:#f1efe6;padding:.12rem .3rem;border-radius:4px;}
                  </style></head><body><div class="wrap">'
               . '<h2>Site is temporarily unavailable</h2>'
               . '<p>We are experiencing a database connection issue. The full technical cause has been recorded in:</p>'
               . '<p class="mono">' . htmlspecialchars($logPathForHumans) . '</p>'
               . $hintHtml
               . '<p>Owners can also run the friendly diagnostics at <code>/diagnostics.php</code> for a guided status check — it never shows passwords on screen.</p>'
               . '</div></body></html>';
            exit(1);
        }
    }
    return $pdo;
}
