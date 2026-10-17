# GiftAura — Troubleshooting Guide

Find the message or symptom you see, then follow the fix. Most problems come from XAMPP not running, the wrong database password or port, or a browser cache.

**First checks for any problem**
1. XAMPP Control Panel: **Apache** and **MySQL** both green.
2. Error details: open the newest file in `C:\xampp\htdocs\giftaura\storage\logs\` (visitors never see these details).
3. Run the system check: `C:\xampp\php\php.exe scripts\system_check.php` (from the giftaura folder). Every FAIL line names the problem.

## 1. Start-up and database

| Symptom / message | Likely cause | Fix |
|---|---|---|
| "GiftAura is not configured yet" | `config/config.php` does not exist | Run `C:\xampp\php\php.exe scripts\setup.php` |
| "Something went wrong" on every page | Database not reachable | Start MySQL in XAMPP; then check the log in `storage/logs` |
| Setup or log says **Access denied for user 'giftaura_app' (1045)** | Password in `config.php` does not match the database user | phpMyAdmin → SQL tab → run `ALTER USER 'giftaura_app'@'localhost' IDENTIFIED BY 'NewPassword'; ALTER USER 'giftaura_app'@'127.0.0.1' IDENTIFIED BY 'NewPassword'; FLUSH PRIVILEGES;` then run `scripts/setup.php` again and type the same password (no brackets or quotes) |
| **Connection refused (2002)** | MySQL stopped, or wrong port | Start MySQL. Check the port in XAMPP (MySQL row, "Port(s)") — this laptop uses **3307** — and re-run setup with that port |
| "Unknown database 'giftaura_db'" or missing tables | Database scripts not imported | phpMyAdmin: run `sql/00_create_database_and_user.sql`, then import `schema.sql` and `seed.sql` (or restore a backup) |
| Apache will not start (port 80 busy) | Skype, IIS or another program uses port 80 | Close that program, or in XAMPP → Apache → Config → `httpd.conf` change `Listen 80` to `Listen 8080` and open `http://localhost:8080/giftaura/` |
| MySQL stops with "shutdown unexpectedly" | Improper shutdown of XAMPP | Do **not** delete files by hand. Restart the computer and XAMPP; if it persists, restore the latest backup into a fresh XAMPP (see `deployment-and-backup.md`) |
| `php` is not recognised in PowerShell | PHP is not on the PATH | Always type the full path: `C:\xampp\php\php.exe ...` |

## 2. Pages and layout

| Symptom | Likely cause | Fix |
|---|---|---|
| Page has no colours / looks like plain text | Old cached files, or the site is not in `htdocs\giftaura` | Press **Ctrl + F5**. If the folder name is different, set `'base_url' => '/newname'` in `config/config.php` and change the two `ErrorDocument` lines in the root `.htaccess` |
| Every page shows "Not Found" from Apache | Project folder renamed or `.htaccess` missing | Restore the root `.htaccess` from Git (`git checkout -- .htaccess`) and check the folder name |
| "Page expired" (419) after submitting a form | The page was open a long time, you logged out in another tab, or cookies are blocked | Go back, refresh the page (F5) and submit again; allow cookies for localhost |
| Logged out unexpectedly | 30 minutes without activity (security feature) | Log in again. The limit is `session_idle_minutes` in `config/config.php` |
| "Too many unsuccessful login attempts" | 5 wrong passwords for that e-mail (or 20 from this computer) in 15 minutes | Wait 15 minutes. Urgent: phpMyAdmin → `login_attempts` → empty the table |
| Customer forgot password | — | Admin → Customers → customer → **Set temporary password** |
| Administrator forgot password | There is no reset for admins on the website (by design) | Create a new administrator: `C:\xampp\php\php.exe scripts\create_admin.php`, then log in with it |
| Contact page says details "not published yet" | Store details are empty in Settings | Admin → **Settings** → fill in phone, address, hours → Save |
| Bank-transfer customers are told to contact the store | Bank details are empty in Settings | Admin → Settings → Bank account section |

## 3. Products, images and stock

| Symptom / message | Likely cause | Fix |
|---|---|---|
| "Only JPG, PNG and WebP images are allowed" | File is another type (HEIC, GIF, PDF) or renamed | Save/export the photo as JPG or PNG |
| "The image is too large" | Over 2 MB or over 5000 px | Resize the photo (e.g. Windows Photos → Resize, or Paint) |
| "This file is not a valid image" | Damaged file or not really an image | Open it in an image viewer and save it again as JPG |
| "Image processing (GD) is not enabled" | GD extension off | `C:\xampp\php\php.ini` → remove `;` before `extension=gd` → restart Apache |
| Product shows "Image coming soon" | No photo uploaded, or the file was deleted from `public/uploads/products` | Upload the photo again |
| Product not visible in the shop | Product or its category (or parent category) is inactive | Admin → Products / Categories → **Activate** |
| "Stock cannot go below zero" | Correction larger than the stock | Check the quantity (use e.g. `-2`, not `-20`) |
| Cannot delete a category | It still has products or subcategories (protection) | Move/deactivate its products first, or simply **Deactivate** the category |

## 4. Orders and payments

| Message | Meaning | What to do |
|---|---|---|
| "Your cart or its prices changed while you were checking out" | Price, stock or delivery fee changed after the checkout page opened | Review the new total and place the order again (this protects the customer) |
| "Sorry, only N of … left in stock" | Someone else bought the item first | Lower the quantity in the cart |
| "An order that is X cannot be changed to Y" | Not an allowed step (e.g. Pending → Delivered) | Move through the steps in order; see the status table in `admin-guide.md` |
| "This order is paid by bank transfer and the payment is not marked as paid yet" | Bank transfer not verified | Check the bank, record **Paid**, then confirm — or tick *Confirm before the bank transfer is verified* |
| "Administrator accounts cannot place orders" | Logged in as admin | Use a customer account to shop |

## 5. Reports, documents and tools

| Symptom | Fix |
|---|---|
| CSV shows strange characters in Excel | Excel → **Data → From Text/CSV** → File origin **65001: Unicode (UTF-8)** |
| `docs/diagrams.html` shows code instead of pictures | It needs internet the first time (it loads the diagram library). Connect and refresh |
| Git shows "LF will be replaced by CRLF" | Harmless Windows line-ending notice — ignore it |
| `system_check.php` shows FAIL for `WEB-…` paths | A `.htaccess` file is missing or was edited: restore it from Git (`git status`, `git checkout -- <file>`) |
| `system_check.php` shows FAIL for `PERF-…` | The computer was busy; close other programs and run again |
