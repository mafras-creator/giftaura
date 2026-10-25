-- =====================================================================
-- GiftAura - database update: in-shop sales and invoices (24 Oct 2026)
-- ---------------------------------------------------------------------
-- Run ONCE on an EXISTING database, as root, in phpMyAdmin:
-- click giftaura_db -> SQL tab -> paste ONE block -> Go, then the next.
-- New installations do NOT need this file (sql/schema.sql has it).
-- Safe to run twice. No data is deleted. Afterwards: 24 tables.
-- Run 2026-10-20_staff_inquiries.sql first.
-- =====================================================================

-- Block 1: invoices for sales made in the shop.
CREATE TABLE IF NOT EXISTS shop_sales (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    invoice_number  VARCHAR(20)      NOT NULL,
    customer_name   VARCHAR(100)     NULL,
    customer_phone  VARCHAR(20)      NULL,
    subtotal        DECIMAL(10,2)    NOT NULL,
    discount        DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    total           DECIMAL(10,2)    NOT NULL,
    payment_method  ENUM('cash','card','bank_transfer') NOT NULL DEFAULT 'cash',
    amount_received DECIMAL(10,2)    NULL,
    note            VARCHAR(255)     NULL,
    status          ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    void_reason     VARCHAR(255)     NULL,
    voided_at       DATETIME         NULL,
    voided_by       INT UNSIGNED     NULL,
    created_by      INT UNSIGNED     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_sales_invoice (invoice_number),
    KEY idx_shop_sales_time (status, created_at),
    CONSTRAINT fk_shop_sales_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_shop_sales_voider FOREIGN KEY (voided_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Block 2: the lines of each invoice.
CREATE TABLE IF NOT EXISTS shop_sale_items (
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
    CONSTRAINT fk_shop_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Block 3: stock movements can now come from an in-shop sale.
ALTER TABLE inventory_movements
    MODIFY reason ENUM('initial_stock','restock','order_placed','order_cancelled','manual_adjustment','shop_sale','shop_sale_voided') NOT NULL,
    ADD COLUMN IF NOT EXISTS shop_sale_id INT UNSIGNED NULL AFTER order_id,
    ADD KEY IF NOT EXISTS idx_inventory_shop_sale (shop_sale_id);
