# GiftAura — Demonstration Script, Screenshot List and Viva Preparation

Version 1.0 — Phase 9. A 15-minute demonstration that shows every major requirement working on real data.

## 1. The day before

- [ ] Take a backup (`scripts\backup.bat`) and copy it to a USB drive.
- [ ] Run `C:\xampp\php\php.exe scripts\system_check.php` → **all checks pass**. Keep the saved result file open in a tab.
- [ ] Real or clearly-labelled products with photos; at least one product at its low-stock level.
- [ ] Store details filled in under Admin → Settings (or deliberately left empty).
- [ ] Two browser windows ready: **Chrome** (customer) and **Chrome Incognito** or **Edge** (administrator), so you never have to log out during the demo.
- [ ] One existing **Pending** order and one **bank-transfer** order with a reference, for the admin part.
- [ ] `config/config.php` has `'debug' => false`.
- [ ] Phone charged / laptop on power; notifications off; zoom 100 %.

## 2. Demonstration (about 15 minutes)

| Min | Show | Say (key points) | Requirement |
|---|---|---|---|
| 0–1 | Homepage | "GiftAura's online shop, built with HTML, CSS, JavaScript, PHP 8.2 and MariaDB on XAMPP. Everything on the page comes from the database." | FR-01 |
| 1–3 | Category → subcategory; search "rose"; price filter; In stock only; sort | "Search and filters run on the server with prepared statements." | FR-02 – FR-06 |
| 3–4 | Product page: photo, price in LKR, "Only N left", related items | "Stock labels are live; out-of-stock items cannot be added." | FR-07, FR-08 |
| 4–6 | Add 2 items; change quantity; cart totals; try a quantity above stock | "Prices are always re-read from the database; the browser cannot change them. Quantity is limited to stock." | FR-13 – FR-16 |
| 6–8 | Checkout: new address, Cash on delivery, Place order → reference | "Placing an order is one database transaction: products are locked, stock reduced, item prices saved as a snapshot. If anything fails, nothing is saved." | FR-17 – FR-19, NFR-12 – 14 |
| 8–9 | My orders → order → progress bar | Customer sees status, payment and timeline | FR-21, FR-22 |
| 9–11 | **Admin window**: Dashboard (sales, pending, low stock) → open the new order → Confirmed → Processing; record a payment | "Only allowed status steps are possible; every step is time-stamped. Unpaid bank transfers cannot be confirmed by mistake." | FR-30, FR-39 – FR-43 |
| 11 | Customer window: refresh the order | Status updated instantly | FR-22 |
| 11–12 | Admin: product edit → upload photo; Restock +5 → stock history | "Uploads are checked and re-encoded, so a disguised script cannot be uploaded. Every stock change is recorded." | FR-36, FR-37, NFR-08 |
| 12–13 | Reports → Sales this month → Export CSV → open in Excel | "Sales exclude cancelled and refunded orders, and each order is counted once." | FR-45, FR-46 |
| 13–14 | Security: customer window → `localhost/giftaura/admin/` → Access denied; `localhost/giftaura/config/config.php` → Not found; search for `' OR 1=1 --` → 0 results | "Authorisation is checked on the server; private folders are blocked; SQL injection does not work." | NFR-01 – NFR-11 |
| 14–15 | Test evidence: `docs/test-plan.md` summary and the system-check file | "48 unit checks, 160 automated end-to-end checks, 43 checks on this server and 25 manual tests — all passed." | Testing |

**If something goes wrong:** stay calm, refresh the page, and continue with the next row. If the database stops, show the friendly error page — it demonstrates that no technical details leak — then restart MySQL.

## 3. Screenshots for the report

Take these at 100 % zoom (Windows: **Win + Shift + S**). Phone views: Chrome **F12 → Ctrl + Shift + M → iPhone 12 Pro**.

| # | Screenshot | Report section |
|---|---|---|
| 1 | Homepage (desktop) | Implementation – customer interface |
| 2 | Homepage (phone) | Responsive design |
| 3 | Shop page with a category, search and filters applied | Catalogue |
| 4 | Product detail page with photo | Catalogue |
| 5 | Cart with totals | Cart |
| 6 | Checkout form | Checkout |
| 7 | Order confirmation with reference | Orders |
| 8 | My orders + order detail with progress bar | Order tracking |
| 9 | Register form showing validation errors | Validation |
| 10 | Login "Too many unsuccessful login attempts" | Security – rate limiting |
| 11 | Admin dashboard | Administration |
| 12 | Admin orders list with status tabs | Order management |
| 13 | Admin order detail with status history and payment box | Order workflow |
| 14 | Product edit page with images and stock box | Product & inventory management |
| 15 | Inventory stock history | Inventory |
| 16 | Categories page | Category management |
| 17 | Reports: sales summary with chart | Reporting |
| 18 | CSV opened in Excel | Reporting |
| 19 | Settings page | Configuration |
| 20 | Activity log | Audit trail |
| 21 | Customer opening admin → Access denied | Security – authorisation |
| 22 | `config/config.php` in the browser → Not found | Security – private files |
| 23 | System check output "43 of 43 checks passed" | Testing |
| 24 | phpMyAdmin showing the 15 tables | Database |
| 25 | `docs/diagrams.html`: ER diagram and use-case diagram | Design |

## 4. Likely viva questions and short answers

| Question | Answer |
|---|---|
| Why PHP and MySQL without a framework? | Required stack in the brief; XAMPP is free and matches the store's needs. A small, clear structure (core classes + services + templates) keeps every request easy to follow and mark. |
| How do you prevent SQL injection? | Every query uses PDO prepared statements with real server-side prepares; sort and filter options are whitelisted. Tested with `' OR 1=1 --` (T4-07, WEB-22). |
| How do you prevent XSS? | Every output passes through `htmlspecialchars` (`e()`), plus a Content-Security-Policy that allows only the site's own scripts. Tested with `<script>` in search and in category names. |
| What is CSRF and how is it handled? | A forged request from another website. Every form has a secret per-session token checked with `hash_equals`; missing or wrong tokens return "Page expired" (T3-05). |
| How are passwords stored? | `password_hash()` (bcrypt) and `password_verify()`; never in plain text (DB-12). Login is rate-limited (5 per e-mail / 20 per IP per 15 minutes). |
| How do you stop two customers buying the last item? | The order transaction locks the product rows with `SELECT … FOR UPDATE`; stock is reduced with a conditional update and a CHECK constraint forbids negative stock. Tested with a simultaneous-order race (T5-15) and a MariaDB lock test (DB-08). |
| What if a product's price changes after an order? | `order_items` stores a snapshot of name, SKU, price and quantity, so past orders never change. |
| Why not trust the total from the browser? | It can be edited. The server recalculates from database prices and compares a fingerprint of what the customer saw (T5-07). |
| How are images made safe? | Size, extension, real MIME type and image checks, then re-encoding with GD to a random file name; PHP cannot run in the uploads folder (WEB-20). A JPEG with hidden PHP code was tested and cleaned. |
| Is the database normalised? | Yes, to 3NF, with justified historical snapshots in orders/order_items (explained in `03-database-design.md`). |
| How are sales calculated? | Gross sales = orders not cancelled and not refunded, summed from the `orders` table so each order counts once; collected revenue = payments marked paid. |
| Why no online card payments? | No payment-provider account; the brief forbids claiming a payment integration that is not real. Cash on delivery and verified bank transfer are implemented; the `payments` table is ready for a gateway with server-side verification. |
| Why no e-mail password reset? | XAMPP has no mail server; a half-working reset would be insecure. Administrators issue one-time temporary passwords instead. |
| How did you test? | Unit checks, automated end-to-end suites (160 checks), a system check on the real server (43 checks) and 25 manual acceptance tests, all recorded in `docs/test-plan.md` with a defect log. |
| What would you improve next? | HTTPS hosting, a real payment gateway, e-mail/SMS notifications, password reset by e-mail, two-factor login for admins, product reviews and discounts. |
