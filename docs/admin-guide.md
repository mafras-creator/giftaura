# GiftAura — Administrator Guide

For GiftAura staff who run the online store. No technical knowledge is needed for daily work.

**Admin address:** `http://localhost/giftaura/admin/` (log in with your administrator e-mail and password).
The left menu has: **Dashboard, Orders, Products, Categories, Inventory, Customers, Reports, Activity log, Settings** and **View shop**.

---

## 1. First-time set-up (once)

1. **Settings** → fill in the store details you want customers to see:
   - Store name and tagline, contact e-mail, phone, WhatsApp, address, opening hours.
   - **Delivery fee** and, if you offer it, **Free delivery from** (0 = never free).
   - **Payment methods**: Cash on delivery and/or Bank transfer (at least one must stay on).
   - **Bank account** details (shown only to customers who choose bank transfer).
2. Click **Save settings**. Any field you leave empty is simply hidden on the website — nothing is invented.
3. Replace the sample products (names start with "Sample") with real products, or deactivate them (section 3).

> Change your own password from the top bar: **Change password**.

## 2. Daily routine (5 minutes)

1. Open the **Dashboard**:
   - **Pending orders** — new orders waiting for you (also shown as an orange number next to *Orders*).
   - **Payments to verify** — bank transfers where the customer sent a reference.
   - **Low / out of stock** — products to restock.
   - **Sales today / this month** — from real orders (cancelled and refunded orders are not counted).
2. Process new orders (section 5) and restock (section 4).

## 3. Products and categories

### Add a product
1. **Products** → **+ Add product**.
2. Enter name, category, price (e.g. `4500` or `4500.00`), optional SKU (product code), short and full description.
3. **Low-stock alert level**: you get an alert when stock reaches this number.
4. **Opening stock**: how many you have now.
5. Tick **Active** (visible in the shop) and, if wanted, **Featured on the homepage**.
6. **Create product**. The edit page opens.

### Add photos
1. On the product's edit page, under **Images**, **Choose file** → JPG, PNG or WebP, **max 2 MB**, at least 100 × 100 px.
2. Optional: a short description of the photo (helps blind users).
3. **Upload**. The first photo becomes the **Main image**; use **Make main** to change it. Up to 8 photos per product.
4. Photos are automatically resized and cleaned for safety. If a file is refused, the message says why (type, size or not a real image).

### Edit, hide or bring back a product
- **Products** → **Edit** → change and **Save changes**. Changing the price does **not** change past orders.
- **Deactivate** hides the product from the shop (it is never deleted, because past orders refer to it). **Activate** shows it again.
- Use the search box and filters (category, active/inactive, low/out of stock) to find products.

### Categories
- **Categories** → fill in the form on the right → **Add category**. Choose a **Parent category** to make a subcategory (one level, e.g. *Flower Bouquets › Rose Bouquets*).
- **Display order**: smaller numbers appear first.
- **Deactivate** hides the category and all its products from the shop.
- **Delete** appears only for empty categories. A category with products or subcategories must be emptied first, or deactivated.

## 4. Stock (inventory)

Every stock change is recorded with who, when, why and the new stock level.

| You want to… | Do this |
|---|---|
| Add new stock that arrived | Product edit page → **Stock** box → **Restock (add items)** → quantity → note (e.g. supplier) → **Update stock** |
| Fix a mistake / damaged items | **Correct stock (+ or −)** → e.g. `-2` → note is **required** (e.g. "2 damaged") |
| Update several products quickly | **Inventory** → **Quick stock update** |
| See the history | **Inventory** → **Stock history** (filter by product), or *Recent stock movements* on the product page |

Stock goes down automatically when a customer orders and back up automatically when an order is cancelled. Stock can never go below zero.

## 5. Orders

### Order statuses

| Status | Meaning | Next allowed |
|---|---|---|
| Pending | Just placed; customer may still cancel | Confirmed, Cancelled |
| Confirmed | You accepted the order | Processing, Cancelled |
| Processing | Being prepared | Ready for delivery, Cancelled |
| Ready for delivery | Packed and waiting for the courier | Delivered |
| Delivered | Received by the customer (final) | — |
| Cancelled | Stopped; **stock is returned automatically** (final) | — |

### Process an order
1. **Orders** → click the order number. You see items, customer phone, delivery address, notes (gift message) and payment.
2. In **Update order status**, choose the next step, add an optional note, **Update status**. Repeat as the order progresses.
3. The customer sees each step in **My orders** with the date and time.

Use the tabs (Pending, Confirmed, …) and the search box (order number, name, e-mail or phone) to find orders. Filter by payment status, method or dates.

### Payments

| Situation | What to record (Payment box → **Record payment as**) |
|---|---|
| Cash on delivery collected | **Paid**, note "Cash collected" |
| Bank transfer: the reference and money are in your bank statement | **Paid**, note e.g. "Checked in BOC statement 17 Oct" |
| Bank transfer reference wrong / money not received | **Failed** — the customer can send a new reference |
| Money returned to the customer | **Refunded** — a note with amount, method and date is **required** |

Rules that protect you:
- A **bank-transfer** order cannot be **Confirmed** until the payment is **Paid**, unless you tick **Confirm before the bank transfer is verified** (for example for a trusted customer).
- A cancelled order cannot be marked Paid. Refunds are only possible after Paid.
- **Only mark Paid when the money has really been received.** The website never takes card payments.

## 6. Customers

- **Customers** → search by name, e-mail or phone. Open a customer to see contact details, saved addresses, all orders and total spent.
- **Deactivate account**: the customer is logged out immediately and cannot log in. **Activate account** reverses it.
- **Forgotten password**: open the customer → **Set temporary password**. A new password is shown **once**. Give it to the customer privately (e.g. by phone) and ask them to change it in *My account → Change password*. It is not stored in readable form anywhere.

## 7. Reports

**Reports** → choose a report tab and a date range (or a quick range: This month, Last month, Last 7 days, Today) → **Show**. **Export CSV** downloads the same data for Excel.

| Report | What it shows |
|---|---|
| Sales summary | Gross sales, collected revenue, average order, still-pending value, daily chart |
| Orders in date range | Every order with status and payment |
| Order status totals | Count and value per status (includes cancelled) |
| Best-selling products | Top 10 by quantity |
| Current inventory | All products with stock and stock value |
| Low-stock products | Products at or below their alert level |

**Definitions:** *Gross sales* = orders placed in the period that are not cancelled and not refunded. *Collected revenue* = payments you marked Paid in the period. The explanation is also at the bottom of the Reports page.

## 8. Activity log

**Activity log** lists every admin action (product changes, price changes, stock changes, order and payment updates, settings changes, exports) with the admin's name, time and computer address. Passwords and bank numbers are never recorded.

## 9. Good practice

- Log out when you leave the computer (you are also logged out after 30 minutes without activity).
- Never share the administrator password. Create a separate administrator for each staff member (see README, *Create an administrator*).
- Take a backup every week and before big changes (`docs/deployment-and-backup.md`).
- If something goes wrong, see `docs/troubleshooting.md`.
