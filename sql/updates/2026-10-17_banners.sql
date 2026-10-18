-- =====================================================================
-- GiftAura - database update: homepage banners and category pictures
--            (17 Oct 2026, design refresh)
-- ---------------------------------------------------------------------
-- WHO RUNS IT: run ONCE on an EXISTING GiftAura database, as root,
-- in phpMyAdmin: click giftaura_db -> SQL tab -> paste this file -> Go.
-- Run sql/updates/2026-10-17_email.sql first if you have not yet.
--
-- New installations do NOT need this file: sql/schema.sql already
-- contains these changes. Safe to run twice. No data is deleted.
-- =====================================================================

SET NAMES utf8mb4;

-- 1. Optional picture for each category (round icons on the homepage).
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS image_file VARCHAR(60) NULL
        COMMENT 'Random file name in public/uploads/categories (NULL = use a product photo)'
        AFTER description;

-- 2. Homepage banners (slides), managed in Admin -> Banners.
CREATE TABLE IF NOT EXISTS banners (
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

-- Check: expected 18 tables.
SELECT COUNT(*) AS tables_in_giftaura_db
  FROM information_schema.tables WHERE table_schema = DATABASE();
