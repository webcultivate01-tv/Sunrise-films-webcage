-- ---------------------------------------------------------------------------
-- 17 MIGRATION - settings.company_website
--
-- For a `settings` table created before the company website was printed on
-- bills. A fresh database already has the column. Safe to run more than once.
-- ---------------------------------------------------------------------------

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'company_website'),
    'DO 0',
    'ALTER TABLE settings ADD COLUMN company_website VARCHAR(190) NOT NULL DEFAULT '''' AFTER company_email'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
