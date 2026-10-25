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
4. **E-mail** (on the laptop, in PowerShell from the `giftaura` folder):
   - E-mails start in **test mode**: nothing is sent; each e-mail is saved as a file in `storage\mail\` (double-click to open it).
   - To send real e-mails from Gmail: first create a Gmail **App Password** (Google Account → **Security** → turn on **2-Step Verification** → **App passwords** → name it *GiftAura* → copy the 16 letters). Then run `C:\xampp\php\php.exe scripts\setup_mail.php`, choose **2**, and enter the Gmail address and the App Password.
   - Send yourself a test: `C:\xampp\php\php.exe scripts\test_mail.php`.
   - The App Password is stored only in `config/config.php` (never in Git). To stop sending, run `setup_mail.php` again and choose 1 or 3; you can also delete the App Password in your Google Account at any time.

> Change your own password from the top bar: **Change password**.

**Change the logo:** **Settings** → **Logo** → **Choose File** → pick your logo (PNG with a transparent background is best; JPG/WebP also work; up to 2 MB) → **Upload logo** → in the shop press **Ctrl + F5**. A square logo appears next to the store name; a wide logo that already contains the name is shown on its own. It is also used in the admin panel and as the browser-tab icon. **Use the default logo** switches back.

## 2. Daily routine (5 minutes)

1. Open the **Dashboard**:
   - **Pending orders** — new orders waiting for you (also shown as an orange number next to *Orders*).
   - **Payments to verify** — bank transfers where the customer sent a reference.
   - **Low / out of stock** — products to restock.
   - **Sales today / this month** — from real orders (cancelled and refunded orders are not counted).
   - A red message *"… e-mail(s) could not be sent"* means Gmail refused or could not be reached — open **E-mail log** (section 8).
2. Process new orders (section 5) and restock (section 4).

## 3. Products and categories

### Add a product
1. **Products** → **+ Add product**.
2. Enter name, category, price (e.g. `4500` or `4500.00`), optional SKU (product code), short and full description.
3. **Low-stock alert level**: you get an alert when stock reaches this number.
4. **Opening stock**: how many you have now.
5. **Photos**: click **Choose files** and select up to **4** photos (hold **Ctrl** to pick several). Small previews appear; the first one becomes the main photo.
6. Tick **Active** (visible in the shop) and, if wanted, **Featured on the homepage**.
7. **Create product**. The edit page opens with the photos. (If the form shows an error, for example a missing price, choose the photos again — browsers forget chosen files when a page reloads.)

### Put a product on sale
1. **Products** → **Edit** the product.
2. Keep the **Normal price** (e.g. `2000`) and type the **Sale price** (e.g. `1500`) → **Save changes**.
3. Customers now pay Rs. 1,500; the shop shows ~~Rs. 2,000~~, a red **-25%** badge, "You save Rs. 500 (25%)", and lists the product under **On sale**.
4. To end the sale, empty the **Sale price** box and save. Orders already placed keep the price the customer paid.

### Let customers personalise a product (name or message)
1. **Products** → **Edit** the product (or **+ Add product**).
2. In the **Personalisation** box tick **Customers can personalise this product**.
3. **Question for the customer**: what to type, e.g. `Name to print on the mug` or `Message for the card` (empty = "Your personalised message").
4. **Longest message**: 10 to 500 characters, e.g. `30` for a name on a mug.
5. Keep **The customer must type a message** ticked if the product cannot be made without it; untick it for an optional card message → **Save changes**.
6. The shop now shows **Personalisable** and a **Personalise** button on the product card, and a message box on the product page.
7. When the order arrives, the message is shown under the product on the order page (and the order is marked **Personalised** in the Orders list). Copy it exactly as written — line breaks included. It is also in the customer's order e-mail.
8. To stop personalisation, untick the box and save; the question and limit are remembered for next time. Orders already placed keep their message.

### Manage customer reviews
Customers can review a product only after their order containing it is **Delivered**. Reviews appear on the product page straight away.
1. **Reviews** in the menu lists every review, newest first (filter by stars, shown/hidden, or search a product or customer).
2. **Hide** a review that is rude, off-topic or contains private details (phone numbers, addresses). It disappears from the shop and the average is recalculated; the customer still sees it in their account marked "Hidden by the store". **Show** puts it back.
3. **Delete** removes it permanently (the customer may then write a new one).
4. Do not hide honest negative reviews — answer the customer by phone or e-mail and fix the problem instead; it builds trust.

### Add photos
1. On the product's edit page, under **Images**, **Choose files** → one or several JPG, PNG or WebP photos, **max 2 MB each**, at least 100 × 100 px.
2. Optional: a short description of the photo (helps blind users).
3. **Upload**. **Up to 4 photos per product**; the box shows how many more are allowed and disappears at 4. The first photo becomes the **Main image**; use **Make main** to change it, **Delete** to make room for another.
4. Photos are automatically resized and cleaned for safety. If a file is refused, the message says why (type, size or not a real image).

### Edit, hide or bring back a product
- **Products** → **Edit** → change and **Save changes**. Changing the price does **not** change past orders.
- **Deactivate** hides the product from the shop (it is never deleted, because past orders refer to it). **Activate** shows it again.
- Use the search box and filters (category, active/inactive, low/out of stock) to find products.

### Categories
- **Picture (optional):** in the category form choose a square picture (about 600 × 600 px). It appears as the round icon on the homepage. Without one, a photo of one of the category's products is used. Tick **Remove this picture** to go back to that.
- **Main category** (e.g. *Hampers*): **Categories** → **+ Add main category** → type the name, choose a picture → **Add main category**. It appears as a round picture on the homepage and in **All categories**.
- **Subcategory** (e.g. *Hampers › Fruit hampers*): press **+ Subcategory** next to the main category → type the name → **Add subcategory**. (Or choose the main category in **Belongs to**.) Only one level of subcategories is possible.
- **Products in it**: Products → Add/Edit → **Category** → choose *Hampers* or *Hampers › Fruit hampers*.
- **Popular categories (homepage tiles)**: at the bottom of the Categories page choose up to 6 categories and a short catchy line for each (e.g. *Say it with flowers!*) → **Save popular categories**. Tiles 2 and 4 are wide on a computer. The tile picture is the category picture. Without a choice, the first 6 main categories are shown with their number of gifts.
- Careful: after pressing **Edit**, the form *changes* that category. To add a new one, press **Cancel** or **+ Add main category** first.
- **Display order**: smaller numbers appear first.
- **Deactivate** hides the category and all its products from the shop.
- **Delete** appears only for empty categories. A category with products or subcategories must be emptied first, or deactivated.

### About page

**About page** (menu) → edit the headline, introduction, year the shop started (optional), our story (empty line between paragraphs), founder name, role, quote and photo, mission, vision, location and map → **Save About page** → **View the page**. The numbers at the top (products, categories, ways to pay) are counted automatically. Until you press Save the page shows draft texts — read them and change anything that is not exactly right. Phone, e-mail, full address and opening hours come from **Settings**.

### Homepage banners

**Banners** → the large pictures that slide at the top of the homepage.

1. Make a wide picture, ideally **1920 × 600 px** (at least 1000 px wide), JPG/PNG/WebP up to 2 MB. Keep faces and text in the middle — on phones the sides are cut off.
2. **Add a banner**: choose the picture and type a **Title** (also read out to blind visitors). Optional: short text, **Button text** + **Link**, **Show from / Show until** dates, **Display order** (1 is shown first).
   - Link: a page of the shop, copied from the address bar after `/giftaura/`, e.g. `products.php?category=flower-bouquets`, or a full `https://` address.
   - If your picture already has its own text (like a ready-made poster), untick **Show the title, text and button on the picture** — the whole banner then becomes the link.
3. **Hide** takes a banner off the homepage without deleting it; **Delete** removes it and its picture. The **Status** column shows *Showing*, *Scheduled* (start date in the future), *Ended* or *Hidden*.

With no banner showing, the homepage displays a simple welcome section instead.

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
- **E-mail verified?** Next to the e-mail address you see **Verified** or **Not verified**. A customer must verify before ordering. If the customer did not get the e-mail, press **Send verification e-mail again**. If you have confirmed the address with the customer (e.g. by phone), you may press **Mark e-mail as verified** (recorded in the Activity log).
- **Forgotten password**: customers can reset it themselves with **Forgotten your password?** on the login page. If e-mail is not working, open the customer → **Set temporary password**. A new password is shown **once**. Give it to the customer privately (e.g. by phone) and ask them to change it in *My account → Change password*. It is not stored in readable form anywhere.

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

## 8. E-mail log

**E-mail log** lists every e-mail the shop tried to send: time, recipient, type (verification, password reset, order confirmation, status update, payment update), subject, linked order and the **result**:

| Result | Meaning |
|---|---|
| **Sent** | Accepted by Gmail (it may still land in the customer's Spam folder) |
| **Saved** | Test mode — the e-mail is a file in `storage\mail\` |
| **Skipped** | E-mail is switched off |
| **Failed** | Not sent; the reason is shown underneath. The order or action itself **was** completed. See `docs/troubleshooting.md` §5 |

Customers automatically receive e-mails when you change an order to **Confirmed, Processing, Ready for delivery, Delivered or Cancelled**, and when you mark a payment **Paid, Failed or Refunded**. A note you type with a status change is included in the e-mail (the customer can also see it on their order page), so write it for the customer.

## 9. Activity log

**Activity log** lists every admin action (product changes, price changes, stock changes, order and payment updates, settings changes, exports) with the admin's name, time and computer address. Passwords and bank numbers are never recorded.

## 9b. Staff accounts (main administrator only)

You (the account created with `scripts/create_admin.php`) are the **main administrator** and can open everything. Give each helper their own **staff account** — never share your password.

1. Admin → **Staff accounts** → *Add a staff member*: name, a **username** (e.g. `nimal.staff`, cannot be changed later), e-mail (optional).
2. Tick the areas they may work in. Default: Products and stock, Categories, Customer inquiries. Others: Orders and payments, Customers, Reviews, Banners and About page, Reports.
3. Press **Create account**. A green box shows the username and a **generated password**. Copy it and give it to the person privately (it is shown only once and cannot be looked up later).
4. They log in on the normal login page with the **username** and that password, and must then choose their own password.

In the list you can **Edit** (change name, e-mail and areas — takes effect on their next click), **New password** (generates another one-time password, e.g. if they forgot theirs) and **Deactivate** (logs them out at once; nothing they did is deleted). Staff never see Staff accounts, Settings, the E-mail log or the Activity log. Everything they do is recorded in the Activity log with their name, and order history shows "(staff)".

## 9c. Customer inquiries

Messages sent from the **Contact** page appear in Admin → **Inquiries** (the badge shows how many are new). Open one, type the reply and choose **In progress** or **Resolved** → **Send reply**. The reply is e-mailed to the customer (reference INQ-…) and, if they were logged in when they wrote, shown under My account → My inquiries. Use *Change status without replying* for messages that need no answer (e.g. spam). Never ask customers for card numbers or passwords.

## 9d. In-shop sales and invoices

When a customer buys in the shop: Admin → **In-shop sales** → **+ New in-shop sale** (or **Sell in the shop** on a product's page).

1. Choose each product and its quantity (type the first letters in the list to jump to a product; **+ Add another line** for more). Prices come from the website.
2. Optional: customer name and mobile, a discount in rupees, a note for the invoice.
3. Choose how they paid. For cash, type the cash received to see the change.
4. **Save sale and create invoice** → the stock goes down at once and the invoice opens → **Print invoice** (or save it as PDF from the print window).

The invoice shows the shop name, the reference (INV-241024-0007), date and time, who served the customer, items, total and change. To show the shop address, phone and e-mail on invoices, fill them in under Settings. If a sale was entered by mistake or the customer returns the items, open the invoice → **Cancel invoice** with a reason: the stock goes back and the invoice stays in the list marked Cancelled. Shop sales appear on the Dashboard (Shop sales today) and in Reports (In-shop sales, Website + shop). Give staff the **In-shop sales** area to let them sell.

## 10. Good practice

- Log out when you leave the computer (you are also logged out after 30 minutes without activity).
- Never share the administrator password. Create a **staff account** for each helper (section 9b) and tick only the areas they need.
- Take a backup every week and before big changes (`docs/deployment-and-backup.md`).
- If something goes wrong, see `docs/troubleshooting.md`.
