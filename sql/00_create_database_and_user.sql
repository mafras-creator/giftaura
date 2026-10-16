-- =====================================================================
-- GiftAura E-Commerce Web System
-- File   : sql/00_create_database_and_user.sql
-- Purpose: Create the giftaura_db database and a least-privilege
--          application user. Run ONCE, as root, before schema.sql.
-- Target : MariaDB 10.4 (XAMPP), server port 3307
-- ---------------------------------------------------------------------
-- HOW TO RUN (phpMyAdmin):
--   1. http://localhost/phpmyadmin  ->  SQL tab  ->  paste this whole file.
--   2. IN THE PHPMYADMIN BOX (not in this file), replace both
--      CHANGE_THIS_PASSWORD values with a strong password of your own
--      (12+ characters, letters, numbers and a symbol).
--   3. Click Go. Later, put the SAME password in config/config.php.
--   This file keeps the placeholder so the real password never reaches Git.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS giftaura_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- One account for each way PHP may reach the server on Windows:
-- 'localhost' (name) and '127.0.0.1' (TCP address used by the app).
CREATE USER IF NOT EXISTS 'giftaura_app'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
CREATE USER IF NOT EXISTS 'giftaura_app'@'127.0.0.1' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';

-- Least privilege: the web application only reads and writes rows.
-- It cannot create, alter or drop tables, and cannot see other databases
-- (hrms_db, shoe, room, website1php, ...).
GRANT SELECT, INSERT, UPDATE, DELETE ON giftaura_db.* TO 'giftaura_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON giftaura_db.* TO 'giftaura_app'@'127.0.0.1';

FLUSH PRIVILEGES;

-- Check (should list the two accounts):
SELECT User, Host FROM mysql.user WHERE User = 'giftaura_app';
