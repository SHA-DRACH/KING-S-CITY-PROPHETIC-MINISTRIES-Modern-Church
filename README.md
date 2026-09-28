# King's City Prophetic Ministries — Church Website & Management System

A database-driven church website and administration system for
**King's City Prophetic Ministries** (Apostolic & Prophetic Church), Omega Community,
behind Conex Gas Station, Paynesville, Monrovia, Liberia.

PHP 8.2+ · MySQL 8 / MariaDB 10.4+ · PDO · Bootstrap 5 · AOS · Swiper · GLightbox · Chart.js · Font Awesome

---

## 1. Run it locally on XAMPP

1. Place this folder at `C:\xampp\htdocs\kings-city` (it already is).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Import the database (creates `kings_city` with demo data):
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\kings-city\database.sql
   ```
   or phpMyAdmin → *Import* → `database.sql`.
4. Open **http://localhost/kings-city/**. The admin sign-in is at **http://localhost/kings-city/admin/login.php**.

Default DB settings are XAMPP's (`root`, no password, port 3306). To change them, copy
`config/config.local.example.php` → `config/config.local.php` and edit it.

### Demo accounts (development only)

Every account uses the password `KingsCity@2026` and **must set a new password at first sign-in**.

| Email | Role | What it demonstrates |
|---|---|---|
| superadmin@kingscityministries.org | Super Administrator | Full control, roles, critical permissions, system settings |
| admin@kingscityministries.org | Administrator | Manages the site within granted permissions; cannot touch Super Admin |
| pastor@kingscityministries.org | Senior Pastor | Pastor dashboard: sermons, announcements, prayer, testimonies, pastor profile |
| assistant@kingscityministries.org | Admin Assistant | Content tasks only; items are saved as drafts (no publish permission) |
| youth@kingscityministries.org | Department Head (Youth) | Sees only Youth Ministry events/announcements, plus a direct *Edit Gallery* grant |

Delete or disable these accounts before going live.

---

## 2. Structure

```
admin/          Protected dashboard (every page: login + permission check)
  partials/     Admin layout, init guard, shared tab bars (not web-accessible)
auth/           Login, logout (POST + CSRF), forgot / reset / change password
api/            Public form endpoints: prayer, testimony, giving, contact
config/         config.php (+ config.local.php), constants.php, database.php (PDO)
controllers/    CrudController (generic admin resources), PublicFormController
models/         Data access: Sermon, Event, Department, Gallery, Giving, PaymentGateway…
includes/       bootstrap, helpers, auth, permissions (RBAC), uploads, components, header/footer
assets/         css, js, images (logo, favicon, placeholder artwork)
uploads/        User uploads (PHP execution disabled, random filenames)
tools/          generate-placeholders.php, minify.php (CLI only, web-blocked)
database.sql    Full schema + seed data
```

Most admin modules are **declarative**: e.g. `admin/sermons.php` just describes fields, columns,
filters and permissions; `CrudController` provides listing, search, filters, pagination, modal
create/edit (AJAX), delete confirmation, publish toggle, secure uploads and activity logging.

---

## 3. Roles & permissions (RBAC)

```
USER → ROLE → DEPARTMENT → ROLE PERMISSIONS (+ direct user grants − direct user revokes)
```

* **Permissions** are granular (`sermons.create`, `events.publish`, `users.delete`, …), 63 in total.
  Manage them in *Admin → Permissions* (role × permission matrix, click to toggle).
* **Roles** carry default permissions and a **level**. Users can only manage users/roles *below* their own level.
* **Direct user permissions**: on a user's page, tick/untick permissions. Differences from the role are
  stored as grants (green) or revokes (red).
* **Department scoping**: users without `departments.all_access` only see and manage events,
  announcements and gallery items belonging to their own department(s).
* **Critical permissions** (roles.*, system settings, activity logs) can only be granted by a Super Administrator.
* **Anti-escalation**: nobody can grant a permission they don't hold, assign a role at/above their own level,
  or edit/reset/disable a Super Administrator. Every rule is enforced server-side in
  `includes/permissions.php`. Hidden buttons are cosmetic only.
* **Publish gating**: users without `*.publish` can create content, but it is saved as *draft*.

## 4. Security

PDO prepared statements everywhere · CSRF token on every POST (checked centrally in `bootstrap.php`) ·
output escaping (`e()`) and an HTML sanitizer for page content · `password_hash`/`password_verify` with
automatic rehash · forced password change for new/reset accounts · hardened sessions (HttpOnly, SameSite,
strict mode, ID regeneration, idle timeout, user-agent binding) · login throttling per email and IP ·
POST-only logout · honeypot + rate limits on public forms · uploads validated by extension, real MIME type,
`getimagesize`, and size, stored under random names · `/uploads` cannot execute scripts · internal folders
denied via `.htaccess` · security headers · full activity log with IP (CSV export).

Prayer requests are **private by default**; only requests where the visitor chose *Share publicly* appear on
the prayer wall (first name + initial, never contact details). Testimonies need the sender's permission
**and** staff approval before publication.

## 5. Content you should replace

* **Photos & video**: the site ships with branded placeholder artwork. Upload real photos in Gallery,
  Sermons, Events, Departments, Pastor, Leadership and *Settings → Homepage* (welcome and giving images).
* **Page videos**: *Admin → Website → Hero Video*. Pick the page (Home, About, Sermons, Events, …), then
  upload an MP4 (30 s or longer is fine) plus a poster image. Each page has one active video; large files
  upload in 5 MB chunks with a progress bar (limit set in *Settings → System*, default 1024 MB).
  Visitors with data-saver or reduced-motion see the poster instead.
* **Gallery**: *Admin → Gallery*. Choose a day or a month, then select or drag in as many photos as you like;
  no captions are needed. **Photo Downloads** albums let members find the service they attended and download
  their photos (one at a time or all as a ZIP). **Event Gallery** albums are the reference gallery.
  Thumbnails are generated automatically and phone photos are rotated upright.
* **Upgrading an existing install**: run the files in `migrations/` once, oldest first.
* **Giving methods**: *Admin → Giving → Payment Methods*. The seeded accounts say "Configure in admin".
  Nothing is hard-coded. Add an online gateway later by implementing `PaymentGatewayDriver`
  (`models/PaymentGateway.php`) without redesigning the page.
* **Social links, service times, church info, SEO**: all under *Website* in the sidebar.

## 6. Deploying to live hosting

1. Upload the files; import `database.sql` into a new database with a dedicated DB user.
2. Create `config/config.local.php` with `'debug' => false`, the real `base_url` and DB credentials.
3. In `.htaccess`: set `ErrorDocument 404 /404.php` and uncomment the HTTPS redirect.
4. `php tools/minify.php` to generate minified CSS (served automatically when debug is off).
5. Raise `upload_max_filesize` / `post_max_size` in php.ini if you will upload large videos.
6. Remove or disable the demo accounts, then submit `https://your-domain/sitemap.xml` to Google Search Console.
7. Configure PHP `mail()` / SMTP on the server so password-reset emails are delivered.
   (In debug mode without mail, the reset link is shown on screen for testing.)
