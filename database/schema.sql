-- ---------------------------------------------------------------------------
-- Sunrise Films - database schema
-- Run with:  mysql -u root -p < database/schema.sql
--
-- This file only creates the database, then loads one file per table from
-- database/schema/. Open a single file in that folder to read or change one
-- table instead of scrolling through every table at once.
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS sunrise_films
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE sunrise_films;

-- Order matters: a table that has a FOREIGN KEY must load after the table
-- it points to (e.g. customers points to users, so users loads first).
SOURCE database/schema/01_users.sql;
SOURCE database/schema/02_customers.sql;
SOURCE database/schema/03_projects.sql;
SOURCE database/schema/04_auth_tokens.sql;
SOURCE database/schema/05_password_resets.sql;
SOURCE database/schema/06_login_attempts.sql;
SOURCE database/schema/07_tasks.sql;
SOURCE database/schema/08_task_salary_credits.sql;
SOURCE database/schema/10_settings.sql;
SOURCE database/schema/11_salary_settlements.sql;
SOURCE database/schema/12_payments.sql;
