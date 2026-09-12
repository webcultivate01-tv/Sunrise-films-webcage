<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A piece of work for a customer (Work Management). Every project belongs to
 * exactly one customer and is registered by an Admin or a Manager.
 */
final class Project
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED];
    }

    public function __construct(
        public readonly int $id,
        public readonly int $customerId,
        public readonly string $name,
        public readonly string $description,
        public readonly string $folderName,
        public readonly string $deadline,
        public readonly float $totalPayment,
        public readonly string $status,
        public readonly ?int $createdBy,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        /** Joined in from `customers` for the list and detail pages. */
        public readonly ?string $customerName = null,
        /** Name of the user who registered this project, when joined in. */
        public readonly ?string $createdByName = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:             (int) $row['id'],
            customerId:     (int) $row['customer_id'],
            name:           (string) $row['name'],
            description:    (string) $row['description'],
            folderName:     (string) $row['folder_name'],
            deadline:       (string) $row['deadline'],
            totalPayment:   (float) $row['total_payment'],
            status:         (string) $row['status'],
            createdBy:      isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt:      isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt:      isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            customerName:   isset($row['customer_name']) ? (string) $row['customer_name'] : null,
            createdByName:  isset($row['created_by_name']) ? (string) $row['created_by_name'] : null,
        );
    }

    public static function findById(int $id): ?self
    {
        $row = Database::selectOne(
            'SELECT p.*, c.name AS customer_name, u.name AS created_by_name
               FROM projects p
               LEFT JOIN customers c ON c.id = p.customer_id
               LEFT JOIN users u ON u.id = p.created_by
              WHERE p.id = ?
              LIMIT 1',
            [$id],
        );

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * The project list, narrowed by the search box, the status filter and a
     * specific customer, sorted as requested. Every Admin and Manager sees
     * every project, so there is no ownership filter here.
     *
     * Deadline (soonest first) is the default sort - it is what lets an
     * Admin/Manager see at a glance which projects need attention next.
     *
     * $startDate/$endDate narrow to projects registered in that range
     * (Reports: Work Report) - blank means no bound either side.
     *
     * @param string $sort '' (deadline soonest, default) | deadline_desc | newest | oldest
     * @return list<self>
     */
    public static function all(string $search = '', string $status = '', ?int $customerId = null, string $sort = '', string $startDate = '', string $endDate = ''): array
    {
        $sql      = 'SELECT p.*, c.name AS customer_name, u.name AS created_by_name
                       FROM projects p
                       LEFT JOIN customers c ON c.id = p.customer_id
                       LEFT JOIN users u ON u.id = p.created_by
                      WHERE 1 = 1';
        $bindings = [];

        if ($search !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.folder_name LIKE ? OR c.name LIKE ?)';
            $like = Database::like($search);
            array_push($bindings, $like, $like, $like);
        }

        if ($status !== '') {
            $sql .= ' AND p.status = ?';
            $bindings[] = $status;
        }

        if ($customerId !== null) {
            $sql .= ' AND p.customer_id = ?';
            $bindings[] = $customerId;
        }

        if ($startDate !== '') {
            $sql .= ' AND p.created_at >= ?';
            $bindings[] = $startDate . ' 00:00:00';
        }

        if ($endDate !== '') {
            $sql .= ' AND p.created_at <= ?';
            $bindings[] = $endDate . ' 23:59:59';
        }

        $sql .= match ($sort) {
            'deadline_desc' => ' ORDER BY p.deadline DESC, p.id DESC',
            'newest'        => ' ORDER BY p.created_at DESC, p.id DESC',
            'oldest'        => ' ORDER BY p.created_at ASC, p.id ASC',
            default         => ' ORDER BY p.deadline ASC, p.id ASC',
        };

        $rows = Database::select($sql, $bindings);

        return array_map(self::fromRow(...), $rows);
    }

    public static function create(
        int $customerId,
        string $name,
        string $description,
        string $folderName,
        string $deadline,
        float $totalPayment,
        ?int $createdBy,
        string $status = self::STATUS_PENDING,
    ): int {
        Database::statement(
            'INSERT INTO projects
                (customer_id, name, description, folder_name, deadline, total_payment, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$customerId, $name, $description, $folderName, $deadline, $totalPayment, $status, $createdBy],
        );

        return Database::lastInsertId();
    }

    public static function update(
        int $id,
        int $customerId,
        string $name,
        string $description,
        string $folderName,
        string $deadline,
        float $totalPayment,
    ): void {
        Database::statement(
            'UPDATE projects
                SET customer_id = ?, name = ?, description = ?, folder_name = ?,
                    deadline = ?, total_payment = ?
              WHERE id = ?',
            [$customerId, $name, $description, $folderName, $deadline, $totalPayment, $id],
        );
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::statement('UPDATE projects SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function delete(int $id): void
    {
        Database::statement('DELETE FROM projects WHERE id = ?', [$id]);
    }

    /**
     * Whether $customerId has any project on record, so a customer with
     * project history cannot be deleted out from under it.
     */
    public static function existsForCustomer(int $customerId): bool
    {
        return Database::selectOne(
            'SELECT id FROM projects WHERE customer_id = ? LIMIT 1',
            [$customerId],
        ) !== null;
    }

    /**
     * @return array<string, int> status => count
     */
    public static function statusCounts(): array
    {
        $counts = array_fill_keys(self::statuses(), 0);

        foreach (Database::select('SELECT status, COUNT(*) AS total FROM projects GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function isOverdue(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)
            && strtotime($this->deadline) < strtotime('today');
    }
}
