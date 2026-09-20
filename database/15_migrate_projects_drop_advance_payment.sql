-- ---------------------------------------------------------------------------
-- 15 MIGRATION - drop projects.advance_payment
--
-- Advance payments now live in Payment Management (the `payments` table), so
-- the old column on `projects` is removed. A fresh database never had it.
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'advance_payment'),
    'ALTER TABLE projects DROP COLUMN advance_payment',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
