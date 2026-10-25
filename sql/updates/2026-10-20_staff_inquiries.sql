-- =====================================================================
-- GiftAura - database update: staff administrators and customer
--            inquiries (20 Oct 2026)
-- ---------------------------------------------------------------------
-- Run ONCE on an EXISTING database, as root, in phpMyAdmin:
-- click giftaura_db -> SQL tab -> paste ONE block -> Go, then the next.
-- New installations do NOT need this file (sql/schema.sql has it).
-- Safe to run twice. No data is deleted. Afterwards: 22 tables.
-- Run 2026-10-18_customization.sql and 2026-10-18_reviews.sql first.
-- =====================================================================

-- Block 1: staff role, username login, permissions, forced password change.
ALTER TABLE users
    MODIFY role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    MODIFY email VARCHAR(190) NULL,
    ADD COLUMN IF NOT EXISTS username VARCHAR(40) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS permissions VARCHAR(255) NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER permissions,
    ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL AFTER must_change_password;

-- Block 2: usernames must be unique.
ALTER TABLE users ADD UNIQUE KEY IF NOT EXISTS uq_users_username (username);

-- Block 3: messages from the Contact page.
CREATE TABLE IF NOT EXISTS customer_inquiries (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NULL,
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

-- Block 4: replies from the store.
CREATE TABLE IF NOT EXISTS inquiry_replies (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    inquiry_id      INT UNSIGNED     NOT NULL,
    admin_id        INT UNSIGNED     NULL,
    message         VARCHAR(3000)    NOT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inquiry_replies (inquiry_id, created_at),
    CONSTRAINT fk_inquiry_replies_inquiry FOREIGN KEY (inquiry_id) REFERENCES customer_inquiries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inquiry_replies_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
