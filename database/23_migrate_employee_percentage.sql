-- ---------------------------------------------------------------------------
-- 23 MIGRATION - employee percentage and task project cost
--
-- users.percentage    The share (0-100) of a project's total cost an employee
--                     earns. Set when the employee is added or edited.
-- tasks.project_cost  The total project cost the admin entered when assigning
--                     the task. tasks.amount is then project_cost x percentage,
--                     which is what the employee sees and is paid.
--
-- Existing rows keep NULL, and existing tasks keep their amount untouched.
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'percentage'),
    'DO 0',
    'ALTER TABLE users ADD COLUMN percentage DECIMAL(5,2) NULL DEFAULT NULL AFTER photo'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'project_cost'),
    'DO 0',
    'ALTER TABLE tasks ADD COLUMN project_cost DECIMAL(12,2) NULL DEFAULT NULL AFTER priority'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
