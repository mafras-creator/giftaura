# GiftAura E-Commerce Web System — Requirements Checklist

| | |
|---|---|
| Project | E-Commerce Web System for GiftAura (MIT final-year Software Development Project, South Eastern University of Sri Lanka) |
| Document | Functional and non-functional requirements, business rules, scope |
| Version | 1.0 — Phase 2 (design) |
| Stack | HTML5, CSS3, vanilla JavaScript, PHP 8.2, MariaDB 10.4, Apache (XAMPP) |

**Status key:** `Planned` → `Built` → `Tested` (with the test case ID that proved it). This table is updated at the end of every phase. Nothing is marked `Tested` unless a test was actually executed.

---

## 1. Actors

| Actor | Description |
|---|---|
| Guest | Visitor who is not logged in. Can browse, search and use a session cart. |
| Customer | Registered user (`role = customer`). Can check out, manage profile and addresses, view and track own orders. |
| Administrator | Staff user (`role = admin`). Manages catalogue, stock, orders, payments, settings and reports. |
| System | Server-side PHP logic: recalculates totals, updates stock, records history. |

---

## 2. Functional requirements — customer side

| ID | Requirement | Phase | Status |
|---|---|---|---|
| FR-01 | Display the homepage with a hero section, category links, featured products and latest products loaded from the database. | 4 | Planned |
| FR-02 | List active products by category, including products in its subcategories. | 4 | Planned |
| FR-03 | Search products by keyword (name, short description, description). | 4 | Planned |
| FR-04 | Filter products by category, price range and availability (in stock only). | 4 | Planned |
| FR-05 | Sort products by newest, price low→high, price high→low, and name. | 4 | Planned |
| FR-06 | Paginate product listings. | 4 | Planned |
| FR-07 | Show a product detail page (images, price in LKR, availability, description, category) using the product slug. Inactive products return "not found". | 4 | Planned |
| FR-08 | Show a placeholder image when a product has no uploaded image. | 4 | Planned |
| FR-09 | Register a customer account (name, email, phone, password with confirmation). | 3 | Planned |
| FR-10 | Log in and log out. | 3 | Planned |
| FR-11 | View and edit profile (name, phone); change password (requires current password). | 3 | Planned |
| FR-12 | Manage saved delivery addresses, with one default address. | 5 | Planned |
| FR-13 | Add a product to the cart from the listing and detail pages (guest or customer). | 5 | Planned |
| FR-14 | Change cart quantities and remove items; cart shows live subtotal from current database prices. | 5 | Planned |
| FR-15 | Keep a logged-in customer's cart in the database; merge the guest session cart on login. | 5 | Planned |
| FR-16 | Warn in the cart when an item's stock has fallen below the cart quantity or the product was deactivated. | 5 | Planned |
| FR-17 | Checkout requires login; collects contact details, delivery address (new or saved), delivery notes and payment method. | 5 | Planned |
| FR-18 | Show subtotal, delivery charge, discount (0 in this version) and total before the order is placed. | 5 | Planned |
| FR-19 | Place an order and receive a unique order reference (e.g. `GA261016-7K3M9Q`) on a confirmation page. | 5 | Planned |
| FR-20 | For bank transfer, show the store's bank details and let the customer submit a transfer reference. | 5 | Planned |
| FR-21 | View own order history (reference, date, status, payment status, total). | 5 | Planned |
| FR-22 | View own order details: items, quantities, prices, delivery charge, total, payment method and status, and a status timeline. | 5 | Planned |
| FR-23 | Cancel own order while it is still `pending`. | 5 | Planned |
| FR-24 | Track an order on the Track Order page (own orders only when logged in; guests can look up by order reference + email). | 5 | Planned |
| FR-25 | Contact / store information page and About page, using details from Settings (empty values are hidden, never invented). | 4 | Planned |

## 3. Functional requirements — administrator side

| ID | Requirement | Phase | Status |
|---|---|---|---|
| FR-30 | Admin dashboard with real counts: today's and this month's sales, orders by status, number of customers, active products, low-stock and out-of-stock products, latest orders. | 6 | Planned |
| FR-31 | Create, edit, activate/deactivate and reorder categories and subcategories; slug generated automatically and editable. | 6 | Planned |
| FR-32 | A category that still contains products or subcategories cannot be deleted (it can be deactivated). | 6 | Planned |
| FR-33 | List products with search, category/status/stock filters and pagination. | 6 | Planned |
| FR-34 | Add and edit products (name, slug, SKU, category, descriptions, price, low-stock level, featured, active). | 6 | Planned |
| FR-35 | Deactivate and reactivate products (no hard delete once a product has orders). | 6 | Planned |
| FR-36 | Upload product images securely, choose the primary image, reorder and remove images. | 6 | Planned |
| FR-37 | Adjust stock (restock or manual correction with a reason); every change is recorded in the inventory ledger. | 6 | Planned |
| FR-38 | Low-stock alerts on the dashboard and a low-stock list (stock ≤ product's threshold). | 6 | Planned |
| FR-39 | List orders with filters (status, payment status, date range, search by reference/customer) and pagination. | 6 | Planned |
| FR-40 | View an order: customer contact, delivery address, items, totals, payment, status history. | 6 | Planned |
| FR-41 | Change order status only along permitted transitions (section 5.1); each change is time-stamped in the status history. | 6 | Planned |
| FR-42 | Cancel an order (with a reason) — stock is restored automatically and recorded. | 6 | Planned |
| FR-43 | Record payment status and method: mark paid, failed or refunded; verify bank transfer references. | 6 | Planned |
| FR-44 | View customers (name, email, phone, order count, total spent) and a customer's orders; activate/deactivate customer accounts; set a temporary password for a customer who has forgotten theirs. | 6 | Planned |
| FR-45 | Reports for a selected date range: orders, sales, order-status totals, best-selling products, current inventory, low-stock products. | 6 | Planned |
| FR-46 | Export each report to CSV. | 6 | Planned |
| FR-47 | View inventory movement history (per product and overall) and the admin activity log. | 6 | Planned |
| FR-48 | Edit store settings: contact details, delivery fee, free-delivery threshold, payment methods on/off, bank details. | 6 | Planned |
| FR-49 | Create the first administrator from a command-line script only (no default admin account, no public admin registration). | 3 | Planned |

## 4. Non-functional requirements

| ID | Category | Requirement | Phase | Status |
|---|---|---|---|---|
| NFR-01 | Security | All database access through PDO prepared statements; emulated prepares disabled. | 3 | Planned |
| NFR-02 | Security | All output HTML-escaped (`htmlspecialchars`) to prevent XSS. | 3 | Planned |
| NFR-03 | Security | CSRF token required on every state-changing (POST) request. | 3 | Planned |
| NFR-04 | Security | Passwords stored with `password_hash()` and checked with `password_verify()`; minimum 8 characters. | 3 | Planned |
| NFR-05 | Security | Session cookie `HttpOnly`, `SameSite=Lax`, strict mode; session ID regenerated after login; full session destroy on logout; idle timeout. | 3 | Planned |
| NFR-06 | Security | Role checks on the server for every admin page and action; customers can only see their own orders and addresses. | 3 | Planned |
| NFR-07 | Security | Login rate limiting: after 5 failed attempts for an email or 20 from an IP in 15 minutes, further attempts are blocked temporarily. | 3 | Planned |
| NFR-08 | Security | Image uploads: max 2 MB, JPEG/PNG/WebP only, checked by extension, `finfo` MIME type and `getimagesize`, re-encoded with GD, saved under a random name; PHP execution disabled in the uploads folder. | 6 | Planned |
| NFR-09 | Security | Credentials in `config/config.php`, outside `public/`, excluded from Git; `app/`, `config/`, `sql/`, `storage/`, `docs/`, `scripts/` denied to browsers. | 3 | Planned |
| NFR-10 | Security | Customer-facing errors are generic; details go to `storage/logs/` (never passwords or payment data). | 3 | Planned |
| NFR-11 | Security | Application connects with a least-privilege MySQL user (`giftaura_app`: SELECT, INSERT, UPDATE, DELETE on `giftaura_db` only). | 2 | Planned |
| NFR-12 | Integrity | Order placement, cancellation and stock adjustment run in database transactions with row locks; stock can never go below zero. | 5 | Planned |
| NFR-13 | Integrity | Prices and totals are always recalculated on the server from database prices; browser-submitted prices are ignored. | 5 | Planned |
| NFR-14 | Integrity | Order items store name, SKU, unit price and quantity as a snapshot. | 5 | Planned |
| NFR-15 | Usability | Responsive layout for mobile (≥ 360 px), tablet and desktop. | 4–7 | Planned |
| NFR-16 | Usability | Every page has clear empty, validation-error, success and error states; forms keep entered values after a validation error. | 4–7 | Planned |
| NFR-17 | Usability | Purple-and-white GiftAura visual identity; consistent typography; accessible colour contrast and form labels. | 4–7 | Planned |
| NFR-18 | Performance | Indexed queries; product listings paginated (12 per page); pages render in under 1 s on the local XAMPP server with sample data. | 4 | Planned |
| NFR-19 | Maintainability | Modular PHP: shared core (database, session, auth, CSRF, validation, view) plus one service per domain (products, cart, orders, payments, inventory, reports). | 3 | Planned |
| NFR-20 | Portability | Runs on XAMPP (PHP 8.2, MariaDB 10.4) with no Composer packages or Node tools. MariaDB port is set in config (3307 on the development laptop). | 3 | Planned |
| NFR-21 | Localisation | Currency LKR shown as `Rs. 4,500.00`; times in Asia/Colombo; utf8mb4 so Sinhala and Tamil text is stored correctly. | 3 | Planned |

---

## 5. Business rules

### 5.1 Order status workflow

| From | Allowed next status | Who |
|---|---|---|
| `pending` | `confirmed`, `cancelled` | Admin; the customer may cancel |
| `confirmed` | `processing`, `cancelled` | Admin |
| `processing` | `ready_for_delivery`, `cancelled` | Admin |
| `ready_for_delivery` | `delivered` | Admin |
| `delivered` | — (final) | — |
| `cancelled` | — (final) | — |

- Every change writes a row to `order_status_history` (from, to, who, when, note).
- Cancelling restores each item's quantity to stock and writes an `order_cancelled` inventory movement.
- A bank-transfer order is confirmed by the admin only after the payment is verified as `paid` (the system warns otherwise).

### 5.2 Payment rules

| Method | Initial status | Typical flow |
|---|---|---|
| Cash on Delivery (`cod`) | `pending` | Admin marks `paid` when cash is collected at delivery. |
| Bank transfer (`bank_transfer`) | `pending` → `awaiting_verification` once the customer submits a reference | Admin checks the bank account, then marks `paid` or `failed`. |

- `refunded` can only follow `paid` and records `refunded_at`.
- **No online card payment gateway is integrated.** The site never claims a payment is complete unless an administrator has recorded it.

### 5.3 Stock rules

- Stock is decremented when the order is placed, inside the same transaction, after locking the product rows (`SELECT … FOR UPDATE`). If any item has insufficient stock, the whole order is rejected and nothing is changed.
- Stock is restored only once when an order is cancelled.
- Every stock change writes an `inventory_movements` row with the resulting stock level.
- A product is "low stock" when `0 < stock_quantity ≤ low_stock_threshold`, and "out of stock" when `stock_quantity = 0`. Out-of-stock products are visible but cannot be added to the cart.

### 5.4 Delivery charge

- Delivery fee = `delivery_fee` setting, unless `free_delivery_threshold > 0` and the subtotal is at or above it, in which case the fee is 0.
- The fee is stored on the order, so later changes to the setting do not affect past orders.

### 5.5 Report definitions (applied consistently everywhere)

| Figure | Definition |
|---|---|
| Orders in range | All orders whose `created_at` falls in the range, any status. |
| **Gross sales** | Sum of `orders.total_amount` for orders in range whose status is **not** `cancelled` and whose payment is **not** `refunded`. Each order counted once (summed from `orders`, never from a join with `order_items`). |
| **Collected revenue** | Sum of `payments.amount` where `status = 'paid'`, by `paid_at` in range. Refunded payments are excluded. |
| Pending value | Orders in range with status `pending` (shown separately; included in gross sales). |
| Order status totals | Count and value of orders in range grouped by current status, including cancelled. |
| Best-selling products | Sum of `order_items.quantity` and `line_total` per product for non-cancelled orders in range, top 10 by quantity. |
| Current inventory | All products with stock, threshold, stock value (`price × stock`) and status. |
| Low-stock products | Active products where `stock_quantity ≤ low_stock_threshold`, lowest first. |

Date ranges are inclusive of both start and end dates, in Asia/Colombo time.

---

## 6. Scope decisions

| Item | Decision | Reason |
|---|---|---|
| Online card payments | **Not included** | No payment provider account; the brief forbids claiming a payment integration that is not real. The `payments` table is ready for a future gateway. |
| Password reset by email | **Not included** in this version | Requires a working outgoing mail server, which a local XAMPP install does not have. A half-working reset would be insecure. An administrator can set a temporary password for a customer instead (FR-44). |
| Discounts / coupons | Column present (`discount_amount`, always 0) | Not in the approved proposal; structure allows adding it later. |
| Guest checkout | Not included | Order history and tracking need an account; guests can browse and fill a cart. |
| Product reviews, wishlists | Not included | Not in the approved proposal. |
| Email / SMS notifications | Not included | No mail/SMS service locally; order status is visible in the customer account. |
