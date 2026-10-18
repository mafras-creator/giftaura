-- =====================================================================
-- GiftAura - database update: e-mail verification, password reset
--            and e-mail log  (17 Oct 2026)
-- ---------------------------------------------------------------------
-- WHO RUNS IT: run ONCE on an EXISTING GiftAura database, as root,
-- in phpMyAdmin: click giftaura_db -> SQL tab -> paste this file -> Go.
-- (The application user giftaura_app cannot change table structure,
--  on purpose. Its existing rights automatically cover the new tables.)
--
-- New installations do NOT need this file: sql/schema.sql already
-- contains these changes.
--
-- Safe to run twice: columns and tables are only created if missing.
-- Accounts that exist before the update are marked as verified, so
-- current customers are not asked to verify. Take a backup first
-- (scripts\backup.bat).
-- =====================================================================

SET NAMES utf8mb4;

-- 1. When the customer confirmed their e-mail address (NULL = not yet)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER password_hash;

-- 2. One-time links for e-mail verification and password reset.
--    Only a SHA-256 hash of the secret is stored, never the secret itself.
CREATE TABLE IF NOT EXISTS user_tokens (
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

-- 3. Every e-mail the system tried to send, and whether it worked.
CREATE TABLE IF NOT EXISTS email_log (
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

-- 4. Existing accounts count as verified, so current customers are not
--    asked to verify. Every account created by the new code receives a
--    verification link (a row in user_tokens), so those are never touched
--    here - running this file again later is harmless.
UPDATE users
   SET email_verified_at = created_at
 WHERE email_verified_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM user_tokens t WHERE t.user_id = users.id);

-- Check: expected result 17 tables, and the new column listed.
SELECT COUNT(*) AS tables_in_giftaura_db
  FROM information_schema.tables WHERE table_schema = DATABASE();
SELECT COUNT(*) AS users_marked_verified FROM users WHERE email_verified_at IS NOT NULL;
