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

## Known follow-ups

Deliberately left for a later phase, in rough priority order:

1. **Self-hosted fonts.** The three Google Fonts families are still loaded from
   `fonts.googleapis.com` — two render-blocking round-trips. Downloading them as WOFF2 into
   `assets/fonts/` with `font-display:swap` is the single biggest win for slow connections.
2. **Automatic image resizing.** Uploads are stored at their original dimensions. Generating a
   WebP thumbnail on upload would cut page weight substantially.
3. **Email.** Password reset by emailed token is schemed (`password_resets`) but not wired up —
   Super Admins issue temporary passwords instead. Enable it once Hostinger mail is verified.
4. **GIS map.** `churches.latitude` / `longitude` are captured and staff-only, ready for a map.
5. **Full-text search** across stories.
