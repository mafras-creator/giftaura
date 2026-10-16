-- =====================================================================
-- GiftAura E-Commerce Web System
-- File   : sql/seed.sql
-- Purpose: SAMPLE data for development and testing.
-- Run    : after schema.sql (phpMyAdmin -> giftaura_db -> Import).
-- ---------------------------------------------------------------------
-- IMPORTANT
--   * Every product, price and stock figure below is SAMPLE DATA for
--     testing only. Names start with "Sample" and SKUs with "SAMPLE-".
--     Replace them with GiftAura's real catalogue from the admin panel.
--   * Store contact details and bank details are left EMPTY on purpose.
--     Fill them in from Admin -> Settings. Nothing here is a real
--     GiftAura address, phone number or bank account.
--   * No user accounts are created here. The first administrator is
--     created with scripts/create_admin.php (see README.md), so there
--     is never a default admin account with a known password.
--   * Products have no images yet; the site shows a placeholder until
--     images are uploaded from the admin panel.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Store settings (editable later in Admin -> Settings)
-- ---------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value, value_type, label, description) VALUES
('store_name',               'GiftAura', 'text',      'Store name',                    'Shown in the header, emails and page titles'),
('store_tagline',            'Thoughtful gifts for every occasion', 'text', 'Tagline', 'Short line under the logo on the homepage (editable)'),
('store_email',              '',         'email',     'Contact email',                 'Leave empty until the real address is confirmed'),
('store_phone',              '',         'text',      'Contact phone',                 'Leave empty until the real number is confirmed'),
('store_whatsapp',           '',         'text',      'WhatsApp number',               'Optional'),
('store_address',            '',         'multiline', 'Store address',                 'Physical shop address shown on the Contact page'),
('store_hours',              '',         'multiline', 'Opening hours',                 'e.g. Mon-Sat 9.00 am - 7.00 pm'),
('delivery_fee',             '400.00',   'money',     'Standard delivery fee (LKR)',   'SAMPLE VALUE - set GiftAura''s real delivery charge'),
('free_delivery_threshold',  '0.00',     'money',     'Free delivery from (LKR)',      'Orders with a subtotal at or above this amount get free delivery. 0 = never free'),
('max_quantity_per_item',    '10',       'integer',   'Maximum quantity per product',  'Upper limit a customer can add of one product in one order'),
('default_low_stock_threshold','5',      'integer',   'Default low-stock level',       'Used for new products; each product can override it'),
('cod_enabled',              '1',        'boolean',   'Cash on Delivery enabled',      '1 = customers can choose Cash on Delivery'),
('bank_transfer_enabled',    '1',        'boolean',   'Bank transfer enabled',         '1 = customers can choose manual bank transfer'),
('bank_name',                '',         'text',      'Bank name',                     'Shown to customers who choose bank transfer'),
('bank_branch',              '',         'text',      'Bank branch',                   ''),
('bank_account_name',        '',         'text',      'Account name',                  ''),
('bank_account_number',      '',         'text',      'Account number',                'Enter the real account number in Admin -> Settings only');

-- ---------------------------------------------------------------------
-- Categories (from the approved proposal) + one SAMPLE subcategory
-- to demonstrate the parent/child structure.
-- ---------------------------------------------------------------------
INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active) VALUES
(NULL, 'Flower Bouquets', 'flower-bouquets', 'Fresh and preserved flower arrangements for every occasion.', 1, 1),
(NULL, 'Beauty Creams',   'beauty-creams',   'Skin-care and beauty creams.',                                 2, 1),
(NULL, 'Gift Items',      'gift-items',      'Gift boxes, hampers, keepsakes and accessories.',              3, 1);

INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active)
SELECT id, 'Rose Bouquets', 'rose-bouquets', 'SAMPLE subcategory of Flower Bouquets.', 1, 1
FROM categories WHERE slug = 'flower-bouquets';

-- ---------------------------------------------------------------------
-- SAMPLE products. Prices and stock are invented test values.
-- Stock levels deliberately include a low-stock item and an
-- out-of-stock item so alerts and "Out of stock" labels can be tested.
-- ---------------------------------------------------------------------
INSERT INTO products (category_id, sku, name, slug, short_description, description, price, stock_quantity, low_stock_threshold, is_featured, is_active)
SELECT c.id, p.sku, p.name, p.slug, p.short_description, p.description, p.price, p.stock_quantity, p.low_stock_threshold, p.is_featured, p.is_active
FROM (
    SELECT 'rose-bouquets' AS cat, 'SAMPLE-FB-001' AS sku, 'Sample Red Rose Bouquet' AS name, 'sample-red-rose-bouquet' AS slug,
           'A classic bouquet of red roses (sample product).' AS short_description,
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.' AS description,
           4500.00 AS price, 12 AS stock_quantity, 5 AS low_stock_threshold, 1 AS is_featured, 1 AS is_active
    UNION ALL SELECT 'rose-bouquets', 'SAMPLE-FB-002', 'Sample Pink Rose Bunch', 'sample-pink-rose-bunch',
           'Soft pink roses wrapped in paper (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           3800.00, 3, 5, 0, 1
    UNION ALL SELECT 'flower-bouquets', 'SAMPLE-FB-003', 'Sample Mixed Flower Basket', 'sample-mixed-flower-basket',
           'Seasonal mixed flowers in a basket (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           6200.00, 8, 3, 1, 1
    UNION ALL SELECT 'flower-bouquets', 'SAMPLE-FB-004', 'Sample Sunflower Bouquet', 'sample-sunflower-bouquet',
           'Bright sunflowers with greenery (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           5000.00, 0, 3, 0, 1
    UNION ALL SELECT 'beauty-creams', 'SAMPLE-BC-001', 'Sample Moisturising Day Cream', 'sample-moisturising-day-cream',
           'Daily moisturising cream, 50 ml (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           2200.00, 25, 5, 1, 1
    UNION ALL SELECT 'beauty-creams', 'SAMPLE-BC-002', 'Sample Night Repair Cream', 'sample-night-repair-cream',
           'Overnight cream, 50 ml (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           2900.00, 18, 5, 0, 1
    UNION ALL SELECT 'beauty-creams', 'SAMPLE-BC-003', 'Sample Hand Cream Trio', 'sample-hand-cream-trio',
           'Three travel-size hand creams (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           1800.00, 4, 5, 0, 1
    UNION ALL SELECT 'gift-items', 'SAMPLE-GI-001', 'Sample Chocolate Gift Box', 'sample-chocolate-gift-box',
           'Assorted chocolates in a gift box (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           3500.00, 20, 5, 1, 1
    UNION ALL SELECT 'gift-items', 'SAMPLE-GI-002', 'Sample Teddy Bear', 'sample-teddy-bear',
           'Soft plush teddy bear, medium size (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           2500.00, 15, 4, 0, 1
    UNION ALL SELECT 'gift-items', 'SAMPLE-GI-003', 'Sample Scented Candle Set', 'sample-scented-candle-set',
           'Set of three scented candles (sample product).',
           'SAMPLE PRODUCT FOR TESTING. Replace with a real GiftAura product, price and stock level.',
           2750.00, 10, 3, 0, 1
    UNION ALL SELECT 'gift-items', 'SAMPLE-GI-004', 'Sample Inactive Gift Card Holder', 'sample-inactive-gift-card-holder',
           'Inactive sample - must NOT appear in the shop (used to test deactivation).',
           'SAMPLE PRODUCT FOR TESTING. This product is inactive on purpose.',
           900.00, 30, 5, 0, 0
) AS p
JOIN categories c ON c.slug = p.cat;

-- ---------------------------------------------------------------------
-- Opening stock ledger: one "initial_stock" movement per product that
-- has stock, so inventory_movements matches products.stock_quantity.
-- ---------------------------------------------------------------------
INSERT INTO inventory_movements (product_id, quantity_change, stock_after, reason, note)
SELECT id, stock_quantity, stock_quantity, 'initial_stock', 'Opening stock from seed.sql (sample data)'
FROM products
WHERE stock_quantity > 0;

-- ---------------------------------------------------------------------
-- Quick check: expected 4 categories, 11 products (10 active),
-- 10 inventory movements, 17 settings.
-- ---------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM categories)                      AS categories,
    (SELECT COUNT(*) FROM products)                        AS products,
    (SELECT COUNT(*) FROM products WHERE is_active = 1)    AS active_products,
    (SELECT COUNT(*) FROM inventory_movements)             AS inventory_movements,
    (SELECT COUNT(*) FROM settings)                        AS settings;
