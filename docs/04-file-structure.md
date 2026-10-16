# GiftAura — File Structure

Version 1.0 — Phase 2. Planned structure for the whole project; the **Phase** column shows when each file is built. Files marked ✅ exist now.

Project root on the development laptop: `C:\xampp\htdocs\giftaura\`
Public URL: `http://localhost/giftaura/`

## 1. Folder overview

| Folder | Browser access | Contents |
|---|---|---|
| `public/` | **Yes** — the only public folder | Page controllers, admin pages, CSS, JS, images, uploads |
| `app/` | Denied | PHP core classes, services, view templates |
| `config/` | Denied | `config.php` (real credentials, not in Git) and `config.example.php` |
| `sql/` | Denied | Database scripts |
| `scripts/` | Denied | Command-line tools (create first admin, build diagrams) |
| `storage/` | Denied | Error logs |
| `docs/` | Denied through the web (open the files directly from disk) | Design documents, diagrams, guides, test plan |

## 2. Full tree

```
giftaura/
├── .htaccess                       ✅ routes every request into public/
├── .gitignore                      ✅ keeps config.php, logs, uploads out of Git
├── README.md                       ✅ installation and startup
│
├── config/
│   ├── .htaccess                   ✅ Require all denied
│   ├── config.example.php             P3  template, committed to Git
│   └── config.php                     P3  real settings (copied from example, NOT in Git)
│
├── app/
│   ├── .htaccess                   ✅ Require all denied
│   ├── bootstrap.php                  P3  loads config, error handling, session, autoloader
│   ├── helpers.php                    P3  e(), url(), asset(), money(), redirect(), old()
│   ├── core/
│   │   ├── Database.php               P3  PDO connection (singleton), transactions
│   │   ├── Session.php                P3  hardened session start, timeout, regenerate
│   │   ├── Auth.php                   P3  login, logout, current user, requireCustomer/requireAdmin
│   │   ├── RateLimiter.php            P3  login attempt limits
│   │   ├── Csrf.php                   P3  token create/verify, hidden field helper
│   │   ├── Validator.php              P3  reusable validation rules + error collection
│   │   ├── View.php                   P3  render templates inside layouts
│   │   ├── Flash.php                  P3  one-time success/error messages
│   │   ├── Logger.php                 P3  writes to storage/logs (no secrets)
│   │   └── ImageUploader.php          P6  secure image upload + GD re-encode
│   ├── services/
│   │   ├── UserService.php            P3  register, profile, password, admin customer tools
│   │   ├── SettingsService.php        P3  read/update settings table
│   │   ├── CategoryService.php        P4  category tree, CRUD, slug
│   │   ├── ProductService.php         P4  listing, search, filters, detail, CRUD
│   │   ├── AddressService.php         P5  address book
│   │   ├── CartService.php            P5  session + DB cart, merge on login, totals
│   │   ├── OrderService.php           P5  place order (transaction), cancel, status workflow
│   │   ├── PaymentService.php         P5  payment status workflow
│   │   ├── InventoryService.php       P5  every stock change + ledger row
│   │   ├── ReportService.php          P6  report queries and CSV rows
│   │   └── ActivityLogService.php     P6  admin audit log
│   └── views/
│       ├── layouts/shop.php           P3  header, navigation, footer
│       ├── layouts/admin.php          P3  sidebar, top bar
│       ├── partials/                  P3+ product card, pagination, flash, form errors, order timeline
│       ├── shop/                      P3+ page templates (home, products, product, cart, checkout, account/…)
│       ├── admin/                     P6  admin page templates
│       └── errors/                    P3  403, 404, 500 pages
│
├── public/
│   ├── env-check.php               ✅ (your existing file — delete before submission)
│   ├── index.php                      P4  homepage
│   ├── products.php                   P4  listing: ?category= &q= &min= &max= &in_stock= &sort= &page=
│   ├── product.php                    P4  detail: ?slug=
│   ├── cart.php                       P5  view/update cart (POST actions)
│   ├── checkout.php                   P5  checkout form + place order
│   ├── order-confirmation.php         P5  ?order=GA…
│   ├── track-order.php                P5  track by reference
│   ├── login.php · register.php · logout.php     P3
│   ├── contact.php · about.php        P4
│   ├── account/
│   │   ├── index.php                  P3  profile
│   │   ├── password.php               P3  change password
│   │   ├── addresses.php              P5  address book
│   │   ├── orders.php                 P5  order history
│   │   └── order.php                  P5  order details, cancel, bank reference
│   ├── admin/
│   │   ├── index.php                  P6  dashboard
│   │   ├── categories.php · category-edit.php            P6
│   │   ├── products.php · product-edit.php · product-images.php   P6
│   │   ├── inventory.php              P6  stock adjust, movements, low stock
│   │   ├── orders.php · order.php     P6  list, detail, status, payment
│   │   ├── customers.php · customer.php            P6
│   │   ├── reports.php · report-export.php         P6  reports + CSV
│   │   ├── activity.php               P6  admin activity log
│   │   └── settings.php               P6  store settings
│   ├── assets/
│   │   ├── css/shop.css · admin.css   P3
│   │   ├── js/shop.js · admin.js      P3+
│   │   └── img/logo.svg · placeholder-product.svg   P3
│   └── uploads/
│       ├── .htaccess               ✅ no PHP execution, images only
│       └── products/                  P6  generated image files (not in Git)
│
├── scripts/
│   ├── .htaccess                   ✅ Require all denied
│   ├── create_admin.php               P3  create the first administrator (command line only)
│   └── build_diagrams.php          ✅ regenerates docs/diagrams.html from the docs
│
├── sql/
│   ├── .htaccess                   ✅ Require all denied
│   ├── 00_create_database_and_user.sql   ✅
│   ├── schema.sql                  ✅
│   └── seed.sql                    ✅
│
├── storage/
│   ├── .htaccess                   ✅ Require all denied
│   └── logs/.gitkeep               ✅
│
└── docs/
    ├── .htaccess                   ✅ Require all denied
    ├── 01-requirements.md          ✅
    ├── 02-architecture.md          ✅
    ├── 03-database-design.md       ✅
    ├── 04-file-structure.md        ✅
    ├── diagrams.html               ✅ open from disk to view all diagrams
    ├── use-cases.md                   P9
    ├── test-plan.md                   P8
    ├── security-checklist.md          P7
    ├── troubleshooting.md             P9
    ├── admin-guide.md                 P9
    ├── customer-guide.md              P9
    └── deployment-and-backup.md       P9
```

## 3. Naming conventions

| Item | Convention | Example |
|---|---|---|
| Public pages | lowercase, hyphenated | `order-confirmation.php` |
| Classes | PascalCase, one class per file, file name = class name | `OrderService.php` |
| Methods / variables | camelCase | `placeOrder()`, `$deliveryFee` |
| Database tables / columns | snake_case, plural tables | `order_items.unit_price` |
| CSS classes | lowercase, hyphenated, block prefix | `.product-card__price` |
| URLs | Pretty slugs for products and categories | `product.php?slug=sample-red-rose-bouquet` |
