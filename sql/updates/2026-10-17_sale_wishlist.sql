-- =====================================================================
-- GiftAura - database update: sale prices and wishlist (17 Oct 2026)
-- ---------------------------------------------------------------------
-- Run ONCE on an EXISTING database, as root, in phpMyAdmin:
-- click giftaura_db -> SQL tab -> paste ONE block -> Go, then the next.
-- New installations do NOT need this file (sql/schema.sql has it).
-- Safe to run twice. No data is deleted.
-- =====================================================================

-- Block 1: the price before the discount (NULL = not on sale).
--          products.price stays the price the customer pays.
ALTER TABLE products ADD COLUMN IF NOT EXISTS compare_at_price DECIMAL(10,2) NULL AFTER price;

-- Block 2: products saved by customers.
CREATE TABLE IF NOT EXISTS wishlist_items (
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    KEY idx_wishlist_product (product_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
