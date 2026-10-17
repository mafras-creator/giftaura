# GiftAura — Test Plan and Test Results

Version 1.0 — Phase 8 (16 Oct 2026). Results are recorded **only after a test has actually been run**. A test that has not been run yet is marked *Not yet run*.

## 1. Purpose and scope

To show that the GiftAura E-Commerce Web System meets the functional and non-functional requirements in `docs/01-requirements.md` and the security controls in `docs/security-checklist.md`.

**In scope:** customer website, administration panel, database rules, security controls, responsive layout, performance on the local server.
**Out of scope:** online card payments, e-mail delivery, load testing with many simultaneous users (see the known limitations in the security checklist).

## 2. Test levels and approach

| Level | What | How | Who / where |
|---|---|---|---|
| Unit | Helpers, validation rules, CSRF tokens, redirect safety, log redaction | PHP script, 48 checks (T3-02) | Claude, development workspace |
| Integration / end-to-end (automated) | Real pages over HTTP: cart, checkout, orders, cancellation, ownership, tracking, overselling race; every admin function | `tests/shop_e2e.sh` (54 checks), `tests/admin_e2e.sh` (106 checks) against a temporary SQLite copy of the schema + real seed data | Claude, development workspace |
| System (automated, real server) | MariaDB constraints, row locks, least-privilege user, data consistency; Apache `.htaccess` protection; security headers; page speed | `scripts/system_check.php` (43 checks) | Developer, **laptop (XAMPP)** |
| Acceptance / usability (manual) | Complete user journeys, real image upload, Excel export, phone and tablet layout | Section 6 checklist (M-01 – M-25) | Developer, **laptop (Chrome)** |
| Security | Every control in `docs/security-checklist.md` | Covered by the levels above (IDs cross-referenced) | Both |
| Regression | Re-run all automated suites after every phase | Same scripts | Claude, each phase |

## 3. Test environment

| Item | Development laptop (system + manual tests) | Automated test workspace |
|---|---|---|
| OS | Windows | Linux |
| Web server | Apache 2.4.58 (XAMPP) | PHP built-in server + test router |
| PHP | 8.2.12 | 8.3 |
| Database | MariaDB 10.4.32, port 3307, user `giftaura_app` | SQLite copy of the schema (same tables/columns) |
| Browser | Chrome (desktop + device toolbar for phone/tablet) | curl |

**Known difference:** SQLite has no `SELECT … FOR UPDATE`; it locks the whole database during a write instead. Row locking on MariaDB is therefore tested separately by `system_check.php` (DB-08).

## 4. Entry and exit criteria

- **Entry:** schema and seed imported; `scripts/setup.php` passed; an administrator exists.
- **Exit:** all automated checks pass; all `system_check.php` checks pass on the laptop; every manual test M-01 – M-25 run with no open critical defect; results recorded below.

## 5. Automated test results

### 5.1 Unit and early integration (Phase 3–4)
| Suite | Checks | Result | Date |
|---|---|---|---|
| Unit checks (T3-02) | 48 | 48 passed | 16 Oct 2026 |
| HTTP guards, CSRF, headers, error pages (T3-04 – T3-08) | 5 groups | all passed | 16 Oct 2026 |
| Catalogue (T4-01 – T4-20) and authentication (T3-09 – T3-12) | 24 | all passed | 16 Oct 2026 |

### 5.2 `tests/shop_e2e.sh` — 54 checks
Last run: 16 Oct 2026 on the Phase 7 code — **54 passed, 0 failed** (also passed on Phases 5 and 6).

| ID | Area | Example checks | Result |
|---|---|---|---|
| T5-01 – T5-04 | Cart | totals 9,000 + 400 = 9,400; quantity limited to stock; out-of-stock/inactive/invalid items refused; POST without CSRF → 419 | Pass |
| T5-05 – T5-08 | Checkout | login required; guest cart merged; invalid form → no order; tampered totals/fingerprint refused; stock drop just before ordering → refused | Pass |
| T5-09 | Order placement | totals, item snapshots, normalised phones, stock reduced, ledger rows, payment `pending`, history row, cart emptied, address saved | Pass |
| T5-10 – T5-11 | Privacy | other customer gets 404 on order/confirmation/cancel/address; tracking needs the right e-mail and hides the address | Pass |
| T5-12 – T5-14 | Cancel, bank transfer, delivery | stock restored once; reference → awaiting verification; free-delivery threshold | Pass |
| T5-15 | Race | two customers buy the last unit at the same moment → exactly one order, stock 0 | Pass |
| T5-16 | Roles | administrator cannot check out | Pass |

### 5.3 `tests/admin_e2e.sh` — 106 checks
Last run: 16 Oct 2026 on the Phase 7 code — **106 passed, 0 failed**.

| Area | Checks | Result |
|---|---|---|
| Access control (customer 403 on every admin page and on a forged POST; guest → login) | 24 | Pass |
| Categories (slugs, nesting rules, delete rules, XSS escaped, deactivation hides products) | 10 | Pass |
| Products (create with ledger, validation, price-change log, deactivate, search/filter) | 11 | Pass |
| Image upload security (PHP file, fake image, tiny/huge files refused; polyglot JPEG cleaned; random names; main image) | 11 | Pass |
| Stock (restock, correction rules, never negative, ledger = stock) | 5 | Pass |
| Orders and payments (workflow rules, bank-transfer guard, refunds, cancellation restores stock) | 17 | Pass |
| Reports (gross vs collected, cancelled/refunded excluded, best seller, bad dates) | 6 | Pass |
| CSV export (type, rows, formula-injection guard, unknown report) | 5 | Pass |
| Customers (spent, deactivate logs out, admin protected, temporary password) | 8 | Pass |
| Settings (validation, payment-method rule, contact page, no secrets in log, dashboard) | 9 | Pass |

### 5.4 `scripts/system_check.php` on the laptop (MariaDB + Apache)
**Run on the laptop on 16 Oct 2026 (Windows, XAMPP Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32): 43 of 43 checks passed.**
The full output (including page timings and the row-lock wait time) is saved on the laptop as `docs/system-check-2026-10-16-<time>.txt` and should be attached to the report as evidence.

| ID range | Checks |
|---|---|
| DB-01 – DB-12 | connection, 15 tables, +05:30 time zone, least-privilege user, negative stock refused, wrong order total refused, foreign key, **row lock (second checkout waits)**, ledger = stock, order subtotal = items, every order has a payment, no plain-text passwords |
| WEB-01 – WEB-25 | homepage, CSP, X-Frame-Options, nosniff, no X-Powered-By, cookie flags; 13 private paths blocked (config, app, sql, logs, scripts, tests, docs, .git, .htaccess, env-check, upload listing); PHP cannot run in uploads; admin → login; SQL and script text in search harmless; 404 page; logout not possible by link |
| PERF-01 – PERF-06 | homepage, shop, search, category, product, cart each under 1 second (median of 3) |

## 6. Manual acceptance tests (laptop, Chrome)

Record the actual result and Pass/Fail for each test.

| ID | Requirement | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| M-01 | FR-09 | Register a new customer with name, e-mail, mobile and password | Account created, "My account" opens | Account created, My account opened (16 Oct 2026) | Pass |
| M-02 | FR-10 | Log out, log in again | Logged in; "Welcome back" message | Logged out and in again; welcome message shown (16 Oct 2026) | Pass |
| M-03 | FR-11 | My account → change name and mobile → Save | "Your profile has been updated" | Profile updated message shown (16 Oct 2026) | Pass |
| M-04 | FR-11 | Change password → log out → log in with the new password | New password works, old one does not | New password accepted, old password refused (16 Oct 2026) | Pass |
| M-05 | FR-01, FR-02 | Homepage → click a category → click a subcategory | Products of that category; breadcrumb shows the path | Flower Bouquets 4, Rose Bouquets 2, breadcrumb correct (16 Oct 2026) | Pass |
| M-06 | FR-03 – FR-06 | Search "rose"; set price 2000–5000; tick In stock; sort by price | Results match all filters | Filters and price sort applied correctly (16 Oct 2026) | Pass |
| M-07 | FR-13, FR-14 | Add a product from a card and another from its page with quantity 2; change quantity in the cart | Badge and totals update correctly | Cart badge and totals updated (16 Oct 2026) | Pass |
| M-08 | FR-15 | Log out, add an item as a guest, log in | The guest item is in the customer's cart | Guest item kept after login (16 Oct 2026) | Pass |
| M-09 | FR-17 – FR-19 | Checkout with Cash on delivery and a new address (save it) | Order reference shown; address saved under Addresses | Order reference shown; address saved (16 Oct 2026) | Pass |
| M-10 | FR-20, FR-43 | Place a bank-transfer order; enter a transfer reference; admin opens the order | Payment "Awaiting verification" with the reference | Payment shown as Awaiting verification with reference (16 Oct 2026) | Pass |
| M-11 | FR-23 | Customer cancels a pending order | Status Cancelled; stock back to the previous value | Order cancelled; stock restored (16 Oct 2026) | Pass |
| M-12 | FR-24 | Log out → Track your order with reference + e-mail | Status and progress shown, no address | Status and progress shown without address (16 Oct 2026) | Pass |
| M-13 | FR-31, FR-32 | Admin adds a category and a subcategory; tries to delete a category with products | Created; delete refused with a clear message | Categories created; deactivated category hidden from shop; no delete for non-empty category (17 Oct 2026) | Pass |
| M-14 | FR-34, FR-36 | Admin adds a product with opening stock and uploads a JPG photo | Product with photo appears in the shop | Product created and photo shown in the shop (17 Oct 2026) | Pass |
| M-15 | FR-37 | Admin restocks +5, then corrects −1 with a note | Stock changes; two ledger lines | Stock 5 → 10 → 9; two ledger lines (17 Oct 2026) | Pass |
| M-16 | FR-41 | Admin moves an order Pending → Confirmed → Processing → Ready → Delivered | Each step in history; customer sees the same | All status steps in history; customer saw Delivered (17 Oct 2026) | Pass |
| M-17 | FR-43 | Admin marks a COD order Paid, then Refunded with a note | Paid date, then refunded date recorded | Paid date then refunded date recorded (17 Oct 2026) | Pass |
| M-18 | FR-45, FR-46 | Reports → Sales (this month) → Export CSV → open in Excel | Figures match the dashboard; CSV opens correctly | CSV opened in Excel; figures matched dashboard (17 Oct 2026) | Pass |
| M-19 | FR-44 | Admin deactivates a customer, then sets a temporary password and reactivates | Customer cannot log in while inactive; temp password works | Login refused while inactive; temporary password worked (17 Oct 2026) | Pass |
| M-20 | FR-48, FR-25 | Admin enters store phone/address and bank details | Contact page and bank-transfer instructions show them | Phone on Contact page; bank details on bank-transfer order (17 Oct 2026) | Pass |
| M-21 | FR-38 | Reduce a product's stock to its alert level | It appears in Low-stock alerts on the dashboard | Product appeared in Low-stock alerts (17 Oct 2026) | Pass |
| M-22 | NFR-15 | Chrome F12 → device toolbar → 375 px: home, shop, product, cart, checkout, My orders, admin | Everything readable, no sideways scrolling, menu button works | Phone (390 px): pages readable, no sideways scroll, menu button works (17 Oct 2026) | Pass |
| M-23 | NFR-15 | Same at 768 px (tablet) | Layout adapts, nothing overlaps | Tablet (768 px): layout adapts, no overlap (17 Oct 2026) | Pass |
| M-24 | Security S6 | Log in → My orders → Log out → browser Back | Private page not shown | After logout, Back did not show orders (17 Oct 2026) | Pass |
| M-25 | All | Run `scripts/system_check.php` | All checks PASS | 43 of 43 passed (16 Oct 2026) | Pass |

### 6.1 Summary of manual tests
**25 of 25 manual acceptance tests passed** on the development laptop (16–17 Oct 2026). No open defects.

## 7. Requirements traceability (summary)

| Requirements | Covered by |
|---|---|
| FR-01 – FR-08, FR-25 (catalogue, pages) | T4-01 – T4-20, M-05, M-06, M-20, PERF-01 – 06 |
| FR-09 – FR-11 (accounts) | T3-09 – T3-12, M-01 – M-04 |
| FR-12 – FR-24 (cart, checkout, orders, tracking) | T5-01 – T5-16, M-07 – M-12 |
| FR-30 – FR-48 (administration) | admin_e2e (106), M-13 – M-21 |
| FR-49 (first admin via CLI) | Laptop setup, Phase 3 |
| NFR-01 – NFR-11 (security) | security checklist IDs, admin_e2e access control, system_check DB/WEB |
| NFR-12 – NFR-14 (integrity) | T5-07 – T5-15, DB-05 – DB-11 |
| NFR-15 – NFR-17 (usability) | M-22, M-23, laptop screenshots |
| NFR-18 (performance) | PERF-01 – PERF-06 |
| NFR-19 – NFR-21 (maintainability, portability, localisation) | Code structure; laptop run; LKR/time-zone checks (DB-03) |

## 8. Defects found and fixed

| # | Found in | Defect | Cause | Fix | Re-test |
|---|---|---|---|---|---|
| D-01 | Phase 5, automated test | A login could fail in the test database about 1 time in 50 | Clean-up query used MySQL-only `INTERVAL` syntax inside the login | Date calculated in PHP instead | 54/54 passed three times |
| D-02 | Phase 6, automated test | Price change in the activity log showed `1250.5` instead of `1250.50` | Value printed exactly as returned by the database | Formatted to 2 decimals | 106/106 passed |
| D-03 | Phase 6, laptop screenshot | Dashboard amounts wrapped onto two lines; welcome message shown twice | Cards too narrow for large amounts; greeting repeated in the page text | Responsive font, wider cards; duplicate line removed | Phase 7 re-test |
| D-04 | Phase 3, laptop setup | Database login refused during setup | Password typed with brackets `[ ]` (user input, not a code defect) | Instructions clarified; password reset via phpMyAdmin | Setup passed |

## 9. How to re-run the automated tests (optional)

1. **System check (recommended before the demonstration):** in PowerShell, from the project folder:
   `C:\xampp\php\php.exe scripts\system_check.php`
2. **End-to-end suites** need Git Bash. See `tests/README.md`. They use a temporary SQLite database and never touch `giftaura_db`.
