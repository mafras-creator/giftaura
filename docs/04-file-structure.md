# GiftAura — File Structure

Version 2.0 — final (Phase 9). This is the structure **as built**.

Project root on the development laptop: `C:\xampp\htdocs\giftaura\`
Public URL: `http://localhost/giftaura/`

## 1. Folder overview

| Folder | Reachable from a browser? | Contents |
|---|---|---|
| `public/` | **Yes** — the only public folder | Page controllers (shop, account, admin), CSS, JavaScript, images, uploaded product photos |
| `app/` | No (denied) | Bootstrap, helper functions, core classes, services (business logic), view templates |
| `config/` | No | `config.php` (real database password, **not in Git**) and `config.example.php` |
| `sql/` | No | Database creation, schema and sample-data scripts |
| `scripts/` | No | Command-line tools: setup, create admin, system check, diagram builder, backup |
| `storage/` | No | Application error logs |
| `tests/` | No | Automated end-to-end test scripts (SQLite test copy) |
| `docs/` | No (open the files directly from disk) | Requirements, design, diagrams, guides, test plan, security checklist |

The root `.htaccess` sends every request into `public/`, and each private folder has its own `Require all denied` as a second lock (tested by `scripts/system_check.php`, WEB-07 – WEB-19).

## 2. Full tree

```
giftaura/
├── .htaccess                 routes requests into public/, error pages, blocks hidden/test files
├── .gitignore                keeps config.php, logs, uploads, backups and test artefacts out of Git
├── README.md                 installation, startup and document index
│
├── app/
│   ├── bootstrap.php         config, autoloader, error handling, security headers, session
│   ├── helpers.php           e(), url(), asset(), money(), redirect(), safe_next(), slugify(), badges …
│   ├── core/                 framework-like building blocks
│   │   ├── Auth.php          login, logout, current user, requireLogin(), requireAdmin()
│   │   ├── Csrf.php          token create/verify
│   │   ├── Database.php      PDO connection, prepared queries, transactions
│   │   ├── Flash.php         one-time messages
│   │   ├── ImageUploader.php secure upload + GD re-encode
│   │   ├── Logger.php        log files without secrets
│   │   ├── Mailer.php        sends e-mail: test mode (files), Gmail SMTP, or off
│   │   ├── RateLimiter.php   login attempt limits
│   │   ├── Session.php       hardened session, idle timeout
│   │   ├── Validator.php     server-side validation rules
│   │   └── View.php          templates and layouts
│   ├── services/             business logic (one class per area)
│   │   ├── UserService.php            register, profile, password, admin creation
│   │   ├── SettingsService.php        store settings (read + validated update)
│   │   ├── CategoryService.php        category tree for the shop
│   │   ├── CategoryAdminService.php   category management
│   │   ├── ProductService.php         catalogue, search, filters, detail
│   │   ├── ProductAdminService.php    product, image and stock management
│   │   ├── CartService.php            session/database cart, totals in cents
│   │   ├── AddressService.php         address book, delivery address validation
│   │   ├── OrderService.php           place order (transaction), status workflow, cancel
│   │   ├── OrderAdminService.php      order list, admin status and payment changes
│   │   ├── PaymentService.php         methods, statuses, bank reference
│   │   ├── InventoryService.php       every stock change + ledger row
│   │   ├── CustomerAdminService.php   customer list, activation, temporary password
│   │   ├── ReportService.php          reports and CSV rows
│   │   ├── DashboardService.php       dashboard figures
│   │   ├── ActivityLogService.php     admin audit trail
│   │   ├── CheckoutException.php      customer-fixable error inside a transaction
│   │   ├── TokenService.php           one-time e-mail links (hashed, expiring, single use)
│   │   ├── BannerService.php          homepage banners: rules, dates, safe links
│   │   ├── WishlistService.php        heart button: save/remove, pending add after login
│   │   ├── BrandService.php           uploaded shop logo (no database needed)
│   │   ├── AboutService.php           About page texts (storage/content/about.json)
│   │   ├── PopularService.php         homepage "Popular categories" tiles (storage/content/popular.json)
│   │   └── EmailService.php           which e-mails are sent, e-mail log
│   └── views/
│       ├── layouts/          shop.php, admin.php, error.php, email.php
│       ├── partials/         product-card, pagination, account-nav, order-progress, bank-details, movement-list
│       ├── shop/             home, products, product, cart, checkout, order-confirmation, track-order,
│       │                     login, register, about, contact, verify-email, forgot-password, reset-password,
│       │                     account/{profile,password,orders,order,addresses}
│       ├── emails/           verify-email, reset-password, order-placed, order-status, payment, test, _button
│       ├── admin/            dashboard, orders, order, products, product-edit, categories, inventory,
│       │                     customers, customer, reports, banners, emails, activity, settings
│       └── errors/           403, 404, 500, generic
│
├── config/
│   ├── config.example.php    template (committed)
│   └── config.php            real settings, created by scripts/setup.php (NOT committed)
│
├── public/
│   ├── index.php · products.php · product.php · cart.php · checkout.php · order-confirmation.php
│   ├── track-order.php · login.php · register.php · logout.php · about.php · contact.php · 403.php · 404.php
│   ├── verify-email.php · resend-verification.php · forgot-password.php · reset-password.php · wishlist.php
│   ├── account/              index.php (profile) · password.php · orders.php · order.php · addresses.php · wishlist.php
│   ├── admin/                index.php (dashboard) · orders.php · order.php · products.php · product-edit.php
│   │                         categories.php · inventory.php · customers.php · customer.php
│   │                         banners.php · reports.php · report-export.php · emails.php · activity.php · settings.php
│   ├── assets/
│   │   ├── css/              shop.css · admin.css
│   │   ├── js/               shop.js · admin.js
│   │   └── img/              logo.svg · placeholder-product.svg
│   └── uploads/
│       ├── .htaccess         PHP disabled, only jpg/png/webp served
│       ├── products/         uploaded product photos (random names, not in Git)
│       ├── banners/          homepage banner pictures
│       ├── categories/       round category pictures
│       ├── brand/            uploaded shop logo
│       └── people/           founder photo (About page)
│
├── scripts/
│   ├── setup.php             checks PHP, tests the database, writes config/config.php
│   ├── create_admin.php      creates an administrator (command line only)
│   ├── system_check.php      50 checks on the real server (43 in Phase 8 + 6 e-mail checks + DB-13 columns)
│   ├── setup_mail.php        choose e-mail mode: test mode, Gmail (App Password) or off
│   ├── test_mail.php         sends one test e-mail and shows the conversation with the server
│   ├── backup.bat            database + photo backup with date in the file name
│   └── build_diagrams.php    rebuilds docs/diagrams.html from the Mermaid diagrams in docs/
│
├── sql/
│   ├── 00_create_database_and_user.sql   database + least-privilege user
│   ├── schema.sql                        20 tables, keys, constraints
│   ├── seed.sql                          settings, categories, SAMPLE products
│   └── updates/                          2026-10-17_email.sql, _banners.sql, _sale_wishlist.sql, 2026-10-18_customization.sql, 2026-10-18_reviews.sql (existing databases)
│
├── storage/
│   ├── logs/                 app-YYYY-MM-DD.log
│   ├── mail/                 e-mails saved in test mode (not in Git, blocked from the browser)
│   └── content/              about.json (About page texts, edited in the admin panel)
│
├── tests/                    schema_sqlite.sql, reset_test_db.php, test_router.php,
│                             shop_e2e.sh (58 checks), admin_e2e.sh (107 checks), email_e2e.sh (69 checks),
│                             banner_e2e.sh (91 checks), sale_wishlist_e2e.sh (36 checks), custom_e2e.sh (48 checks), review_e2e.sh (52 checks), email_unit.php (17 checks), fake_smtp.php,
│                             make_fixtures.php, README.md
│
└── docs/
    ├── 01-requirements.md        functional / non-functional requirements, business rules, status
    ├── 02-architecture.md        architecture, component and sequence diagrams, state machines, security layers
    ├── 03-database-design.md     ER diagram, tables, normalisation, referential actions
    ├── 04-file-structure.md      this file
    ├── 05-use-cases-and-flows.md use-case diagram and system flow diagrams
    ├── diagrams.html             all 12 diagrams as pictures (open from disk)
    ├── security-checklist.md     53 controls (38 + 13 e-mail + 2 banners), how to test them, limitations
    ├── test-plan.md              test levels, results, 39 manual tests, traceability, defects
    ├── admin-guide.md            how to run the store
    ├── customer-guide.md         how to shop
    ├── troubleshooting.md        common problems and fixes
    ├── deployment-and-backup.md  backup, restore, moving to another computer or a web host
    ├── demo-script.md            demonstration plan, screenshot list, likely viva questions
    └── phase-log.md              what each phase delivered and which tests were run
```

## 3. Naming conventions

| Item | Convention | Example |
|---|---|---|
| Public pages | lowercase, hyphenated | `order-confirmation.php` |
| Classes | PascalCase, one class per file, file name = class name | `OrderService.php` |
| Methods / variables | camelCase | `placeOrder()`, `$deliveryFee` |
| Database tables / columns | snake_case, plural tables | `order_items.unit_price` |
| CSS classes | lowercase, hyphenated, block__element | `.product-card__price` |
| URLs | slugs for products and categories | `product.php?slug=sample-red-rose-bouquet` |

## 4. Page → service → table map

| Page | Main service | Main tables |
|---|---|---|
| `products.php`, `product.php`, `index.php` | `ProductService`, `CategoryService` | products, categories, product_images |
| `cart.php` | `CartService` | carts, cart_items, products |
| `checkout.php` | `OrderService`, `AddressService`, `InventoryService` | orders, order_items, payments, order_status_history, inventory_movements, customer_addresses |
| `account/*.php` | `UserService`, `OrderService`, `AddressService`, `PaymentService` | users, orders, payments, customer_addresses |
| `track-order.php` | `OrderService` | orders, order_status_history |
| `admin/index.php` | `DashboardService`, `ReportService` | orders, payments, products, users |
| `admin/products.php`, `product-edit.php` | `ProductAdminService`, `ImageUploader` | products, product_images, inventory_movements |
| `admin/categories.php` | `CategoryAdminService` | categories |
| `admin/inventory.php` | `ProductAdminService`, `ReportService` | inventory_movements, products |
| `admin/orders.php`, `order.php` | `OrderAdminService`, `OrderService` | orders, payments, order_status_history |
| `admin/customers.php`, `customer.php` | `CustomerAdminService` | users, orders, customer_addresses |
| `admin/reports.php`, `report-export.php` | `ReportService` | orders, order_items, payments, products |
| `admin/activity.php` | `ActivityLogService` | admin_activity_log |
| `admin/settings.php` | `SettingsService` | settings |
| `login.php` | `Auth`, `RateLimiter` | users, login_attempts |
| `register.php`, `verify-email.php`, `resend-verification.php` | `UserService`, `TokenService`, `EmailService` | users, user_tokens, email_log |
| `forgot-password.php`, `reset-password.php` | `EmailService`, `TokenService`, `UserService` | users, user_tokens, email_log |
| `admin/emails.php` | `EmailService` | email_log, orders |
| `admin/banners.php`, `index.php` (slider) | `BannerService`, `ImageUploader` | banners |
