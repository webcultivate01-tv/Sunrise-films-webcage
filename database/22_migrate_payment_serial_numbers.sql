-- ---------------------------------------------------------------------------
-- 22 MIGRATION - serial numbers (1, 2, 3 ...) for payment transactions
--
-- Adds a `serial_no` column to payments: the "No" shown on each transaction in
-- Payment History. Payments are permanent records that are never deleted, so
-- the numbers never need closing up.
--
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sf_add_serial_no;
DELIMITER $$
CREATE PROCEDURE sf_add_serial_no(IN tbl VARCHAR(64), IN scope VARCHAR(100))
BEGIN
    DECLARE col_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO col_exists
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = 'serial_no';

    IF col_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN serial_no INT UNSIGNED NULL AFTER id');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;

    -- (Re)number every row oldest-first: 1, 2, 3 ...
    SET @n = 0;
    SET @sql = CONCAT('UPDATE `', tbl, '` SET serial_no = (@n := @n + 1) WHERE ', scope, ' ORDER BY id');
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
END$$
DELIMITER ;

CALL sf_add_serial_no('payments', '1 = 1');

DROP PROCEDURE sf_add_serial_no;
