# GiftAura — E-Commerce Web System

Final-year Software Development Project (MIT), South Eastern University of Sri Lanka.
An online shop for the GiftAura gift store: flower bouquets, beauty creams and gift items.

**Stack:** HTML5 · CSS3 · vanilla JavaScript · PHP 8.2 · MariaDB 10.4 · Apache (XAMPP). No frameworks and no Composer packages.

> **Project status: complete (Phase 9).** All 65 original requirements are built and tested: 48 unit checks, 160 automated end-to-end checks, 43 checks on the real XAMPP server and 25 manual acceptance tests all passed (see `docs/test-plan.md`).
>
> **Design refresh (17 Oct 2026):** purple header with a large search bar, "All categories" menu, homepage banners managed in **Admin → Banners**, round category pictures, yellow **Add to cart** buttons (FR-58 – FR-60). Automated: 91 new checks passed; laptop checks M-35 – M-39 pending.
>
> **E-mail extension (17 Oct 2026):** e-mail verification before checkout, password reset by e-mail, order and payment e-mails, and an e-mail log (FR-50 – FR-57). Automated tests: 58 + 107 + 69 end-to-end and 17 unit checks passed. Real Gmail sending and the laptop checks (M-26 – M-34) are still to be run — see section 2.8 and 9.

## Features at a glance

| Customers | Administrators |
|---|---|
| Browse categories and subcategories; search, filter by price and stock, sort, paginate | Dashboard with live sales, pending orders, payments to verify and stock alerts |
| Product pages with photos, price in LKR and live availability | Products with secure photo upload; categories and subcategories |
| Cart (kept after login), quantity limits, live totals and delivery charge | Stock restock/corrections with a full stock ledger and low-stock alerts |
| Checkout with saved addresses, Cash on delivery or bank transfer | Order workflow Pending → Confirmed → Processing → Ready → Delivered / Cancelled |
| Order reference, order history, progress tracking, cancel while pending | Payment recording: paid, failed, refunded (with who and when) |
| Bank-transfer reference submission; public order tracking | Customers: details, orders, deactivate, temporary password, e-mail verified or not |
| E-mail verification, password reset by e-mail, order and payment e-mails | E-mail log with the result of every e-mail; Gmail or test mode |
| Homepage banner slider, round category pictures, yellow Add to cart | Banners (pictures, links, dates) and category pictures |
| Sale prices (old price crossed out, -% badge, On sale page) and wishlist (heart on every product picture) | Normal + sale price per product |
| Personalised products: "Personalisable" badge, Personalise button and message box; message kept in cart, order, e-mails and admin order page | Personalisation box in the product form (question, length, required) |
| Product reviews: only after delivery, from My account → My reviews; stars and average on product cards; review section under each product | Admin → Reviews: filter, hide/show, delete |
| Add to cart asks guests to log in or register, then adds the product automatically; profile card with icon menu | **In-shop sales**: sell to walk-in customers, printable invoice (INV-…), stock reduced automatically, cancel invoice |
| Contact form with reference number; My account → My inquiries with the store's replies | Inquiries: reply by e-mail, status; **Staff accounts** with generated passwords and per-area permissions |
| Profile, password and address book | Reports for any date range with CSV export; activity log; store settings |

---

## 1. Requirements

| Software | Version used in development |
|---|---|
| XAMPP (Apache + MariaDB + PHP) | Apache 2.4.58, MariaDB 10.4.32, PHP 8.2.12 |
| PHP extensions | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `gd` (enable `extension=gd` in `C:\xampp\php\php.ini`) |
| Git | 2.55 |
| Browser | Any current Chrome, Edge or Firefox |

In `C:\xampp\php\php.ini` also set `date.timezone=Asia/Colombo`, then restart Apache.

> On the development laptop MariaDB listens on port **3307**, not the default 3306. Check yours in the XAMPP Control Panel (MySQL row, "Port(s)").

## 2. Installation

### 2.1 Copy the project
Place the project folder at `C:\xampp\htdocs\giftaura\`.

### 2.2 Create the database and the application user
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Choose a strong password for the application user (12+ characters, letters, numbers and a symbol) and keep it somewhere safe — you need it again in step 2.4.
3. Open <http://localhost/phpmyadmin> → **SQL** tab → paste the contents of `sql/00_create_database_and_user.sql`.
4. **In the phpMyAdmin box** (not in the file), replace both `CHANGE_THIS_PASSWORD` values with your password, then click **Go**. The file in the project keeps the placeholder, so the real password never ends up in Git.
5. The last result should list `giftaura_app` with hosts `localhost` and `127.0.0.1`.

### 2.3 Create the tables and sample data
1. In phpMyAdmin click **giftaura_db** in the left panel → **Import** → choose `sql/schema.sql` → **Import**.
2. Still in `giftaura_db` → **Import** → choose `sql/seed.sql` → **Import**.
3. The final result of `seed.sql` should show: **categories 4, products 11, active_products 10, inventory_movements 10, settings 17**.

> Re-importing `schema.sql` deletes all GiftAura data and starts again. It never touches other databases.

### 2.4 Configure the application
Open PowerShell in the project folder and run:
```
C:\xampp\php\php.exe scripts\setup.php
```
Press **Enter** to accept each suggested value, and type the `giftaura_app` password from step 2.2 when asked. The script tests the connection and writes `config/config.php` (never committed to Git). To configure by hand instead, copy `config/config.example.php` to `config/config.php` and edit it.

### 2.5 Create the first administrator
There is no default admin account and no way to become an admin through the website. Run:
```
C:\xampp\php\php.exe scripts\create_admin.php
```

Run it again any time to add another administrator (one per staff member).

### 2.6 Open the shop
- Shop: <http://localhost/giftaura/>
- Admin: <http://localhost/giftaura/admin/>

### 2.7 Check the installation
```
C:\xampp\php\php.exe scripts\system_check.php
```
All checks should pass. It tests the database rules, the security of private folders, e-mail settings and page speed, without changing any data.

### 2.8 E-mail (optional, can be done later)
E-mails start in **test mode**: nothing is sent, and each e-mail is saved as a file in `storage\mail\` that you can double-click to read. Customers must open their verification link before they can check out, so in test mode open the link from that file.

To send real e-mails from a Gmail account:
1. Google Account → **Security** → turn on **2-Step Verification** → **App passwords** → create one named *GiftAura* → copy the 16 letters. (Your normal Gmail password does **not** work.)
2. `C:\xampp\php\php.exe scripts\setup_mail.php` → choose **2** → enter the Gmail address and the App Password.
3. `C:\xampp\php\php.exe scripts\test_mail.php` → enter your own address → `[OK] Sent` → check the inbox (and Spam).

The App Password is stored only in `config/config.php`, which is never committed to Git. Problems: `docs/troubleshooting.md` §5.

## 3. Daily use

- **Start:** open the XAMPP Control Panel → **Start** Apache and MySQL → open the shop.
- **Stop:** **Stop** both in XAMPP when finished.
- **Backup:** double-click `scripts\backup.bat` every week (see `docs/deployment-and-backup.md`).
- **Staff instructions:** `docs/admin-guide.md`. **Customer instructions:** `docs/customer-guide.md`.
- **Problems:** `docs/troubleshooting.md`.

## 4. Project layout

```
giftaura/
├── public/     only folder reachable from the browser (pages, admin, assets, uploads)
├── app/        PHP core classes, services and templates (blocked from the browser)
├── config/     database settings (config.php is not committed to Git)
├── sql/        00_create_database_and_user.sql, schema.sql, seed.sql, updates/
├── scripts/    command-line tools
├── storage/    logs, and e-mails saved in test mode (storage/mail)
├── tests/      automated test scripts (blocked from the browser)
└── docs/       requirements, architecture, database design, diagrams, guides
```
Full tree: `docs/04-file-structure.md`.

## 5. Documentation

| Document | File |
|---|---|
| Requirements checklist and business rules | `docs/01-requirements.md` |
| System architecture, sequence and state diagrams, security layers | `docs/02-architecture.md` |
| Database design, ER diagram, normalisation | `docs/03-database-design.md` |
| File structure and page → service → table map | `docs/04-file-structure.md` |
| Use-case diagram and system flow diagrams | `docs/05-use-cases-and-flows.md` |
| All 12 diagrams as pictures (SVG download) | `docs/diagrams.html` — double-click to open; needs internet the first time. Rebuild with `C:\xampp\php\php.exe scripts\build_diagrams.php` |
| Security checklist, how to test each control, known limitations | `docs/security-checklist.md` |
| Test plan, results, manual tests, traceability, defect log | `docs/test-plan.md` |
| Administrator user guide | `docs/admin-guide.md` |
| Customer user guide | `docs/customer-guide.md` |
| Troubleshooting guide | `docs/troubleshooting.md` |
| Deployment, backup and restore | `docs/deployment-and-backup.md` |
| Demonstration script, screenshot list, viva questions | `docs/demo-script.md` |
| Phase-by-phase log of files and tests | `docs/phase-log.md` |

## 6. Payments

Cash on Delivery and manual bank transfer (verified by an administrator). **No online card payment gateway is integrated**, and the website never asks for card details. Known limitations and possible improvements are listed in `docs/security-checklist.md` §8.

## 7. Sample data

`sql/seed.sql` contains **sample** products (names start with "Sample", SKUs with "SAMPLE-") with invented prices and stock for testing. Store contact and bank details are left empty on purpose. Replace them with GiftAura's real information from the admin panel before real use.

## 8. Version control checkpoints

One Git commit is made at the end of every phase:
```
git add -A
git commit -m "Phase N: <summary>"
git log --oneline
```
`config/config.php`, logs, uploaded images and backups are excluded by `.gitignore`.

## 9. Updating an existing installation (e-mail + design refresh)

If GiftAura was installed **before 17 Oct 2026**:
1. Take a backup (`scripts\backup.bat`).
2. Copy the new files over the project folder (your `config/config.php` and uploaded photos are kept).
3. phpMyAdmin → click **giftaura_db** → **SQL** tab → paste the contents of `sql/updates/2026-10-17_email.sql` → **Go**. Then do the same with `sql/updates/2026-10-17_banners.sql`, `sql/updates/2026-10-17_sale_wishlist.sql` `sql/updates/2026-10-18_customization.sql`, `sql/updates/2026-10-18_reviews.sql` and `sql/updates/2026-10-20_staff_inquiries.sql` and `sql/updates/2026-10-24_shop_sales.sql` (one block at a time; expected result: **24** tables). Log in to phpMyAdmin as `root` (the default) — `giftaura_app` is not allowed to change tables. Existing accounts are marked as verified.
4. Add the e-mail settings: `C:\xampp\php\php.exe scripts\setup_mail.php` → **1** (test mode) for now. This also adds `site_url` to your config.
5. `C:\xampp\php\php.exe scripts\system_check.php` → all checks should pass.
6. Commit: `git add -A` then `git commit -m "E-mail verification, password reset and order e-mails"`.
