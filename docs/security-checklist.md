# GiftAura — Security Checklist

Version 1.0 — Phase 7. For each control: what it protects against, where it is implemented, and how to test it yourself.
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
| A6 | Forgotten password: admin issues a random temporary password, shown once, stored only as a hash, never logged | Insecure e-mail resets on a server without mail | `CustomerAdminService::setTemporaryPassword()` | Admin → Customers → customer → Set temporary password; check `admin_activity_log` does not contain it | Tested (admin_e2e) |

## 2. Sessions

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| S1 | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, path `/giftaura/` | Cookie theft by scripts, cross-site requests | `Session::start()` | F12 → Application → Cookies → `GIFTAURA_SID` shows HttpOnly ✓ and SameSite Lax | Tested (T3-07) |
| S2 | Strict mode, cookies only, no session ID in URLs | Session fixation | `Session::start()` | — | Built |
| S3 | New session ID at login, registration, logout and password change | Session fixation / hijacking | `Auth::loginById()`, `Auth::logout()`, `UserService::changePassword()` | Note the cookie value, log in, the value changes | Built (manual) |
| S4 | 30-minute idle timeout | Unattended computers | `Session::start()` (`security.session_idle_minutes`) | Log in, wait 31 minutes, refresh → logged out with a message | Built (manual) |
| S5 | User reloaded from the database on every request; deactivated accounts are logged out immediately | Continued access after an account is disabled | `Auth::user()` | Admin deactivates a customer who is logged in → their next click goes to login | Tested (admin_e2e) |
| S6 | `Cache-Control: no-store` on every page shown to a logged-in user | Private pages visible with the Back button after logout | `app/bootstrap.php` | Log in, open My orders, log out, press Back → page is reloaded and redirects to login | Built (manual) |

## 3. Authorisation

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| Z1 | `Auth::requireAdmin()` at the top of every admin page and action; role read from the database, never from the browser | Customers using admin functions | `public/admin/*.php` | Log in as a customer, open `localhost/giftaura/admin/settings.php` → **Access denied** | Tested (14 checks) |
| Z2 | Every customer query includes `user_id` of the logged-in user | Viewing or cancelling another customer's order / address (IDOR) | `OrderService::findForCustomer()`, `AddressService::find()` | As customer B, open customer A's order URL → **404** | Tested (T5-10) |
| Z3 | Order references are random (`GA261016-7K3M9Q`) and tracking also needs the order e-mail | Guessing other orders | `OrderService::newOrderNumber()`, `track-order.php` | Track with a wrong e-mail → "could not find" | Tested (T5-11) |
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
| D7 | Least-privilege database user (`giftaura_app`: SELECT/INSERT/UPDATE/DELETE on `giftaura_db` only) | Damage if the app were compromised | `sql/00_create_database_and_user.sql` | `mysql -u giftaura_app -p -e "DROP TABLE giftaura_db.settings"` → Access denied | Manual (T2-4) |
| D8 | CSV export neutralises cells starting with `= + - @` | Formula injection in Excel | `ReportService::csvCell()` | Customer name `=HYPERLINK(...)` → exported as `'=HYPERLINK(...)` | Tested (admin_e2e) |

## 5. Requests

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| R1 | CSRF token on every form that changes data; checked with `hash_equals` | Cross-site request forgery | `Csrf`, every POST handler | Submit a form after deleting the hidden `_csrf` field (F12 → Elements) → **Page expired** | Tested (T3-05, T5-04) |
| R2 | Changes only by POST (logout too); Post/Redirect/Get | Changes through links/images; double submission on refresh | all pages | `localhost/giftaura/logout.php` typed in the address bar does not log out | Tested (T3-04) |
| R3 | Redirect targets (`next=`) must be inside the site | Open redirect / phishing | `safe_next()` | `login.php?next=https://evil.com` → after login you stay on GiftAura | Tested (T3-12) |
| R4 | Prices and totals recalculated from the database; cart fingerprint must match | Price tampering | `CartService`, `OrderService::placeOrder()` | Add `total_amount=1` to the checkout form → ignored / refused | Tested (T5-07) |
| R5 | Transactions with row locks; stock can never go below zero | Overselling, race conditions | `OrderService`, `InventoryService` | Two customers buy the last unit at once → one order | Tested (T5-15, SQLite); MariaDB in Phase 8 |
| R6 | Double-submit protection: buttons disabled after the first click | Duplicate orders | `shop.js`, `admin.js` | Double-click Place order → one order | Built (manual) |

## 6. Files and server

| # | Control | Protects against | Implemented in | How to test | Status |
|---|---|---|---|---|---|
| F1 | Upload checks: size ≤ 2 MB, extension, real MIME (`finfo`), `getimagesize`, 100–5000 px | Uploading scripts or fake images | `ImageUploader` | Upload a `.php` file or a text file renamed `.jpg` → refused | Tested (admin_e2e) |
| F2 | Images re-encoded with GD into a new file with a random 32-character name | Hidden code and EXIF/GPS data in images; path tricks | `ImageUploader` | Upload a JPEG with PHP code appended → saved file contains no PHP | Tested (admin_e2e) |
| F3 | `public/uploads/.htaccess`: PHP engine off, scripts denied, only jpg/png/webp served | Running an uploaded script | `public/uploads/.htaccess` | `localhost/giftaura/uploads/products/x.php` → 403/404 | Manual |
| F4 | Only `public/` is reachable; `app/`, `config/`, `sql/`, `storage/`, `scripts/`, `docs/`, `tests/` denied; hidden files and `env-check.php` denied; no directory listings | Leaking code, passwords, logs, SQL | root `.htaccess` + folder `.htaccess` files | `localhost/giftaura/config/config.php`, `/sql/schema.sql`, `/storage/logs/` → 403 or 404 | Manual (Phase 8) |
| F5 | Database password only in `config/config.php`, excluded from Git | Credentials in the repository | `.gitignore` | `git ls-files config` → only `config.example.php` and `.htaccess` | Verified (Phase 3 commit) |
| F6 | Errors: generic page for visitors, details only in `storage/logs`; `display_errors` off; passwords/tokens/bank numbers removed from log context | Information disclosure | `app/bootstrap.php`, `Logger`, root `.htaccess` | Stop MySQL in XAMPP, open the shop → "Something went wrong" without SQL details | Tested (T3-08) |
| F7 | Security headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`; `X-Powered-By` removed; HSTS when served over HTTPS | Click-jacking, MIME sniffing, version disclosure | `app/bootstrap.php`, root `.htaccess` | F12 → Network → headers | Tested (Phase 7) |
| F8 | Activity log of all admin actions (never secrets) | Untraceable changes | `ActivityLogService` | Admin → Activity log | Tested (admin_e2e) |

## 7. Known limitations (honest list for the report)

| Limitation | Reason | Possible improvement |
|---|---|---|
| No e-mail password reset or e-mail notifications | XAMPP has no mail server | Add SMTP (e.g. PHPMailer) and a time-limited, single-use reset token |
| No online card payments | No payment provider account | Integrate a provider (e.g. PayHere) with **server-side** payment verification |
| Registration is not rate-limited | Low risk on a local system | Add a per-IP limit or CAPTCHA before public deployment |
| Changing a password does not log out other open sessions | Sessions are stored by PHP, not in the database | Store a "password changed at" time and reject older sessions |
| Runs over HTTP on localhost | Local development | Use HTTPS (Let's Encrypt) on a real server; cookies then get `Secure` and HSTS automatically |
| No two-factor authentication for admins | Not in the approved scope | Add TOTP (authenticator app) for administrators |

## 8. Before demonstration or deployment

- [ ] `public/env-check.php` deleted (it is also blocked by `.htaccess`)
- [ ] `config/config.php` has `'debug' => false`
- [ ] Real store details entered in Admin → Settings (or left empty — nothing is invented)
- [ ] Sample products replaced or clearly marked as samples
- [ ] Database backup taken (see deployment guide, Phase 9)
- [ ] `giftaura_app` password is strong and not reused
