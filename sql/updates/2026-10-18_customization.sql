-- =====================================================================
-- GiftAura - database update: product personalisation (18 Oct 2026)
-- ---------------------------------------------------------------------
-- Run ONCE on an EXISTING database, as root, in phpMyAdmin:
-- click giftaura_db -> SQL tab -> paste ONE block -> Go, then the next.
-- New installations do NOT need this file (sql/schema.sql has it).
-- Safe to run twice. No data is deleted. The number of tables stays 19.
-- =====================================================================

-- Block 1: which products can be personalised, and how.
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS is_customizable TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN IF NOT EXISTS custom_label VARCHAR(80) NULL AFTER is_customizable,
    ADD COLUMN IF NOT EXISTS custom_max_length SMALLINT NOT NULL DEFAULT 100 AFTER custom_label,
    ADD COLUMN IF NOT EXISTS custom_required TINYINT(1) NOT NULL DEFAULT 1 AFTER custom_max_length;

-- Block 2: the message typed by the customer, kept with the cart line.
ALTER TABLE cart_items
    ADD COLUMN IF NOT EXISTS customization_text VARCHAR(500) NULL AFTER quantity;

-- Block 3: the message saved with the order (a snapshot, like the price).
ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS customization_text VARCHAR(500) NULL AFTER line_total;
