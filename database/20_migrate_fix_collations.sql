-- ---------------------------------------------------------------------------
-- 20 MIGRATION - unify every table on utf8mb4_unicode_ci
--
-- Fixes "SQLSTATE[HY000]: 1267 Illegal mix of collations
-- (utf8mb4_unicode_ci) and (utf8mb4_general_ci) for operation '='" on hosts
-- (e.g. Hostinger) whose database default is utf8mb4_general_ci, which left
-- some tables/columns on a different collation than the rest and broke the
-- salary / monthly-salary joins.
--
-- Converts the database default and every application table that is not
-- already utf8mb4_unicode_ci (columns included). Safe to run more than once.
-- ---------------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

-- Database default, so future tables/columns inherit the right collation.
SET @sql = CONCAT(
    'ALTER DATABASE `', DATABASE(), '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One block per table; skipped when the table is missing or already correct
-- (both at table level and on every column).
DROP PROCEDURE IF EXISTS sf_fix_collation;
DELIMITER $$
CREATE PROCEDURE sf_fix_collation(IN tbl VARCHAR(64))
BEGIN
    DECLARE needs_fix INT DEFAULT 0;

    SELECT COUNT(*) INTO needs_fix
      FROM information_schema.TABLES t
     WHERE t.TABLE_SCHEMA = DATABASE()
       AND t.TABLE_NAME = tbl
       AND (
            t.TABLE_COLLATION <> 'utf8mb4_unicode_ci'
            OR EXISTS (
                SELECT 1 FROM information_schema.COLUMNS c
                 WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA
                   AND c.TABLE_NAME = t.TABLE_NAME
                   AND c.COLLATION_NAME IS NOT NULL
                   AND c.COLLATION_NAME <> 'utf8mb4_unicode_ci'
            )
       );

    IF needs_fix > 0 THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', tbl, '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL sf_fix_collation('users');
CALL sf_fix_collation('photographers');
CALL sf_fix_collation('projects');
CALL sf_fix_collation('auth_tokens');
CALL sf_fix_collation('password_resets');
CALL sf_fix_collation('login_attempts');
CALL sf_fix_collation('tasks');
CALL sf_fix_collation('task_salary_credits');
CALL sf_fix_collation('task_descriptions');
CALL sf_fix_collation('settings');
CALL sf_fix_collation('salary_settlements');
CALL sf_fix_collation('payments');

DROP PROCEDURE IF EXISTS sf_fix_collation;

SET FOREIGN_KEY_CHECKS = 1;
