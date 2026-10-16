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
| `shop_e2e.sh` | 54 checks: cart, checkout, orders, cancellation, ownership, tracking, overselling race |
| `admin_e2e.sh` | 106 checks: admin access control, categories, products, image-upload security, stock, order/payment workflow, reports, CSV, customers, settings |

The scripts are written for **bash** (Git Bash on Windows works) and need `curl` and PHP with `pdo_sqlite` and `gd`.
How to run them is described in the Phase 8 test plan. Results of every run are recorded in `docs/phase-log.md`.

Known difference: SQLite has no `SELECT … FOR UPDATE`; the test router removes it, and SQLite instead locks the whole
database during a write. Row locking itself must therefore be confirmed on MariaDB (Phase 8).
