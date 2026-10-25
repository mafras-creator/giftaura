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

### 5.2 `tests/shop_e2e.sh` — 58 checks
Last run: 17 Oct 2026 on the code with the e-mail extension — **58 passed, 0 failed** (54 of 54 on Phases 5–7; T5-17 added with the e-mail extension).

| ID | Area | Example checks | Result |
|---|---|---|---|
| T5-01 – T5-04 | Cart | totals 9,000 + 400 = 9,400; quantity limited to stock; out-of-stock/inactive/invalid items refused; POST without CSRF → 419 | Pass |
| T5-05 – T5-08 | Checkout | login required; guest cart merged; invalid form → no order; tampered totals/fingerprint refused; stock drop just before ordering → refused | Pass |
| T5-09 | Order placement | totals, item snapshots, normalised phones, stock reduced, ledger rows, payment `pending`, history row, cart emptied, address saved | Pass |
| T5-10 – T5-11 | Privacy | other customer gets 404 on order/confirmation/cancel/address; tracking needs the right e-mail and hides the address | Pass |
| T5-12 – T5-14 | Cancel, bank transfer, delivery | stock restored once; reference → awaiting verification; free-delivery threshold | Pass |
| T5-15 | Race | two customers buy the last unit at the same moment → exactly one order, stock 0 | Pass |
| T5-16 | Roles | administrator cannot check out | Pass |
| T5-17 | E-mail verification | new customer cannot open checkout; cart shows the notice; verification e-mail logged; link verifies the account | Pass |

### 5.3 `tests/admin_e2e.sh` — 107 checks
Last run: 17 Oct 2026 on the code with the e-mail extension — **107 passed, 0 failed** (106 of 106 on Phase 7; one access-control check added for the E-mail log page).

| Area | Checks | Result |
|---|---|---|
| Access control (customer 403 on every admin page and on a forged POST; guest → login) | 25 | Pass |
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
With the e-mail extension, design refresh, personalisation and reviews the script has **50 checks** (DB-02 expects 20 tables; DB-13 checks the sale-price and personalisation columns) (DB-02 now expects 18 tables; MAIL-01 – MAIL-06 added). The 49-check run on the laptop is manual test M-34.
The full output (including page timings and the row-lock wait time) is saved on the laptop as `docs/system-check-2026-10-16-<time>.txt` and should be attached to the report as evidence.

| ID range | Checks |
|---|---|
| DB-01 – DB-12 | connection, 18 tables (15 before the e-mail extension and design refresh), +05:30 time zone, least-privilege user, negative stock refused, wrong order total refused, foreign key, **row lock (second checkout waits)**, ledger = stock, order subtotal = items, every order has a payment, no plain-text passwords |
| WEB-01 – WEB-25 | homepage, CSP, X-Frame-Options, nosniff, no X-Powered-By, cookie flags; 13 private paths blocked (config, app, sql, logs, scripts, tests, docs, .git, .htaccess, env-check, upload listing); PHP cannot run in uploads; admin → login; SQL and script text in search harmless; 404 page; logout not possible by link |
| MAIL-01 – MAIL-06 | e-mail mode set; `app.site_url` set; `storage/mail` writable in test mode; saved e-mails not reachable from the browser; SMTP settings complete (Gmail mode); no failed e-mails in the last 7 days |
| PERF-01 – PERF-06 | homepage, shop, search, category, product, cart each under 1 second (median of 3) |

### 5.5 E-mail extension — `tests/email_e2e.sh` (69 checks) and `tests/email_unit.php` (17 checks)
Last run: 17 Oct 2026 — **69 passed, 0 failed** and **17 passed, 0 failed**. The SMTP part uses `tests/fake_smtp.php`, a small local mail server, so no real e-mail is sent. **Real Gmail sending can only be tested on the laptop (M-30 – M-32).**

| ID | Area | Example checks | Result |
|---|---|---|---|
| TE-01 | Registration | verification e-mail logged; secret is 64 hex characters; database holds only its SHA-256 hash; test-mode header; banner shown | Pass |
| TE-02 | Checkout blocked | unverified customer redirected from checkout; direct POST creates no order | Pass |
| TE-03 | Verification link | GET only shows a button and does not use the link; POST without CSRF → 419; tampered, non-hex, expired and used links refused; resend makes a new link and cancels the old one; 4th link within an hour refused | Pass |
| TE-04 | Order e-mails | confirmation with order number, total and link; customer name escaped (`<b>` shown as text); confirmed / processing / ready / delivered / paid e-mails; status note included; customer cancellation e-mail | Pass |
| TE-05 | Forgotten password | same answer for unknown and known address; no e-mail for unknown or admin addresses; weak password refused without using the link; new password works, old does not; link single-use; 6th request in 15 min refused | Pass |
| TE-06 | Admin tools | "not verified" badge in list and detail; mark verified + activity log; E-mail log lists and filters; test-mode notice; customers get 403 | Pass |
| TE-07 | Mode off | order placed, e-mail logged as "skipped" | Pass |
| TE-08 | SMTP (fake server) | login accepted; correct recipient; encoded subject; text + HTML parts; password not in logs; wrong password → order still placed, logged "failed" with App Password hint, dashboard warning; server unreachable → order placed; unencrypted SMTP to an outside host refused | Pass |
| TE-09 | Unit (`email_unit.php`) | line breaks in subject/name refused; invalid or multiple addresses refused; token single use, wrong purpose, altered secret, older link cancelled, hourly limit, inactive account | Pass |

### 5.6 Design refresh — `tests/banner_e2e.sh` (91 checks)
Last run: 18 Oct 2026 — **91 passed, 0 failed** (with all other suites passing on the same code). Same day, on the same code: unit 17/17, `shop_e2e.sh` 58/58, `admin_e2e.sh` 107/107, `email_e2e.sh` 69/69. Pages were also checked in Chromium at 1366 px and 390 px (phone) width.

| ID | Area | Example checks | Result |
|---|---|---|---|
| TD-01 | New design | search bar with yellow button; All categories menu; welcome section without banners; round category pictures; every product card's Add button is yellow; no Add button when out of stock; big yellow Add to cart and "Similar products" on product pages; cart badge | Pass |
| TD-02 | Access | customer gets 403 on Admin → Banners and on a forged POST; guest → login | Pass |
| TD-03 | Validation | picture required; picture narrower than 1000 px, PHP disguised as JPG, `javascript:` and `//other-site` links, end date before start, button without link — all refused, no file left behind | Pass |
| TD-04 | Homepage | slider shown; title escaped; button link inside the shop; picture served; arrows and dots only with 2+ banners; display order; picture-only banner is a whole link with the title as alt text | Pass |
| TD-05 | Life cycle | hide; future start → *Scheduled*; past end → *Ended*; edit keeps the picture; replacing deletes the old file; banner with a missing file skipped; delete removes row and file; 7 activity-log entries | Pass |
| TD-06 | Category pictures | upload shown in the round icon; bad file refused (category still saved, old picture kept); remove picture | Pass |
| TD-08 | Product photos | new product with 4 photos at once (first is main); 5 chosen → 4 kept; 5th upload refused; bad file skipped with its name in the message, good ones kept; product without photos; 2 photos at once on the edit page; no orphan files | Pass |
| TD-09 | Logo | default logo; customer 403; square logo in header (name kept), browser-tab icon and admin; fake image refused (logo kept); wide logo replaces old file and hides the name text; reset to default; activity log | Pass |
| TD-10 | About page | founder card, initials and location; live category count; map frame allowed only on this page; customer 403 on editor; draft notice; invalid year refused; save with photo; texts escaped and split into paragraphs; year and photo shown; remove photo; map switched off; activity log | Pass |
| TD-11 | Popular categories | default tiles with gift counts; customer 403; unknown category and too-long line refused; chosen order, duplicate ignored, line escaped; admin box keeps the choice; inactive category hidden | Pass |
| TD-07 | Main categories | add main category *Hampers* with picture; **+ Subcategory** form pre-filled; *Fruit Hampers* saved under it; both appear on the homepage / menu | Pass |

### 5.7 Sale prices and wishlist — `tests/sale_wishlist_e2e.sh` (36 checks)
Last run: 17 Oct 2026 — **36 passed, 0 failed**. Same day, same code: unit 17/17, shop 58/58, admin 107/107, e-mail 69/69, design 60/60.

| ID | Area | Example checks | Result |
|---|---|---|---|
| TS-01 | Admin | sale price equal to normal, 0 or text refused; saved as price 3375 / normal 4500; activity log; form shows both; admin list shows the sale | Pass |
| TS-02 | Shop | -25% badge, crossed-out normal price and sale price on cards; "You save Rs. 1,125.00 (25%)"; On sale page and menu link | Pass |
| TS-03 | Money | cart and order line use the sale price; ending the sale restores the normal price; past order unchanged; badge gone | Pass |
| TS-04 | Wishlist | heart on every card and on the product page; CSRF; guest → login → product saved; toggle; header count; My wishlist page; inactive/unknown products refused; outside return address ignored; separate per customer; admins excluded; deactivated product hidden | Pass |

### 5.8 Personalised products — `tests/custom_e2e.sh` (48 checks)
Last run: 18 Oct 2026 — **48 passed, 0 failed**. Same day, on the same code (fresh copy): unit 17/17, `shop_e2e.sh` 58/58, `admin_e2e.sh` 107/107, `email_e2e.sh` 69/69, `banner_e2e.sh` 91/91, `sale_wishlist_e2e.sh` 36/36; no errors in the log. Desktop and phone screenshots checked.

| ID | Area | Example checks | Result |
|---|---|---|---|
| TC-01 | Admin | off by default; longest message < 10 or > 500 and question > 80 characters refused; saved with question, limit and required; activity log; form ticked; admin list "Personalised" | Pass |
| TC-02 | Shop | card shows "Personalisable" and a **Personalise** button instead of quick Add to cart; product page message box with question, limit and Required | Pass |
| TC-03 | Cart | required message missing → refused; 31 characters with a 30 limit → refused and the text kept once for correction; line breaks and control characters cleaned; adding again replaces the message (qty 2); HTML shown as text; CSRF; empty message refused; normal products cannot get a message; Sinhala text with joiner kept | Pass |
| TC-04 | Login | guest message moved to the saved cart; edit in the cart | Pass |
| TC-05 | Order | checkout summary; message changed in another tab → order refused (fingerprint); order item keeps the message; confirmation, My order, admin order page, admin list badge and order e-mail show it; later product edits do not change it | Pass |
| TC-06 | Settings changes | optional message (add without); made required later → cart asks and checkout is blocked; switched off → Add to cart again, settings kept, old cart message not ordered | Pass |

### 5.9 Product reviews — `tests/review_e2e.sh` (52 checks)
Last run: 18 Oct 2026 — **52 passed, 0 failed**. Same day, all suites on one fresh copy: unit 17/17, shop 58/58, admin 107/107, e-mail 69/69, banner 91/91, sale/wishlist 36/36, personalisation 48/48; no errors in the logs. Desktop and phone screenshots checked.

| ID | Area | Example checks | Result |
|---|---|---|---|
| TR-01 | Before delivery | review form 404; "Nothing to review yet"; no review links; product shows "No reviews yet" | Pass |
| TR-02 | After delivery | delivered e-mail links to My reviews; both products waiting; links on the order page; CSRF; no stars / 6 stars / 1001 characters refused | Pass |
| TR-03 | Showing | saved with "Nimal P." and the delivered order; product page count, average 5.0, full 5-star bar, comment escaped, Verified purchase, e-mail never shown; card shows rating and count; edit keeps one review | Pass |
| TR-04 | Who may review | customer without a delivered order, product not in the order, administrator → 404; direct POST saves nothing; cancelled order does not count; average 2.5 shows half stars | Pass |
| TR-05 | Administrator | customer 403; list, real customer name, rating filter; hide → gone from product page and average recalculated; customer sees "Hidden by the store"; customer edit keeps it hidden; show; activity log; delete; unknown action 400 | Pass |
| TR-06 | Delete and paging | own review deleted and can be written again; 7 reviews → 5 per page, Older reviews, out-of-range page clamped | Pass |

### 5.10 Staff administrators and customer inquiries — `tests/staff_e2e.sh` (96 checks)
Last run: 20 Oct 2026 — **96 passed, 0 failed**. Same day, all suites on one fresh copy: unit 17/17, shop 58/58, admin 107/107, e-mail 69/69, banner 91/91, sale/wishlist 36/36, personalisation 48/48, reviews 52/52; no errors in the logs. Screenshots checked.

| ID | Area | Example checks | Result |
|---|---|---|---|
| TA-01 | Create staff | Staff accounts page for the main administrator only; CSRF; bad username, no area and duplicate username refused; unknown area removed; 12-character password with upper/lower/digit shown once; stored only as a hash and not in any log; activity log | Pass |
| TA-02 | First login | username login (any letter case); wrong password refused; forced to Change password before any page; weak or same password refused; then dashboard | Pass |
| TA-03 | Permissions | menu and dashboard show only the ticked areas; 5 allowed pages 200; 13 other pages (orders, customers, reports, CSV, reviews, banners, about, settings, e-mail log, activity log, staff, order, customer) 403; staff cannot create staff by a direct POST; staff adds a category; staff cannot check out | Pass |
| TA-04 | Manage staff | change areas takes effect at once; super admin cannot be edited as staff (404); new password forces a change and old password stops working; deactivate logs the person out and blocks login; activate; activity log; customers cannot log in with a username or open admin pages | Pass |
| TI-01 | Contact form | form and hidden trap field; logged-in details and `?order=` filled in; CSRF; 5 validation errors and typed values kept; spam trap looks sent but saves nothing; guest and customer messages saved (lower-case e-mail, upper-case order); reference INQ-0001 shown; 6th message in an hour refused | Pass |
| TI-02 | Answering | My inquiries shows only own messages; dashboard card and menu badge; list escaped, subject filter, search; unknown id 404; no order/customer links without those areas; empty reply refused; reply saved with the staff member, status, e-mail log and e-mail (reference, escaped, link to My inquiries or Contact for guests); customer sees the reply; status change without reply; unknown status ignored; activity log | Pass |

### 5.11 In-shop sales, login before cart, profile — `tests/shop_sales_e2e.sh` (76 checks)
Last run: 25 Oct 2026 — **76 passed, 0 failed** (all other suites passed again the same day). Same day, all suites on one fresh copy: unit 17/17, shop 58/58, admin 107/107, e-mail 69/69, banner 91/91, sale/wishlist 36/36, personalisation 48/48, reviews 52/52, staff 96/96; no errors in the logs. Screenshots (desktop, phone, print) checked.

| ID | Area | Example checks | Result |
|---|---|---|---|
| TP-01 | Login before cart | guest Add to cart → login page with "Almost there" and the product name; Create account link; register page shows it too; out-of-stock or missing required message → not sent to login; after login → back to the same product page with "added to your cart" and cart count 2; after registration → back to the page they were on, cart has the product and its personalisation; unsafe return address ignored; verification e-mail still sent; logged-in customers go straight to the cart | Pass |
| TP-02 | Profile | avatar initial, large name, Verified / not verified, e-mail, mobile, location from the address (or Sri Lanka); Edit opens the form, Cancel; errors keep the form open; saved name shown; 7-item icon menu on all account pages | Pass |
| TS-01 | Access | customer 403; admin list/new/menu; staff with the area can sell, without it 403 on all three pages; CSRF | Pass |
| TS-02 | Validation | no product, quantity 0, line without product, more than stock (also when the same product is on two lines), discount > subtotal, bad discount/phone/method, cash less than total; typed values kept; nothing saved and stock unchanged | Pass |
| TS-03 | Sale | invoice INV-YYMMDD-0001, prices from the database (a price sent by the browser is ignored), discount and change, items snapshot, stock reduced, ledger rows linked to the invoice, invoice page (shop name, phone when set, customer, totals, Print, note), product hidden online can be sold, list/search/date filter, dashboard, reports, inventory link, website shows out of stock, activity log | Pass |
| TS-04 | Cancel invoice | reason required; stock returned; ledger rows; Cancelled stamp; second cancel refused; cancelled invoices not counted; unknown action 400 | Pass |

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

**E-mail extension (17 Oct 2026) — to be run on the laptop after installing the update.** M-26 – M-29 use test mode (no internet needed); M-30 – M-32 need Gmail.

| ID | Requirement | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| M-26 | FR-50 | Test mode. Register a new customer → open the newest file in `storage\mail` (double-click) → press **Verify my e-mail address** → **Confirm** | "Your e-mail address is verified"; banner disappears | | Pending |
| M-27 | FR-51 | Before step M-26's confirmation: add a product to the cart | Yellow banner on every page; cart says "Please verify…" and the checkout button is greyed out | | Pending |
| M-28 | FR-52 | Log out → Log in → **Forgotten your password?** → enter the customer's e-mail → open the newest `storage\mail` file → choose a new password → log in | New password works; the same link opened again says "invalid, expired or already used" | | Pending |
| M-29 | FR-53 – FR-55 | Place a COD order → admin: Confirmed → Processing → Ready → Delivered → mark Paid → Admin → **E-mail log** | 6 e-mails for the order, all "Saved"; each file in `storage\mail` shows the right status | | Pending |
| M-30 | FR-57 | `scripts\setup_mail.php` → 2 (Gmail) → Gmail address + App Password → `scripts\test_mail.php` → your own address | `[OK] Sent`; "It works!" e-mail arrives (check Spam) | | Pending |
| M-31 | FR-50, FR-53 | Gmail mode. Register with a second real address you own → open the e-mail on your phone and press the button → place an order | Verification and order confirmation e-mails arrive; the link works from the phone **only if** `site_url` is reachable from the phone (on `localhost` open it on the laptop) | | Pending |
| M-32 | FR-55 | Run `setup_mail.php` again with a wrong 16-letter App Password → place an order → open the dashboard and the E-mail log → then set the correct password again | Order placed anyway; dashboard warns; log shows "Failed — … For Gmail use an App Password" | | Pending |
| M-33 | Docs | Open `docs\diagrams.html` | ER diagram shows `USER_TOKENS` and `EMAIL_LOG` (could not be rendered in Claude's workspace, which has no internet) | | Pending |
| M-34 | All | `scripts\system_check.php` | 50 of 50 checks pass (MAIL-06 fails if M-32 was done less than 7 days ago — expected) | | Pending |

**Design refresh (17 Oct 2026) — to be run on the laptop after `sql/updates/2026-10-17_banners.sql`.**

| ID | Requirement | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| M-35 | FR-58 | Admin → **Banners** → add 2 banners with real 1920 × 600 pictures, one with a button linking to a category | Homepage slides between them every 6 s; arrows and dots work; button opens the category | | Pending |
| M-36 | FR-58 | Hide one banner; give the other a "Show from" date tomorrow | Homepage shows the welcome section; status *Scheduled* | | Pending |
| M-37 | FR-59 | Admin → Categories → Edit → upload a square picture | Round picture on the homepage | | Pending |
| M-38 | FR-60 | Chrome, normal width and F12 phone view: homepage, All categories menu, product page, Add to cart | Purple header, yellow buttons, everything readable, no sideways scrolling | | Pending |
| M-39 | Backup | Double-click `scripts\backup.bat` | Also creates `pictures-….zip` (changed script — not runnable in Claude's workspace) | | Pending |
| M-40 | FR-62 | Edit a product: normal 2000, sale 1500 → open the shop; add it to the cart | Crossed-out Rs. 2,000.00, red Rs. 1,500.00 and -25%; cart charges 1,500; listed under **On sale** | | Pending |
| M-41 | FR-63 | Logged out: press a heart → log in → open **Wishlist** | Product is in the wishlist; heart is filled red; pressing again removes it | | Pending |
| M-42 | FR-67 | Run `sql/updates/2026-10-18_customization.sql`. Admin → Products → Edit → tick **Customers can personalise this product**, question "Name to print", 30 characters → Save. In the shop press **Personalise**, type a name with two lines → Add to cart → change it in the cart → place the order | Card shows Personalisable; message counter works; cart, confirmation, My order, admin order page and order e-mail show the message exactly as typed | | Pending |
| M-43 | FR-68 | Run `sql/updates/2026-10-18_reviews.sql`. Place an order as a customer → admin moves it to **Delivered** → customer opens **My account → My reviews** → **Write a review** (choose stars, type a comment) → open the product page. Then Admin → **Reviews** → **Hide** it | Review shows under the product with the average; card shows the stars; after Hide it disappears from the shop and the customer sees "Hidden by the store" | | Pending |
| M-44 | FR-69 | Run `sql/updates/2026-10-20_staff_inquiries.sql`. Admin → **Staff accounts** → add a staff member (name, username, tick Products, Categories, Inquiries) → copy the password. In another browser (or private window) log in with the username and that password → choose a new password | Staff sees only Dashboard, Inquiries, Products, Inventory, Categories; opening `admin/orders.php` shows Access denied; the Staff accounts list shows "Active" and the last login | | Pending |
| M-45 | FR-70 | Log out → Contact → send a message → as staff: Admin → **Inquiries** → open it → write a reply → **Resolved** → Send reply | Message appears with reference INQ-…; the reply arrives by e-mail (Gmail mode) or in `storage/mail` (test mode); a customer who was logged in sees it under My account → My inquiries | | Pending |
| M-46 | FR-71 | Run `sql/updates/2026-10-24_shop_sales.sql`. Note the stock of a product. Admin → **In-shop sales → New in-shop sale** → choose it, quantity 2, Cash, cash received 10000 → Save → **Print invoice**. Then **Cancel invoice** with a reason | Invoice with GiftAura, INV-…, date, items, total and change; stock went down by 2, Inventory shows "Sold in the shop" with the invoice; after cancelling the stock is back and the invoice shows Cancelled | | Pending |
| M-47 | FR-72 | Log out. Open a product → **Add to cart** → log in (or Create account) | Login page says "Almost there!" with the product name; after login you are back on the same product page with "… has been added to your cart" and the Cart count went up | | Pending |
| M-48 | FR-73 | Log in as a customer → **My account** → Edit → change the name → Save | Profile card with the round letter, name, e-mail, mobile and location; the new name appears | | Pending |

### 6.1 Summary of manual tests
**25 of 25 manual acceptance tests passed** on the development laptop (16–17 Oct 2026). No open defects.
E-mail extension, design refresh, personalisation, reviews, staff, inquiries, in-shop sales, cart login and profile: M-26 – M-48 are **pending** until run on the laptop.

## 7. Requirements traceability (summary)

| Requirements | Covered by |
|---|---|
| FR-01 – FR-08, FR-25 (catalogue, pages) | T4-01 – T4-20, M-05, M-06, M-20, PERF-01 – 06 |
| FR-09 – FR-11 (accounts) | T3-09 – T3-12, M-01 – M-04 |
| FR-12 – FR-24 (cart, checkout, orders, tracking) | T5-01 – T5-16, M-07 – M-12 |
| FR-30 – FR-48 (administration) | admin_e2e (106), M-13 – M-21 |
| FR-49 (first admin via CLI) | Laptop setup, Phase 3 |
| FR-50 – FR-57 (e-mail) | TE-01 – TE-09, T5-17, MAIL-01 – MAIL-06, M-26 – M-34 |
| FR-58 – FR-61 (banners, category pictures, design, photos) | TD-01 – TD-08, M-35 – M-39 |
| FR-62 – FR-63 (sale prices, wishlist) | TS-01 – TS-04, M-40 – M-41 |
| FR-67 (personalised products) | TC-01 – TC-06, M-42 |
| FR-68 (product reviews) | TR-01 – TR-06, M-43 |
| FR-69 (staff administrators) | TA-01 – TA-04, M-44 |
| FR-70 (customer inquiries) | TI-01 – TI-02, M-45 |
| FR-71 (in-shop sales and invoices) | TS-01 – TS-04, M-46 |
| FR-72 (login before adding to cart) | TP-01, M-47 |
| FR-73 (profile page) | TP-02, M-48 |
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
| D-05 | E-mail extension, admin_e2e | Admin customer page crashed ("Undefined array key email_verified_at") | The new column was added to the customer-list query but not to the single-customer query | Column added to both queries | 107/107 passed |
| D-06 | E-mail extension, fake SMTP inspection | Plain-text version of e-mails repeated the subject and ran table cells together ("ItemQtyTotal") | `<head>` not removed; no space between cells | Head removed, spaces between cells | 69/69 passed |
| D-07 | E-mail extension, screenshot of a test-mode e-mail | "From" address empty in test mode | Fallback ignored an empty `from_email` setting | Uses the first valid address (from_email, username, store e-mail, else no-reply@localhost) | 69/69 passed |
| D-08 | Design refresh, banner_e2e | Saving a banner with an empty "Display order" was refused | Empty value was treated as "not a number" | Empty now means 0 | 46/46 passed |

## 9. How to re-run the automated tests (optional)

1. **System check (recommended before the demonstration):** in PowerShell, from the project folder:
   `C:\xampp\php\php.exe scripts\system_check.php`
2. **End-to-end suites** need Git Bash. See `tests/README.md`. They use a temporary SQLite database and never touch `giftaura_db`.
3. **Gmail test:** `C:\xampp\php\php.exe scripts\test_mail.php` sends one real e-mail with the current settings and shows the conversation with Gmail (password hidden).
