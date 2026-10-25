# GiftAura — Security Checklist

Version 1.3 — section 7 (e-mail) and F9–F10 (banners) added 17 Oct 2026. For each control: what it protects against, where it is implemented, and how to test it yourself.
"Tested" refers to the automated tests in `tests/` (results in `docs/phase-log.md`) unless marked *manual*.
Manual tests use Chrome on the development laptop; press **F12 → Network** to see response headers.

## 1. Authentication

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| A1 | Passwords stored with `password_hash()` (bcrypt), checked with `password_verify()`; automatic re-hash if the algorithm changes | Password theft from the database | `UserService`, `Auth::attempt()` | phpMyAdmin → `users`: `password_hash` starts with `$2y$` — no readable passwords | Tested (T3-09) |
| A2 | Password rules: 8–72 characters, at least one letter and one number | Weak passwords | `Validator` rule `password` | Register with `abcdefgh` → "must contain at least one letter and one number" | Tested (T3-02) |
| A3 | Same message for unknown email, wrong password and deactivated account; dummy hash check keeps timing similar | Discovering which emails are registered | `Auth::attempt()` | Log in with an unknown email and with a wrong password → identical message | Tested (T3-11) |
| A4 | Login rate limit: 5 failures per email or 20 per IP in 15 minutes | Password guessing (brute force) | `RateLimiter`, `login_attempts` table | Enter a wrong password 6 times → "Too many unsuccessful login attempts"; the correct password is also refused until the lock expires | Tested (T3-11) |
| A5 | No default admin account; administrators are created only from the command line | Known default credentials | `scripts/create_admin.php` | There is no admin sign-up page; `register.php` with a forged `role=admin` field creates a customer | Tested (T3-09) |
| A6 | Fallback when e-mail is not available: admin issues a random temporary password, shown once, stored only as a hash, never logged | Customer locked out when e-mail cannot be used | `CustomerAdminService::setTemporaryPassword()` | Admin → Customers → customer → Set temporary password; check `admin_activity_log` does not contain it | Tested (admin_e2e) |

## 2. Sessions

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| S1 | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, path `/giftaura/` | Cookie theft by scripts, cross-site requests | `Session::start()` | F12 → Application → Cookies → `GIFTAURA_SID` shows HttpOnly ✓ and SameSite Lax | Tested (T3-07) |
| S2 | Strict mode, cookies only, no session ID in URLs | Session fixation | `Session::start()` | — | Built |
| S3 | New session ID at login, registration, logout and password change | Session fixation / hijacking | `Auth::loginById()`, `Auth::logout()`, `UserService::changePassword()` | Note the cookie value, log in, the value changes | Built — not separately tested |
| S4 | 30-minute idle timeout | Unattended computers | `Session::start()` (`security.session_idle_minutes`) | Log in, wait 31 minutes, refresh → logged out with a message | Built — not separately tested |
| S5 | User reloaded from the database on every request; deactivated accounts are logged out immediately | Continued access after an account is disabled | `Auth::user()` | Admin deactivates a customer who is logged in → their next click goes to login | Tested (admin_e2e) |
| S6 | `Cache-Control: no-store` on every page shown to a logged-in user | Private pages visible with the Back button after logout | `app/bootstrap.php` | Log in, open My orders, log out, press Back → page is reloaded and redirects to login | Tested (M-24) |

## 3. Authorisation

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| Z1 | `Auth::requireAdmin()` at the top of every admin page and action; role read from the database, never from the browser | Customers using admin functions | `public/admin/*.php` | Log in as a customer, open `localhost/giftaura/admin/settings.php` → **Access denied** | Tested (14 checks) |
| Z2 | Every customer query includes `user_id` of the logged-in user | Viewing or cancelling another customer's order / address (IDOR) | `OrderService::findForCustomer()`, `AddressService::find()` | As customer B, open customer A's order URL → **404** | Tested (T5-10) |
| Z3 | Order references are random (`GA261016-7K3M9Q`) and tracking also needs the order e-mail | Guessing other orders | `OrderService::newOrderNumber()`, `track-order.php` | Track with a wrong e-mail → "could not find" | Tested (T5-11) |
| Z5 | Staff permissions: `Auth::requireAdmin('area')` on every admin page and POST; permissions read from the database on every request and checked against a fixed list; Staff accounts, Settings, E-mail log and Activity log use `Auth::requireSuperAdmin()` | Staff opening areas they were not given | `app/core/Auth.php`, `StaffService`, `public/admin/*.php` | Log in as staff with only Products → open `admin/orders.php` → **Access denied** | Tested (staff_e2e, 13 pages) |
| Z6 | Staff passwords generated with `random_int()` (12 characters), shown once, stored only as a hash, never logged; forced change at first login and after a reset; deactivation logs the person out | Shared or known passwords; ex-staff access | `StaffService`, `admin/password.php` | Create staff → the password is not in `users`, `admin_activity_log` or `storage/logs` | Tested (staff_e2e) |
| Z7 | In-shop sales: page guard `requireAdmin('shop_sales')`; prices read from the database (never from the form); products locked and stock reduced through `InventoryService` in one transaction; amounts in whole cents; CHECK constraints on totals; cancelling is logged and can happen only once | Wrong prices, overselling, lost stock, hidden fraud | `ShopSaleService`, `admin/sale-new.php`, `admin/sale.php` | Post a `price[]` field → ignored; sell more than the stock → refused | Tested (shop_sales_e2e) |
| Z4 | Admin accounts cannot be deactivated from the customer screen; admins cannot check out | Accidental lock-out, mixed data | `CustomerAdminService`, `checkout.php` | — | Tested (admin_e2e) |

## 4. Input, output and database

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| D1 | PDO prepared statements with real (not emulated) prepares; integers bound as integers | SQL injection | `Database` | Search for `' OR 1=1 --` → 0 results, no error | Tested (T4-07) |
| D2 | Sort orders and filters are whitelisted; dynamic SQL only joins fixed fragments | SQL injection through sort/filter options | `ProductService::SORTS`, admin list services | `products.php?sort=DROP` → normal page | Tested (T4-13) |
| D3 | Every printed value passes through `e()` (`htmlspecialchars`, UTF-8) | Cross-site scripting (XSS) | all templates | Search for `<script>alert(1)</script>` → shown as text; category named `<script>` → shown as text | Tested (T4-08, admin_e2e) |
| D4 | Content-Security-Policy: scripts and styles only from this site; no inline scripts | XSS even if escaping were missed | `app/bootstrap.php` | F12 → Network → any page → `Content-Security-Policy` header | Tested (T3-07) |
| D5 | Server-side validation of every form (types, lengths, e-mail, Sri Lankan phone, district list, amounts) | Invalid or malicious data | `Validator`, services | Checkout with phone `123` → error under the field | Tested |
| D6 | CHECK constraints, foreign keys and unique keys in the database | Bad data even if the code had a bug | `sql/schema.sql` | phpMyAdmin: `UPDATE products SET stock_quantity=-1 WHERE id=1` → error | Tested on MariaDB (T2-2) |
| D7 | Least-privilege database user (`giftaura_app`: SELECT/INSERT/UPDATE/DELETE on `giftaura_db` only) | Damage if the app were compromised | `sql/00_create_database_and_user.sql` | `mysql -u giftaura_app -p -e "DROP TABLE giftaura_db.settings"` → Access denied | Tested (DB-04) |
| D8 | CSV export neutralises cells starting with `= + - @` | Formula injection in Excel | `ReportService::csvCell()` | Customer name `=HYPERLINK(...)` → exported as `'=HYPERLINK(...)` | Tested (admin_e2e) |
| D9 | Contact form: CSRF, hidden spam-trap field, 5 messages per hour per IP or e-mail, length limits, control characters removed, every message and reply escaped on screen and in e-mails | Spam, flooding, stored XSS | `InquiryService`, `contact.php` | Send `<b>x</b>` → shown as text in Admin → Inquiries | Tested (staff_e2e) |

## 5. Requests

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| R1 | CSRF token on every form that changes data; checked with `hash_equals` | Cross-site request forgery | `Csrf`, every POST handler | Submit a form after deleting the hidden `_csrf` field (F12 → Elements) → **Page expired** | Tested (T3-05, T5-04) |
| R2 | Changes only by POST (logout too); Post/Redirect/Get | Changes through links/images; double submission on refresh | all pages | `localhost/giftaura/logout.php` typed in the address bar does not log out | Tested (T3-04) |
| R3 | Redirect targets (`next=`) must be inside the site | Open redirect / phishing | `safe_next()` | `login.php?next=https://evil.com` → after login you stay on GiftAura | Tested (T3-12) |
| R4 | Prices and totals recalculated from the database; cart fingerprint must match | Price tampering | `CartService`, `OrderService::placeOrder()` | Add `total_amount=1` to the checkout form → ignored / refused | Tested (T5-07) |
| R5 | Transactions with row locks; stock can never go below zero | Overselling, race conditions | `OrderService`, `InventoryService` | Two customers buy the last unit at once → one order | Tested (T5-15; MariaDB row lock DB-08) |
| R6 | Double-submit protection: buttons disabled after the first click | Duplicate orders | `shop.js`, `admin.js` | Double-click Place order → one order | Built — not separately tested |

## 6. Files and server

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| F1 | Upload checks: size ≤ 2 MB, extension, real MIME (`finfo`), `getimagesize`, 100–5000 px | Uploading scripts or fake images | `ImageUploader` | Upload a `.php` file or a text file renamed `.jpg` → refused | Tested (admin_e2e) |
| F2 | Images re-encoded with GD into a new file with a random 32-character name | Hidden code and EXIF/GPS data in images; path tricks | `ImageUploader` | Upload a JPEG with PHP code appended → saved file contains no PHP | Tested (admin_e2e) |
| F3 | `public/uploads/.htaccess`: PHP engine off, scripts denied, only jpg/png/webp served | Running an uploaded script | `public/uploads/.htaccess` | `localhost/giftaura/uploads/products/x.php` → 403/404 | Tested (WEB-20) |
| F4 | Only `public/` is reachable; `app/`, `config/`, `sql/`, `storage/`, `scripts/`, `docs/`, `tests/` denied; hidden files and `env-check.php` denied; no directory listings | Leaking code, passwords, logs, SQL | root `.htaccess` + folder `.htaccess` files | `localhost/giftaura/config/config.php`, `/sql/schema.sql`, `/storage/logs/` → 403 or 404 | Tested (WEB-07 – WEB-19) |
| F5 | Database password only in `config/config.php`, excluded from Git | Credentials in the repository | `.gitignore` | `git ls-files config` → only `config.example.php` and `.htaccess` | Verified (Phase 3 commit) |
| F6 | Errors: generic page for visitors, details only in `storage/logs`; `display_errors` off; passwords/tokens/bank numbers removed from log context | Information disclosure | `app/bootstrap.php`, `Logger`, root `.htaccess` | Stop MySQL in XAMPP, open the shop → "Something went wrong" without SQL details | Tested (T3-08) |
| F7 | Security headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`; `X-Powered-By` removed; HSTS when served over HTTPS | Click-jacking, MIME sniffing, version disclosure | `app/bootstrap.php`, root `.htaccess` | F12 → Network → headers | Tested (WEB-02 – WEB-05) |
| F8 | Activity log of all admin actions (never secrets) | Untraceable changes | `ActivityLogService` | Admin → Activity log | Tested (admin_e2e) |
| F9 | Banner and category pictures use the same upload checks and re-encoding as product photos (banners at least 1000 px wide), into `uploads/banners` and `uploads/categories`, covered by `public/uploads/.htaccess` | Uploading scripts through the new forms | `ImageUploader` (folders), `BannerService`, `CategoryAdminService::setImage()` | Upload a PHP file renamed `.jpg` as a banner → refused | Tested (banner_e2e) |
| F11 | Wishlist: POST with CSRF only; always uses the logged-in customer's id (never one from the form); only active products can be added; the return address must be inside the shop (`safe_next`) | Changing someone else's wishlist; open redirect | `wishlist.php`, `WishlistService` | Post with `return=https://evil.example/` → back to the shop | Tested (sale_wishlist_e2e) |
| F12 | Sale prices: only the server-side `price` is charged; the sale price must be above 0 and below the normal price | Wrong or negative prices | `ProductAdminService::save()`, `CartService` | Sale price equal to the normal price → refused | Tested (sale_wishlist_e2e) |
| F13 | Personalisation message: CSRF on add/edit; cleaned on the server (control characters removed, line breaks normalised), length limited per product (max 500), always printed with `e()` (HTML shown as text), included in the checkout fingerprint and re-checked inside the order transaction | XSS, oversized input, message changed after the customer saw the total | `CartService::checkMessage`, `OrderService::placeOrder`; `tests/custom_e2e.sh` |
| F14 | Reviews: allowed only for products in the customer's own **delivered** orders (checked on GET and again on every save); one per customer and product (unique key); rating 1–5 and comment ≤ 1000 characters validated on the server; comment escaped; public name is first name + initial only; CSRF on save/delete and on admin hide/show/delete; admin pages 403 for customers | Fake reviews, XSS, privacy, review spam | `ReviewService`; `tests/review_e2e.sh` |
| F10 | Banner links: only a page of this shop (safe characters, no `//`, no `..`) or a valid `http(s)://` address; title and texts escaped | `javascript:` links and script injection on the homepage | `BannerService::href()` | Save a banner with link `javascript:alert(1)` → refused | Tested (banner_e2e) |

## 7. E-mail (verification, password reset, notifications)

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| E1 | Link secrets are 32 random bytes (`random_bytes`); only their SHA-256 hash is stored | Someone who reads the database (or a backup) using the links | `TokenService` | phpMyAdmin → `user_tokens`: `token_hash` differs from the code in the e-mail link | Tested (email_e2e, email_unit) |
| E2 | Links expire (verify 24 h, reset 60 min), work once, and a new link cancels older ones; use is an atomic `UPDATE … WHERE used_at IS NULL` | Re-using an old or leaked link; double use at the same moment | `TokenService::consume()` | Open a used link → "This link does not work" | Tested (email_e2e) |
| E3 | Opening a link only shows a button; the change happens on a POST with CSRF token | E-mail security scanners "clicking" links and using them up; CSRF | `verify-email.php`, `reset-password.php` | Open the link → nothing changes until the button is pressed | Tested (email_e2e) |
| E4 | "Forgotten password" gives the same answer for every address; administrators and inactive accounts get no e-mail | Finding out who is a customer; resetting admin accounts by e-mail | `forgot-password.php`, `EmailService::sendPasswordReset()` | Request a reset for an unknown address and for your own → same message | Tested (email_e2e) |
| E5 | Limits: 3 links per account per hour; 5 reset requests per browser session per 15 min | Flooding a customer's inbox; abusing the shop as a spam sender | `TokenService::tooMany()`, `forgot-password.php` | Press "Send the link again" 4 times → 4th refused | Tested (email_e2e) |
| E6 | Checkout requires a verified address, checked on the checkout page **and** again in `OrderService::placeOrder()` | Orders with fake or mistyped e-mail addresses | `checkout.php`, `OrderService` | Unverified customer posts the checkout form directly → no order | Tested (email_e2e) |
| E7 | Addresses, names and subjects with line breaks are refused; one recipient only | E-mail header injection (sending spam through the shop) | `Mailer::send()` | — | Tested (email_unit) |
| E8 | All values in e-mails are escaped with `e()` | HTML/script injection into e-mails (e.g. a customer name with tags) | `app/views/emails/*` | Order with name `Nimal <b>Bold</b>` → e-mail shows the tags as text | Tested (email_e2e) |
| E9 | Gmail App Password (not the real password) kept only in `config/config.php` (not in Git); never written to logs or the SMTP trace | Leaking the mail account | `scripts/setup_mail.php`, `Mailer` | `git ls-files config`; `scripts\test_mail.php` shows `[password hidden]` | Tested (email_e2e: password absent from logs) |
| E10 | TLS certificate always verified (never switched off); unencrypted SMTP only allowed to this computer | Someone on the network reading the password or e-mails | `Mailer::sendSmtp()` | Point the mail host to another server without encryption → refused | Tested (email_e2e) |
| E11 | E-mails are sent after the order is saved; a failure is logged and never stops the order | Losing orders because Gmail is down or the password changed | `EmailService` | Wrong App Password → order still placed; Admin → E-mail log shows "failed" | Tested (email_e2e, fake SMTP server) |
| E12 | Test-mode e-mails (`storage/mail/`) are not reachable from the browser and not committed to Git | Leaking customer data and live links | `storage/.htaccess`, `.gitignore` | `localhost/giftaura/storage/mail/` → 403/404 | Built; checked by `system_check.php` MAIL-04 |
| E13 | Links contain only the secret; `Referrer-Policy: same-origin` stops it leaking to other sites | Link leaking through the Referer header | `app/bootstrap.php` | F12 → Network → headers | Tested (WEB headers) |

## 8. Known limitations (honest list for the report)

| Limitation | Reason | Possible improvement |
|---|---|---|
| E-mails sent from a personal Gmail account (500 per day limit, may land in Spam) | No paid mail service for the project | Use a business mail service with SPF/DKIM for the shop's own domain |
| E-mails are sent while the page loads (adds about 1–3 s when Gmail is used) | No background job system on XAMPP | Queue e-mails in the database and send them with a scheduled task |
| The customer cannot change their e-mail address | Out of scope; e-mail is the login name | Add a change-address flow that verifies the new address first |
| No online card payments | No payment provider account | Integrate a provider (e.g. PayHere) with **server-side** payment verification |
| Registration is not rate-limited | Low risk on a local system | Add a per-IP limit or CAPTCHA before public deployment |
| Changing a password does not log out other open sessions | Sessions are stored by PHP, not in the database | Store a "password changed at" time and reject older sessions |
| Runs over HTTP on localhost | Local development | Use HTTPS (Let's Encrypt) on a real server; cookies then get `Secure` and HSTS automatically |
| No two-factor authentication for admins | Not in the approved scope | Add TOTP (authenticator app) for administrators |

## 9. Before demonstration or deployment

- [ ] `public/env-check.php` deleted (it is also blocked by `.htaccess`; checked by WEB-17/18)
- [ ] `config/config.php` has `'debug' => false`
- [ ] Real store details entered in Admin → Settings (or left empty — nothing is invented)
- [ ] Sample products replaced or clearly marked as samples
- [ ] Database backup taken (see deployment guide, Phase 9)
- [ ] `giftaura_app` password is strong and not reused
- [ ] E-mail mode chosen (`scripts\setup_mail.php`); for Gmail a test e-mail arrived (`scripts\test_mail.php`)
- [ ] `app.site_url` in `config/config.php` is the address customers use (links in e-mails start with it)
