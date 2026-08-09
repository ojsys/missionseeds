<?php
/**
 * Mission Seedlings — diagnostics utility
 *
 * Browse to https://missionseedlings.com/diagnostics.php when the site shows
 * "Site is temporarily unavailable" or any other 500.
 *
 * This file runs its own checks and NEVER:
 *   - outputs a password, API key, or session secret
 *   - modifies the database or files on disk
 *   - reveals full stack traces to the browser (those are only in logs/app.log)
 *
 * You can safely leave it in place. It contains no write operations.
 */

require_once __DIR__ . '/config.php';

/* ---------- helpers ---------- */

function mask_value(string $val): string {
    if ($val === '') return '(empty)';
    $len = strlen($val);
    if ($len <= 4) return '***';
    return substr($val, 0, 2) . '***' . substr($val, -2) . " (len=$len)";
}

function status_row(string $label, $ok, string $detail = '', bool $warn = false): string {
    if ($ok === null) {
        $cls = 'row neutral';
        $dot = '?';
    } elseif ($ok) {
        $cls = 'row ok';
        $dot = '✓';
    } elseif ($warn) {
        $cls = 'row warn';
        $dot = '!';
    } else {
        $cls = 'row fail';
        $dot = '✗';
    }
    $detailHtml = $detail !== '' ? '<div class="detail">' . htmlspecialchars($detail) . '</div>' : '';
    return "<div class=\"$cls\"><span class=\"dot\">$dot</span>"
         . '<div class="col"><div class="label">' . htmlspecialchars($label) . '</div>'
         . $detailHtml . '</div></div>';
}

$checks = [];

/* ---------- PHP runtime ---------- */

$phpVer = PHP_VERSION;
$phpOk = version_compare($phpVer, '8.0.0', '>=');
$checks[] = status_row("PHP version $phpVer", $phpOk, $phpOk ? '' : 'Hostinger supports 8.x — switch in hPanel > PHP Configuration.');

foreach (['pdo', 'pdo_mysql', 'mysqli', 'mbstring', 'fileinfo', 'gd', 'curl', 'json', 'openssl'] as $ext) {
    $loaded = extension_loaded($ext);
    $critical = in_array($ext, ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo'], true);
    if ($critical) {
        $checks[] = status_row("Extension: $ext", $loaded, $loaded ? '' : "Install/enable $ext in hPanel > PHP > Extensions.", false);
    } else {
        $checks[] = status_row("Extension: $ext", $loaded, $loaded ? '' : "Optional extension missing.", true);
    }
}

/* ---------- config.php values ---------- */

$hostPlaceholder = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') ? false : (strpos(DB_HOST, 'REPLACE') !== false);
$checks[] = status_row('DB_HOST constant', !$hostPlaceholder, DB_HOST . (!$hostPlaceholder ? '' : ' — still the placeholder; edit config.php DB_HOST, DB_NAME, DB_USER, DB_PASS.'));

$dbNameOk = (strpos(DB_NAME, 'REPLACE_') === false && DB_NAME !== '');
$checks[] = status_row('DB_NAME constant', $dbNameOk, $dbNameOk ? mask_value(DB_NAME) : 'Still the placeholder — copy the full prefixed database name from hPanel MySQL Databases.');

$dbUserOk = (strpos(DB_USER, 'REPLACE_') === false && DB_USER !== '');
$checks[] = status_row('DB_USER constant', $dbUserOk, $dbUserOk ? mask_value(DB_USER) : 'Still the placeholder — copy the full prefixed username from hPanel MySQL Databases.');

$dbPassOk = (strpos(DB_PASS, 'REPLACE_') === false && strlen(DB_PASS) >= 8);
$checks[] = status_row('DB_PASS constant (strength)', $dbPassOk, $dbPassOk ? 'set, 8+ chars' : 'Too short or still placeholder. Use the strong password you created in hPanel when making the DB user.');

$appKeyOk = (strpos(APP_KEY, 'REPLACE_') === false && strlen(APP_KEY) >= 32);
$checks[] = status_row('APP_KEY constant', $appKeyOk, $appKeyOk ? mask_value(APP_KEY) : 'Too short. 64 hex chars (256-bit) recommended.');

/* ---------- writable folders ---------- */

function dir_writable_test(string $path, string $label, bool $create = true): array {
    $exists  = is_dir($path);
    if (!$exists && $create) {
        @mkdir($path, 0755, true);
        $exists = is_dir($path);
    }
    $writable = $exists && is_writable($path);
    $probe = $writable ? @file_put_contents(rtrim($path, '/') . '/.diagnostic_probe', 'ok') : false;
    if ($probe !== false) {
        @unlink(rtrim($path, '/') . '/.diagnostic_probe');
    }
    return [$writable && ($probe !== false), $path . ' — ' . ($probe !== false ? 'probe file wrote OK' : 'probe write failed')];
}

[$upOk, $upDetail] = dir_writable_test(UPLOAD_DIR, 'uploads');
$checks[] = status_row('Writable: assets/uploads/', $upOk, $upDetail . (!$upOk ? ' — in hPanel File Manager, chmod this folder 0755 (or 0775 only if 0755 fails) and ensure Owner matches the PHP user.' : ''));

[$logOk, $logDetail] = dir_writable_test(LOG_DIR, 'logs', true);
$checks[] = status_row('Writable: logs/', $logOk, $logDetail . (!$logOk ? ' — app.log cannot be written until this is fixed.' : ''));

/* ---------- DB connection probes ---------- */

$pdoResults = [];
$candidates = array_values(array_unique([DB_HOST, 'localhost', '127.0.0.1']));
$anyConnected = false;
$connectedHost = null;
$connectedDb   = null;

foreach ($candidates as $host) {
    $dsn = 'mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $t0 = microtime(true);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ]);
        $dt = round((microtime(true) - $t0) * 1000, 1);
        $anyConnected = true;
        if ($host === DB_HOST) {
            $connectedHost = $host;
            $connectedDb   = DB_NAME;
        }
        $pdoResults[$host] = [true, $dt];
    } catch (PDOException $e) {
        $code = (int) ($e->errorInfo[1] ?? $e->getCode());
        $dt = round((microtime(true) - $t0) * 1000, 1);
        $pdoResults[$host] = [false, $dt, $code, $e->getMessage()];
    }
}

foreach ($pdoResults as $host => $r) {
    [$ok, $ms] = $r;
    $marker = ($host === DB_HOST) ? ' ← currently in config.php' : '';
    if ($ok) {
        $checks[] = status_row("PDO connect via host=$host", true, "{$ms}ms$marker");
    } else {
        [, , $code, $msg] = $r;
        // Hint based on code.
        $hint = '';
        if ($code === 1045) $hint = ' — credentials rejected. Verify DB_USER/DB_PASS in config.php match hPanel exactly.';
        if ($code === 1049) $hint = ' — database name not found. Create it in hPanel MySQL Databases first.';
        if ($code === 2002 || $code === 2003) $hint = ' — cannot reach MySQL on this host. Try the other candidate above.';
        $maskedMsg = preg_replace("/access denied for user '([^']+)'@'([^']+)'/i", "access denied for user ***@***", $msg) ?? $msg;
        $checks[] = status_row("PDO connect via host=$host", false, "code=$code, {$ms}ms — " . substr($maskedMsg, 0, 220) . $hint . $marker);
    }
}

/* ---------- Schema checks (when connected) ---------- */

$tablesSummary = [];
if ($anyConnected) {
    // Use whatever host worked.
    $dsn = 'mysql:host=' . ($connectedHost ?? '127.0.0.1') . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Tables we expect after each migration layer.
        $tables = [
            'settings',
            'list_items',
            'partners',
            'admin_users',  // v1 base — present before 002, then renamed away.
            'users',        // 002 renames admin_users → users.
            'pathway_stages',
            'churches',
            'indicators',
            'media',
            'stories',
            'resources',
            'submissions',
            'kobo_forms',
            'audit_log',
            'login_attempts',
            'password_resets',
            'milestones',
            'indicator_history',
            'story_media',
        ];
        foreach ($tables as $t) {
            $row = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
            $exists = (bool) $row;
            $count  = null;
            $roleCol = null;
            if ($exists) {
                try {
                    $count = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                } catch (Throwable $e) {
                    $count = null;
                }
            }
            if ($t === 'users' && $exists) {
                $roleCol = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
            }
            $tablesSummary[$t] = ['exists' => $exists, 'count' => $count, 'roleCol' => $roleCol];
        }
    } catch (Throwable $e) {
        $checks[] = status_row('Schema probe', false, 'Connected but could not inspect tables: ' . substr($e->getMessage(), 0, 180));
    }
}

if ($anyConnected && $tablesSummary) {
    // v1: settings, list_items, partners, admin_users exist
    $v1MinOk = !empty($tablesSummary['settings']['exists'])
            && !empty($tablesSummary['list_items']['exists'])
            && !empty($tablesSummary['partners']['exists'])
            && !empty($tablesSummary['admin_users']['exists']);
    $v1AltOk = !empty($tablesSummary['settings']['exists'])
            && !empty($tablesSummary['list_items']['exists'])
            && !empty($tablesSummary['partners']['exists'])
            && !empty($tablesSummary['users']['exists']);

    $checks[] = status_row('Schema: v1 base tables present', $v1MinOk || $v1AltOk,
        ($v1MinOk || $v1AltOk)
            ? 'install.sql applied (settings/list_items/partners + ' . ($v1AltOk ? 'users (renamed)' : 'admin_users') . ').'
            : 'Missing tables — import install.sql first (phpMyAdmin > Import).',
        !($v1MinOk || $v1AltOk));

    // 002_platform: users table EXISTS AND has a `role` column.
    $platformOk = !empty($tablesSummary['users']['exists'])
               && !empty($tablesSummary['users']['roleCol'])
               && !empty($tablesSummary['pathway_stages']['exists'])
               && !empty($tablesSummary['churches']['exists'])
               && !empty($tablesSummary['indicators']['exists']);
    $checks[] = status_row('Schema: 002_platform migration applied', $platformOk,
        $platformOk
            ? 'Yes — users.role column exists, pathway_stages/churches/indicators present. All code that queries `users` works.'
            : 'No — admin/frontend code queries `users.role`; if you ran 002_platform.sql it failed part-way. Re-run 002_platform.sql (it is safe). If you have NOT run 002_platform.sql yet AND the site shows a DB "table not found" error on the homepage, this is the root cause.',
        !$platformOk);

    // Show counts for key tables.
    $countBits = [];
    foreach (['settings', 'list_items', 'partners', 'users', 'admin_users', 'churches', 'stories', 'submissions'] as $t) {
        if (!empty($tablesSummary[$t]['exists']) && $tablesSummary[$t]['count'] !== null) {
            $countBits[] = "$t=" . $tablesSummary[$t]['count'];
        }
    }
    if ($countBits) {
        $checks[] = status_row('Row counts', null, implode(' · ', $countBits));
    }

    // Check that the default admin user exists in either users or admin_users.
    try {
        $adminRow = null;
        if (!empty($tablesSummary['users']['exists'])) {
            $s = $pdo->prepare('SELECT id, username, role, is_active FROM users WHERE username = ? LIMIT 1');
            $s->execute(['admin']);
            $adminRow = $s->fetch();
        } elseif (!empty($tablesSummary['admin_users']['exists'])) {
            $s = $pdo->prepare('SELECT id, username FROM admin_users WHERE username = ? LIMIT 1');
            $s->execute(['admin']);
            $adminRow = $s->fetch();
        }
        if ($adminRow) {
            $roleBit = isset($adminRow['role']) ? (' role=' . $adminRow['role']) : '';
            $activeBit = isset($adminRow['is_active']) ? (' active=' . ($adminRow['is_active'] ? 'yes' : 'NO')) : '';
            $checks[] = status_row('Default admin account ("admin")', true, "present in {$connectedDb} DB$roleBit$activeBit. Log in at /admin/login.php");
        } else {
            $checks[] = status_row('Default admin account ("admin")', false, 'Not present in users or admin_users. Re-run the admin INSERT from install.sql (it is INSERT IGNORE now, so safe).');
        }
    } catch (Throwable $e) {
        $checks[] = status_row('Default admin account probe', false, substr($e->getMessage(), 0, 180));
    }
}

/* ---------- app.log tail ---------- */

$logTail = [];
if (is_file(LOG_PATH) && is_readable(LOG_PATH)) {
    $lines = @file(LOG_PATH, FILE_IGNORE_NEW_LINES);
    if ($lines) {
        $logTail = array_slice($lines, -40);
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mission Seedlings — diagnostics</title>
<meta name="robots" content="noindex,nofollow">
<style>
  body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f7f5ef;color:#1d2a1d;margin:0;padding:2rem 1rem 4rem;}
  .wrap{max-width:860px;margin:0 auto;}
  h1{font-size:1.4rem;margin:0 0 .25rem;}
  .sub{color:#475549;margin:0 0 1.5rem;}
  .card{background:#fff;border:1px solid #e6e2d2;border-radius:14px;padding:1rem 1.1rem;margin:0 0 1.25rem;}
  h2{font-size:1rem;margin:0 0 .75rem;}
  .row{display:flex;gap:.65rem;padding:.45rem 0;border-bottom:1px dashed #ece8d8;align-items:flex-start;}
  .row:last-child{border-bottom:0;}
  .dot{width:22px;height:22px;flex:0 0 22px;display:inline-grid;place-items:center;border-radius:50%;font-weight:700;font-size:12px;}
  .row.ok    .dot{background:#d9ecd1;color:#1f4324;}
  .row.fail  .dot{background:#f3d3d1;color:#6f1e1b;}
  .row.warn  .dot{background:#f8e5b9;color:#6b4a07;}
  .row.neutral .dot{background:#e5e2d5;color:#4a4a40;}
  .label{font-weight:600;}
  .detail{color:#445;font-size:.86rem;margin-top:.15rem;white-space:pre-wrap;word-break:break-word;}
  .log{font-family:ui-monospace,Menlo,monospace;font-size:.8rem;white-space:pre-wrap;word-break:break-word;background:#f1efe6;padding:.75rem;border-radius:8px;max-height:340px;overflow:auto;}
  .banner{border-radius:12px;padding:.9rem 1rem;margin:0 0 1rem;}
  .banner.warn {background:#fff6df;border:1px solid #e9d79a;color:#5a420a;}
  .banner.ok   {background:#e2f0dc;border:1px solid #bfdcaf;color:#264a1f;}
  .banner.fail {background:#fbe0dd;border:1px solid #e8b9b4;color:#6a1e1a;}
  code{background:#f1efe6;padding:.1rem .3rem;border-radius:4px;}
  .pill{display:inline-block;padding:.1rem .45rem;border-radius:999px;background:#e5e2d5;font-size:.75rem;margin-left:.25rem;}
  a{color:#2e5d30;}
</style>
</head>
<body>
<div class="wrap">
  <h1>Mission Seedlings — diagnostics</h1>
  <p class="sub">A read-only status check. No passwords, keys, or visitor data are shown here.</p>

  <?php if ($anyConnected && $platformOk): ?>
    <div class="banner ok">✅ Database looks healthy and the 002_platform migration is applied. The site should load normally.</div>
  <?php elseif ($anyConnected && !$platformOk): ?>
    <div class="banner warn">⚠️  Database connects but the 002_platform migration is missing. See the "Schema: 002_platform" check below for next steps.</div>
  <?php elseif (!$anyConnected && $dbUserOk && $dbNameOk && $dbPassOk): ?>
    <div class="banner fail">❌ Cannot connect to MySQL. The credentials in config.php look plausible but no host candidate succeeded. Use the rows below to pick a different DB_HOST (e.g. 127.0.0.1 instead of localhost).</div>
  <?php else: ?>
    <div class="banner fail">❌ config.php still has placeholder values. At minimum edit config.php lines 27–30 and fill DB_NAME / DB_USER / DB_PASS from the hPanel MySQL Databases screen.</div>
  <?php endif; ?>

  <div class="card">
    <h2>Checks <span class="pill"><?= count($checks) ?> items</span></h2>
    <?= implode('', $checks) ?>
  </div>

  <div class="card">
    <h2>logs/app.log — last <?= count($logTail) ?> lines</h2>
    <?php if (!$logTail): ?>
      <p class="detail">No log entries yet. Reload the homepage once so the error is triggered, then refresh this page.</p>
    <?php else: ?>
      <div class="log"><?= htmlspecialchars(implode("\n", $logTail)) ?></div>
    <?php endif; ?>
    <p class="detail">For the full file, use FTP / hPanel File Manager and open: <code>logs/app.log</code></p>
  </div>

  <div class="card">
    <h2>Fast path — most common fixes</h2>
    <ol>
      <li>If PDO fails on <em>localhost</em> but works on <em>127.0.0.1</em>, edit <code>config.php</code> and change <code>DB_HOST</code> from <code>localhost</code> to <code>'127.0.0.1'</code>.</li>
      <li>If credentials are rejected (1045), open hPanel → MySQL Databases, copy the <em>full prefixed</em> DB name and username — they start with something like <code>u89abcd12_</code>.</li>
      <li>If tables are missing, import SQL in this order:
        <ol type="a">
          <li><code>install.sql</code> — base 4 tables</li>
          <li><code>migrations/002_platform.sql</code> — expands to the full platform</li>
        </ol>
      </li>
      <li>If the 002_platform migration is missing, all <code>includes/auth.php</code> queries that SELECT from <code>users</code> fail on a missing table — this is the most common cause of "worked after install.sql, broke after migration." Run the 002_platform.sql import again.</li>
    </ol>
  </div>
</div>
</body>
</html>
