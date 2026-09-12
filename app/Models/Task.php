<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A piece of work assigned to one employee, on one project (Task Management).
 *
 * Lifecycle: Assigned -> Accepted -> In Progress -> Completed
 *                                              \-> Exited -> Reassigned
 *
 * An exited task is never mutated back to life: reassigning it creates a new
 * row (see parentTaskId) with its own Assigned -> ... lifecycle, so the
 * original assignment's history is never overwritten (task spec s9, s16).
 */
final class Task
{
    public const STATUS_ASSIGNED    = 'assigned';
    public const STATUS_ACCEPTED    = 'accepted';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_EXITED      = 'exited';
    public const STATUS_REASSIGNED  = 'reassigned';

    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW    = 'low';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_ASSIGNED, self::STATUS_ACCEPTED, self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED, self::STATUS_EXITED, self::STATUS_REASSIGNED,
        ];
    }

    /** @return list<string> */
    public static function priorities(): array
    {
        return [self::PRIORITY_HIGH, self::PRIORITY_MEDIUM, self::PRIORITY_LOW];
    }

    /** @return list<int> the only percentages progress may ever hold */
    public static function progressSteps(): array
    {
        return [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
    }

    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly int $employeeId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $priority,
        public readonly float $amount,
        public readonly string $status,
        public readonly int $progress,
        public readonly ?int $parentTaskId,
        public readonly ?string $acceptedAt,
        public readonly ?string $completedAt,
        public readonly ?string $exitedAt,
        public readonly ?int $createdBy,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        /** Joined in for listings and detail pages. */
        public readonly ?string $projectName = null,
        public readonly ?string $customerName = null,
        public readonly ?string $employeeName = null,
        public readonly ?string $createdByName = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:            (int) $row['id'],
            projectId:     (int) $row['project_id'],
            employeeId:    (int) $row['employee_id'],
            title:         (string) $row['title'],
            description:   (string) $row['description'],
            startDate:     (string) $row['start_date'],
            endDate:       (string) $row['end_date'],
            priority:      (string) $row['priority'],
            amount:        (float) $row['amount'],
            status:        (string) $row['status'],
            progress:      (int) $row['progress'],
            parentTaskId:  isset($row['parent_task_id']) ? (int) $row['parent_task_id'] : null,
            acceptedAt:    isset($row['accepted_at']) ? (string) $row['accepted_at'] : null,
            completedAt:   isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            exitedAt:      isset($row['exited_at']) ? (string) $row['exited_at'] : null,
            createdBy:     isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt:     isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt:     isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            projectName:   isset($row['project_name']) ? (string) $row['project_name'] : null,
            customerName:  isset($row['customer_name']) ? (string) $row['customer_name'] : null,
            employeeName:  isset($row['employee_name']) ? (string) $row['employee_name'] : null,
            createdByName: isset($row['created_by_name']) ? (string) $row['created_by_name'] : null,
        );
    }

    private const SELECT = 'SELECT t.*, p.name AS project_name, c.name AS customer_name,
                                    e.name AS employee_name, u.name AS created_by_name
                               FROM tasks t
                               LEFT JOIN projects p ON p.id = t.project_id
                               LEFT JOIN customers c ON c.id = p.customer_id
                               LEFT JOIN users e ON e.id = t.employee_id
                               LEFT JOIN users u ON u.id = t.created_by';

    public static function findById(int $id): ?self
    {
        $row = Database::selectOne(self::SELECT . ' WHERE t.id = ? LIMIT 1', [$id]);

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * The Admin/Manager task list, narrowed by the search box and the filters
     * from task spec s12. $employeeIds scopes the list to a Manager's own
     * employees; null means no scope (Admin sees everything).
     *
     * @param  array<string, string> $filters
     * @param  list<int>|null        $employeeIds
     * @return list<self>
     */
    public static function all(array $filters, ?array $employeeIds = null): array
    {
        $sql      = self::SELECT . ' WHERE 1 = 1';
        $bindings = [];

        if ($employeeIds !== null) {
            if ($employeeIds === []) {
                return [];
            }

            $sql .= ' AND t.employee_id IN (' . implode(', ', array_fill(0, count($employeeIds), '?')) . ')';
            array_push($bindings, ...$employeeIds);
        }

        $search = $filters['q'] ?? '';

        if ($search !== '') {
            $sql .= ' AND (t.title LIKE ? OR p.name LIKE ? OR e.name LIKE ?)';
            $like = Database::like($search);
            array_push($bindings, $like, $like, $like);
        }

        if (($filters['project_id'] ?? '') !== '') {
            $sql       .= ' AND t.project_id = ?';
            $bindings[] = (int) $filters['project_id'];
        }

        if (($filters['employee_id'] ?? '') !== '') {
            $sql       .= ' AND t.employee_id = ?';
            $bindings[] = (int) $filters['employee_id'];
        }

        if (in_array($filters['priority'] ?? '', self::priorities(), true)) {
            $sql       .= ' AND t.priority = ?';
            $bindings[] = $filters['priority'];
        }

        if (in_array($filters['status'] ?? '', self::statuses(), true)) {
            $sql       .= ' AND t.status = ?';
            $bindings[] = $filters['status'];
        }

        if (($filters['start_date'] ?? '') !== '') {
            $sql       .= ' AND t.start_date >= ?';
            $bindings[] = $filters['start_date'];
        }

        if (($filters['end_date'] ?? '') !== '') {
            $sql       .= ' AND t.end_date <= ?';
            $bindings[] = $filters['end_date'];
        }

        $rows = Database::select($sql . ' ORDER BY t.id DESC', $bindings);

        return array_map(self::fromRow(...), $rows);
    }

    /**
     * My Work: one employee's own tasks, narrowed by their own search box and
     * filters (task spec s13).
     *
     * @param  array<string, string> $filters
     * @return list<self>
     */
    public static function allForEmployee(int $employeeId, array $filters): array
    {
        $sql      = self::SELECT . ' WHERE t.employee_id = ?';
        $bindings = [$employeeId];

        $search = $filters['q'] ?? '';

        if ($search !== '') {
            $sql .= ' AND (t.title LIKE ? OR p.name LIKE ?)';
            $like = Database::like($search);
            array_push($bindings, $like, $like);
        }

        if (($filters['project_id'] ?? '') !== '') {
            $sql       .= ' AND t.project_id = ?';
            $bindings[] = (int) $filters['project_id'];
        }

        if (in_array($filters['priority'] ?? '', self::priorities(), true)) {
            $sql       .= ' AND t.priority = ?';
            $bindings[] = $filters['priority'];
        }

        if (in_array($filters['status'] ?? '', self::statuses(), true)) {
            $sql       .= ' AND t.status = ?';
            $bindings[] = $filters['status'];
        }

        if (($filters['view'] ?? '') === 'pending') {
            $sql       .= ' AND t.status = ?';
            $bindings[] = self::STATUS_ASSIGNED;
        } elseif (($filters['view'] ?? '') === 'active') {
            $sql .= ' AND t.status IN (?, ?, ?)';
            array_push($bindings, self::STATUS_ASSIGNED, self::STATUS_ACCEPTED, self::STATUS_IN_PROGRESS);
        } elseif (($filters['view'] ?? '') === 'completed') {
            $sql       .= ' AND t.status = ?';
            $bindings[] = self::STATUS_COMPLETED;
        } elseif (($filters['view'] ?? '') === 'exited') {
            $sql       .= ' AND t.status = ?';
            $bindings[] = self::STATUS_EXITED;
        }

        $rows = Database::select($sql . ' ORDER BY t.id DESC', $bindings);

        return array_map(self::fromRow(...), $rows);
    }

    public static function create(
        int $projectId,
        int $employeeId,
        string $title,
        string $description,
        string $startDate,
        string $endDate,
        string $priority,
        float $amount,
        ?int $createdBy,
        ?int $parentTaskId = null,
    ): int {
        Database::statement(
            'INSERT INTO tasks
                (project_id, employee_id, title, description, start_date, end_date, priority, amount, created_by, parent_task_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $employeeId, $title, $description, $startDate, $endDate, $priority, $amount, $createdBy, $parentTaskId],
        );

        return Database::lastInsertId();
    }

    public static function accept(int $id): void
    {
        Database::statement(
            "UPDATE tasks SET status = ?, accepted_at = NOW() WHERE id = ?",
            [self::STATUS_ACCEPTED, $id],
        );
    }

    public static function updateProgress(int $id, int $progress, string $status): void
    {
        Database::statement(
            'UPDATE tasks SET progress = ?, status = ? WHERE id = ?',
            [$progress, $status, $id],
        );
    }

    public static function markCompleted(int $id): void
    {
        Database::statement(
            "UPDATE tasks SET status = ?, progress = 100, completed_at = NOW() WHERE id = ?",
            [self::STATUS_COMPLETED, $id],
        );
    }

    public static function markExited(int $id): void
    {
        Database::statement(
            "UPDATE tasks SET status = ?, exited_at = NOW() WHERE id = ?",
            [self::STATUS_EXITED, $id],
        );
    }

    public static function markReassigned(int $id): void
    {
        Database::statement('UPDATE tasks SET status = ? WHERE id = ?', [self::STATUS_REASSIGNED, $id]);
    }

    /**
     * @return array<string, int> status => count for one project, excluding
     * 'reassigned' rows - each is superseded by the task it was reassigned
     * into, so counting it too would double-count the same piece of work.
     * This is the live signal Work Management's automatic project status
     * (pending / in progress / completed) is recomputed from.
     */
    public static function statusCountsForProject(int $projectId): array
    {
        $counts = array_fill_keys(self::statuses(), 0);

        $sql = 'SELECT status, COUNT(*) AS total FROM tasks WHERE project_id = ? AND status != ? GROUP BY status';

        foreach (Database::select($sql, [$projectId, self::STATUS_REASSIGNED]) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return array<string, int> status => count, scoped the same way all() is.
     */
    public static function statusCounts(?array $employeeIds = null): array
    {
        $counts   = array_fill_keys(self::statuses(), 0);
        $sql      = 'SELECT status, COUNT(*) AS total FROM tasks WHERE 1 = 1';
        $bindings = [];

        if ($employeeIds !== null) {
            if ($employeeIds === []) {
                return $counts;
            }

            $sql .= ' AND employee_id IN (' . implode(', ', array_fill(0, count($employeeIds), '?')) . ')';
            array_push($bindings, ...$employeeIds);
        }

        foreach (Database::select($sql . ' GROUP BY status', $bindings) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function isOverdue(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_EXITED, self::STATUS_REASSIGNED], true)
            && strtotime($this->endDate) < strtotime('today');
    }
}
