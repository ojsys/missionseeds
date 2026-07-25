# Seedlings — CMS-powered site

A plain PHP 8 + MySQL site for the Seedlings "Coffee Ministry Mission" campaign,
built for shared cPanel hosting (no framework, no Composer dependencies).

Almost everything on the page — headlines, paragraphs, the criteria list, the
benefits grid, the hero photo, the deadline, and the WhatsApp / application
links — is editable from a friendly admin panel, including inline editing
directly on the live page (click a headline, type, click away — it saves).

---

## 1. What's in this folder

```
index.php              ← the public page (reads everything from the database)
config.php              ← your database credentials — EDIT THIS FIRST
install.sql              ← database schema + starter content — IMPORT THIS

includes/               ← PHP helpers (db connection, auth, content helpers, icon set)
assets/
  css/style.css           ← site design
  css/admin.css           ← admin panel design
  js/site.js               ← growth-vine animation + inline editing
  uploads/                 ← hero photo lives here (writable folder)

admin/
  login.php               ← admin sign-in
  index.php                ← dashboard
  lists.php                 ← manage "who should apply" + "what churches receive"
  settings.php               ← deadline, application link, WhatsApp number, site title
  change-password.php         ← change the admin password
  api.php                      ← powers the inline editing on the live page
```

## 2. Deploy to cPanel

1. **Create a database.** In cPanel → *MySQL® Databases*: create a database
   and a database user, then add that user to the database with **All
   Privileges**. Note the full database name and username — cPanel usually
   prefixes both with your account name (e.g. `cpaneluser_seedlings`).

2. **Import the schema.** In cPanel → *phpMyAdmin*, select your new database,
   go to *Import*, and upload `install.sql`. This creates the tables and
   loads the current flyer copy as starter content, plus one admin login.

3. **Upload the files.** Upload everything in this folder to your domain's
   web root (e.g. `public_html/` or `public_html/seedlings/` for a subfolder)
   via cPanel *File Manager* or FTP. Keep the folder structure intact.

4. **Edit `config.php`.** Open it in the File Manager code editor and fill in:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — from step 1
   - `SITE_URL` — your live URL, no trailing slash
   - `APP_KEY` — any random string

5. **Set folder permissions.** Make sure `assets/uploads/` is writable
   (usually `755` is enough on cPanel; try `775` if photo uploads fail).

6. **Log in and change the password.** Visit `yoursite.com/admin/login.php`:
   - Username: `admin`
   - Password: `SeedlingsAdmin2026!`

   Go straight to **Password** in the admin nav and change it. This default
   is public (it's in this README and in `install.sql`), so don't skip this.

That's it — the site is live and editable.

## 3. Editing content

**Text (headlines, paragraphs, deadline note, footer line):** while logged
in, open the live site itself. Editable text gets a soft outline on hover —
click it, edit like a normal text field, click elsewhere to save. A brief
green flash confirms it saved.

**Hero photo:** hover the hero image while logged in and click *Change
photo*.

**"Who should apply" and "What churches receive" lists:** you can delete or
hide items right on the page (small icons appear top-right of each card when
logged in), or use **Manage lists** in the admin bar for full control —
add, reorder, hide, or edit any item, including its icon.

**Deadline date, application link, WhatsApp number, site title:** these live
in **Settings**, since they're structured data (dates, links, phone numbers)
rather than free text.

## 4. The "Apply now" / "Expression of interest" button

This site does **not** host its own application form. Every "Apply now" /
"Express interest" button links out to whatever URL you set in
**Settings → Application → Expression of interest form URL** (a Google Form,
Typeform, JotForm, etc.) and opens it in a new tab. Paste your real form link
there before launch — it currently points to a placeholder.

## 5. Security notes

- Passwords are hashed (bcrypt via PHP's `password_hash`) — never stored in
  plain text.
- All admin actions are protected by session auth + CSRF tokens.
- `.htaccess` files block direct access to `install.sql` and disable script
  execution inside `assets/uploads/`. (These rules apply on real Apache/cPanel
  hosting — they're inert if you test locally with PHP's built-in server.)
- Change the default admin password immediately (see step 6 above).
- Consider adding a second admin user only if needed — the current build
  supports one `admin_users` table row per login; add more rows directly in
  phpMyAdmin with `password_hash()`-generated hashes if you need multiple
  editors.

## 6. Local testing (optional, for developers)

```bash
php -S localhost:8000
```

Point `config.php` at a local MySQL/MariaDB database with `install.sql`
imported, then visit `http://localhost:8000`.
