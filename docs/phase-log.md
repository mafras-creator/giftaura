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

**Still to run on the laptop:** profile update and password change (registration, login, lock-out and admin blocking were later tested — see T3-09 to T3-12 under Phase 4).

**Tested on the laptop (16 Oct 2026):** `scripts/setup.php` connected and wrote the configuration; `scripts/create_admin.php` created the administrator; admin login showed the dashboard with Active products 10, categories 4, customers 1, low stock 2, out of stock 1 and the correct three low-stock rows; a customer account was registered. ✅
Phase 3 committed to Git (`101cd05`).

## Phase 4 — Product catalogue (16 Oct 2026)

### Files
| Status | Files |
|---|---|
| New | `app/services/ProductService.php`; `public/products.php`, `product.php`, `about.php`, `contact.php`; `app/views/shop/products.php`, `product.php`, `about.php`, `contact.php`; `app/views/partials/product-card.php`, `pagination.php` |
| Changed | `app/services/CategoryService.php` (tree, slugs, subcategories), `app/helpers.php` (`product_image_url`, `stock_status`), `public/index.php` + `app/views/shop/home.php` (featured, latest, category links, search), `app/views/layouts/shop.php` (Shop/About/Contact menu, header search, footer links), `public/assets/css/shop.css`, `public/assets/js/shop.js` |

### Design decisions
- Search uses `LIKE` on name, short description, description and SKU; every word must match. Wildcards typed by the user (`%`, `_`) are escaped. The FULLTEXT index in the schema remains available if the catalogue grows large.
- A product is visible only if it is active and its category (and that category's parent) is active.
- Sort options and filter values are whitelisted/validated; invalid prices are ignored with a message; min/max are swapped if reversed; page numbers are clamped.
- No "Add to cart" button yet: it is added in Phase 5 together with the working cart, so there is no button without a function.

### Tests run by Claude
The real `seed.sql` was loaded into an SQLite copy of the schema (same tables and columns) and the actual pages were requested over HTTP. MariaDB-specific behaviour is still to be confirmed on the laptop.

| ID | Test | Expected | Result |
|---|---|---|---|
| T4-01 | Homepage | 3 category cards with counts 4 / 3 / 3, 4 featured, 8 latest | ✅ |
| T4-02 | All products | 10 (inactive sample hidden) | ✅ |
| T4-03 | Category Flower Bouquets (includes Rose Bouquets subcategory) | 4 | ✅ |
| T4-04 | Subcategory Rose Bouquets, breadcrumb shows parent | 2, parent link | ✅ |
| T4-05 | Unknown category / SQL text in slug | 404 | ✅ |
| T4-06 | Search "rose" / "pink rose" / "SAMPLE-GI" / "%" | 2 / 1 / 3 / 0 | ✅ |
| T4-07 | Search `' OR 1=1 --` | 0 results, no error (SQL injection blocked) | ✅ |
| T4-08 | Search `<script>alert(1)</script>` | shown as text, never executed (XSS blocked) | ✅ |
| T4-09 | Price 2000–3000, and reversed 3000–2000 | 4 and 4 | ✅ |
| T4-10 | Invalid price `abc` | ignored with a warning | ✅ |
| T4-11 | In stock only | 9 | ✅ |
| T4-12 | Sort low→high / high→low / A–Z | Rs. 1,800 / Rs. 6,200 / Chocolate Gift Box first | ✅ |
| T4-13 | Invalid sort, page 99, page −1 | page still loads (fallbacks) | ✅ |
| T4-14 | 25 products → pagination | 3 pages, links keep the sort | ✅ |
| T4-15 | Product detail: price, breadcrumb, 3 related items | correct | ✅ |
| T4-16 | Out-of-stock and low-stock labels | "Out of stock", "Only 3 left" | ✅ |
| T4-17 | Inactive product / unknown slug / `../../x` | 404 | ✅ |
| T4-18 | Parent category deactivated | its subcategory and products disappear (404) | ✅ |
| T4-19 | Product with uploaded image / image file missing | image shown / placeholder shown | ✅ |
| T4-20 | Contact page with empty settings | "not been published yet" message, nothing invented | ✅ |
| T3-09 | Register with a forged `role=admin` field | account created as **customer**; phone normalised | ✅ |
| T3-10 | Customer opens `admin/` | 403 | ✅ |
| T3-11 | 6 wrong passwords | 5× "incorrect", then "Too many…"; correct password also refused while locked | ✅ |
| T3-12 | Login with `next=https://evil.com` and upper-case email | logged in, redirected inside the site | ✅ |

**Tested on the laptop:** Phase 4 committed to Git (`afd3f19`).

## Phase 5 — Cart, checkout, orders, inventory (16 Oct 2026)

### Files
| Status | Files |
|---|---|
| New services | `CartService.php`, `AddressService.php`, `InventoryService.php`, `OrderService.php`, `PaymentService.php`, `CheckoutException.php` |
| New pages | `public/cart.php`, `checkout.php`, `order-confirmation.php`, `track-order.php`, `account/orders.php`, `account/order.php`, `account/addresses.php` |
| New views | `shop/cart.php`, `checkout.php`, `order-confirmation.php`, `track-order.php`, `account/orders.php`, `account/order.php`, `account/addresses.php`; partials `order-progress.php`, `bank-details.php` |
| Changed | product card and product page (Add to cart), shop layout (cart link with count, Track order / My orders links), account menu, `login.php` and `register.php` (merge guest cart), `helpers.php` (status badges), `RateLimiter.php` (portable clean-up query), CSS and JS |

### Design decisions
- **Cart storage:** session for guests, `carts`/`cart_items` for customers, merged at login/registration. Only product ids and quantities are stored, never prices.
- **Money:** calculated in whole cents (integers) and stored as `DECIMAL(10,2)`; no floating-point rounding.
- **Order placement:** one transaction — products locked with `SELECT … FOR UPDATE`, availability re-checked, totals recalculated from locked prices, order + item snapshots + payment + status history inserted, stock reduced through `InventoryService` (conditional `UPDATE … WHERE stock_quantity + change >= 0` plus ledger row), cart emptied. Any failure rolls everything back.
- **Cart fingerprint:** the checkout form carries a hash of items, quantities, prices and delivery fee. If anything changed before "Place order", the order is refused and the customer sees the new total — the customer always agrees to the exact amount charged.
- **Cancellation:** customers may cancel only `pending` orders; the status is re-checked under a row lock; stock is restored once, with `order_cancelled` ledger rows.
- **Order references** such as `GA261016-7K3M9Q` use random characters (no 0/O/1/I), so they cannot be guessed by counting.
- **Tracking page:** reference + order email, sent by POST (email not in URL/logs), limited to 10 lookups per 10 minutes per session, shows status only (no address or phone).
- **Payments:** Cash on Delivery and bank transfer only. Bank details come from Settings; if empty, the customer is told to contact the store — nothing is invented. A customer's bank reference moves the payment to "awaiting verification"; only an administrator (Phase 6) can mark it paid.
- **Administrators cannot place orders** (prevents mixing staff and customer data).

### Tests run by Claude (SQLite copy of schema + real seed data, real pages over HTTP; `e2e.sh`, run 3 times on a fresh database)
**Result: 54 passed, 0 failed** (all three runs). Highlights:

| ID | Test | Result |
|---|---|---|
| T5-01 | Guest adds 2 × Rs 4,500 → subtotal 9,000, delivery 400 (setting), total 9,400 | ✅ |
| T5-02 | Adding 5 of a product with 3 in stock → limited to 3 with a message | ✅ |
| T5-03 | Out-of-stock / inactive / invalid product id cannot be added | ✅ |
| T5-04 | Cart POST without CSRF token → 419 | ✅ |
| T5-05 | Guest checkout → login page, then registration merges the guest cart | ✅ |
| T5-06 | Invalid checkout form → errors, no order created | ✅ |
| T5-07 | Tampered fingerprint and fake `subtotal`/`total_amount` fields → refused, no order | ✅ |
| T5-08 | Stock drops below cart quantity just before ordering → refused, stock unchanged | ✅ |
| T5-09 | Valid COD order: totals 12,800 + 400 = 13,200; item snapshots; phones normalised; stock 12→10 and 3→2; two ledger rows; payment `cod/pending`; history `NULL→pending`; cart emptied; address saved as default | ✅ |
| T5-10 | Another customer: order page, confirmation, cancel, edit/delete address → 404 / no change | ✅ |
| T5-11 | Tracking: right email shows status; wrong email refused; address not shown | ✅ |
| T5-12 | Customer cancels pending order → stock restored 10→12, 2→3; cancelling again does not restore twice | ✅ |
| T5-13 | Bank-transfer order with saved address; bank details not invented; reference → `awaiting_verification`; `<script>` reference rejected | ✅ |
| T5-14 | Free-delivery threshold 5,000 → delivery "Free" | ✅ |
| T5-15 | **Race:** two customers order the last unit at the same moment → exactly one order, stock 0 (never negative) | ✅ |
| T5-16 | Administrator cannot check out | ✅ |

A test run also exposed a portability problem in the login-attempt clean-up query (it used MySQL-only date syntax and could interrupt a login under the test database). It was rewritten to use a date calculated in PHP, which works everywhere.

**Note:** the row-lock (`FOR UPDATE`) behaviour itself is MariaDB-specific; the SQLite test database achieves the same protection by locking the whole database during a write. The race test should be repeated on the laptop in Phase 8.

**Tested on the laptop:** a real COD order (GA261016-QDTTZS, Sample Scented Candle Set, Rs 2,750 + Rs 400 = Rs 3,150) was placed on MariaDB and confirmed on screen. Phase 5 committed to Git.

## Phase 6 — Administration panel (16 Oct 2026)

### Files
| Status | Files |
|---|---|
| New services | `CategoryAdminService.php`, `ProductAdminService.php`, `OrderAdminService.php`, `CustomerAdminService.php`, `ReportService.php`, `ActivityLogService.php`; `app/core/ImageUploader.php` |
| Changed services | `SettingsService.php` (validated update), `DashboardService.php` (sales, orders, payments), `helpers.php` (`slugify`) |
| New admin pages | `public/admin/orders.php`, `order.php`, `products.php`, `product-edit.php`, `categories.php`, `inventory.php`, `customers.php`, `customer.php`, `reports.php`, `report-export.php`, `activity.php`, `settings.php` (and updated `index.php`) |
| New views | `app/views/admin/*.php` for each page; partial `movement-list.php`; full admin sidebar in `layouts/admin.php` |
| Assets | `admin.css` (forms, tables, tabs, image grid, chart, settings), `admin.js` (auto-submit filters, image preview, double-submit guard) |
| Tests | `tests/` folder with the SQLite test harness and both end-to-end scripts (blocked from the browser) |

### Design decisions
- **Products are deactivated, never deleted**, because orders and the stock ledger refer to them. Empty categories may be deleted; others are deactivated.
- **Stock is only changed through the stock box / inventory page**, each change writing a ledger row with a reason. Corrections require a note; stock can never go below zero.
- **Image uploads:** extension + real MIME (`finfo`) + `getimagesize` + size and dimension limits, then **re-encoded with GD** into a new file with a random name. This strips hidden code and EXIF/GPS data. Uploads folder cannot run PHP.
- **Order workflow** enforced by `OrderService::TRANSITIONS`; confirming an unpaid bank-transfer order needs an explicit "confirm before payment" tick.
- **Payment workflow:** pending/awaiting → paid/failed, failed → paid, paid → refunded (refund requires a note). A cancelled order cannot be marked paid. Who and when are recorded.
- **Reports** use one definition everywhere (see `docs/01-requirements.md` §5.5); sales are summed from `orders`, never from a join, so each order counts once. A daily bar chart is drawn as SVG without any chart library.
- **CSV export** includes a UTF-8 marker (Sinhala/Tamil safe in Excel) and neutralises cells beginning with `= + - @` (CSV/formula injection).
- **Temporary passwords** are random, shown once to the admin, stored only as a hash and never logged. Admin accounts cannot be deactivated from the customer screen.
- **Activity log** records every admin action (never passwords or bank numbers; settings changes record only *which* keys changed).

### Tests run by Claude — `tests/admin_e2e.sh` on a fresh SQLite copy: **106 passed, 0 failed**
| Area | What was checked | Result |
|---|---|---|
| Access control (14) | Customer gets 403 on all 11 admin pages incl. CSV export and a forged settings POST; guest redirected to login; all 10 pages load for the admin | ✅ |
| Categories (10) | Auto slug; duplicate slug refused; category with children cannot become a child; subcategory cannot be a parent; non-empty category not deleted; XSS in names escaped; empty category deleted; deactivating a subcategory hides its products | ✅ |
| Products (11) | Create with opening stock written to the ledger; duplicate slug, negative price, 3-decimal price and unknown category refused; price change logged; deactivate → 404 in shop; search and stock filters | ✅ |
| Image security (11) | Valid JPEG/PNG accepted with random names; `.php` file, PHP renamed to `.jpg`, too-small and 2.5 MB files refused; **JPEG with PHP code appended is accepted but the code is removed by re-encoding**; no stray files; main image switching and deletion (file removed too) | ✅ |
| Stock (5) | Restock +5; correction without note refused; −2 accepted; −500 refused; negative restock refused; ledger sum = stock | ✅ |
| Orders & payments (17) | Cannot skip pending → delivered; full workflow with 5 history rows; delivered cannot be cancelled; COD paid with time and admin; customer sees the new status; unpaid bank transfer blocked from confirmation unless overridden; refund only after paid and only with a note; admin cancellation restores stock; cancelled order cannot be marked paid | ✅ |
| Reports (6) | Gross sales and collected revenue exclude cancelled and refunded orders; status totals include cancelled; best seller correct; invalid dates fall back with a message | ✅ |
| CSV (5) | `text/csv`; one row per order; `=HYPERLINK(...)` neutralised to `'=HYPERLINK(...)`; inventory export; unknown report 404 | ✅ |
| Customers (8) | Total spent excludes cancelled/refunded; deactivation logs the customer out and blocks login; admin cannot be deactivated here; temporary password shown once, not logged, and works for login | ✅ |
| Settings (9) | Invalid fee refused; both payment methods cannot be disabled; values saved and normalised; phone appears on Contact page; bank number not in activity log; dashboard shows today's sales | ✅ |

The Phase 5 shop tests (`tests/shop_e2e.sh`) were re-run on the Phase 6 code: **54 passed, 0 failed** (no regressions).

**Tested on the laptop:** admin dashboard on MariaDB showed sales today Rs 12,200 (3,150 + 3,150 + 5,900), collected Rs 6,300 (two orders marked paid), 1 pending, 2 in progress, low/out of stock 2/1 — all matching the definitions. Phase 6 committed to Git (`07dc359`).

## Phase 7 — Security hardening and polish (16 Oct 2026)

### Code review performed
- Every page that handles POST calls `Csrf::verify()` — checked automatically for all files in `public/` (none missing).
- Every unescaped `<?= ... ?>` in templates was reviewed: all print only fixed strings (e.g. `' selected'`) or trusted helpers (`csrf_field`, partials, badges).
- Every SQL statement built with string interpolation only joins fixed WHERE fragments or whitelisted ORDER BY clauses; all user values are bound parameters.

### Changes
| Area | Change |
|---|---|
| Headers (`app/bootstrap.php`) | `X-Powered-By` removed; `Cross-Origin-Opener-Policy: same-origin`; HSTS when served over HTTPS; `Cache-Control: no-store, private` for every page shown to a logged-in user |
| Root `.htaccess` | `display_errors` forced off for the whole site; `env-check.php`, `phpinfo.php`, `info.php`, `test.php` denied; security headers on static files |
| Admin dashboard | Duplicate welcome line removed; money values no longer wrap (responsive font size, wider cards) |
| Admin top bar | "Change password" link |
| Shop header | Desktop menu now starts at 1080 px so medium screens use the menu button instead of a crowded header |
| Small phones (≤ 420 px) | Product cards, hero search, bank details and product facts stack neatly |
| Loading states | Log in, Create account, Place order, Upload and Save settings show "Please wait"-style text and `aria-busy` after the first click |
| Documentation | `docs/security-checklist.md` (38 controls with test steps, known limitations, pre-demo checklist) |

### Tests run by Claude after the changes
- `php -l` on every PHP file and `node --check` on both JavaScript files: no errors.
- `tests/admin_e2e.sh`: **106 passed, 0 failed**. `tests/shop_e2e.sh`: **54 passed, 0 failed**.
- Headers: guest page has `X-Frame-Options`, `Cross-Origin-Opener-Policy`, no `X-Powered-By`; logged-in page has `Cache-Control: no-store, private`.

**Not verifiable here (no Apache in the test workspace):** the `.htaccess` rules themselves. They are part of the Phase 8 manual tests on the laptop.

**Tested on the laptop:** Phase 7 installed; user started Phase 8.

## Phase 8 — Testing (16 Oct 2026)

### Files
| Status | Files |
|---|---|
| New | `scripts/system_check.php` (43 automated checks on the real XAMPP server: MariaDB constraints, row lock, least-privilege user, data consistency, `.htaccess` protection, headers, uploads cannot run PHP, page speed); `docs/test-plan.md` (levels, environment, criteria, results, 25 manual acceptance tests, traceability, defect log) |

### Tests run by Claude
- `php -l scripts/system_check.php`: no syntax errors.
- The system check itself needs Apache + MariaDB and is therefore run **on the laptop**; its first results will be recorded in `docs/test-plan.md` §5.4.

**Tested on the laptop (16 Oct 2026):** `scripts/system_check.php` — **43 of 43 checks passed** on MariaDB + Apache (includes the MariaDB row-lock test DB-08 and all `.htaccess` protections).

**Manual acceptance tests (16–17 Oct 2026):** M-01 – M-25 all passed on the laptop (Chrome, desktop, 390 px phone and 768 px tablet views). Details in `docs/test-plan.md` §6.

### Phase 8 summary
| Suite | Result |
|---|---|
| Unit checks | 48 / 48 |
| `tests/shop_e2e.sh` | 54 / 54 |
| `tests/admin_e2e.sh` | 106 / 106 |
| `scripts/system_check.php` (laptop, MariaDB + Apache) | 43 / 43 |
| Manual acceptance tests (laptop) | 25 / 25 |

**Tested on the laptop:** Phase 8 committed to Git.

## Phase 9 — Documentation and demonstration preparation (17 Oct 2026)

### Files
| Status | Files |
|---|---|
| New | `docs/05-use-cases-and-flows.md` (use-case diagram, use-case descriptions, 6 flow diagrams), `docs/admin-guide.md`, `docs/customer-guide.md`, `docs/troubleshooting.md`, `docs/deployment-and-backup.md`, `docs/demo-script.md`, `scripts/backup.bat`, `.gitattributes` |
| Updated | `docs/04-file-structure.md` (final tree and page → service → table map), `docs/02-architecture.md` (payment diagram: failed → paid), `docs/security-checklist.md` (test IDs from Phase 8), `docs/diagrams.html` (12 diagrams), `README.md` (final) |

### Checks run by Claude
- `php -l` on all PHP files: no errors. No application code changed in this phase.
- Every file path mentioned in `README.md` and `docs/*.md` was checked to exist (the only exceptions are `config/config.php`, which is created on each computer, and `public/env-check.php`, which was deliberately deleted).
- `docs/diagrams.html` rebuilt with 12 diagrams. The Mermaid diagrams could not be rendered in the build workspace (no browser or internet access), so they are to be checked visually by opening `docs/diagrams.html` on the laptop.
- `scripts/backup.bat` could not be run in the build workspace (Windows-only); it is to be run once on the laptop.

## E-mail extension — verification, password reset, order e-mails (17 Oct 2026)

Requested after Phase 9: "customer Gmail verification" and an e-mail when an order is placed or completed. Decisions agreed with the student: block **checkout** (not login) until the e-mail is verified; built-in Gmail sender with **test mode** as the default; add **"Forgotten password?"** by e-mail.

### Files
| Status | Files |
|---|---|
| New | `app/core/Mailer.php`, `app/services/TokenService.php`, `app/services/EmailService.php`, `app/views/layouts/email.php`, `app/views/emails/*` (7), `app/views/shop/{verify-email,forgot-password,reset-password}.php`, `app/views/admin/emails.php`, `public/{verify-email,resend-verification,forgot-password,reset-password}.php`, `public/admin/emails.php`, `scripts/setup_mail.php`, `scripts/test_mail.php`, `sql/updates/2026-10-17_email.sql`, `storage/mail/.gitkeep`, `tests/{email_e2e.sh,email_unit.php,fake_smtp.php,make_fixtures.php}` |
| Updated | `sql/schema.sql` (17 tables), `config/config.example.php` (`site_url`, `mail`), `scripts/setup.php` (keeps mail settings), `scripts/system_check.php` (MAIL-01 – 06), `app/core/{Auth,View}.php`, `app/helpers.php` (`absolute_url`), `UserService`, `OrderService`, `OrderAdminService`, `CustomerAdminService`, shop and admin layouts, cart, checkout, login, register, admin customer pages, dashboard, `shop.css`, `.gitignore`, tests, all documents |

### Tests run by Claude (Linux workspace, PHP 8.3, SQLite copy of the schema, real pages over HTTP)
- `php -l` on every PHP file: no errors.
- `tests/email_unit.php`: **17 passed, 0 failed**. `tests/email_e2e.sh`: **69 passed, 0 failed** (includes SMTP against `tests/fake_smtp.php`: accepted login, wrong password, unreachable server).
- Regression: `tests/shop_e2e.sh` **58 passed** (54 + 4 new), `tests/admin_e2e.sh` **107 passed** (106 + 1 new), 0 failed, on a fresh copy; no errors in the application log.
- Three defects found and fixed during these tests (D-05 – D-07 in the test plan).
- The update script's "mark existing accounts verified" rule was checked on SQLite (run twice: old account verified, new unverified account untouched).
- Pages and e-mails were viewed in Chromium (cart with the verification notice, forgotten-password page on a phone width, order and payment e-mails).

### Not tested by Claude (needs the laptop)
- Real sending through Gmail (no internet access to Gmail from the workspace), the MariaDB update script, `system_check.php` (49 checks), and the ER diagram rendering — manual tests **M-26 – M-34**.

## Design refresh — homepage banners and new shop look (17 Oct 2026)

Requested by the student with reference screenshots of a large Sri Lankan gift shop: purple header with a big search bar and yellow search button, "All categories" menu, a banner section **managed from the admin panel**, round category pictures, product grids and a **yellow Add to cart** button. Only the layout ideas were used; no names, logos, pictures or text were copied.

### Files
| Status | Files |
|---|---|
| New | `app/services/BannerService.php`, `public/admin/banners.php`, `app/views/admin/banners.php`, `sql/updates/2026-10-17_banners.sql`, `public/uploads/{banners,categories}/.gitkeep`, `tests/banner_e2e.sh` |
| Updated | `app/core/ImageUploader.php` (upload folders), `CategoryService` (pictures), `CategoryAdminService` + categories admin page (picture upload), shop layout (new header), `home.php`, `product.php`, product card, `shop.css`, `shop.js` (menu, slider), `admin.css`, admin menu, `sql/schema.sql` (18 tables), `scripts/system_check.php` (DB-02 = 18), `scripts/backup.bat` (pictures zip), `tests/{schema_sqlite.sql,test_router.php,make_fixtures.php}`, documents |

### Tests run by Claude
- `tests/banner_e2e.sh`: **60 passed, 0 failed** (after fixing D-08). Regression on the same code: unit 17/17, shop 58/58, admin 107/107, e-mail 69/69; no errors in the log.
- Screenshots in Chromium at 1366 px and 390 px of the homepage, menu, product page and Admin → Banners; two layout problems found and fixed (banner text unreadable on phones → text moved under the picture; admin banner table too wide).

### Not tested by Claude (needs the laptop)
- MariaDB update script, `backup.bat` change (Windows only), real banner pictures, Chrome check — manual tests **M-35 – M-39**.

## Sale prices and wishlist (17 Oct 2026)

Requested: show a crossed-out old price next to the new price on discounted products (e.g. cake 2000 → 1500), and a wishlist heart on every product picture.

- **Design decision:** `products.price` stays the price the customer pays; the new column `compare_at_price` holds the normal price while on sale. Cart, checkout, orders and reports therefore need no change and can never charge the crossed-out price.
- New: `WishlistService`, `wishlist_items` table, `public/wishlist.php`, `account/wishlist.php`, partials `price` and `wishlist-button`, `sale_info()` helper, `sql/updates/2026-10-17_sale_wishlist.sql`, `tests/sale_wishlist_e2e.sh`. Updated: product admin (normal + sale price fields, list), `ProductService` (columns, On sale filter), product card, product page, header (Wishlist + count, On sale link), account menu, login/register (pending heart), CSS, schema (19 tables), system check, documents.
- **Tests run by Claude:** `sale_wishlist_e2e.sh` **36/36**; regression unit 17, shop 58, admin 107, e-mail 69, design 60 — all passed, no errors logged. Screenshots at desktop and phone width checked.
- **Pending on the laptop:** update SQL, M-40 – M-41.

## Logo upload (18 Oct 2026)

Admin → Settings → **Logo**: upload a PNG/JPG/WebP (ImageUploader folder `brand`), used in the shop header, admin sidebar, error pages and browser tab; wide logos hide the name text; **Use the default logo** resets. File-based (`BrandService`), so no database update is needed. `backup.bat` now also saves `public\uploads\brand`. Tests: `banner_e2e.sh` 70/70 (10 new logo checks); all other suites passed on the same code; header, phone and admin sidebar checked in screenshots (one layout fix: label under a wide logo in the admin sidebar).

## About page (18 Oct 2026)

Rebuilt from the student's reference screenshots: hero (logo, location pill, two-colour headline, live numbers), our story with founder card (**Asjadh Mohamed Mafras**, photo or initials, quote), mission and vision cards, "what we offer" (categories + real shop features), and "Visit us" for **Bandarakoswatta, Kurunegala** with an embedded Google map (CSP allows `frame-src https://www.google.com` on this page only). Texts were written as a **draft** and are edited in **Admin → About page** (`AboutService`, JSON in `storage/content`, founder photo in `uploads/people`) — no invented figures, no database change. `backup.bat` also saves the founder photo and `about.json`. Tests: `banner_e2e.sh` 83/83 (13 new About checks), all other suites passed; desktop, phone and editor screenshots checked (one padding fix). The map itself could not load in Claude's workspace (no internet) — check on the laptop.

## Footer and Popular categories (18 Oct 2026)

- Footer: categories in two columns (first 12 by display order + "View all N categories" and "On sale"); new **Customer care** column. Fixes the very tall footer with 18 categories.
- Homepage **Popular categories** mosaic (from the student's reference): pastel tiles with a short line, category name and round picture; chosen in Admin → Categories (`PopularService`, `storage/content/popular.json`, no database change).
- Tests: `banner_e2e.sh` 91/91, all other suites passed; desktop and phone screenshots checked (two layout fixes: picture overlapping long names; "Shop now" hidden on phones).

## Personalised products (18 Oct 2026)

Requested with screenshots of a gift shop that shows "Personalizable" on product cards: the administrator marks a product as customisable; the customer types a message that is ordered with the product.

- **Database:** `products.is_customizable`, `custom_label`, `custom_max_length`, `custom_required`; `cart_items.customization_text`; `order_items.customization_text` (snapshot). Update script `sql/updates/2026-10-18_customization.sql` (3 blocks, still 19 tables). `system_check.php` DB-13 checks the columns (50 checks).
- **Admin:** Personalisation box in the product form (tick box, question, longest message 10–500, required); "Personalised" in the product list; personalised orders flagged in the order list; message shown under the item on the order page.
- **Shop:** "Personalisable" label and **Personalise** button on cards; message box with live character counter on the product page; message shown and editable in the cart; kept when a guest logs in; checkout summary, confirmation, My order page and order e-mail show it.
- **Rules:** message cleaned on the server and always escaped; included in the checkout fingerprint; re-checked inside the order transaction; one message per product per order (adding again replaces it).
- **Tests run by Claude:** `custom_e2e.sh` **48/48**; regression on a fresh copy: unit 17, shop 58, admin 107, e-mail 69, banner 91, sale/wishlist 36 — all passed, no errors logged. Desktop and phone screenshots checked.
- **Pending on the laptop:** update SQL, M-42.

## Product reviews (18 Oct 2026)

Requested with a screenshot of a gift shop's "Reviews (2)" section: a customer whose order was delivered can review the product from their account, and the review is shown under the product.

- **Database:** new table `product_reviews` (20 tables) — `sql/updates/2026-10-18_reviews.sql`; system check DB-02 now expects 20.
- **Customer:** My account → **My reviews** (waiting / written), star picker (works without JavaScript), optional comment with counter, edit and delete; "Write a review" links on delivered orders; the "delivered" e-mail invites a review.
- **Shop:** "Customer reviews (N)" section under every product (average, stars, bars per star, first name + initial, Verified purchase, 5 per page); rating next to the price; stars and count on product cards.
- **Admin:** Admin → **Reviews** (search, star and status filters, Hide/Show, Delete; activity log).
- **Tests run by Claude:** `review_e2e.sh` **52/52**; all other suites passed on one fresh copy (17, 58, 107, 69, 91, 36, 48); no errors logged. Screenshots at desktop and phone width checked.
- **Pending on the laptop:** update SQL, M-43.

## Staff administrators and customer inquiries (20 Oct 2026)

- **Roles:** main (super) administrator = `admin`; new role `staff`. Staff log in with a username, get a generated one-time password and must change it; areas: products, categories, inquiries, orders, customers, reviews, content, reports. Staff accounts, Settings, E-mail log and Activity log stay with the main administrator.
- **Database:** `users` gets `username`, `permissions`, `must_change_password`, `created_by` (e-mail now optional); new tables `customer_inquiries` and `inquiry_replies` (**22 tables**) — `sql/updates/2026-10-20_staff_inquiries.sql`; system check DB-02 expects 22, DB-13 checks the new columns.
- **Admin:** Staff accounts page; Change password page; menu, dashboard cards and page guards follow the permissions; role label in the top bar; order history shows who changed a status. Inquiries list (filters, search, badge) and inquiry page (reply by e-mail, status).
- **Shop:** Contact page message form (spam trap, 5 per hour); My account → **My inquiries**; reply e-mail `inquiry_reply`.
- **Tests run by Claude:** `staff_e2e.sh` **96/96**; all other suites passed on one fresh copy (17, 58, 107, 69, 91, 36, 48, 52); no errors logged. Screenshots checked.
- **Pending on the laptop:** update SQL, M-44, M-45. Chapter 5 of the report does not yet describe this feature.

## In-shop sales, login before cart, profile redesign (24 Oct 2026)

- **In-shop sales (FR-71):** Admin → In-shop sales (list, search, dates, status), New in-shop sale (products, quantities, discount, payment, cash received, live totals), printable invoice INV-YYMMDD-0001, cancel invoice with reason (stock returned). Stock moves through `InventoryService` (reasons `shop_sale`, `shop_sale_voided`, linked by `shop_sale_id`). New staff area "In-shop sales". Dashboard card and Reports cards. Tables `shop_sales`, `shop_sale_items` (**24 tables**) — `sql/updates/2026-10-24_shop_sales.sql`.
- **Login before cart (FR-72):** guest Add to cart → login page "Almost there!" → after login or registration the product is in the cart and the customer returns to the page they were on (changed 25 Oct at the owner's request; previously went to the cart page). Logged-in customers also stay on the same page after Add to cart; the cart opens only from the Cart link.
- **Profile (FR-73):** igp-style profile card and icon side menu on all My account pages.
- **Tests run by Claude:** `shop_sales_e2e.sh` **79/79**; all other suites passed on one fresh copy (17, 58, 107, 69, 91, 36, 48, 52, 96); no errors logged. Screenshots at desktop and phone width and print preview checked.
- **Pending on the laptop:** update SQL, M-46 – M-48. Chapter 5 does not yet describe FR-69 – FR-73.

## Project deliverables (from the project brief) → where they are

| # | Deliverable | Location |
|---|---|---|
| 1 | Complete source code | `app/`, `public/`, `scripts/` |
| 2 | MySQL schema and sample seed data | `sql/schema.sql`, `sql/seed.sql`, `sql/00_create_database_and_user.sql` |
| 3 | Secure database configuration template | `config/config.example.php`, `scripts/setup.php` |
| 4 | README with installation and startup instructions | `README.md` |
| 5 | Functional requirements list | `docs/01-requirements.md` |
| 6 | Database ER diagram | `docs/03-database-design.md`, `docs/diagrams.html` |
| 7 | System architecture diagram | `docs/02-architecture.md`, `docs/diagrams.html` |
| 8 | Use-case diagram and system flow diagrams | `docs/05-use-cases-and-flows.md`, `docs/diagrams.html` |
| 9 | Test plan with test cases and actual results | `docs/test-plan.md`, `tests/`, `scripts/system_check.php` |
| 10 | Troubleshooting guide | `docs/troubleshooting.md` |
| 11 | Administrator user guide | `docs/admin-guide.md` |
| 12 | Customer user guide | `docs/customer-guide.md` |
| 13 | Security checklist | `docs/security-checklist.md` |
| 14 | Deployment and backup instructions | `docs/deployment-and-backup.md`, `scripts/backup.bat` |
| + | E-mail extension (verification, password reset, notifications) | see sections above; `docs/test-plan.md` §5.5 and M-26 – M-34 |
| + | Design refresh and admin-managed banners | see sections above; `docs/test-plan.md` §5.6 and M-35 – M-39 |
| — | Screenshots and demonstration preparation | `docs/demo-script.md` (screenshot list, script, viva questions) |

**Tested on the laptop (17 Oct 2026):** `docs/diagrams.html` showed all 12 diagrams; `scripts/backup.bat` created the database backup successfully. **Project complete.**
