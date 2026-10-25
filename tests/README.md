# GiftAura automated tests

These scripts were used to test the application during development. They run the **real pages** of the
website through PHP's built-in web server, against a temporary **SQLite copy** of the database
(`schema_sqlite.sql` mirrors the tables and columns of `sql/schema.sql`; the real `sql/seed.sql` is loaded into it).
Nothing touches the real `giftaura_db` database.

| File | Purpose |
|---|---|
| `schema_sqlite.sql` | SQLite version of the schema (test only) |
| `reset_test_db.php` | Creates `tests/test.db` from the schema + seed data and adds a test admin (`admin@example.com` / `admin123`) |
| `test_router.php` | Router for `php -S`: maps `/giftaura/...` to `public/` and connects the app to `tests/test.db` |
| `shop_e2e.sh` | 58 checks: cart, checkout, e-mail verification before checkout, orders, cancellation, ownership, tracking, overselling race |
| `admin_e2e.sh` | 107 checks: admin access control, categories, products, image-upload security, stock, order/payment workflow, reports, CSV, customers, settings |
| `email_e2e.sh` | 69 checks: verification links, checkout block, order/status/payment e-mails, password reset, admin e-mail tools, mode off, SMTP against `fake_smtp.php` |
| `banner_e2e.sh` | 91 checks: new shop design (yellow Add to cart, menus), banner access/validation/life cycle, category pictures |
| `sale_wishlist_e2e.sh` | 36 checks: sale prices (admin, shop, cart/order), wishlist heart, login hand-over, access rules |
| `custom_e2e.sh` | 48 checks: personalised products (admin settings, Personalise button, message box, cart edit, guest-to-login hand-over, checkout fingerprint, order snapshot, e-mail, required/optional, switching off) |
| `review_e2e.sh` | 52 checks: reviews only after delivery, validation, escaping, public name, averages and star bars, card rating, edit/delete, other customers and admins refused, admin hide/show/delete, paging |
| `staff_e2e.sh` | 96 checks: staff accounts (generated password, username login, forced change, permissions, 403 pages, reset, deactivate), contact form (validation, spam trap, rate limit), My inquiries, admin reply and e-mail, status |
| `shop_sales_e2e.sh` | 75 checks: login before add to cart (product kept after login/registration), profile card, in-shop sales (access, validation, invoice, stock, ledger, reports, cancel, staff area) |
| `email_unit.php` | 17 checks: header-injection guards and one-time link rules (in-memory database) |
| `fake_smtp.php` | Tiny local SMTP server used by `email_e2e.sh` (accepts or rejects the login). Test only |
| `make_fixtures.php` | Creates the image files used by the upload-security checks in `admin_e2e.sh` |

The scripts are written for **bash** (Git Bash on Windows works) and need `curl` and PHP with `pdo_sqlite` and `gd`.
How to run them (from the `tests` folder, after copying `config/config.example.php` to `config/config.php` on a **test copy** of the project — the e-mail suite rewrites the `mail` settings in that file):
```
php make_fixtures.php
php -d opcache.enable=0 -S 127.0.0.1:8097 test_router.php &    # shop_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8096 test_router.php &    # admin_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8095 test_router.php &    # email_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8094 test_router.php &    # banner_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8093 test_router.php &    # sale_wishlist_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8092 test_router.php &    # custom_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8091 test_router.php &    # review_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8089 test_router.php &    # staff_e2e.sh
php -d opcache.enable=0 -S 127.0.0.1:8088 test_router.php &    # shop_sales_e2e.sh
php reset_test_db.php && bash shop_e2e.sh      # repeat reset before each suite
php email_unit.php
```
`opcache.enable=0` makes the test server notice the config changes made by `email_e2e.sh` immediately. Results of every run are recorded in `docs/phase-log.md`.

Known difference: SQLite has no `SELECT … FOR UPDATE`; the test router removes it, and SQLite instead locks the whole
database during a write. Row locking itself must therefore be confirmed on MariaDB (Phase 8).
