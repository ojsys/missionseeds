# Mission Seedlings — Developer Notes

Plain PHP 8 + MySQL/MariaDB. No framework, no Composer, no build step. Built to run on Hostinger
shared hosting (LiteSpeed) and to be maintainable by whoever inherits it.

---

## Layout

```
index.php                front controller — every public request lands here
config.php               credentials + SITE_URL + APP_KEY (config.local.php overrides it locally)
.htaccess                HTTPS, security headers, clean-URL rewrite, directory blocks

includes/
  bootstrap.php          the single entry point every page requires
  router.php             route table + dispatch
  layout.php             public <head>, nav, footer, admin bar
  auth.php               roles, capabilities, sessions, throttling, CSRF, audit
  db.php                 PDO singleton
  helpers.php            escaping, settings, uploads, formatting, rich text
  icons.php              the curated SVG icon set
  content_keys.php       allow-list of inline-editable settings keys, grouped by page
  models/
    project.php          churches, pathway stages, milestones, indicators
    stories.php          stories, media, gallery, the approval workflow
    resources.php        resource hub + Kobo form links
    kobo.php             KoboToolbox API (stubbed until a token is configured)
    submissions.php      contact / interest / partnership enquiries
    users.php            account management + audit log reads
    tokens.php           single-use invitation and reset tokens
  mailer.php             SMTP client, templates, delivery log
  emails/                HTML email templates (layout + one file per message)

pages/                   public templates, rendered inside the layout
  partials/story-card.php

admin/                   staff area (super_admin, project_manager, editor)
  partials/nav.php       sidebar navigation model + interface icons
  partials/shell.php     the shared sidebar/topbar chrome
  partials/header.php    admin entry point into the shell
  partials/footer.php    closing markup + drawer script
  partials/form.php      field rendering helpers for the CRUD screens
portal/                  church coordinator area (same shell, shorter nav)
migrations/              numbered SQL, run once each, in order
bin/kobo-sync.php        cron entry point for future Kobo sync
DOCS/                    this file + the staff guide
```

`includes/`, `pages/`, `migrations/`, `bin/`, and `DOCS/` are blocked in `.htaccess` — they are
only ever reached through `index.php` or the command line.

---

## How a request flows

1. `.htaccess` sends anything that is not a real file to `index.php`.
2. `index.php` requires `includes/bootstrap.php` (config, db, auth, helpers, all models) and
   `includes/router.php`, then calls `dispatch()`.
3. `dispatch()` matches the path against `ROUTES` / `ROUTE_PATTERNS` and calls `render()`.
4. `render()` buffers the template from `pages/`, so the template can set `$meta` (title,
   description, canonical, body class) before `<head>` is written, and can call `not_found()`
   mid-render to bail out to the 404 page.
5. `layout_render($meta, $content)` writes the page.

`admin/` and `portal/` are ordinary PHP files served directly. They require `bootstrap.php`
themselves and gate at the top with `require_capability()` or `require_role()`.

### The admin shell

Both areas share one chrome. A page sets a few variables and includes its header:

```php
$pageEyebrow = 'Project';                 // optional small label
$pageTitle   = 'Participating churches';  // <title>, and the h1
$pageIntro   = 'One line of context.';    // optional
$pageActions = '<a class="btn">…</a>';    // optional, raw HTML, right of the heading
$active      = 'churches';                // nav key — drives the highlight and breadcrumb
include __DIR__ . '/partials/header.php';
```

The sidebar comes from `admin_nav_groups()` / `portal_nav_groups()` in `partials/nav.php`, built
from capabilities so a role never sees a link it would be refused on. Badge counts (pending
stories, new enquiries) are read there too. Add a page by adding one entry to the relevant group.

**Scrolling:** the content column scrolls, not the page — `body.admin` is `overflow:hidden` and
`.shell-main` is the scroll container. This is deliberate. The public stylesheet sets
`overflow-x:hidden` on `<html>` (the hero and growth vine need it), which makes `<html>` the scroll
container and silently breaks `position:sticky` for everything inside it. Giving the admin its own
scrolling column sidesteps that: the sidebar is simply full height, and the topbar sticks inside a
container that really is the scroller. Below 960px the sidebar becomes a `position:fixed`
off-canvas drawer, which is unaffected either way.

Adding a page: create `pages/thing.php`, add `'thing' => 'thing'` to `ROUTES`, add its copy keys
to `CONTENT_KEYS`, and add a nav entry in `nav_items()` if it belongs in the menu.

---

## Roles and capabilities

Four roles, defined in `includes/auth.php`. Pages never check a role directly; they check a named
capability, so changing what a Project Manager may do is one line in `ROLE_CAPABILITIES`.

```php
require_capability('manage_project');   // 403 unless the role has it
require_role(['church_coordinator']);   // when the role itself is the point
user_can('publish_stories');            // for conditional UI
```

`current_user()` reads the database on every request rather than trusting the session, so
deactivating an account or changing a role takes effect on the next page load.

### Ownership scoping

Coordinator scoping is enforced in the model layer, never by a hidden form field. `save_story()`
assigns `church_id` from the signed-in user and downgrades any attempt to set `published`. A
tampered request changes nothing. `can_edit_story()` is the single ownership rule; call it before
showing an edit link *and* before writing.

---

## Privacy

The schema has **no column anywhere** for participant names, phone numbers, household income,
national ID, household GPS, seed-fund balances, budgets, staff allowances, or cooperative bank
details. That data lives only in KoboToolbox.

This is the main structural safeguard in the project: exposure is impossible rather than merely
unlikely. **Please do not add such columns.** If a future requirement seems to need one, the answer
is almost certainly a Kobo form plus an aggregate indicator.

Related conventions:

- Public read functions default to published-only; showing drafts requires passing an explicit flag.
- Restricted resources are filtered out of the SQL query, not hidden with CSS.
- `submissions` holds contact PII and is gated behind `view_submissions`; the audit log records
  submission IDs only, never the enquirer's details.
- IP addresses are stored as salted SHA-256 hashes, for rate limiting only.
- `media.has_consent` flags photos of identifiable people.

---

## Security

- Sessions: `httponly`, `samesite=Lax`, `secure` under HTTPS; regenerated on login; 30-minute idle
  and 12-hour absolute timeouts; bound to a user-agent fingerprint.
- Login throttling: 5 failures per username *or* IP in 15 minutes (`login_attempts`).
  `attempt_login()` always runs a hash comparison, even for unknown usernames, so timing does not
  reveal whether an account exists.
- CSRF on every mutating POST: `csrf_field()` in the form, `require_csrf()` at the top of the handler.
- Uploads sniff the real MIME type with `finfo`; SVG is refused outright (it can carry script and
  is served from our own origin). `assets/uploads/.htaccess` disables script execution.
- `rich_text()` escapes everything and then re-adds a tiny markdown subset, so no author can inject
  HTML.
- Every mutating admin action calls `audit()`.

---

## Local development

```bash
# 1. Database
mysql -e "CREATE DATABASE seedlings_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql seedlings_dev < install.sql
mysql seedlings_dev < migrations/002_platform.sql

# 2. config.local.php (git-ignored, overrides config.php)
cat > config.local.php <<'PHP'
<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'seedlings_dev');
define('DB_USER', 'youruser');
define('DB_PASS', 'yourpass');
define('SITE_URL', 'http://localhost:8000');
define('APP_KEY', 'any-long-random-string-for-local-only');
PHP

# 3. Serve. PHP's built-in server ignores .htaccess, so it needs a router shim:
php -S localhost:8000 -t . dev-router.php
```

`dev-router.php` (not committed — create it locally if you want clean URLs):

```php
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(includes|pages|migrations|DOCS|bin)(/|$)#', $path)) { http_response_code(403); return true; }
if ($path !== '/' && file_exists($_SERVER['DOCUMENT_ROOT'] . $path) && !is_dir($_SERVER['DOCUMENT_ROOT'] . $path)) return false;
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
```

Browse on the **same hostname as `SITE_URL`** — `url()` builds absolute links from it.
(Assets and images use root-relative paths via `asset()` / `image_url()`, so they survive a
hostname mismatch; page links do not.)

---

## Migrations

**`includes/schema.php` detects unimported migrations** and shows a banner across the admin naming
the exact file. Migrations are imported by hand through phpMyAdmin, so forgetting one is normal;
without the check the symptom is a blank page or a 500 with no explanation. When you add a
migration, add a line to `migration_checks()` — one representative table or column is enough.

Features that need a migration degrade rather than crash: `admin/email.php` explains what to
import, `get_email_log()` returns empty, and `create_token()` throws a message naming the file.

Numbered, run once, in order. `install.sql` is the original v1 schema; `migrations/002_platform.sql`
turns it into the full platform and is **not** idempotent (it renames `admin_users` to `users`).

Adding a migration: create `migrations/003_whatever.sql`, note it in the deploy doc, and run it
against the live database before uploading code that depends on it.

---

## KoboToolbox

Phase one links to forms and does not read from them. Everything for live sync is already in place:

- `indicators.source` / `kobo_form_id` / `kobo_field` / `last_synced_at`
- `kobo_forms.cached_summary`
- `includes/models/kobo.php` with a working API client and a stubbed
  `kobo_sync_indicator()`
- `bin/kobo-sync.php` as the cron entry point

To switch it on: set `KOBO_API_TOKEN` in `config.php`, point an indicator at a form UID, and
schedule `bin/kobo-sync.php` daily. No schema change needed.

Only aggregate counts should ever cross that boundary. Raw submissions contain participant PII and
must stay in Kobo.

---

## Email

`includes/mailer.php` is a small hand-rolled SMTP client — this project has no Composer, so no
PHPMailer. It does exactly what the site needs and no more: one recipient, multipart/alternative,
STARTTLS or implicit TLS, AUTH LOGIN. No attachments, no bulk sending. Please do not grow it into a
general mail library; if the project ever needs that, add Composer and swap it out.

**TLS is verified.** `verify_peer` and `verify_peer_name` are on, and there is deliberately no
option to disable them — an untrusted certificate fails with a clear message rather than silently
sending credentials over an unverified connection.

**Configuration** resolves in `mail_config()`: `config.php` constants (`SMTP_HOST`, `SMTP_PORT`,
`SMTP_USERNAME`, `SMTP_PASSWORD`, …) win, otherwise Settings. The SMTP password in the database is
AES-256-GCM encrypted with a key derived from `APP_KEY` — see `encrypt_secret()`. **Rotating
`APP_KEY` makes the stored password undecryptable**; it has to be re-entered.

**Templates** live in `includes/emails/`. Each sets `$subject` and echoes body HTML; `layout.php`
wraps it. They are table-based with inline styles on purpose — email clients still do not support
stylesheets or flexbox reliably. A plain-text alternative is generated automatically unless the
template sets `$text`.

Add one with `render_email('name', $vars)` / `send_email($to, 'name', $vars)`.

**Sending never throws.** A mail failure must not break account creation, so `send_email()` returns
`['ok' => bool, 'error' => ?string]` and logs to `email_log`. Every caller that matters offers a
copyable fallback link — email in rural Nigeria is not guaranteed and WhatsApp usually is.

### Enquiry notifications

`notify_new_submission()` in `includes/models/submissions.php`, fired from `pages/contact.php`
inside a `register_shutdown_function()` that first calls `fastcgi_finish_request()`. The visitor's
"thank you" page is therefore already delivered before the mail server is contacted — measured at
~43ms even with SMTP unreachable. Do not move this back inline: a 12-second connect timeout would
make the contact form feel broken on a slow connection.

The notification deliberately omits the enquirer's email and phone. They stay in the admin inbox
behind a login, rather than being scattered across staff mailboxes and forwarded threads — the same
reasoning that keeps enquirer details out of the audit log.

### Tokens

`includes/models/tokens.php`. Invitations and resets share one mechanism, distinguished by
`password_resets.purpose`: invites last 7 days, resets 2 hours. Only a SHA-256 hash is stored, so a
database dump cannot be used to take over accounts. Issuing a token retires the previous one of the
same purpose; completing one retires all of them and clears any login lockout.

`create_user()` gives the account a random unusable password, so an unclaimed invitation is never a
way in. `reset_user_password()` scrambles the existing password immediately rather than waiting for
the reset to be completed.

`request_password_reset()` (the public "forgot password" form) returns nothing and says nothing
about whether the address exists — otherwise the form is an account-enumeration oracle. The page is
throttled through the same `login_attempts` table.

---

## Known follow-ups

Deliberately left for a later phase, in rough priority order:

1. **Self-hosted fonts.** The three Google Fonts families are still loaded from
   `fonts.googleapis.com` — two render-blocking round-trips. Downloading them as WOFF2 into
   `assets/fonts/` with `font-display:swap` is the single biggest win for slow connections.
2. **Automatic image resizing.** Uploads are stored at their original dimensions. Generating a
   WebP thumbnail on upload would cut page weight substantially.
3. **A real mail queue.** Sends happen inline (after the response, for the contact form). One
   recipient at a time is fine at this scale; a `mail_queue` table drained by cron would be the
   next step if volume grows.
4. **GIS map.** `churches.latitude` / `longitude` are captured and staff-only, ready for a map.
5. **Full-text search** across stories.
