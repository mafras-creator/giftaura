# GiftAura — Phase Log

A record of what each phase delivered and which tests were actually run. Results are recorded only after a test has been executed.

## Phase 1 — Environment (16 Oct 2026)
Windows laptop with XAMPP: Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32 (port **3307**), phpMyAdmin 5.2.1, Git 2.55, VS Code. GD extension enabled during this phase.

## Phase 2 — Design and database (16 Oct 2026)
Requirements, architecture, ER diagram, file structure, `schema.sql`, `seed.sql`, database user script.
**Tested on the laptop:** schema and seed imported in phpMyAdmin — 15 tables; categories 4, products 11, inventory_movements 10, settings 17. ✅

## Phase 3 — Foundation, authentication, authorisation (16 Oct 2026)

### Files created
| Area | Files |
|---|---|
| Configuration | `config/config.example.php`, `scripts/setup.php` (writes `config/config.php`) |
| Bootstrap | `app/bootstrap.php`, `app/helpers.php` |
| Core | `app/core/Database.php`, `Session.php`, `Csrf.php`, `Validator.php`, `View.php`, `Flash.php`, `Logger.php`, `Auth.php`, `RateLimiter.php` |
| Services | `app/services/UserService.php`, `SettingsService.php`, `CategoryService.php`, `DashboardService.php` |
| Layouts and views | `app/views/layouts/shop.php`, `admin.php`, `error.php`; `shop/home.php`, `login.php`, `register.php`, `account/profile.php`, `account/password.php`; `admin/dashboard.php`; `partials/account-nav.php`; `errors/403.php`, `404.php`, `500.php`, `generic.php` |
| Pages | `public/index.php`, `login.php`, `register.php`, `logout.php`, `account/index.php`, `account/password.php`, `admin/index.php`, `403.php`, `404.php` |
| Assets | `public/assets/css/shop.css`, `admin.css`; `js/shop.js`, `admin.js`; `img/logo.svg`, `placeholder-product.svg` |
| Scripts | `scripts/create_admin.php` |
| Modified | `.htaccess` (friendly 403/404 pages), `README.md`, `docs/01-requirements.md` |

### Tests run by Claude in a Linux workspace (PHP 8.3, no MariaDB available)
| ID | Test | Result |
|---|---|---|
| T3-01 | `php -l` on all 42 PHP files | ✅ no syntax errors |
| T3-02 | 48 unit checks: `url()`, `money()`, `e()` escaping, `safe_next()` rejects 5 open-redirect tricks, phone normalisation, all validation rules, CSRF token create/compare, log redaction of passwords/tokens | ✅ 48 passed, 0 failed |
| T3-03 | Render every template with sample data: values escaped, no inline scripts or style attributes (CSP-safe) | ✅ |
| T3-04 | HTTP: `GET logout.php` → 303 redirect, does not log out | ✅ |
| T3-05 | HTTP: `POST logout.php` without CSRF token → 419 "Page expired" | ✅ |
| T3-06 | HTTP: guest opens `admin/` and `account/password.php` → 303 to `login.php?next=…` | ✅ |
| T3-07 | HTTP: security headers present; session cookie `HttpOnly; SameSite=Lax; path=/giftaura/` | ✅ |
| T3-08 | HTTP: unknown page → 404 page; database down → generic 500 page with no SQL details; error written to log | ✅ |

**Not yet run (needs the real database on the laptop):** registration, login, wrong-password message, rate-limit lock after 5 failures, profile update, password change, admin dashboard figures, customer blocked from admin (403). These are tests T3-09 to T3-16 in the Phase 3 instructions and are recorded here once done.
