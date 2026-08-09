# Mission Seedlings — website and CMS

The website for Mission Seedlings, a church-based community enterprise platform. The first pilot is
the Coffee Ministry Mission in Jos, Plateau State.

Plain PHP 8 + MySQL, built for Hostinger shared hosting. No framework, no Composer, no build step.

**Live site:** https://missionseedlings.com

---

## What's here

A public website of nine sections — home, about, the seven-stage pathway, a growth tracker,
the cooperative model, participating churches, growth stories, a resource hub, and contact —
plus an admin dashboard and a portal for church coordinators.

The original call-for-applications landing page is preserved at **/call** and can be reopened for
each new application cycle from Settings.

```
index.php        front controller
config.php       database credentials + SITE_URL — EDIT THIS FIRST
install.sql      v1 schema — import first
migrations/      002_platform.sql — import second
DOCS/            staff guide and developer notes
```

---

## First-time install

1. **Create a database.** hPanel → Databases → MySQL Databases. Create a database and a user, then
   add the user to the database with **All Privileges**. Hostinger prefixes both names with your
   account prefix (e.g. `u123456789_seedlings`).

2. **Import the schema**, in this order, via phpMyAdmin → Import:
   - `install.sql`
   - `migrations/002_platform.sql`

3. **Upload the files** to `public_html/`, keeping the folder structure intact.

4. **Edit `config.php`** with your `DB_NAME`, `DB_USER`, `DB_PASS`, and confirm `SITE_URL` has no
   trailing slash. `APP_KEY` is already a random 256-bit value — leave it unless you need to
   rotate it (doing so signs everyone out).

5. **Make `assets/uploads/` writable** (755, or 775 if uploads fail).

6. **Sign in and change the password immediately** at `/admin/login.php`:
   - Username: `admin`
   - Password: `SeedlingsAdmin2026!`

   That default is published in this file and in `install.sql`, so it must not survive your first
   login. Then go to **Users** and create a real account for each person.

Full deployment detail, including the cron job and backups, is in `DEPLOY-HOSTINGER.md`.

---

## Documentation

- **[DOCS/STAFF-GUIDE.md](DOCS/STAFF-GUIDE.md)** — how to run the website day to day. Written for
  project staff, no technical knowledge assumed.
- **[DOCS/DEVELOPER.md](DOCS/DEVELOPER.md)** — architecture, roles and capabilities, the privacy
  model, local setup, and the list of deliberate follow-ups.
- **[DEPLOY-HOSTINGER.md](DEPLOY-HOSTINGER.md)** — deployment and hosting.

---

## Accounts

Four roles, invite-only — there is no public sign-up anywhere.

| Role | Area | What they do |
|---|---|---|
| Super Admin | `/admin` | Everything, including creating accounts |
| Project Manager | `/admin` | Churches, pathway, indicators, stories, resources, enquiries, settings |
| Editor | `/admin` | Website copy and stories |
| Church Coordinator | `/portal` | Submits updates and photos for their own church, for review |

Partner organisations have no accounts. They follow the public Growth Tracker, and staff send them
a fuller update on request — `/tracker` has a print stylesheet so it saves cleanly as a PDF.

---

## Privacy

The site must never publish participant names or phone numbers, household income or data, national
ID records, individual household GPS coordinates, the project budget, bank details, seed-fund
balances, staff allowances, or internal cooperative account information.

The database schema has **no column for any of it** — that data lives only in KoboToolbox. This is
the project's main structural safeguard, and it should stay that way. See `DOCS/DEVELOPER.md`
before adding fields.

---

## Editing content

Most page text is edited in place: sign in, open any public page, click a headline or paragraph,
type, click away. A green flash confirms the save.

Structured things — dates, links, phone numbers, church records, indicators, stories — have their
own admin screens. See the staff guide.

---

## KoboToolbox

KoboToolbox remains the field data collection system. The website links to authorised forms with
role-based visibility, and Growth Tracker figures are entered by project managers.

Live API sync is scaffolded and needs no schema change to enable: set `KOBO_API_TOKEN` in
`config.php`, point an indicator at a form UID, and schedule `bin/kobo-sync.php`.

---

## Security summary

- Passwords hashed with bcrypt; temporary passwords forced to change on first sign-in
- Role-based access enforced by capability, checked server-side on every page
- Login throttling, session idle and absolute timeouts, user-agent binding
- CSRF tokens on every form
- Uploads validated by real MIME type; SVG refused; script execution disabled in the uploads folder
- Full audit log of every admin action
- HTTPS forced, HSTS, and a full set of security headers in `.htaccess`
