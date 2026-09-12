<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A customer of the business (module spec s3, s4). Unlike a User a customer
 * never signs in, so there is no role, password or token: the record exists so
 * that work orders, bills and payments have somebody to point at.
 */
final class Customer
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $address,
        public readonly string $status,
        public readonly ?int $createdBy,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        /** Name of the user who registered this customer, when joined in. */
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
            name:          (string) $row['name'],
            email:         (string) $row['email'],
            phone:         (string) $row['phone'],
            address:       (string) $row['address'],
            status:        (string) $row['status'],
            createdBy:     isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt:     isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt:     isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            createdByName: isset($row['created_by_name']) ? (string) $row['created_by_name'] : null,
        );
    }

    public static function findById(int $id): ?self
    {
        $row = Database::selectOne(
            'SELECT c.*, u.name AS created_by_name
               FROM customers c
               LEFT JOIN users u ON u.id = c.created_by
              WHERE c.id = ?
              LIMIT 1',
            [$id],
        );

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * Module spec s14: the email address is what makes a customer unique, so a
     * second record for the same address is refused. $exceptId lets an edit
     * keep its own address.
     */
    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql      = 'SELECT id FROM customers WHERE email = ?';
        $bindings = [mb_strtolower($email)];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $exceptId;
        }

        return Database::selectOne($sql . ' LIMIT 1', $bindings) !== null;
    }

    /**
     * The customer list, optionally narrowed by the search box (module spec
     * s5). Every Admin and Manager sees every customer (module spec s13), so
     * there is no ownership filter here.
     *
     * $startDate/$endDate narrow to customers registered in that range
     * (Reports: Customer Report) - blank means no bound either side.
     *
     * @return list<self>
     */
    public static function all(string $search = '', string $status = '', string $startDate = '', string $endDate = ''): array
    {
        $sql      = 'SELECT c.*, u.name AS created_by_name
                       FROM customers c
                       LEFT JOIN users u ON u.id = c.created_by
                      WHERE 1 = 1';
        $bindings = [];

        if ($search !== '') {
            $sql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.address LIKE ?)';
            $like = Database::like($search);
            array_push($bindings, $like, $like, $like, $like);
        }

        if ($status !== '') {
            $sql .= ' AND c.status = ?';
            $bindings[] = $status;
        }

        if ($startDate !== '') {
            $sql .= ' AND c.created_at >= ?';
            $bindings[] = $startDate . ' 00:00:00';
        }

        if ($endDate !== '') {
            $sql .= ' AND c.created_at <= ?';
            $bindings[] = $endDate . ' 23:59:59';
        }

        $rows = Database::select($sql . ' ORDER BY c.id DESC', $bindings);

        return array_map(self::fromRow(...), $rows);
    }

    public static function create(
        string $name,
        string $email,
        string $phone,
        string $address,
        ?int $createdBy,
        string $status = self::STATUS_ACTIVE,
    ): int {
        Database::statement(
            'INSERT INTO customers (name, email, phone, address, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$name, mb_strtolower($email), $phone, $address, $status, $createdBy],
        );

        return Database::lastInsertId();
    }

    public static function update(int $id, string $name, string $email, string $phone, string $address): void
    {
        Database::statement(
            'UPDATE customers SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?',
            [$name, mb_strtolower($email), $phone, $address, $id],
        );
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::statement('UPDATE customers SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function delete(int $id): void
    {
        Database::statement('DELETE FROM customers WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, int> status => count
     */
    public static function statusCounts(): array
    {
        $counts = [self::STATUS_ACTIVE => 0, self::STATUS_INACTIVE => 0];

        foreach (Database::select('SELECT status, COUNT(*) AS total FROM customers GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function initials(): string
    {
        $parts    = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($this->email, 0, 1));
    }
}
