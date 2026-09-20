-- ---------------------------------------------------------------------------
-- 18 MIGRATION - give every existing task its opening description thread
--
-- A task's opening description is row one of its own thread in
-- `task_descriptions`. Tasks created before that table existed have no row,
-- so one is added from `tasks.description`. Tasks that already have a thread
-- are left alone, so this is safe to run more than once (and does nothing on
-- a fresh database with no tasks).
-- ---------------------------------------------------------------------------

INSERT INTO task_descriptions (task_id, body, created_by, created_at)
SELECT t.id, t.description, t.created_by, t.created_at
FROM tasks t
WHERE NOT EXISTS (SELECT 1 FROM task_descriptions d WHERE d.task_id = t.id)
ORDER BY t.id ASC;
