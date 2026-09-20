-- ---------------------------------------------------------------------------
-- 01 MIGRATION - Customer Management -> Photographer Management
--
-- Only matters for a database created before photographers were called
-- "customers". Nothing is dropped and no value is retyped:
--
--   customers             -> photographers
--   projects.customer_id  -> projects.photographer_id
--   projects.name         -> projects.customer_name
--
-- On a fresh database none of these exist, so every step is a no-op. It runs
-- first so the CREATE TABLE files after it see the renamed tables.
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

-- customers -> photographers (only when photographers does not exist yet)
SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers')
    AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photographers'),
    'RENAME TABLE customers TO photographers',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- projects.customer_id -> photographer_id: drop the old foreign key first.
SET @fk = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'customer_id' AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);
SET @sql = IF(@fk IS NULL, 'DO 0', CONCAT('ALTER TABLE projects DROP FOREIGN KEY `', @fk, '`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ...rename the column...
SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'customer_id')
    AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'photographer_id'),
    'ALTER TABLE projects CHANGE COLUMN customer_id photographer_id INT UNSIGNED NOT NULL',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ...and point the foreign key at photographers.
SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'photographer_id')
    AND EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photographers')
    AND NOT EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects'
          AND COLUMN_NAME = 'photographer_id' AND REFERENCED_TABLE_NAME IS NOT NULL
    ),
    'ALTER TABLE projects ADD CONSTRAINT fk_projects_photographer FOREIGN KEY (photographer_id) REFERENCES photographers (id) ON DELETE RESTRICT',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- projects.name -> customer_name (existing project names become customer names)
SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'name')
    AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'customer_name'),
    'ALTER TABLE projects CHANGE COLUMN name customer_name VARCHAR(150) NOT NULL, ADD INDEX idx_projects_customer_name (customer_name)',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
