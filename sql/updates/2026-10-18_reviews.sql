-- =====================================================================
-- GiftAura - database update: product reviews (18 Oct 2026)
-- ---------------------------------------------------------------------
-- Run ONCE on an EXISTING database, as root, in phpMyAdmin:
-- click giftaura_db -> SQL tab -> paste the block below -> Go.
-- New installations do NOT need this file (sql/schema.sql has it).
-- Safe to run twice. Afterwards the database has 20 tables.
-- Run sql/updates/2026-10-18_customization.sql first if you have not yet.
-- =====================================================================

CREATE TABLE IF NOT EXISTS product_reviews (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED     NOT NULL,
    user_id         INT UNSIGNED     NOT NULL,
    order_id        INT UNSIGNED     NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    comment         VARCHAR(1000)    NULL,
    reviewer_name   VARCHAR(60)      NOT NULL,
    is_visible      TINYINT(1)       NOT NULL DEFAULT 1,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_user_product (user_id, product_id),
    KEY idx_reviews_product (product_id, is_visible, created_at),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
