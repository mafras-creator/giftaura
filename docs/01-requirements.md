# GiftAura E-Commerce Web System — Requirements Checklist

| | |
|---|---|
| Project | E-Commerce Web System for GiftAura (MIT final-year Software Development Project, South Eastern University of Sri Lanka) |
| Document | Functional and non-functional requirements, business rules, scope |
| Version | 1.8 — e-mail extension and design refresh added 17 Oct 2026 (Phase column "E" = e-mail extension, "D" = design refresh, "S" = sale prices and wishlist) |
| Stack | HTML5, CSS3, vanilla JavaScript, PHP 8.2, MariaDB 10.4, Apache (XAMPP) |

**Status key:** `Planned` → `Built` → `Tested` (the test cases that prove each requirement are listed in `docs/test-plan.md` §7). This table is updated at the end of every phase. Nothing is marked `Tested` unless a test was actually executed.

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
| FR-01 | Display the homepage with a hero section, category links, featured products and latest products loaded from the database. | 4 | Tested |
| FR-02 | List active products by category, including products in its subcategories. | 4 | Tested |
| FR-03 | Search products by keyword (name, short description, description). | 4 | Tested |
| FR-04 | Filter products by category, price range and availability (in stock only). | 4 | Tested |
| FR-05 | Sort products by newest, price low→high, price high→low, and name. | 4 | Tested |
| FR-06 | Paginate product listings. | 4 | Tested |
| FR-07 | Show a product detail page (images, price in LKR, availability, description, category) using the product slug. Inactive products return "not found". | 4 | Tested |
| FR-08 | Show a placeholder image when a product has no uploaded image. | 4 | Tested |
| FR-09 | Register a customer account (name, email, phone, password with confirmation). | 3 | Tested |
| FR-10 | Log in and log out. | 3 | Tested |
| FR-11 | View and edit profile (name, phone); change password (requires current password). | 3 | Tested |
| FR-12 | Manage saved delivery addresses, with one default address. | 5 | Tested |
| FR-13 | Add a product to the cart from the listing and detail pages (guest or customer). | 5 | Tested |
| FR-14 | Change cart quantities and remove items; cart shows live subtotal from current database prices. | 5 | Tested |
| FR-15 | Keep a logged-in customer's cart in the database; merge the guest session cart on login. | 5 | Tested |
| FR-16 | Warn in the cart when an item's stock has fallen below the cart quantity or the product was deactivated. | 5 | Tested |
| FR-17 | Checkout requires login; collects contact details, delivery address (new or saved), delivery notes and payment method. | 5 | Tested |
| FR-18 | Show subtotal, delivery charge, discount (0 in this version) and total before the order is placed. | 5 | Tested |
| FR-19 | Place an order and receive a unique order reference (e.g. `GA261016-7K3M9Q`) on a confirmation page. | 5 | Tested |
| FR-20 | For bank transfer, show the store's bank details and let the customer submit a transfer reference. | 5 | Tested |
| FR-21 | View own order history (reference, date, status, payment status, total). | 5 | Tested |
| FR-22 | View own order details: items, quantities, prices, delivery charge, total, payment method and status, and a status timeline. | 5 | Tested |
| FR-23 | Cancel own order while it is still `pending`. | 5 | Tested |
| FR-24 | Track an order on the Track Order page (own orders only when logged in; guests can look up by order reference + email). | 5 | Tested |
| FR-25 | Contact / store information page and About page, using details from Settings (empty values are hidden, never invented). | 4 | Tested |

## 3. Functional requirements — administrator side

| ID | Requirement | Phase | Status |
|---|---|---|---|
| FR-30 | Admin dashboard with real counts: today's and this month's sales, orders by status, number of customers, active products, low-stock and out-of-stock products, latest orders. | 6 | Tested |
| FR-31 | Create, edit, activate/deactivate and reorder categories and subcategories; slug generated automatically and editable. | 6 | Tested |
| FR-32 | A category that still contains products or subcategories cannot be deleted (it can be deactivated). | 6 | Tested |
| FR-33 | List products with search, category/status/stock filters and pagination. | 6 | Tested |
| FR-34 | Add and edit products (name, slug, SKU, category, descriptions, price, low-stock level, featured, active). | 6 | Tested |
| FR-35 | Deactivate and reactivate products (no hard delete once a product has orders). | 6 | Tested |
| FR-36 | Upload product images securely, choose the primary image, reorder and remove images. | 6 | Tested |
| FR-37 | Adjust stock (restock or manual correction with a reason); every change is recorded in the inventory ledger. | 6 | Tested |
| FR-38 | Low-stock alerts on the dashboard and a low-stock list (stock ≤ product's threshold). | 6 | Tested |
| FR-39 | List orders with filters (status, payment status, date range, search by reference/customer) and pagination. | 6 | Tested |
| FR-40 | View an order: customer contact, delivery address, items, totals, payment, status history. | 6 | Tested |
| FR-41 | Change order status only along permitted transitions (section 5.1); each change is time-stamped in the status history. | 6 | Tested |
| FR-42 | Cancel an order (with a reason) — stock is restored automatically and recorded. | 6 | Tested |
| FR-43 | Record payment status and method: mark paid, failed or refunded; verify bank transfer references. | 6 | Tested |
| FR-44 | View customers (name, email, phone, order count, total spent) and a customer's orders; activate/deactivate customer accounts; set a temporary password for a customer who has forgotten theirs. | 6 | Tested |
| FR-45 | Reports for a selected date range: orders, sales, order-status totals, best-selling products, current inventory, low-stock products. | 6 | Tested |
| FR-46 | Export each report to CSV. | 6 | Tested |
| FR-47 | View inventory movement history (per product and overall) and the admin activity log. | 6 | Tested |
| FR-48 | Edit store settings: contact details, delivery fee, free-delivery threshold, payment methods on/off, bank details. | 6 | Tested |
| FR-49 | Create the first administrator from a command-line script only (no default admin account, no public admin registration). | 3 | Tested |

### 3.1 E-mail (added 17 Oct 2026, after Phase 9)

| ID | Requirement | Phase | Status |
|---|---|---|---|
| FR-50 | After registering, the customer receives an e-mail with a one-time link (valid 24 h) to verify the e-mail address. The customer can ask for a new link (at most 3 per hour). | E | Tested |
| FR-51 | A customer can browse, log in and fill the cart before verifying, but cannot check out until the address is verified (enforced on the checkout page and again when the order is saved). | E | Tested |
| FR-52 | "Forgotten your password?": a customer receives a one-time reset link (valid 60 min) and chooses a new password. The page gives the same answer whether or not the address has an account. | E | Tested |
| FR-53 | The customer receives an e-mail when an order is placed (items, totals, delivery address, bank details for transfers) and when it is confirmed, being prepared, ready for delivery, delivered or cancelled. | E | Tested |
| FR-54 | The customer receives an e-mail when a payment is marked paid, failed or refunded. | E | Tested |
| FR-55 | Every e-mail attempt is recorded (Admin → E-mail log, with result sent / saved / skipped / failed). A failed e-mail never stops an order; the dashboard warns about failures. | E | Tested |
| FR-56 | The administrator sees whether a customer's address is verified, can send the link again and can mark an address as verified (recorded in the activity log). | E | Tested |
| FR-57 | E-mail mode is chosen on the server with `scripts/setup_mail.php`: **test mode** (saved as files, the default), **Gmail** (SMTP with an App Password) or **off**. | E | Tested in test mode and against a local test SMTP server; real Gmail sending is tested on the laptop (M-30) |

### 3.2 Design refresh and homepage banners (added 17 Oct 2026)

| ID | Requirement | Phase | Status |
|---|---|---|---|
| FR-58 | The administrator manages homepage banners (Admin → Banners): upload a wide picture, title, short text, button text and link, display order, optional start and end dates, show/hide, delete. Up to 6 active banners rotate on the homepage; with none, a welcome section is shown. | D | Tested |
| FR-59 | The administrator can give each category a picture; the homepage shows round category pictures (a product photo is used when no picture is set). | D | Tested |
| FR-61 | When adding a product, the administrator can upload up to 4 photos at once; each product has at most 4 photos (more can be chosen at once on the edit page while places are free). | D | Tested |
| FR-62 | Sale prices: the administrator enters a normal price and an optional lower sale price. Customers pay the sale price; the shop shows the normal price crossed out, a "-25%" badge and "You save", and an **On sale** page/filter lists all discounted products. Orders keep the price actually paid. | S | Tested |
| FR-63 | Wishlist: a heart button on every product picture (cards and product page). Customers save/remove products and see them in My account → My wishlist; a guest who presses the heart logs in first and the product is then saved. | S | Tested |
| FR-64 | The administrator uploads the shop logo in Settings (checked and re-encoded like product photos); it is used in the shop header, admin panel, error pages and browser tab, and can be reset to the default. | D | Tested |
| FR-65 | About page in the style of the reference: hero with logo, location and live numbers; our story with founder card (Asjadh Mohamed Mafras, photo or initials, quote); mission and vision; what we offer; location Bandarakoswatta, Kurunegala with Google map. All texts and the founder photo are edited in Admin → About page. | D | Tested |
| FR-66 | "Popular categories" mosaic of coloured tiles near the bottom of the homepage (short line, name, picture); the administrator chooses up to 6 categories and their lines in Admin → Categories. | D | Tested |
| FR-67 | Personalised products: the administrator marks a product as customisable and sets the question shown to the customer, the longest message (10–500 characters) and whether a message is required. The card shows "Personalisable" and a **Personalise** button; the product page has a message box with a live character counter; the message is kept with the cart line (editable in the cart, kept when a guest logs in), checked again at checkout, saved with the order item as a snapshot and shown on the confirmation, My order, admin order pages and in the order e-mail. One message per product per order. | C | Tested |
| FR-68 | Product reviews: a customer can rate (1–5 stars) and comment on a product only after an order containing it has been **delivered** (checked again on every save); one review per customer per product, editable and deletable in My account → My reviews (also linked from the delivered order and the "delivered" e-mail). Reviews show at once under the product with the average, star bars, first name + initial and "Verified purchase"; cards show the average and count. The administrator can filter, hide/show and delete reviews (Admin → Reviews, activity log). | R | Tested |
| FR-69 | Staff administrators: the main (super) administrator creates staff accounts in Admin → **Staff accounts** with a name, a username and the areas they may work in (products and stock, categories, customer inquiries, orders and payments, customers, reviews, banners and About page, reports). The system generates a 12-character password that is shown **once** and stored only as a hash; the staff member logs in with the username and must choose a new password first. The menu, dashboard and every page follow the ticked areas (other pages: 403). Staff accounts, Settings, the E-mail log and the Activity log are for the super administrator only. The super administrator can edit areas, generate a new password and deactivate/activate staff; all of it is in the activity log, and order history shows who changed a status. | C | Tested |
| FR-70 | Customer inquiries: the Contact page has a message form (name, e-mail, optional mobile and order reference, subject, message up to 2000 characters) with a hidden spam-trap field and a limit of 5 messages per hour per computer or e-mail. Each message gets a reference (INQ-0001). Staff with the inquiries area and the super administrator see new messages on the dashboard and menu badge, filter and search them, reply (the reply is e-mailed and saved) and set the status New / In progress / Resolved. Logged-in customers see their messages and replies in My account → **My inquiries**. | C | Tested |
| FR-71 | In-shop sales and invoices: the main administrator and staff with the "In-shop sales" area record a sale to a customer who visits the shop (Admin → **In-shop sales → New in-shop sale**): products and quantities (prices read from the database), optional customer name and mobile, discount, payment (cash/card/bank transfer) and cash received. Stock is reduced in the same transaction as website orders (never below zero) and every movement is linked to the invoice. A printable invoice shows the shop name (and address/phone/e-mail when set in Settings), reference **INV-YYMMDD-0001**, date and time, staff member, items, subtotal, discount, total and change. Invoices can be searched and filtered; a mistaken invoice can be cancelled with a reason (stock returned, invoice kept as Cancelled). Dashboard and Reports show in-shop sales. | C | Tested |
| FR-72 | Login before adding to cart: a guest who presses Add to cart is taken to the login page ("Almost there!") with a link to create an account; after logging in or registering the chosen product (quantity and personalisation message) is in their cart automatically and they return to the page they were on, with an "added to your cart" message and the cart count updated (they are not forced to the cart page). Logged-in customers who press Add to cart also stay on the same page with the message and the updated cart count; the cart opens only when they click **Cart**. | C | Tested |
| FR-73 | Profile page redesign: My account shows a profile card (round avatar with the first letter, large name, verified label, e-mail, mobile, location from the saved address, **Edit** link that opens the form) and a side menu with icons; counts of orders, wishlist items and reviews. | C | Tested |
| FR-60 | Shop design: purple header with a large search bar, "All categories" menu, yellow **Add to cart** buttons on product cards and product pages, "Similar products" on product pages, layout usable on phones. | D | Tested (automated + screenshots); on the laptop M-35 – M-38 |

## 4. Non-functional requirements

| ID | Category | Requirement | Phase | Status |
|---|---|---|---|---|
| NFR-01 | Security | All database access through PDO prepared statements; emulated prepares disabled. | 3 | Tested |
| NFR-02 | Security | All output HTML-escaped (`htmlspecialchars`) to prevent XSS. | 3 | Tested |
| NFR-03 | Security | CSRF token required on every state-changing (POST) request. | 3 | Tested |
| NFR-04 | Security | Passwords stored with `password_hash()` and checked with `password_verify()`; minimum 8 characters. | 3 | Tested |
| NFR-05 | Security | Session cookie `HttpOnly`, `SameSite=Lax`, strict mode; session ID regenerated after login; full session destroy on logout; idle timeout. | 3 | Tested |
| NFR-06 | Security | Role checks on the server for every admin page and action; customers can only see their own orders and addresses. | 3 | Tested |
| NFR-07 | Security | Login rate limiting: after 5 failed attempts for an email or 20 from an IP in 15 minutes, further attempts are blocked temporarily. | 3 | Tested |
| NFR-08 | Security | Image uploads: max 2 MB, JPEG/PNG/WebP only, checked by extension, `finfo` MIME type and `getimagesize`, re-encoded with GD, saved under a random name; PHP execution disabled in the uploads folder. | 6 | Tested |
| NFR-09 | Security | Credentials in `config/config.php`, outside `public/`, excluded from Git; `app/`, `config/`, `sql/`, `storage/`, `docs/`, `scripts/` denied to browsers. | 3 | Tested |
| NFR-10 | Security | Customer-facing errors are generic; details go to `storage/logs/` (never passwords or payment data). | 3 | Tested |
| NFR-11 | Security | Application connects with a least-privilege MySQL user (`giftaura_app`: SELECT, INSERT, UPDATE, DELETE on `giftaura_db` only). | 2 | Tested |
| NFR-12 | Integrity | Order placement, cancellation and stock adjustment run in database transactions with row locks; stock can never go below zero. | 5 | Tested |
| NFR-13 | Integrity | Prices and totals are always recalculated on the server from database prices; browser-submitted prices are ignored. | 5 | Tested |
| NFR-14 | Integrity | Order items store name, SKU, unit price and quantity as a snapshot. | 5 | Tested |
| NFR-15 | Usability | Responsive layout for mobile (≥ 360 px), tablet and desktop. | 4–7 | Tested |
| NFR-16 | Usability | Every page has clear empty, validation-error, success and error states; forms keep entered values after a validation error. | 4–7 | Tested |
| NFR-17 | Usability | Purple-and-white GiftAura visual identity; consistent typography; accessible colour contrast and form labels. | 4–7 | Tested |
| NFR-18 | Performance | Indexed queries; product listings paginated (12 per page); pages render in under 1 s on the local XAMPP server with sample data. | 4 | Tested (PERF-01–06) |
| NFR-19 | Maintainability | Modular PHP: shared core (database, session, auth, CSRF, validation, view) plus one service per domain (products, cart, orders, payments, inventory, reports). | 3 | Tested |
| NFR-20 | Portability | Runs on XAMPP (PHP 8.2, MariaDB 10.4) with no Composer packages or Node tools. MariaDB port is set in config (3307 on the development laptop). | 3 | Tested |
| NFR-21 | Localisation | Currency LKR shown as `Rs. 4,500.00`; times in Asia/Colombo; utf8mb4 so Sinhala and Tamil text is stored correctly. | 3 | Tested |

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
| Password reset by email | **Added** on 17 Oct 2026 (FR-52) | Uses Gmail through an App Password. The administrator's temporary password (FR-44) remains as a fallback. Administrators cannot reset their password by e-mail (by design). |
| Discounts / coupons | Column present (`discount_amount`, always 0) | Not in the approved proposal; structure allows adding it later. |
| Guest checkout | Not included | Order history and tracking need an account; guests can browse and fill a cart. |
| Product reviews, wishlists | Not included | Not in the approved proposal. |
| Email notifications | **Added** on 17 Oct 2026 (FR-50–FR-57) | Sent through Gmail; in test mode they are saved as files so the whole flow can be checked without internet. |
| SMS notifications | Not included | Needs a paid SMS gateway account. |
