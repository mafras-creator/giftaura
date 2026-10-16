# GiftAura — E-Commerce Web System

Final-year Software Development Project (MIT), South Eastern University of Sri Lanka.
An online shop for the GiftAura gift store: flower bouquets, beauty creams and gift items.

**Stack:** HTML5 · CSS3 · vanilla JavaScript · PHP 8.2 · MariaDB 10.4 · Apache (XAMPP). No frameworks and no Composer packages.

> **Project status: Phase 7 complete.** All customer and administration features are built, and security hardening is done (see `docs/security-checklist.md`). Phase 8 is full testing on MariaDB; Phase 9 completes the user guides. See `docs/01-requirements.md` for the status of each requirement.

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

### 2.6 Open the shop
- Shop: <http://localhost/giftaura/>
- Admin: <http://localhost/giftaura/admin/>

## 3. Project layout

```
giftaura/
├── public/     only folder reachable from the browser (pages, admin, assets, uploads)
├── app/        PHP core classes, services and templates (blocked from the browser)
├── config/     database settings (config.php is not committed to Git)
├── sql/        00_create_database_and_user.sql, schema.sql, seed.sql
├── scripts/    command-line tools
├── storage/    logs
├── tests/      automated test scripts (blocked from the browser)
└── docs/       requirements, architecture, database design, diagrams, guides
```
Full tree: `docs/04-file-structure.md`.

## 4. Documentation

| Document | File |
|---|---|
| Requirements checklist | `docs/01-requirements.md` |
| System architecture, order and payment workflows | `docs/02-architecture.md` |
| Database design and ER diagram | `docs/03-database-design.md` |
| File structure | `docs/04-file-structure.md` |
| Security checklist (controls, how to test them, known limitations) | `docs/security-checklist.md` |
| Phase-by-phase log of files and test results | `docs/phase-log.md` |
| All diagrams as pictures | `docs/diagrams.html` — open the file directly from disk (double-click). It needs an internet connection the first time to load the diagram library. Rebuild with `C:\xampp\php\php.exe scripts\build_diagrams.php`. |

## 5. Payments

Cash on Delivery and manual bank transfer (verified by an administrator). **No online card payment gateway is integrated.**

## 6. Version control checkpoints

One Git commit is made at the end of every phase:
```
git add -A
git commit -m "Phase N: <summary>"
git log --oneline
```
`config/config.php`, logs and uploaded images are excluded by `.gitignore`.
