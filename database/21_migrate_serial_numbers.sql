-- ---------------------------------------------------------------------------
-- 21 MIGRATION - serial numbers (1, 2, 3 ...) for list rows
--
-- Adds a `serial_no` column to photographers, projects, users and tasks. The
-- number shown in the "#" column of each management list is this stored value,
-- so what you see on screen is what is in the database. The app gives every new
-- row the next number and renumbers the table after a delete, so there are
-- never gaps.
--
-- users: only managers and employees are numbered (the Admin stays NULL), so
-- Employee Management counts 1, 2, 3 ... without the Admin taking number 1.
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

CALL sf_add_serial_no('photographers', '1 = 1');
CALL sf_add_serial_no('projects',      '1 = 1');
CALL sf_add_serial_no('tasks',         '1 = 1');
CALL sf_add_serial_no('users',         "role <> 'admin'");

DROP PROCEDURE sf_add_serial_no;
