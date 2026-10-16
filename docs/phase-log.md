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
