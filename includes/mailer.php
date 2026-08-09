<?php
/**
 * Email delivery.
 *
 * A small hand-rolled SMTP client, because this project has no Composer and so
 * no PHPMailer. It covers exactly what the site needs: one recipient, a
 * multipart/alternative body, STARTTLS or implicit TLS, and AUTH LOGIN/PLAIN.
 * It is not a general-purpose mail library — no attachments, no bulk sending.
 *
 * Configuration comes from Settings (see admin/email.php), with constants in
 * config.php taking precedence for anyone who prefers file-based config.
 * The SMTP password is stored encrypted; see encrypt_secret() below.
 *
 * Sending never throws. A mail failure must not break account creation — the
 * caller is given a copyable invitation link instead.
 */

/* ===========================================================================
   Secret storage
   =========================================================================== */

/** Derives the symmetric key from APP_KEY. Rotating APP_KEY invalidates secrets. */
function secret_key(): string {
    return hash('sha256', 'seedlings-secret|' . APP_KEY, true);
}

/** AES-256-GCM, returning base64(iv|tag|ciphertext). Null if OpenSSL is absent. */
function encrypt_secret(string $plain): ?string {
    if (!function_exists('openssl_encrypt')) return null;
    $iv  = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return null;
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $stored): ?string {
    if (!$stored || !function_exists('openssl_decrypt')) return null;
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 29) return null;
    $plain = openssl_decrypt(
        substr($raw, 28), 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA,
        substr($raw, 0, 12), substr($raw, 12, 16)
    );
    return $plain === false ? null : $plain;
}

/* ===========================================================================
   Configuration
   =========================================================================== */

/**
 * Resolved mail configuration. config.php constants win over Settings, so a
 * developer can pin credentials outside the database if they want to.
 */
function mail_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $pick = fn(string $const, string $key, string $default = '')
        => defined($const) ? (string) constant($const) : get_setting($key, $default);

    $cfg = [
        'enabled'    => defined('SMTP_HOST')
                          ? SMTP_HOST !== ''
                          : (get_setting('smtp_enabled', '0') === '1' && get_setting('smtp_host') !== ''),
        'host'       => $pick('SMTP_HOST', 'smtp_host'),
        'port'       => (int) $pick('SMTP_PORT', 'smtp_port', '587'),
        'encryption' => $pick('SMTP_ENCRYPTION', 'smtp_encryption', 'tls'),  // tls | ssl | none
        'username'   => $pick('SMTP_USERNAME', 'smtp_username'),
        'password'   => defined('SMTP_PASSWORD')
                          ? (string) SMTP_PASSWORD
                          : (decrypt_secret(get_setting('smtp_password_enc')) ?? ''),
        'from_email' => $pick('MAIL_FROM_EMAIL', 'mail_from_email'),
        'from_name'  => $pick('MAIL_FROM_NAME', 'mail_from_name', 'Mission Seedlings'),
        'reply_to'   => get_setting('mail_reply_to'),
        'signoff'    => get_setting('mail_signoff', 'The Mission Seedlings team'),
    ];

    // A From address is required by every mail server. Fall back to the
    // SMTP username, then to the site's contact address.
    if ($cfg['from_email'] === '') {
        $cfg['from_email'] = filter_var($cfg['username'], FILTER_VALIDATE_EMAIL)
            ? $cfg['username']
            : get_setting('contact_email');
    }
    return $cfg;
}

/** True when the site can actually send mail through SMTP. */
function mail_is_configured(): bool {
    return mail_config_problems() === [];
}

/**
 * Precisely why mail is not going out, in the order worth fixing.
 *
 * A bare "Not configured" is useless — the whole point of this list is that the
 * status panel can say which switch or field is the problem.
 *
 * @return array<int, array{title:string, fix:string}>
 */
function mail_config_problems(): array {
    $c = mail_config();
    $problems = [];

    if ($c['host'] === '') {
        $problems[] = [
            'title' => 'No outgoing mail server',
            'fix'   => 'Fill in the SMTP host below — on Hostinger it is usually smtp.hostinger.com.',
        ];
    }
    if (!$c['enabled'] && $c['host'] !== '') {
        $problems[] = [
            'title' => 'SMTP is switched off',
            'fix'   => 'The details are saved but not in use. Tick "Use SMTP to send email" below and save.',
        ];
    }
    if ($c['from_email'] === '') {
        $problems[] = [
            'title' => 'No From address',
            'fix'   => 'Every mail server needs one. Use a mailbox on your own domain, such as noreply@'
                     . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'yourdomain.com') . '.',
        ];
    } elseif (!filter_var($c['from_email'], FILTER_VALIDATE_EMAIL)) {
        $problems[] = [
            'title' => 'The From address is not a valid email address',
            'fix'   => 'Check it for typos — it must look like noreply@yourdomain.com.',
        ];
    }
    if ($c['enabled'] && $c['host'] !== '' && $c['username'] !== '' && $c['password'] === '') {
        $problems[] = [
            'title' => 'No password saved for the SMTP username',
            'fix'   => 'Enter the mailbox password below. Almost every provider requires one.',
        ];
    }
    return $problems;
}

/* ===========================================================================
   Templates
   =========================================================================== */

/**
 * Renders an email template to ['subject','html','text'].
 *
 * Templates live in includes/emails/ and set $subject, then echo the body
 * content — the shared layout wraps it.
 */
function render_email(string $template, array $vars = []): array {
    $file = BASE_PATH . '/includes/emails/' . basename($template) . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Unknown email template: ' . $template);
    }

    $cfg     = mail_config();
    $subject = '';
    $text    = '';           // templates may set a plain-text alternative

    extract($vars, EXTR_SKIP);
    ob_start();
    include $file;
    $body = ob_get_clean();

    ob_start();
    include BASE_PATH . '/includes/emails/layout.php';
    $html = ob_get_clean();

    if ($text === '') {
        $text = email_html_to_text($body);
    }
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/** A readable plain-text fallback, so the message is not blank in text-only clients. */
function email_html_to_text(string $html): string {
    $t = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
    $t = preg_replace('#</(p|div|h1|h2|h3|tr|li)>#i', "\n\n", $t) ?? $t;
    // Keep the destination of links — the whole point of an invitation email.
    $t = preg_replace('#<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>#is', '$2: $1', $t) ?? $t;
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t) ?? $t;
    $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
    return trim($t);
}

/* ===========================================================================
   Sending
   =========================================================================== */

/**
 * Renders a template and sends it.
 *
 * @return array{ok: bool, error: ?string}
 */
function send_email(string $to, string $template, array $vars = [], ?int $userId = null): array {
    try {
        $msg = render_email($template, $vars);
    } catch (Throwable $e) {
        log_email($to, '(render failed)', $template, 'none', false, $e->getMessage(), $userId);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    return send_raw_email($to, $msg['subject'], $msg['html'], $msg['text'], $template, $userId);
}

/**
 * Sends an already-rendered message. Uses SMTP when configured, otherwise falls
 * back to PHP's mail() — which works on some shared hosts and quietly does
 * nothing on others, so the result is logged either way.
 */
function send_raw_email(
    string $to, string $subject, string $html, string $text,
    ?string $template = null, ?int $userId = null
): array {
    $cfg = mail_config();

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_email($to, $subject, $template, 'none', false, 'Invalid recipient address', $userId);
        return ['ok' => false, 'error' => 'That is not a valid email address.'];
    }
    if ($cfg['from_email'] === '') {
        log_email($to, $subject, $template, 'none', false, 'No From address configured', $userId);
        return ['ok' => false, 'error' => 'Email is not configured yet — no sender address has been set.'];
    }

    $transport = mail_is_configured() ? 'smtp' : 'mail';
    try {
        if ($transport === 'smtp') {
            smtp_send($cfg, $to, $subject, $html, $text);
        } else {
            php_mail_send($cfg, $to, $subject, $html, $text);
        }
        log_email($to, $subject, $template, $transport, true, null, $userId);
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('[Seedlings mail] ' . $e->getMessage());
        log_email($to, $subject, $template, $transport, false, $e->getMessage(), $userId);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function log_email(
    string $to, string $subject, ?string $template, string $transport,
    bool $ok, ?string $error, ?int $userId
): void {
    if (!schema_has_table('email_log')) {
        return;
    }
    try {
        db()->prepare(
            'INSERT INTO email_log (to_email, subject, template, transport, status, error, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($to, 0, 190),
            mb_substr($subject, 0, 255),
            $template,
            $transport,
            $ok ? 'sent' : 'failed',
            $error !== null ? mb_substr($error, 0, 500) : null,
            $userId,
        ]);
    } catch (Throwable $e) {
        error_log('[Seedlings mail log] ' . $e->getMessage());
    }
}

/** Builds the MIME body shared by both transports. */
function build_mime(array $cfg, string $subject, string $html, string $text): array {
    $boundary = 'sdl_' . bin2hex(random_bytes(12));

    $headers = [];
    $headers[] = 'From: ' . mime_addr($cfg['from_name'], $cfg['from_email']);
    if (!empty($cfg['reply_to']) && filter_var($cfg['reply_to'], FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $cfg['reply_to'];
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    // Transactional mail: keep it out of bulk-mail filters and auto-responders.
    $headers[] = 'Auto-Submitted: auto-generated';
    $headers[] = 'X-Auto-Response-Suppress: All';

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text)) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= "--$boundary--\r\n";

    return ['headers' => $headers, 'body' => $body];
}

/** RFC 2047 encoding, so non-ASCII names and subjects survive. */
function mime_encode(string $text): string {
    return preg_match('/[^\x20-\x7E]/', $text)
        ? '=?UTF-8?B?' . base64_encode($text) . '?='
        : $text;
}

function mime_addr(string $name, string $email): string {
    $name = trim(str_replace(['"', "\r", "\n"], '', $name));
    return $name === '' ? $email : '"' . mime_encode($name) . '" <' . $email . '>';
}

function php_mail_send(array $cfg, string $to, string $subject, string $html, string $text): void {
    $mime = build_mime($cfg, $subject, $html, $text);
    $ok = @mail(
        $to,
        mime_encode($subject),
        $mime['body'],
        implode("\r\n", $mime['headers']),
        '-f' . $cfg['from_email']
    );
    if (!$ok) {
        throw new RuntimeException(
            "The server's basic mail function was rejected, and SMTP is not switched on. "
            . "Set it up under System → Email."
        );
    }
}

/* ---------------------------------------------------------------------------
   Minimal SMTP conversation
   --------------------------------------------------------------------------- */

function smtp_send(array $cfg, string $to, string $subject, string $html, string $text): void {
    $encryption = strtolower($cfg['encryption']);
    $host       = $cfg['host'];
    $port       = $cfg['port'] ?: ($encryption === 'ssl' ? 465 : 587);
    $target     = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

    $context = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    ]]);

    $errno = 0; $errstr = '';
    $socket = @stream_socket_client($target, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        throw new RuntimeException("Could not reach the mail server ($host:$port): $errstr");
    }
    stream_set_timeout($socket, 15);

    try {
        smtp_expect($socket, 220);

        $ehloName = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
        smtp_cmd($socket, 'EHLO ' . $ehloName, 250);

        if ($encryption === 'tls') {
            smtp_cmd($socket, 'STARTTLS', 220);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
                throw new RuntimeException('The mail server refused the secure (TLS) handshake.');
            }
            // A fresh EHLO is required after the connection is upgraded.
            smtp_cmd($socket, 'EHLO ' . $ehloName, 250);
        }

        if ($cfg['username'] !== '') {
            smtp_cmd($socket, 'AUTH LOGIN', 334);
            smtp_cmd($socket, base64_encode($cfg['username']), 334);
            // A wrong password surfaces here; say so plainly rather than "535".
            try {
                smtp_cmd($socket, base64_encode($cfg['password']), 235);
            } catch (RuntimeException $e) {
                throw new RuntimeException('The mail server rejected the username or password.');
            }
        }

        smtp_cmd($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>', 250);
        smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($socket, 'DATA', 354);

        $mime = build_mime($cfg, $subject, $html, $text);
        $data = 'Date: ' . date('r') . "\r\n"
              . 'To: ' . $to . "\r\n"
              . 'Subject: ' . mime_encode($subject) . "\r\n"
              . 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . ($ehloName) . ">\r\n"
              . implode("\r\n", $mime['headers']) . "\r\n\r\n"
              . $mime['body'];

        // Dot-stuffing: a line that is just "." would otherwise end the message.
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($socket, $data . "\r\n.\r\n");
        smtp_expect($socket, 250);

        @fwrite($socket, "QUIT\r\n");
    } finally {
        @fclose($socket);
    }
}

/** @param int|int[] $expect */
function smtp_cmd($socket, string $command, $expect): string {
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expect);
}

/** @param int|int[] $expect */
function smtp_expect($socket, $expect): string {
    $expect = (array) $expect;
    $response = '';

    // Multi-line replies look like "250-EXTENSION" and end with "250 ".
    while (($line = fgets($socket, 1024)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') break;
    }
    $meta = stream_get_meta_data($socket);
    if (!empty($meta['timed_out'])) {
        throw new RuntimeException('The mail server stopped responding.');
    }

    $code = (int) substr(ltrim($response), 0, 3);
    if (!in_array($code, $expect, true)) {
        throw new RuntimeException('Mail server said: ' . trim(preg_replace('/\s+/', ' ', $response) ?? $response));
    }
    return $response;
}

/* ===========================================================================
   Log reading
   =========================================================================== */

function get_email_log(int $limit = 40): array {
    // The table arrives with migration 003; before that this is simply empty
    // rather than a fatal error on a page whose job is to explain the problem.
    if (!schema_has_table('email_log')) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    return db()->query("SELECT * FROM email_log ORDER BY created_at DESC, id DESC LIMIT $limit")->fetchAll();
}
