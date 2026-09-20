-- ---------------------------------------------------------------------------
-- 14 MIGRATION - users.address and users.photo
--
-- For a database whose `users` table was created before addresses and profile
-- photos existed. A fresh database already has both, so this does nothing.
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'address'),
    'DO 0',
    'ALTER TABLE users ADD COLUMN address VARCHAR(500) NULL DEFAULT NULL AFTER phone'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'photo'),
    'DO 0',
    'ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL AFTER address'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
