<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * The salary-eligible amount created by completing a task (task spec s7, s17).
 * The `task_id` unique key is the real guarantee against double crediting;
 * `INSERT IGNORE` here just means a repeated "Mark as Completed" click never
 * throws, it simply has no further effect.
 */
final class TaskSalaryCredit
{
    public static function creditForTask(int $taskId, int $employeeId, int $projectId, float $amount): void
    {
        Database::statement(
            'INSERT IGNORE INTO task_salary_credits (task_id, employee_id, project_id, amount)
             VALUES (?, ?, ?, ?)',
            [$taskId, $employeeId, $projectId, $amount],
        );
    }

    public static function existsForTask(int $taskId): bool
    {
        return Database::selectOne(
            'SELECT id FROM task_salary_credits WHERE task_id = ? LIMIT 1',
            [$taskId],
        ) !== null;
    }

    /**
     * The salary-eligible total for one employee (Monthly Salary spec s6).
     */
    public static function totalForEmployee(int $employeeId): float
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM task_salary_credits WHERE employee_id = ?',
            [$employeeId],
        );

        return (float) ($row['total'] ?? 0);
    }

    /**
     * The salary-eligible total for one employee in one calendar month
     * ($month as 'Y-m'), what a settlement is validated and deducted against
     * (Monthly Salary spec s5, s7).
     */
    public static function totalForEmployeeMonth(int $employeeId, string $month): float
    {
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM task_salary_credits
             WHERE employee_id = ? AND DATE_FORMAT(credited_at, '%Y-%m') = ?",
            [$employeeId, $month],
        );

        return (float) ($row['total'] ?? 0);
    }

    /**
     * The salary-eligible total across every employee in $employeeIds for one
     * calendar month (null = every employee, for an Admin), the earned side of
     * the Monthly Salary list's this-month summary.
     */
    public static function totalForScopeMonth(?array $employeeIds, string $month): float
    {
        if ($employeeIds === []) {
            return 0.0;
        }

        $sql      = "SELECT COALESCE(SUM(amount), 0) AS total FROM task_salary_credits
                      WHERE DATE_FORMAT(credited_at, '%Y-%m') = ?";
        $bindings = [$month];

        if ($employeeIds !== null) {
            $sql .= ' AND employee_id IN (' . implode(', ', array_fill(0, count($employeeIds), '?')) . ')';
            array_push($bindings, ...$employeeIds);
        }

        $row = Database::selectOne($sql, $bindings);

        return (float) ($row['total'] ?? 0);
    }

    /**
     * One employee's earnings grouped by calendar month, for the Monthly
     * Salary breakdown (spec s5). Previous months are never overwritten here
     * - this only ever reads the permanent credit rows.
     *
     * @return array<string, float> 'Y-m' => total
     */
    public static function monthlyTotalsForEmployee(int $employeeId): array
    {
        $rows = Database::select(
            "SELECT DATE_FORMAT(credited_at, '%Y-%m') AS month, COALESCE(SUM(amount), 0) AS total
               FROM task_salary_credits WHERE employee_id = ? GROUP BY month",
            [$employeeId],
        );

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row['month']] = (float) $row['total'];
        }

        return $totals;
    }

    /**
     * The individual completed-task credits behind one employee's one-month
     * earnings, for the "select a month to view its detailed salary entries"
     * drill-down (Monthly Salary spec s5).
     *
     * @return list<array<string, mixed>>
     */
    public static function forEmployeeMonth(int $employeeId, string $month): array
    {
        return Database::select(
            "SELECT tsc.*, t.title AS task_title, p.customer_name AS customer_name
               FROM task_salary_credits tsc
               JOIN tasks t ON t.id = tsc.task_id
               LEFT JOIN projects p ON p.id = tsc.project_id
              WHERE tsc.employee_id = ? AND DATE_FORMAT(tsc.credited_at, '%Y-%m') = ?
              ORDER BY tsc.credited_at DESC",
            [$employeeId, $month],
        );
    }

    /**
     * "Salary Earned" entries for the Monthly Salary history (spec s12, s13),
     * scoped to $employeeIds (null = every employee, for an Admin) and
     * narrowed by the same filters the settlement history uses.
     *
     * @param  list<int>|null        $employeeIds
     * @param  array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public static function creditsForScope(?array $employeeIds, array $filters = []): array
    {
        $sql = "SELECT tsc.*, t.title AS task_title, p.customer_name AS customer_name,
                        e.name AS employee_name, e.email AS employee_email
                   FROM task_salary_credits tsc
                   JOIN tasks t ON t.id = tsc.task_id
                   LEFT JOIN projects p ON p.id = tsc.project_id
                   JOIN users e ON e.id = tsc.employee_id
                  WHERE 1 = 1";
        $bindings = [];

        if ($employeeIds !== null) {
            if ($employeeIds === []) {
                return [];
            }

            $sql .= ' AND tsc.employee_id IN (' . implode(', ', array_fill(0, count($employeeIds), '?')) . ')';
            array_push($bindings, ...$employeeIds);
        }

        if (($filters['employee_id'] ?? '') !== '') {
            $sql       .= ' AND tsc.employee_id = ?';
            $bindings[] = (int) $filters['employee_id'];
        }

        if (($filters['month'] ?? '') !== '') {
            $sql       .= " AND DATE_FORMAT(tsc.credited_at, '%Y-%m') = ?";
            $bindings[] = $filters['month'];
        }

        if (($filters['start_date'] ?? '') !== '') {
            $sql       .= ' AND tsc.credited_at >= ?';
            $bindings[] = $filters['start_date'] . ' 00:00:00';
        }

        if (($filters['end_date'] ?? '') !== '') {
            $sql       .= ' AND tsc.credited_at <= ?';
            $bindings[] = $filters['end_date'] . ' 23:59:59';
        }

        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (e.name LIKE ? OR e.email LIKE ? OR t.title LIKE ?)';
            $like = Database::like($filters['q']);
            array_push($bindings, $like, $like, $like);
        }

        return Database::select($sql . ' ORDER BY tsc.credited_at DESC', $bindings);
    }
}
