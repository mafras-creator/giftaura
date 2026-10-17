# GiftAura — Deployment, Backup and Restore

Version 1.0 — Phase 9.

## 1. What must be backed up

| What | Where | Why |
|---|---|---|
| **Database** `giftaura_db` | MariaDB | Products, customers, orders, payments, stock history, settings |
| **Product photos** | `public/uploads/products/` | Not in Git and not in the database (the database stores only the file names) |
| `config/config.php` | `config/` | Database password and port — keep a private copy, **never** in Git or a shared folder |
| Source code | Git repository | Already versioned; push to a private GitHub repository for an off-site copy |

Backups contain customers' names, phones and addresses: store them privately (USB drive or private cloud folder), never on a public website.

## 2. Taking a backup

### Option A — one click (recommended)
1. Make sure **MySQL** is running in XAMPP.
2. Double-click `C:\xampp\htdocs\giftaura\scripts\backup.bat` (or run it in PowerShell).
3. Two files appear in `C:\xampp\htdocs\giftaura\backups\`:
   - `giftaura_db-YYYY-MM-DD_HHMM.sql` — the whole database
   - `photos-YYYY-MM-DD_HHMM.zip` — the product photos (only if there are photos)
4. Copy the `backups` folder to a USB drive or cloud storage.

The `backups` folder is excluded from Git and blocked from the browser.
If your XAMPP uses a different MySQL port, edit `DB_PORT` at the top of `backup.bat`.

### Option B — phpMyAdmin
1. `http://localhost/phpmyadmin` → click **giftaura_db** → **Export** → Quick, SQL → **Export**.
2. Copy `public\uploads\products\` by hand.

**How often:** weekly, and always before importing `schema.sql`, updating the code, or moving the system.

## 3. Restoring a backup

> Restoring replaces the current GiftAura data with the data in the backup.

1. Take a fresh backup first (in case you need to go back).
2. phpMyAdmin → **giftaura_db** → **Import** → choose the `giftaura_db-….sql` file → **Import**.
   (Command-line alternative, in **Command Prompt**, not PowerShell:
   `C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root giftaura_db < backups\giftaura_db-2026-10-17_1000.sql`)
3. Unzip `photos-….zip` into `public\uploads\products\`.
4. Open the shop and run `C:\xampp\php\php.exe scripts\system_check.php` — all checks should pass.

phpMyAdmin's default import limit is enough for this project; if a very large backup is refused, use the command-line alternative.

## 4. Moving GiftAura to another Windows computer (e.g. for the demonstration)

1. Install XAMPP (PHP 8.1 or newer) and enable `extension=gd` in `php.ini`; set `date.timezone=Asia/Colombo`; restart Apache.
2. Copy the project folder to `C:\xampp\htdocs\giftaura\` (or `git clone` it there).
3. Start Apache and MySQL. Note the MySQL port shown in XAMPP (often 3306).
4. phpMyAdmin → SQL tab → run `sql/00_create_database_and_user.sql` (replace `CHANGE_THIS_PASSWORD` in the box with a new password).
5. Either import `sql/schema.sql` + `sql/seed.sql` (fresh store) **or** import your latest backup (existing data), and unzip the photos.
6. In the project folder run `C:\xampp\php\php.exe scripts\setup.php` (enter the port and password).
7. Fresh store only: `C:\xampp\php\php.exe scripts\create_admin.php`.
8. Run `C:\xampp\php\php.exe scripts\system_check.php` → all checks pass.

If the folder name is not `giftaura`, set `base_url` in `config/config.php` and update the two `ErrorDocument` lines in the root `.htaccess`.

## 5. Publishing on a real web host (future work)

GiftAura runs on standard shared hosting with PHP 8.1+ (pdo_mysql, mbstring, fileinfo, gd) and MySQL/MariaDB. The project has **not** been deployed to a public server as part of this project; these are the steps to follow when it is.

| Step | Action |
|---|---|
| 1. HTTPS | Use a host with a free Let's Encrypt certificate. Session cookies automatically become `Secure` and HSTS is sent when the site is served over HTTPS |
| 2. Upload | Upload the whole project. Best: point the domain's document root to `public/`. Otherwise upload into the web root and keep the root `.htaccess` (it routes everything into `public/`) |
| 3. Database | In the hosting panel create a database and a user with **only** SELECT, INSERT, UPDATE, DELETE; import `schema.sql` and `seed.sql` (or a backup) with the host's phpMyAdmin |
| 4. Configuration | Create `config/config.php` from `config.example.php`: host's database name, user, password and port (usually 3306); `base_url` = `''` if the site is at the domain root; `debug` = `false` |
| 5. Error pages | Update the `ErrorDocument` paths in `.htaccess` to match the new base URL |
| 6. Administrator | With SSH: run `php scripts/create_admin.php`. Without SSH: create the administrator on the laptop, take a backup, and import it on the host |
| 7. Permissions | `storage/logs/` and `public/uploads/products/` writable by the web server; everything else read-only |
| 8. Check | Open the site; confirm `/config/config.php`, `/sql/schema.sql` and `/storage/logs/` are not reachable; place a test order |
| 9. Content | Enter real store details and products; remove or deactivate all "Sample" products |
| 10. Backups | Use the host's automatic database backups **and** download regular copies |

Before going public, also review the "Known limitations" in `docs/security-checklist.md` (e-mail notifications, online payments, registration rate limiting, two-factor login for administrators).

## 6. Updating the code safely

1. Take a backup (section 2).
2. Commit the current state: `git add -A` then `git commit -m "Before update"`.
3. Copy in the new files (or `git pull`).
4. If the update includes database changes, apply only the update script provided with it — **never re-import `schema.sql` on a live store**, because it deletes all GiftAura data.
5. Run `scripts/system_check.php`. If anything fails, go back with `git checkout <previous commit>` and restore the backup.
