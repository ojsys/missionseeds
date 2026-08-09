# Deploying Seedlings CMS to Hostinger hPanel

> Domain: **missionseedlings.com**
> Hosting plan: Any Hostinger shared plan with hPanel + PHP 8.0+ + MySQL

---

## ✅ BEFORE UPLOADING — 2 edits to `config.php`

Open `config.php` in a text editor and replace these **3 lines only**:

```php
define('DB_NAME', 'REPLACE_WITH_HOSTINGER_PREFIX_seedlings');    // Step 2 below gives you this
define('DB_USER', 'REPLACE_WITH_HOSTINGER_PREFIX_seeduser');     // Step 2 below gives you this
define('DB_PASS',  'REPLACE_WITH_A_STRONG_UNIQUE_PASSWORD');     // Use password generator (24+ chars)
```

Everything else (`SITE_URL`, `APP_KEY`, timezone, etc.) is already set for `missionseedlings.com`.

---

## 📋 Step-by-step in hPanel

### 1. Point DNS (if not done)
- hPanel → Websites → `missionseedlings.com` → **DNS zone editor**
- Ensure `A` record points to your Hostinger server IP (shown on the hPanel dashboard)
- For www: add a `CNAME` record `www → missionseedlings.com.`
- Wait 10–30 min propagation before SSL step.

### 2. Create MySQL database + user
hPanel → **Databases** → **MySQL Databases**

1. Under *Create a New Database*, fill:
   - **Database name suffix:** `seedlings`
   - **User name suffix:** `seeduser`
   - **Password:** use **Generate** button, 24 chars → **SAVE THIS SOMEWHERE** (you'll paste it into config.php)
2. Click **Create**
3. On the *Manage* screen, note the full auto-prefixed names. Hostinger prefixes everything with an 8-char account id like `u89abcd12`. Example:
   ```
   Full DB name:   u89abcd12_seedlings
   Full username:  u89abcd12_seeduser
   Host:           localhost   (always!)
   ```
4. → Plug those exact values into `config.php` (see *Before Uploading* above).

### 3. Import `install.sql` via phpMyAdmin
1. hPanel → Databases → *your* seedling DB → **Enter phpMyAdmin** (opens in new tab)
2. Left column → click the `u89abcd12_seedlings` database (select the DB, NOT the server level)
3. Top tabs → **Import**
4. Choose file → select `install.sql` from this project folder → **Import**
5. You should see "Import has been successfully finished, X queries executed" — and on the left 3 tables: `admin_users`, `list_items`, `settings`.

### 3b. Import `migrations/002_platform.sql` — REQUIRED

`install.sql` alone gives you the original single-page site. The full platform (roles, churches,
pathway, indicators, stories, resources, enquiries) comes from the migration.

1. Same phpMyAdmin tab, same database selected
2. **Import** → choose file → `migrations/002_platform.sql` → **Import**
3. The left column should now list ~19 tables, including `users` (renamed from `admin_users`),
   `churches`, `pathway_stages`, `milestones`, `indicators`, `stories`, `media`, `resources`,
   `kobo_forms`, `submissions`, and `audit_log`.

> Run these **in order and once each**. `002_platform.sql` renames `admin_users` to `users`, so
> re-running it will error. That is expected and harmless — it means it already ran.

Any future `migrations/003_*.sql` files are imported the same way, in number order, before you
upload code that depends on them.

### 4. Upload files via File Manager (or FTP / Hostinger Backup Upload)
Upload **everything inside the seedlings-cms folder** into the **web root** of missionseedlings.com.

**The web root on Hostinger is:**
```
public_html/
  ← upload EVERYTHING here (index.php, config.php, admin/, assets/, includes/, .htaccess, install.sql, etc.)
```

**DO NOT** nest the project inside a subfolder like `public_html/seedlings-cms/` — the URL would become missionseedlings.com/seedlings-cms instead of missionseedlings.com.

**Upload order tip:** Upload `config.php` LAST so no one hits the site before DB credentials are set.

**Folders structure after upload:**
```
public_html/
├── .htaccess              ← HTTPS, security headers, clean-URL rewrite
├── config.php             ← WITH your DB credentials filled in
├── index.php              ← front controller (routes every public page)
├── robots.txt
├── install.sql            ← (blocked from browsers by .htaccess)
├── README.md              ← (blocked from browsers by .htaccess)
├── DEPLOY-HOSTINGER.md    ← (blocked from browsers by .htaccess)
├── admin/                 ← staff area
├── portal/                ← church coordinator area
├── pages/                 ← public templates (403 to browsers)
├── includes/              ← app code + models (403 to browsers)
├── migrations/            ← SQL (403 to browsers)
├── bin/                   ← cron scripts (403 to browsers)
├── DOCS/                  ← staff guide + developer notes (403 to browsers)
└── assets/
    ├── css/  js/  img/
    └── uploads/           ← ⚠️  SET PERMISSIONS (step 5)
```

> **Do not skip `pages/`, `includes/`, `bin/`, or `migrations/`.** They are blocked from browsers
> but the application requires them. `.htaccess` returns 403 for any direct request to those paths.

### 5. Critical: set `assets/uploads/` permissions
The hero image save/change feature needs write access here.

1. hPanel File Manager → `assets/uploads/` → right-click → **Permissions** (CHMOD)
2. Try **755** first. If photo uploads later fail in Admin → try **775**.
3. Verify: `hero.jpg` inside uploads is reachable at `https://missionseedlings.com/assets/uploads/hero.jpg`.

### 6. Enable free Hostinger SSL certificate (HTTPS)
1. hPanel → Websites → `missionseedlings.com` → **SSL** tab
2. Under *Install SSL*, select **Hostinger SSL (Free)** → **Install**
3. Wait a moment, then toggle **Force HTTPS** to **ON** in the SSL screen.
4. The `.htaccess` we uploaded ALSO redirects HTTP → HTTPS. Having both is fine (belt + suspenders).
5. Open Chrome/Edge incognito → visit `https://missionseedlings.com` → confirm 🔒 padlock in address bar.

> ⚠️  Note: SSL installation can take 2-5 minutes. If you get privacy error, wait and refresh.

### 6b. Verify clean URLs work

The site uses clean URLs (`/about`, not `/about.php`), produced by the rewrite rule in `.htaccess`.
LiteSpeed honours it, but check before going further:

1. Visit `https://missionseedlings.com/about` — the About page should load.
2. Visit `https://missionseedlings.com/includes/db.php` — must return **403 Forbidden**.

If `/about` 404s, `mod_rewrite` is not active. In hPanel → Advanced → **PHP Configuration**, confirm
the site is served by LiteSpeed/Apache (not Nginx-only), and that `.htaccess` uploaded intact —
File Manager hides dotfiles until you enable *Show hidden files*.

### 7. Verify site + login + change admin password (URGENT!)
1. Visit `https://missionseedlings.com` — homepage loads (no DB errors, content visible)
2. Visit `https://missionseedlings.com/admin/login.php`
3. **Default credentials** (from install.sql — they are PUBLIC, CHANGE NOW):
   ```
   Username:  admin
   Password:  SeedlingsAdmin2026!
   ```
4. After login, **bottom admin bar → Password → change password IMMEDIATELY** to a unique, strong one (use a password manager).
5. Log out → log back in with the new password. 🎉 Site is live.

---

## 🛠 SETTINGS TO UPDATE BEFORE SHARING

Dashboard → **Settings** → fill in these real values:

| Setting | Where | What to put |
|---|---|---|
| Deadline date | Settings → Deadline | Real closing date (July 31 2026 placeholder) |
| Application form URL | Settings → Application → Expression of interest form URL | Real Google Form / Typeform / Jotform link (currently `forms.gle/replace-with-your-form-link`) |
| WhatsApp number | Settings → Contact → WhatsApp | Real church / coordinator number with country code, no `+` no spaces e.g. `2348137912872` |
| WhatsApp display | Settings → Contact → WhatsApp display | Human-readable e.g. `+234 813 791 2872` |
| Site title | Settings → General → Site title | Usually keep `Seedlings` |

Dashboard → **Manage lists** → verify the "Who should apply" criteria and "What churches receive" benefits match your program.

Live page → while logged in, click any headline / paragraph to test inline editing → it turns green → saved ✓.

---

## 🔒 Production Security — VERIFIED ✅

What we already locked in for Hostinger:

| Layer | Location | Status |
|---|---|---|
| HTTPS forced (www→apex, HTTP→HTTPS) | `.htaccess:10-20` | ✅ HSTS max-age 2 years |
| Secure headers (HSTS, nosniff, XFO, CSP-lite, Permissions-Policy) | `.htaccess:25-35` | ✅ |
| install.sql, README.md, dotfiles blocked | `.htaccess:44-66` | ✅ |
| PHP execution OFF in uploads folder | `assets/uploads/.htaccess:26-43` | ✅ Engine off, handlers removed, PHP 8 module-specific disable |
| Uploads image-only whitelist + everything-else deny | `assets/uploads/.htaccess:7-23` | ✅ |
| Admin sessions cryptographically salted | `config.php:APP_KEY` (256-bit random, unique to this deploy) | ✅ |
| Passwords bcrypt-hashed, never stored plain | `includes/auth.php` (php native `password_hash` / `password_verify`) | ✅ |
| Admin actions protected by CSRF tokens | Every form + `admin/api.php` | ✅ |
| Error display hidden, errors logged only | `config.php:45-48` (`display_errors=0`) | ✅ |
| X-Powered-By removed (fingerprinting) | `.htaccess:34`, uploads `:70` | ✅ |
| Static asset caching (1yr images, 1mo CSS/JS, no-cache HTML) | `.htaccess:82-142` | ✅ Brotli + gzip |

---

## ❓ Troubleshooting (Hostinger-specific)

### White page or "DB connection error"
- Double-check `config.php` host is `localhost` (NOT an IP, NOT `mysql.hostinger.com` — Hostinger uses localhost socket for local DBs)
- Open hPanel → Databases → Verify user `_seeduser` is **Added** to the `_seedlings` DB with **All Privileges** (not just created — they must be linked)

### "File not found" on CSS/JS/images
- Double-check files landed in `public_html/assets/` not `public_html/seedlings-cms/assets/`
- Clear Hostinger cache: hPanel → Websites → missionseedlings.com → **Cache** → **Clear cache** (LiteSpeed holds cached responses)

### Hero photo upload fails (admin)
- `assets/uploads/` permissions: try **775** (755 is default, Hostinger sometimes needs group-writable)
- Also check file is under Hostinger's upload max (usually 64MB, hero photo is ~200KB so this isn't likely)

### HTTPS / 404 issues with .htaccess rules
- Hostinger's LiteSpeed auto-reads `.htaccess`, but sometimes needs a tap:
  - hPanel → Websites → missionseedlings.com → **Cache** → Purge All
  - Or turn LiteSpeed Cache OFF temporarily (Advanced → LiteSpeed Cache → Disabled) while testing rules

### Admin can't stay logged in across pages
- Ensure `SITE_URL` in config.php is EXACTLY `https://missionseedlings.com` — no trailing slash, no www, no http. PHP session cookies are tied to exact domain+scheme.

---

## 📝 What's uploaded vs not

**Upload all files in the project folder.** Hostinger's `.htaccess` blocks public access to `.md` / `.sql` / dotfiles automatically, so even if `install.sql` or `README.md` end up uploaded, they'll return **403 Forbidden** to all browser requests.

---

After changing admin password + filling Settings, share the site 🎉  — `https://missionseedlings.com`


---

## 🔁 Optional: KoboToolbox sync cron

Not needed for launch — the site links to Kobo forms and project managers type the tracker figures
in. Set this up only when you want indicators to update themselves.

1. Put your Kobo API token in `config.php`:
   ```php
   defined('KOBO_API_TOKEN') || define('KOBO_API_TOKEN', 'your-token-here');
   ```
2. In Admin → Indicators, edit an indicator, set **Source** to *KoboToolbox*, and paste the form UID.
3. hPanel → **Advanced** → **Cron Jobs** → Create, once a day:
   ```
   /usr/bin/php /home/uXXXXXXXX/public_html/bin/kobo-sync.php
   ```
   (Replace `uXXXXXXXX` with your account prefix — the path is shown in File Manager.)

The script refuses to run from a browser, and does nothing at all while the token is empty, so it
is safe to schedule early.

---

## 💾 Backups

Hostinger takes automatic backups on most plans, but confirm rather than assume — and take your own
before any change you would not want to undo.

1. hPanel → **Files** → **Backups** → check that automatic backups are on and note the frequency.
2. Before running a migration or a bulk edit, use **Create new backup** for an on-demand snapshot.
3. For an off-site copy of the content: phpMyAdmin → your database → **Export** → *Quick*, SQL.
   Keep that file somewhere outside Hostinger. The database holds all the site's content — the
   uploaded photos in `assets/uploads/` are the other half, so download those too.

A restore is: import the `.sql` export into the database, then re-upload `assets/uploads/`.

---

## 🚑 If something breaks after deploying

| Symptom | Likely cause | Fix |
|---|---|---|
| "Site is temporarily unavailable" | Wrong DB credentials in `config.php` | Re-check `DB_NAME` / `DB_USER` / `DB_PASS` against hPanel, including the account prefix |
| Homepage works, `/about` 404s | Rewrite rule not active | See step 6b |
| Every page unstyled | `assets/` did not upload, or `SITE_URL` has a trailing slash | Re-upload `assets/`; remove the trailing slash |
| "Table 'users' doesn't exist" | `migrations/002_platform.sql` was not imported | Import it (step 3b) |
| Photo uploads fail | `assets/uploads/` not writable | CHMOD 775 (step 5) |
| Locked out of admin | 5 failed logins | Wait 15 minutes, or in phpMyAdmin run `DELETE FROM login_attempts;` |
| Lost the only admin password | — | In phpMyAdmin, run `UPDATE users SET password_hash = '<hash>', must_change_password = 1 WHERE username = 'admin';` using a hash from a bcrypt generator |
