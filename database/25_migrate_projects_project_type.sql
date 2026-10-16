-- ---------------------------------------------------------------------------
-- 25 MIGRATION - project type on projects
--
-- projects.project_type  What kind of work this is (Reels - Regular, Cine
--                        Films (7 minutes), ...). Chosen from a fixed list when
--                        the Admin/Manager registers the work, and used as the
--                        task title when the project is assigned to an employee.
--
-- Existing projects keep NULL. Safe to run more than once.
-- ---------------------------------------------------------------------------

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'project_type'),
    'DO 0',
    'ALTER TABLE projects ADD COLUMN project_type VARCHAR(80) NULL DEFAULT NULL AFTER customer_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
