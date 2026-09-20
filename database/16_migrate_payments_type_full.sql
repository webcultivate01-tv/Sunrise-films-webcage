-- ---------------------------------------------------------------------------
-- 16 MIGRATION - payments.payment_type gains the 'full' option
--
-- Added with Work Management's "Advance payment / Full payment" choice. A
-- `payments` table created before that needs the ENUM widened; a fresh one
-- already has it. Safe to run more than once.
-- ---------------------------------------------------------------------------

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_type'
              AND COLUMN_TYPE NOT LIKE '%''full''%'),
    'ALTER TABLE payments MODIFY COLUMN payment_type ENUM(''advance'',''full'',''milestone'',''partial'',''final'',''other'') NOT NULL DEFAULT ''other''',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
