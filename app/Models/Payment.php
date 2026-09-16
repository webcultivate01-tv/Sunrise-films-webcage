<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * One payment received against a project (Payment Management). Every row is
 * a permanent transaction - amounts are never edited or overwritten, so a
 * project's collected/outstanding figures are always recomputed from these
 * rows rather than stored anywhere.
 */
final class Payment
{
    public const TYPE_ADVANCE   = 'advance';
    public const TYPE_FULL      = 'full';
    public const TYPE_MILESTONE = 'milestone';
    public const TYPE_PARTIAL   = 'partial';
    public const TYPE_FINAL     = 'final';
    public const TYPE_OTHER     = 'other';

    public const METHOD_CASH          = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_UPI           = 'upi';
    public const METHOD_CHEQUE        = 'cheque';
    public const METHOD_CARD          = 'card';
    public const METHOD_OTHER         = 'other';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::TYPE_ADVANCE, self::TYPE_FULL, self::TYPE_MILESTONE,
            self::TYPE_PARTIAL, self::TYPE_FINAL, self::TYPE_OTHER,
        ];
    }

    /**
     * The two types an Admin/Manager can pick when money arrives with the
     * project itself (Work Management): either the photographer has paid an
     * advance against the total, or they have settled the whole thing up
     * front. Everything else in types() is a Payment Management concern.
     *
     * @return list<string>
     */
    public static function upfrontTypes(): array
    {
        return [self::TYPE_ADVANCE, self::TYPE_FULL];
    }

    /** @return list<string> */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH, self::METHOD_BANK_TRANSFER, self::METHOD_UPI,
            self::METHOD_CHEQUE, self::METHOD_CARD, self::METHOD_OTHER,
        ];
    }

    /**
     * The methods offered for money collected with the project itself - the
     * two ways cash actually changes hands at the counter.
     *
     * @return list<string>
     */
    public static function upfrontMethods(): array
    {
        return [self::METHOD_CASH, self::METHOD_UPI];
    }

    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly float $amount,
        public readonly string $paymentType,
        public readonly string $paymentMethod,
        public readonly ?string $referenceNo,
        public readonly string $paymentDate,
        public readonly ?string $notes,
        public readonly ?int $receivedBy,
        public readonly ?string $createdAt,
        /** Joined in from `projects` / `photographers` / `users` for display. */
        public readonly ?string $customerName = null,
        public readonly ?float $projectTotalPayment = null,
        public readonly ?int $photographerId = null,
        public readonly ?string $photographerName = null,
        public readonly ?string $receivedByName = null,
    ) {
    }

    private const SELECT_WITH_JOINS = 'SELECT pay.*, proj.customer_name AS customer_name, proj.total_payment AS project_total_payment,
               proj.photographer_id AS photographer_id, c.name AS photographer_name, u.name AS received_by_name
          FROM payments pay
          LEFT JOIN projects proj ON proj.id = pay.project_id
          LEFT JOIN photographers c ON c.id = proj.photographer_id
          LEFT JOIN users u ON u.id = pay.received_by';

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:                  (int) $row['id'],
            projectId:           (int) $row['project_id'],
            amount:              (float) $row['amount'],
            paymentType:         (string) $row['payment_type'],
            paymentMethod:       (string) $row['payment_method'],
            referenceNo:         isset($row['reference_no']) && $row['reference_no'] !== null ? (string) $row['reference_no'] : null,
            paymentDate:         (string) $row['payment_date'],
            notes:               isset($row['notes']) && $row['notes'] !== null ? (string) $row['notes'] : null,
            receivedBy:          isset($row['received_by']) && $row['received_by'] !== null ? (int) $row['received_by'] : null,
            createdAt:           isset($row['created_at']) ? (string) $row['created_at'] : null,
            customerName:         isset($row['customer_name']) ? (string) $row['customer_name'] : null,
            projectTotalPayment: isset($row['project_total_payment']) ? (float) $row['project_total_payment'] : null,
            photographerId:          isset($row['photographer_id']) ? (int) $row['photographer_id'] : null,
            photographerName:        isset($row['photographer_name']) ? (string) $row['photographer_name'] : null,
            receivedByName:      isset($row['received_by_name']) ? (string) $row['received_by_name'] : null,
        );
    }

    public static function findById(int $id): ?self
    {
        $row = Database::selectOne(self::SELECT_WITH_JOINS . ' WHERE pay.id = ? LIMIT 1', [$id]);

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * The Payment History (module spec s2, s5): every transaction, narrowed
     * by the search box and the available filters, sorted as requested.
     *
     * @param array<string, string> $filters q, project_id, photographer_id, type, method, start_date, end_date, sort
     * @return list<self>
     */
    public static function all(array $filters = []): array
    {
        $sql      = self::SELECT_WITH_JOINS . ' WHERE 1 = 1';
        $bindings = [];

        $search = trim($filters['q'] ?? '');

        if ($search !== '') {
            $sql .= ' AND (proj.customer_name LIKE ? OR c.name LIKE ? OR pay.reference_no LIKE ?)';
            $like = Database::like($search);
            array_push($bindings, $like, $like, $like);
        }

        $projectId = trim($filters['project_id'] ?? '');

        if ($projectId !== '') {
            $sql .= ' AND pay.project_id = ?';
            $bindings[] = (int) $projectId;
        }

        $photographerId = trim($filters['photographer_id'] ?? '');

        if ($photographerId !== '') {
            $sql .= ' AND proj.photographer_id = ?';
            $bindings[] = (int) $photographerId;
        }

        $type = trim($filters['type'] ?? '');

        if ($type !== '' && in_array($type, self::types(), true)) {
            $sql .= ' AND pay.payment_type = ?';
            $bindings[] = $type;
        }

        $method = trim($filters['method'] ?? '');

        if ($method !== '' && in_array($method, self::methods(), true)) {
            $sql .= ' AND pay.payment_method = ?';
            $bindings[] = $method;
        }

        $startDate = trim($filters['start_date'] ?? '');

        if ($startDate !== '') {
            $sql .= ' AND pay.payment_date >= ?';
            $bindings[] = $startDate;
        }

        $endDate = trim($filters['end_date'] ?? '');

        if ($endDate !== '') {
            $sql .= ' AND pay.payment_date <= ?';
            $bindings[] = $endDate;
        }

        $sql .= match ($filters['sort'] ?? '') {
            'oldest'      => ' ORDER BY pay.payment_date ASC, pay.id ASC',
            'amount_high' => ' ORDER BY pay.amount DESC, pay.id DESC',
            'amount_low'  => ' ORDER BY pay.amount ASC, pay.id ASC',
            default       => ' ORDER BY pay.payment_date DESC, pay.id DESC',
        };

        return array_map(self::fromRow(...), Database::select($sql, $bindings));
    }

    /**
     * One project's payment timeline (module spec s3), oldest first.
     *
     * @return list<self>
     */
    public static function timelineForProject(int $projectId): array
    {
        $rows = Database::select(
            self::SELECT_WITH_JOINS . ' WHERE pay.project_id = ? ORDER BY pay.payment_date ASC, pay.id ASC',
            [$projectId],
        );

        return array_map(self::fromRow(...), $rows);
    }

    public static function create(
        int $projectId,
        float $amount,
        string $paymentType,
        string $paymentMethod,
        string $paymentDate,
        ?string $referenceNo,
        ?string $notes,
        ?int $receivedBy,
    ): int {
        Database::statement(
            'INSERT INTO payments
                (project_id, amount, payment_type, payment_method, reference_no, payment_date, notes, received_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $amount, $paymentType, $paymentMethod, $referenceNo, $paymentDate, $notes, $receivedBy],
        );

        return Database::lastInsertId();
    }

    public static function totalForProject(int $projectId): float
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE project_id = ?',
            [$projectId],
        );

        return (float) ($row['total'] ?? 0.0);
    }

    public static function countForProject(int $projectId): int
    {
        $row = Database::selectOne('SELECT COUNT(*) AS total FROM payments WHERE project_id = ?', [$projectId]);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * What has been collected against each of $projectIds, in one query - a
     * report listing hundreds of projects must not fire totalForProject()
     * once per row.
     *
     * @param  list<int>        $projectIds
     * @return array<int,float> project id => amount collected (0.0 when none)
     */
    public static function totalsForProjects(array $projectIds): array
    {
        $projectIds = array_values(array_unique($projectIds));

        if ($projectIds === []) {
            return [];
        }

        $rows = Database::select(
            'SELECT project_id, COALESCE(SUM(amount), 0) AS total
               FROM payments
              WHERE project_id IN (' . implode(', ', array_fill(0, count($projectIds), '?')) . ')
              GROUP BY project_id',
            $projectIds,
        );

        $totals = array_fill_keys($projectIds, 0.0);

        foreach ($rows as $row) {
            $totals[(int) $row['project_id']] = (float) $row['total'];
        }

        return $totals;
    }

    public static function lastPaymentDateForProject(int $projectId): ?string
    {
        $row = Database::selectOne(
            'SELECT MAX(payment_date) AS last_date FROM payments WHERE project_id = ?',
            [$projectId],
        );

        return $row !== null && $row['last_date'] !== null ? (string) $row['last_date'] : null;
    }

    /**
     * Whether every payment recorded for a project is still just an advance -
     * i.e. nothing beyond the advance has come in yet (module spec s1's
     * "Advance Received" status).
     */
    public static function isOnlyAdvance(int $projectId): bool
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total, SUM(payment_type = 'advance') AS advance_total
               FROM payments WHERE project_id = ?",
            [$projectId],
        );

        $total   = (int) ($row['total'] ?? 0);
        $advance = (int) ($row['advance_total'] ?? 0);

        return $total > 0 && $total === $advance;
    }

    /**
     * Whether $projectId has any payment on record, so a project with
     * payment history cannot be deleted out from under it.
     */
    public static function existsForProject(int $projectId): bool
    {
        return Database::selectOne('SELECT id FROM payments WHERE project_id = ? LIMIT 1', [$projectId]) !== null;
    }

    /**
     * The total ever collected for one payment type, e.g. every advance ever
     * received - used by the Payment Dashboard KPIs.
     */
    public static function totalByType(string $type): float
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_type = ?',
            [$type],
        );

        return (float) ($row['total'] ?? 0.0);
    }

    /**
     * Total collected per calendar month for the last $months months
     * (including the current one), oldest first - the series behind the
     * main panel's revenue trend chart. A month with no payments still comes
     * back as 0.0 rather than being left out, so the chart's x-axis never
     * skips a month.
     *
     * @return array<string, float> 'Y-m' => total collected
     */
    public static function monthlyTotals(int $months = 6): array
    {
        $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));

        $rows = Database::select(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
               FROM payments
              WHERE payment_date >= ?
              GROUP BY ym",
            [$start],
        );

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row['ym']] = (float) $row['total'];
        }

        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $key           = date('Y-m', strtotime("-{$i} months"));
            $series[$key]  = $totals[$key] ?? 0.0;
        }

        return $series;
    }
}
