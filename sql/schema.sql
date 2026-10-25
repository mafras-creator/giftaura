-- =====================================================================
-- GiftAura E-Commerce Web System
-- File   : sql/schema.sql
-- Purpose: Complete database structure (tables, keys, constraints).
-- Target : MariaDB 10.4.32 (XAMPP), InnoDB, utf8mb4
-- ---------------------------------------------------------------------
-- HOW TO RUN (phpMyAdmin):
--   1. Run sql/00_create_database_and_user.sql first (once).
--   2. Click giftaura_db in the left panel -> Import -> choose this file -> Import.
--   3. Then import sql/seed.sql the same way.
--
-- WARNING: this file DROPS and re-creates every GiftAura table.
--          Re-importing it deletes all GiftAura data (orders, users, ...).
--          It never touches any other database.
--
-- Design notes
--   * Money is DECIMAL(10,2), in Sri Lankan rupees (LKR). Never FLOAT.
--   * Dates are DATETIME. The application sets the session time zone to
--     +05:30 (Asia/Colombo) so CURRENT_TIMESTAMP is Sri Lankan time.
--   * Products, categories and users are deactivated, not deleted, so
--     past orders, payments and stock history always stay intact.
--   * order_items keeps a snapshot of name, SKU, price and quantity so
--     editing or removing a product never changes a past order.
--   * CHECK constraints are enforced by MariaDB 10.2+ and act as a last
--     line of defence behind the PHP validation.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS inquiry_replies;
DROP TABLE IF EXISTS customer_inquiries;
DROP TABLE IF EXISTS product_reviews;
DROP TABLE IF EXISTS wishlist_items;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS email_log;
DROP TABLE IF EXISTS user_tokens;
DROP TABLE IF EXISTS admin_activity_log;
DROP TABLE IF EXISTS inventory_movements;
DROP TABLE IF EXISTS shop_sale_items;
DROP TABLE IF EXISTS shop_sales;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS customer_addresses;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. users : customers and administrators (one table, role column)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    role            ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer' COMMENT 'admin = super administrator (owner), staff = staff administrator',
    full_name       VARCHAR(100)     NOT NULL,
    email           VARCHAR(190)     NULL COMMENT 'Required for customers; optional for staff (they log in with a username)',
    username        VARCHAR(40)      NULL COMMENT 'Staff login name, created by the super administrator',
    phone           VARCHAR(20)      NULL,
    password_hash   VARCHAR(255)     NOT NULL COMMENT 'password_hash() output, never plaintext',
    permissions     VARCHAR(255)     NULL COMMENT 'Staff only: comma-separated areas, e.g. products,categories,inquiries',
    must_change_password TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '1 = must choose a new password at next login',
    created_by      INT UNSIGNED     NULL COMMENT 'Super administrator who created this staff account',
    email_verified_at DATETIME       NULL COMMENT 'When the e-mail link was confirmed; NULL = not verified',
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    last_login_at   DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role_active (role, is_active),
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_users_active CHECK (is_active IN (0,1)),
    CONSTRAINT chk_users_name   CHECK (CHAR_LENGTH(TRIM(full_name)) > 0),
    CONSTRAINT chk_users_login  CHECK (email IS NOT NULL OR username IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. login_attempts : supports login rate limiting (by email and by IP)
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190)     NOT NULL,
    ip_address      VARCHAR(45)      NOT NULL COMMENT 'IPv4 or IPv6',
    succeeded       TINYINT(1)       NOT NULL DEFAULT 0,
    attempted_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_email_time (email, attempted_at),
    KEY idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. customer_addresses : saved delivery addresses (address book)
-- ---------------------------------------------------------------------
CREATE TABLE customer_addresses (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NOT NULL,
    label           VARCHAR(50)      NOT NULL DEFAULT 'Home',
    recipient_name  VARCHAR(100)     NOT NULL,
    phone           VARCHAR(20)      NOT NULL,
    address_line1   VARCHAR(150)     NOT NULL,
    address_line2   VARCHAR(150)     NULL,
    city            VARCHAR(80)      NOT NULL,
    district        VARCHAR(50)      NOT NULL,
    postal_code     VARCHAR(10)      NULL,
    is_default      TINYINT(1)       NOT NULL DEFAULT 0 COMMENT 'One default per customer, enforced in PHP inside a transaction',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_address_user (user_id, is_default),
    CONSTRAINT fk_address_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_address_default CHECK (is_default IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. categories : self-referencing, so subcategories can be added later
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    parent_id       INT UNSIGNED     NULL COMMENT 'NULL = top-level; PHP prevents loops (a category cannot be its own ancestor)',
    name            VARCHAR(100)     NOT NULL,
    slug            VARCHAR(120)     NOT NULL,
    description     VARCHAR(500)     NULL,
    image_file      VARCHAR(60)      NULL COMMENT 'Random file name in public/uploads/categories (NULL = use a product photo)',
    sort_order      SMALLINT         NOT NULL DEFAULT 0,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id, is_active, sort_order),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_categories_active CHECK (is_active IN (0,1)),
    CONSTRAINT chk_categories_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. products
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    category_id         INT UNSIGNED     NOT NULL,
    sku                 VARCHAR(40)      NULL COMMENT 'Stock-keeping unit, optional but unique',
    name                VARCHAR(150)     NOT NULL,
    slug                VARCHAR(170)     NOT NULL,
    short_description   VARCHAR(255)     NULL,
    description         TEXT             NULL,
    price               DECIMAL(10,2)    NOT NULL COMMENT 'Selling price in LKR',
    compare_at_price DECIMAL(10,2)   NULL COMMENT 'Price before the discount, shown crossed out (NULL = not on sale)',
    stock_quantity      INT              NOT NULL DEFAULT 0,
    low_stock_threshold INT              NOT NULL DEFAULT 5,
    is_featured         TINYINT(1)       NOT NULL DEFAULT 0,
    is_active           TINYINT(1)       NOT NULL DEFAULT 1,
    is_customizable     TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = customer types a personalisation message',
    custom_label        VARCHAR(80)      NULL COMMENT 'Question shown above the message box, e.g. Name to print',
    custom_max_length   SMALLINT         NOT NULL DEFAULT 100 COMMENT 'Longest message allowed (10-500 characters)',
    custom_required     TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '1 = the message must be filled in',
    created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id, is_active),
    KEY idx_products_active_featured (is_active, is_featured),
    KEY idx_products_active_created (is_active, created_at),
    KEY idx_products_price (price),
    FULLTEXT KEY ft_products_search (name, short_description, description),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_products_price     CHECK (price >= 0),
    CONSTRAINT chk_products_stock     CHECK (stock_quantity >= 0),
    CONSTRAINT chk_products_threshold CHECK (low_stock_threshold >= 0),
    CONSTRAINT chk_products_featured  CHECK (is_featured IN (0,1)),
    CONSTRAINT chk_products_active    CHECK (is_active IN (0,1)),
    CONSTRAINT chk_products_custom    CHECK (is_customizable IN (0,1) AND custom_required IN (0,1) AND custom_max_length BETWEEN 10 AND 500),
    CONSTRAINT chk_products_slug      CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. product_images : files live in public/uploads/products/,
--    the database stores only the generated file name.
-- ---------------------------------------------------------------------
CREATE TABLE product_images (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED     NOT NULL,
    file_name       VARCHAR(100)     NOT NULL COMMENT 'Random name generated by the server',
    alt_text        VARCHAR(150)     NULL,
    sort_order      SMALLINT         NOT NULL DEFAULT 0,
    is_primary      TINYINT(1)       NOT NULL DEFAULT 0 COMMENT 'One primary per product, enforced in PHP inside a transaction',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_images_file (file_name),
    KEY idx_product_images_product (product_id, sort_order),
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_product_images_primary CHECK (is_primary IN (0,1)),
    CONSTRAINT chk_product_images_name CHECK (file_name REGEXP '^[a-f0-9]{32}\\.(jpg|png|webp)$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. carts / cart_items : persistent cart for logged-in customers.
--    Guests keep their cart in the PHP session; it is merged into this
--    table when they log in. Prices are NOT stored here: totals are
--    always recalculated from products.price.
-- ---------------------------------------------------------------------
CREATE TABLE carts (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NOT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_user (user_id),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    cart_id         INT UNSIGNED     NOT NULL,
    product_id      INT UNSIGNED     NOT NULL,
    quantity        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    customization_text VARCHAR(500)  NULL COMMENT 'Personalisation message typed by the customer',
    added_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_items_product (cart_id, product_id),
    KEY idx_cart_items_product (product_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_cart_items_qty CHECK (quantity BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. orders : one row per order, with a snapshot of the contact and
--    delivery details at the time of ordering.
--    Status workflow (enforced in PHP, see docs/02-architecture.md):
--      pending -> confirmed -> processing -> ready_for_delivery -> delivered
--      pending / confirmed / processing -> cancelled (stock restored)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    order_number        VARCHAR(20)      NOT NULL COMMENT 'Customer-facing reference, e.g. GA261016-7K3M9Q',
    user_id             INT UNSIGNED     NOT NULL,
    status              ENUM('pending','confirmed','processing','ready_for_delivery','delivered','cancelled')
                                         NOT NULL DEFAULT 'pending',
    -- Contact snapshot
    customer_name       VARCHAR(100)     NOT NULL,
    customer_email      VARCHAR(190)     NOT NULL,
    customer_phone      VARCHAR(20)      NOT NULL,
    -- Delivery snapshot
    recipient_name      VARCHAR(100)     NOT NULL,
    recipient_phone     VARCHAR(20)      NOT NULL,
    address_line1       VARCHAR(150)     NOT NULL,
    address_line2       VARCHAR(150)     NULL,
    city                VARCHAR(80)      NOT NULL,
    district            VARCHAR(50)      NOT NULL,
    postal_code         VARCHAR(10)      NULL,
    delivery_notes      VARCHAR(500)     NULL COMMENT 'e.g. gift message or delivery instructions',
    -- Money (all calculated on the server from database prices)
    subtotal            DECIMAL(10,2)    NOT NULL,
    delivery_fee        DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    discount_amount     DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    total_amount        DECIMAL(10,2)    NOT NULL,
    created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Order placed at',
    updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (order_number),
    KEY idx_orders_user_created (user_id, created_at),
    KEY idx_orders_status_created (status, created_at),
    KEY idx_orders_created (created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_orders_subtotal CHECK (subtotal >= 0),
    CONSTRAINT chk_orders_delivery CHECK (delivery_fee >= 0),
    CONSTRAINT chk_orders_discount CHECK (discount_amount >= 0 AND discount_amount <= subtotal),
    CONSTRAINT chk_orders_total    CHECK (total_amount = subtotal + delivery_fee - discount_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. order_items : historical snapshot of each purchased product
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED     NOT NULL,
    product_id      INT UNSIGNED     NULL COMMENT 'Kept for links/reports; snapshot below is authoritative',
    product_name    VARCHAR(150)     NOT NULL,
    product_sku     VARCHAR(40)      NULL,
    unit_price      DECIMAL(10,2)    NOT NULL,
    quantity        SMALLINT UNSIGNED NOT NULL,
    line_total      DECIMAL(10,2)    NOT NULL,
    customization_text VARCHAR(500)  NULL COMMENT 'Personalisation message (snapshot)',
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_items_product (order_id, product_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_order_items_price CHECK (unit_price >= 0),
    CONSTRAINT chk_order_items_qty   CHECK (quantity >= 1),
    CONSTRAINT chk_order_items_line  CHECK (line_total = unit_price * quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. order_status_history : every status change with who and when.
--     This is the timestamp record for the order lifecycle and the
--     "order activity" view for administrators.
-- ---------------------------------------------------------------------
CREATE TABLE order_status_history (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED     NOT NULL,
    from_status     ENUM('pending','confirmed','processing','ready_for_delivery','delivered','cancelled') NULL
                                     COMMENT 'NULL for the first entry (order placed)',
    to_status       ENUM('pending','confirmed','processing','ready_for_delivery','delivered','cancelled') NOT NULL,
    changed_by      INT UNSIGNED     NULL COMMENT 'Admin or customer who made the change',
    note            VARCHAR(500)     NULL COMMENT 'e.g. cancellation reason',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_history_order (order_id, created_at),
    CONSTRAINT fk_status_history_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_status_history_change CHECK (from_status IS NULL OR from_status <> to_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. payments : one payment record per order.
--     No real online gateway is integrated. Methods are Cash on
--     Delivery and manual bank transfer verified by an administrator.
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED     NOT NULL,
    method          ENUM('cod','bank_transfer') NOT NULL,
    status          ENUM('pending','awaiting_verification','paid','failed','refunded')
                                     NOT NULL DEFAULT 'pending',
    amount          DECIMAL(10,2)    NOT NULL COMMENT 'Equals orders.total_amount when created',
    reference       VARCHAR(100)     NULL COMMENT 'Bank transfer reference given by the customer',
    paid_at         DATETIME         NULL,
    refunded_at     DATETIME         NULL,
    recorded_by     INT UNSIGNED     NULL COMMENT 'Admin who last changed the payment status',
    admin_note      VARCHAR(500)     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_order (order_id),
    KEY idx_payments_status (status),
    KEY idx_payments_paid_at (paid_at),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payments_admin FOREIGN KEY (recorded_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_payments_amount   CHECK (amount >= 0),
    CONSTRAINT chk_payments_paid     CHECK (status NOT IN ('paid','refunded') OR paid_at IS NOT NULL),
    CONSTRAINT chk_payments_refunded CHECK (status <> 'refunded' OR refunded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. inventory_movements : stock ledger. Every change to
--     products.stock_quantity is written here in the same transaction.
-- ---------------------------------------------------------------------
CREATE TABLE inventory_movements (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED     NOT NULL,
    quantity_change INT              NOT NULL COMMENT 'Negative = stock out, positive = stock in',
    stock_after     INT              NOT NULL,
    reason          ENUM('initial_stock','restock','order_placed','order_cancelled','manual_adjustment','shop_sale','shop_sale_voided')
                                     NOT NULL,
    order_id        INT UNSIGNED     NULL,
    shop_sale_id    INT UNSIGNED     NULL COMMENT 'In-shop sale (invoice) that caused it; FK added after table 23',
    user_id         INT UNSIGNED     NULL COMMENT 'Admin or customer whose action caused it',
    note            VARCHAR(255)     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_product_time (product_id, created_at),
    KEY idx_inventory_order (order_id),
    KEY idx_inventory_time (created_at),
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_inventory_change CHECK (quantity_change <> 0),
    CONSTRAINT chk_inventory_after  CHECK (stock_after >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. admin_activity_log : audit trail of administrator actions.
--     Never stores passwords or payment secrets.
-- ---------------------------------------------------------------------
CREATE TABLE admin_activity_log (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    admin_id        INT UNSIGNED     NULL,
    action          VARCHAR(50)      NOT NULL COMMENT 'e.g. product.update, order.status, payment.verify',
    entity_type     VARCHAR(50)      NOT NULL COMMENT 'e.g. product, category, order',
    entity_id       INT UNSIGNED     NULL,
    details         VARCHAR(500)     NULL,
    ip_address      VARCHAR(45)      NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_admin_time (admin_id, created_at),
    KEY idx_activity_entity (entity_type, entity_id),
    KEY idx_activity_time (created_at),
    CONSTRAINT fk_activity_admin FOREIGN KEY (admin_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. settings : editable store configuration (contact details,
--     delivery charges, bank details). Edited from the admin panel.
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    setting_key     VARCHAR(60)      NOT NULL,
    setting_value   VARCHAR(1000)    NOT NULL DEFAULT '',
    value_type      ENUM('text','money','integer','boolean','email','multiline') NOT NULL DEFAULT 'text',
    label           VARCHAR(100)     NOT NULL,
    description     VARCHAR(255)     NULL,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. user_tokens : one-time links for e-mail verification and password
--     reset. Only a SHA-256 hash of the secret is stored.
-- ---------------------------------------------------------------------
CREATE TABLE user_tokens (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NOT NULL,
    purpose         ENUM('verify_email','reset_password') NOT NULL,
    token_hash      CHAR(64)         NOT NULL COMMENT 'sha256 of the secret sent by e-mail',
    expires_at      DATETIME         NOT NULL,
    used_at         DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_tokens_hash (token_hash),
    KEY idx_user_tokens_user (user_id, purpose, created_at),
    CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. email_log : every e-mail the system tried to send and the result.
-- ---------------------------------------------------------------------
CREATE TABLE email_log (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NULL,
    order_id        INT UNSIGNED     NULL,
    template        VARCHAR(50)      NOT NULL COMMENT 'e.g. verify_email, order_placed, order_status',
    recipient       VARCHAR(190)     NOT NULL,
    subject         VARCHAR(200)     NOT NULL,
    transport       ENUM('smtp','file','off') NOT NULL,
    status          ENUM('sent','saved','skipped','failed') NOT NULL,
    error           VARCHAR(255)     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_log_time (created_at),
    KEY idx_email_log_status (status, created_at),
    KEY idx_email_log_order (order_id),
    CONSTRAINT fk_email_log_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_email_log_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. banners : homepage slides managed in Admin -> Banners
-- ---------------------------------------------------------------------
CREATE TABLE banners (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    title           VARCHAR(120)     NOT NULL COMMENT 'Heading on the slide; also the image description for screen readers',
    subtitle        VARCHAR(200)     NULL,
    button_text     VARCHAR(40)      NULL,
    link_url        VARCHAR(255)     NULL COMMENT 'Page inside the shop (e.g. products.php?category=flowers) or https:// address',
    image_file      VARCHAR(60)      NOT NULL COMMENT 'Random file name in public/uploads/banners',
    show_text       TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '0 = the picture already contains its own text',
    sort_order      SMALLINT         NOT NULL DEFAULT 0,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    starts_on       DATE             NULL COMMENT 'Shown from this day (NULL = immediately)',
    ends_on         DATE             NULL COMMENT 'Shown until this day (NULL = no end)',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_banners_active (is_active, sort_order),
    CONSTRAINT chk_banners_active CHECK (is_active IN (0,1)),
    CONSTRAINT chk_banners_show_text CHECK (show_text IN (0,1)),
    CONSTRAINT chk_banners_dates CHECK (starts_on IS NULL OR ends_on IS NULL OR ends_on >= starts_on),
    CONSTRAINT chk_banners_file CHECK (image_file REGEXP '^[a-f0-9]{32}\\.(jpg|png|webp)$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 19. wishlist_items : products saved by customers (heart button)
-- ---------------------------------------------------------------------
CREATE TABLE wishlist_items (
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    KEY idx_wishlist_product (product_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 20. product_reviews : star rating and comment by a customer who
--     received the product (a delivered order). One review per customer
--     per product; the customer can edit it, the administrator can hide it.
-- ---------------------------------------------------------------------
CREATE TABLE product_reviews (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED     NOT NULL,
    user_id         INT UNSIGNED     NOT NULL,
    order_id        INT UNSIGNED     NULL COMMENT 'Delivered order that allowed the review',
    rating          TINYINT UNSIGNED NOT NULL COMMENT '1 to 5 stars',
    comment         VARCHAR(1000)    NULL,
    reviewer_name   VARCHAR(60)      NOT NULL COMMENT 'Name shown with the review, e.g. Nimal P.',
    is_visible      TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '0 = hidden by the administrator',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_user_product (user_id, product_id),
    KEY idx_reviews_product (product_id, is_visible, created_at),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT chk_reviews_visible CHECK (is_visible IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 21. customer_inquiries : messages sent from the Contact page
-- ---------------------------------------------------------------------
CREATE TABLE customer_inquiries (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NULL COMMENT 'Customer account if logged in',
    name            VARCHAR(100)     NOT NULL,
    email           VARCHAR(190)     NOT NULL,
    phone           VARCHAR(20)      NULL,
    subject         ENUM('order','product','personalisation','delivery','other') NOT NULL DEFAULT 'other',
    order_number    VARCHAR(20)      NULL,
    message         VARCHAR(2000)    NOT NULL,
    status          ENUM('new','in_progress','resolved') NOT NULL DEFAULT 'new',
    ip_address      VARCHAR(45)      NULL,
    last_reply_at   DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inquiries_status (status, created_at),
    KEY idx_inquiries_user (user_id, created_at),
    KEY idx_inquiries_ip (ip_address, created_at),
    CONSTRAINT fk_inquiries_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 22. inquiry_replies : answers from the store (also e-mailed)
-- ---------------------------------------------------------------------
CREATE TABLE inquiry_replies (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    inquiry_id      INT UNSIGNED     NOT NULL,
    admin_id        INT UNSIGNED     NULL COMMENT 'Super administrator or staff member who replied',
    message         VARCHAR(3000)    NOT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inquiry_replies (inquiry_id, created_at),
    CONSTRAINT fk_inquiry_replies_inquiry FOREIGN KEY (inquiry_id) REFERENCES customer_inquiries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inquiry_replies_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 23. shop_sales : sales made in the physical shop (walk-in customers),
--     each with a printed invoice. Stock is taken from products.
-- ---------------------------------------------------------------------
CREATE TABLE shop_sales (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    invoice_number  VARCHAR(20)      NOT NULL COMMENT 'e.g. INV-261024-0001',
    customer_name   VARCHAR(100)     NULL,
    customer_phone  VARCHAR(20)      NULL,
    subtotal        DECIMAL(10,2)    NOT NULL,
    discount        DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    total           DECIMAL(10,2)    NOT NULL,
    payment_method  ENUM('cash','card','bank_transfer') NOT NULL DEFAULT 'cash',
    amount_received DECIMAL(10,2)    NULL COMMENT 'Cash handed over (cash only)',
    note            VARCHAR(255)     NULL,
    status          ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    void_reason     VARCHAR(255)     NULL,
    voided_at       DATETIME         NULL,
    voided_by       INT UNSIGNED     NULL,
    created_by      INT UNSIGNED     NULL COMMENT 'Administrator or staff member who made the sale',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_sales_invoice (invoice_number),
    KEY idx_shop_sales_time (status, created_at),
    CONSTRAINT fk_shop_sales_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_shop_sales_voider FOREIGN KEY (voided_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_shop_sales_amounts CHECK (subtotal >= 0 AND discount >= 0 AND discount <= subtotal AND total = subtotal - discount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 24. shop_sale_items : invoice lines (snapshot of name and price)
-- ---------------------------------------------------------------------
CREATE TABLE shop_sale_items (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    sale_id         INT UNSIGNED     NOT NULL,
    product_id      INT UNSIGNED     NULL,
    product_name    VARCHAR(150)     NOT NULL,
    sku             VARCHAR(50)      NULL,
    unit_price      DECIMAL(10,2)    NOT NULL,
    quantity        SMALLINT UNSIGNED NOT NULL,
    line_total      DECIMAL(10,2)    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_sale_product (sale_id, product_id),
    CONSTRAINT fk_shop_items_sale FOREIGN KEY (sale_id) REFERENCES shop_sales (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_shop_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_shop_items_qty CHECK (quantity BETWEEN 1 AND 999),
    CONSTRAINT chk_shop_items_total CHECK (line_total = unit_price * quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inventory_movements
    ADD KEY idx_inventory_shop_sale (shop_sale_id),
    ADD CONSTRAINT fk_inventory_shop_sale FOREIGN KEY (shop_sale_id) REFERENCES shop_sales (id) ON DELETE SET NULL ON UPDATE CASCADE;
