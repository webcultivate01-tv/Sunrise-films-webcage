<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A confirmed salary settlement/payout (Monthly Salary spec s7-s11). Every row
 * here is permanent and frozen at the moment it is created: the employee's
 * name/email/phone and the earned/settled/outstanding amounts are all snapshot
 * onto the row itself, so a bill generated from it never changes even if the
 * employee's salary or details change later (spec s9, s18).
 *
 * `idempotency_key` is what stops a duplicate click or a resubmitted form
 * from creating a second settlement for the same confirmation - the unique
 * key on it is the real guarantee, mirroring how `task_salary_credits` uses a
 * unique key on `task_id` to make crediting idempotent.
 */
final class SalarySettlement
{
    public function __construct(
        public readonly int $id,
        public readonly int $employeeId,
        public readonly string $salaryMonth,
        public readonly float $amount,
        public readonly float $monthEarned,
        public readonly float $previousSettled,
        public readonly float $outstandingAfter,
        public readonly string $referenceNo,
        public readonly ?string $idempotencyKey,
        public readonly string $employeeNameSnapshot,
        public readonly string $employeeEmailSnapshot,
        public readonly ?string $employeePhoneSnapshot,
        public readonly ?string $notes,
        public readonly ?int $settledBy,
        public readonly string $settledAt,
        /** Joined in for display. */
        public readonly ?string $settledByName = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:                    (int) $row['id'],
            employeeId:            (int) $row['employee_id'],
            salaryMonth:           (string) $row['salary_month'],
            amount:                (float) $row['amount'],
            monthEarned:           (float) $row['month_earned'],
            previousSettled:       (float) $row['previous_settled'],
            outstandingAfter:      (float) $row['outstanding_after'],
            referenceNo:           (string) ($row['reference_no'] ?? ''),
            idempotencyKey:        isset($row['idempotency_key']) ? (string) $row['idempotency_key'] ?: null : null,
            employeeNameSnapshot:  (string) $row['employee_name_snapshot'],
            employeeEmailSnapshot: (string) $row['employee_email_snapshot'],
            employeePhoneSnapshot: isset($row['employee_phone_snapshot']) ? (string) $row['employee_phone_snapshot'] ?: null : null,
            notes:                 isset($row['notes']) ? (string) $row['notes'] ?: null : null,
            settledBy:             isset($row['settled_by']) ? (int) $row['settled_by'] : null,
            settledAt:             (string) $row['settled_at'],
            settledByName:         isset($row['settled_by_name']) ? (string) $row['settled_by_name'] ?: null : null,
        );
    }

    private const SELECT = 'SELECT s.*, u.name AS settled_by_name
                               FROM salary_settlements s
                               LEFT JOIN users u ON u.id = s.settled_by';

    public static function findById(int $id): ?self
    {
        $row = Database::selectOne(self::SELECT . ' WHERE s.id = ? LIMIT 1', [$id]);

        return $row === null ? null : self::fromRow($row);
    }

    public static function findByIdempotencyKey(string $key): ?self
    {
        if ($key === '') {
            return null;
        }

        $row = Database::selectOne(self::SELECT . ' WHERE s.idempotency_key = ? LIMIT 1', [$key]);

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * One employee's settlements, newest first, narrowed by the same filter
     * shape the global history uses (Monthly Salary spec s13, s16).
     *
     * @param array<string, string> $filters
     * @return list<self>
     */
    public static function forEmployee(int $employeeId, array $filters = []): array
    {
        return self::allForScope([$employeeId], $filters);
    }

    /**
     * The Global History settlement entries (spec s12), scoped to
     * $employeeIds (null = every employee, for an Admin) and narrowed by the
     * search box and filters (spec s16).
     *
     * @param  list<int>|null        $employeeIds
     * @param  array<string, string> $filters
     * @return list<self>
     */
    public static function allForScope(?array $employeeIds, array $filters = []): array
    {
        $sql      = self::SELECT . ' WHERE 1 = 1';
        $bindings = [];

        if ($employeeIds !== null) {
            if ($employeeIds === []) {
                return [];
            }

            $sql .= ' AND s.employee_id IN (' . implode(', ', array_fill(0, count($employeeIds), '?')) . ')';
            array_push($bindings, ...$employeeIds);
        }

        if (($filters['employee_id'] ?? '') !== '') {
            $sql       .= ' AND s.employee_id = ?';
            $bindings[] = (int) $filters['employee_id'];
        }

        if (($filters['month'] ?? '') !== '') {
            $sql       .= ' AND s.salary_month = ?';
            $bindings[] = $filters['month'];
        }

        if (($filters['start_date'] ?? '') !== '') {
            $sql       .= ' AND s.settled_at >= ?';
            $bindings[] = $filters['start_date'] . ' 00:00:00';
        }

        if (($filters['end_date'] ?? '') !== '') {
            $sql       .= ' AND s.settled_at <= ?';
            $bindings[] = $filters['end_date'] . ' 23:59:59';
        }

        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (s.employee_name_snapshot LIKE ? OR s.employee_email_snapshot LIKE ? OR s.reference_no LIKE ?)';
            $like = Database::like($filters['q']);
            array_push($bindings, $like, $like, $like);
        }

        $rows = Database::select($sql . ' ORDER BY s.settled_at DESC, s.id DESC', $bindings);

        return array_map(self::fromRow(...), $rows);
    }

    /**
     * Record a confirmed settlement and hand it a permanent bill reference.
     * The reference is assigned from the row's own id straight after insert
     * (SET-0001, SET-0002, ...) rather than a separate counter table.
     */
    public static function create(
        int $employeeId,
        string $salaryMonth,
        float $amount,
        float $monthEarned,
        float $previousSettled,
        float $outstandingAfter,
        string $idempotencyKey,
        string $employeeName,
        string $employeeEmail,
        ?string $employeePhone,
        ?int $settledBy,
        string $notes,
    ): int {
        Database::statement(
            'INSERT INTO salary_settlements
                (employee_id, salary_month, amount, month_earned, previous_settled, outstanding_after,
                 idempotency_key, employee_name_snapshot, employee_email_snapshot, employee_phone_snapshot,
                 notes, settled_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $employeeId, $salaryMonth, $amount, $monthEarned, $previousSettled, $outstandingAfter,
                $idempotencyKey, $employeeName, mb_strtolower($employeeEmail), $employeePhone,
                $notes !== '' ? $notes : null, $settledBy,
            ],
        );

        $id = Database::lastInsertId();

        Database::statement(
            "UPDATE salary_settlements SET reference_no = CONCAT('SET-', LPAD(?, 4, '0')) WHERE id = ?",
            [$id, $id],
        );

        return $id;
    }

    public static function totalSettledForEmployee(int $employeeId): float
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM salary_settlements WHERE employee_id = ?',
            [$employeeId],
        );

        return (float) ($row['total'] ?? 0);
    }

    public static function totalSettledForEmployeeMonth(int $employeeId, string $month): float
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM salary_settlements WHERE employee_id = ? AND salary_month = ?',
            [$employeeId, $month],
        );

        return (float) ($row['total'] ?? 0);
    }

    /**
     * The total settled across every employee in $employeeIds for one salary
     * month (null = every employee, for an Admin), the paid side of the
     * Monthly Salary list's this-month summary.
     */
    public static function totalSettledForScopeMonth(?array $employeeIds, string $month): float
    {
        if ($employeeIds === []) {
            return 0.0;
        }

        $sql      = 'SELECT COALESCE(SUM(amount), 0) AS total FROM salary_settlements WHERE salary_month = ?';
        $bindings = [$month];

        if ($employeeIds !== null) {
            $sql .= ' AND employee_id IN (' . implode(', ', array_fill(0, count($employeeIds), '?')) . ')';
            array_push($bindings, ...$employeeIds);
        }

        $row = Database::selectOne($sql, $bindings);

        return (float) ($row['total'] ?? 0);
    }

    /**
     * @return array<string, float> 'Y-m' => total settled
     */
    public static function monthlyTotalsForEmployee(int $employeeId): array
    {
        $rows = Database::select(
            'SELECT salary_month, COALESCE(SUM(amount), 0) AS total
               FROM salary_settlements WHERE employee_id = ? GROUP BY salary_month',
            [$employeeId],
        );

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row['salary_month']] = (float) $row['total'];
        }

        return $totals;
    }
}
