-- Add project_type column to tasks (replaces free-text title with a fixed list)
ALTER TABLE `tasks`
    ADD COLUMN `project_type` VARCHAR(100) NOT NULL DEFAULT '' AFTER `title`;

-- Back-fill existing rows so project_type mirrors whatever title was stored
UPDATE `tasks` SET `project_type` = `title` WHERE `project_type` = '';
