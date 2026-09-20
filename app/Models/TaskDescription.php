<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * One round of instructions on a task.
 *
 * A photographer rarely sends the whole brief at once - corrections and extra
 * notes keep arriving for work that is already assigned. So a task's
 * description is a thread rather than a single field: an Admin or Manager
 * appends a new round instead of overwriting the last one, and the employee
 * reads every round in the order it arrived.
 *
 * Row one of every thread is the task's opening description, copied in when
 * the task is created, so the whole brief reads as one list.
 */
final class TaskDescription
{
    public function __construct(
        public readonly int $id,
        public readonly int $taskId,
        public readonly string $body,
        public readonly ?int $createdBy,
        public readonly ?string $createdAt,
        /** Joined in from `users` for display. */
        public readonly ?string $createdByName = null,
        public readonly ?string $createdByRole = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:            (int) $row['id'],
            taskId:        (int) $row['task_id'],
            body:          (string) $row['body'],
            createdBy:     isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt:     isset($row['created_at']) ? (string) $row['created_at'] : null,
            createdByName: isset($row['created_by_name']) ? (string) $row['created_by_name'] : null,
            createdByRole: isset($row['created_by_role']) ? (string) $row['created_by_role'] : null,
        );
    }

    private const SELECT = 'SELECT d.*, u.name AS created_by_name, u.role AS created_by_role
                              FROM task_descriptions d
                              LEFT JOIN users u ON u.id = d.created_by';

    /**
     * The whole thread for one task, oldest first - the order it was sent in
     * is the order it has to be read in.
     *
     * @return list<self>
     */
    public static function forTask(int $taskId): array
    {
        $rows = Database::select(self::SELECT . ' WHERE d.task_id = ? ORDER BY d.id ASC', [$taskId]);

        return array_map(self::fromRow(...), $rows);
    }

    public static function create(int $taskId, string $body, ?int $createdBy): int
    {
        Database::statement(
            'INSERT INTO task_descriptions (task_id, body, created_by) VALUES (?, ?, ?)',
            [$taskId, $body, $createdBy],
        );

        return Database::lastInsertId();
    }

    /**
     * Carry a thread over to a task that supersedes it (reassignment). The
     * new employee inherits the full brief rather than only its opening
     * round; the original rows stay put on the task they were written on.
     */
    public static function copyThread(int $fromTaskId, int $toTaskId): void
    {
        Database::statement(
            'INSERT INTO task_descriptions (task_id, body, created_by, created_at)
             SELECT ?, body, created_by, created_at
               FROM task_descriptions
              WHERE task_id = ?
              ORDER BY id ASC',
            [$toTaskId, $fromTaskId],
        );
    }

    /**
     * How many rounds each of $taskIds has, for the "3 updates" badge on the
     * task lists. Tasks with no thread of their own are simply absent.
     *
     * @param  list<int> $taskIds
     * @return array<int, int> task id => number of rounds
     */
    public static function countsForTasks(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        $sql = 'SELECT task_id, COUNT(*) AS total
                  FROM task_descriptions
                 WHERE task_id IN (' . implode(', ', array_fill(0, count($taskIds), '?')) . ')
                 GROUP BY task_id';

        $counts = [];

        foreach (Database::select($sql, $taskIds) as $row) {
            $counts[(int) $row['task_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * When the most recent round landed, or null for a task nobody has added
     * to. Used to tell an employee that the brief moved after they accepted.
     */
    public static function latestAtForTask(int $taskId): ?string
    {
        $row = Database::selectOne(
            'SELECT created_at FROM task_descriptions WHERE task_id = ? ORDER BY id DESC LIMIT 1',
            [$taskId],
        );

        return $row === null ? null : (string) $row['created_at'];
    }
}
