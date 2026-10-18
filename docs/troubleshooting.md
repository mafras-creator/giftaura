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
| Customer forgot password | — | The customer uses **Forgotten your password?** on the login page. If e-mail is not working: Admin → Customers → customer → **Set temporary password** |
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

## 5. E-mail

First look at **Admin → E-mail log**: the *Result* column shows what happened to every e-mail, and failed ones show the reason. To test the settings: `C:\xampp\php\php.exe scripts\test_mail.php`.

| Symptom / message | Meaning | What to do |
|---|---|---|
| E-mail log says **Saved**, nothing arrives | Test mode: e-mails are saved as files, not sent | Open them in `storage\mail\` — or switch to Gmail: `C:\xampp\php\php.exe scripts\setup_mail.php` → 2 |
| E-mail log says **Skipped** | E-mail mode is *off* | `scripts\setup_mail.php` → 1 (test) or 2 (Gmail) |
| "Login to the mail server was refused. For Gmail use an App Password" (535) | Wrong password, or the normal Gmail password was used | Google Account → **Security** → **2-Step Verification** (must be on) → **App passwords** → create one → run `setup_mail.php` again and paste the 16 letters |
| "Cannot connect to smtp.gmail.com:465 (… certificate verify failed …)" | PHP cannot check Gmail's certificate | Run `setup_mail.php` again (it sets the XAMPP certificate file automatically), or set `'cafile' => 'C:\xampp\apache\bin\curl-ca-bundle.crt'` in the `mail` section of `config/config.php` |
| "Cannot connect … (Connection timed out / refused)" | No internet, or a firewall/antivirus blocks port 465 | Check the internet; allow `php.exe` / `httpd.exe` in the firewall; some networks (e.g. university Wi-Fi) block mail ports — try a mobile hotspot |
| "Unable to find the socket transport ssl" | OpenSSL extension off | `C:\xampp\php\php.ini` → remove `;` before `extension=openssl` → restart Apache |
| E-mail arrives in **Spam** | Normal for a new sender using a personal Gmail | Mark it *Not spam*; tell customers to check Spam. A business mail service fixes this (see security checklist §8) |
| Link in the e-mail opens "This site can't be reached" | `site_url` is `http://localhost`, which only works on the laptop itself | Open the e-mail on the laptop, or (for a real host) set `'site_url'` in the `app` section of `config/config.php` to the real address |
| "This link does not work" | Link already used, older than 24 h (verify) / 60 min (reset), or a newer link was sent | Use the newest e-mail, or ask for a new link |
| "You asked for several links in the last hour" | Limit of 3 links per hour | Use the newest e-mail already received, or wait one hour |
| Customer can't receive e-mails at all | Mistyped address at registration | Admin → Customers → customer → check the address. If confirmed by phone, press **Mark e-mail as verified** |
| "Unknown column email_verified_at" / page error after the update | The database update was not run | phpMyAdmin → `giftaura_db` → **SQL** → run `sql/updates/2026-10-17_email.sql` (as root) |

## 6. Banners and category pictures

| Symptom / message | Fix |
|---|---|
| "The image is too narrow" | Banners must be at least 1000 px wide; use 1920 × 600 px |
| Banner saved but not on the homepage | Check its **Status** in Admin → Banners: *Hidden* → press **Show**; *Scheduled* / *Ended* → change the dates |
| Banner disappeared after restoring a backup | Its picture file is missing: unzip `pictures-….zip` into `public\uploads\`, or upload the picture again (Edit → Replace the picture) |
| Banner text hard to read | Use a picture with a calm, darker left side, or untick "Show the title…" and put the text in the picture itself |
| "Unknown column compare_at_price" / "wishlist_items doesn't exist" | Run `sql/updates/2026-10-17_sale_wishlist.sql` in phpMyAdmin (as root), one block at a time |
| "Unknown column is_customizable" / "customization_text" | Run `sql/updates/2026-10-18_customization.sql` in phpMyAdmin (as root), one block at a time |
| "Table product_reviews doesn't exist" (Products, home page or My reviews shows "Something went wrong") | Run `sql/updates/2026-10-18_reviews.sql` in phpMyAdmin (as root). System check DB-02 expects 20 tables |
| "Unknown column image_file" or a page error after the update | Run `sql/updates/2026-10-17_banners.sql` in phpMyAdmin (as root) |

## 7. Reports, documents and tools

| Symptom | Fix |
|---|---|
| CSV shows strange characters in Excel | Excel → **Data → From Text/CSV** → File origin **65001: Unicode (UTF-8)** |
| `docs/diagrams.html` shows code instead of pictures | It needs internet the first time (it loads the diagram library). Connect and refresh |
| Git shows "LF will be replaced by CRLF" | Harmless Windows line-ending notice — ignore it |
| `system_check.php` shows FAIL for `WEB-…` paths | A `.htaccess` file is missing or was edited: restore it from Git (`git status`, `git checkout -- <file>`) |
| `system_check.php` shows FAIL for `PERF-…` | The computer was busy; close other programs and run again |
