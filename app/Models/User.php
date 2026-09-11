<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

/**
 * A system user. Roles and statuses are fixed by the schema enums.
 */
final class User
{
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_MANAGER  = 'manager';
    public const ROLE_EMPLOYEE = 'employee';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_INACTIVE  = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $photo,
        public readonly string $passwordHash,
        public readonly string $role,
        public readonly string $status,
        public readonly ?int $createdBy,
        public readonly ?string $lastLoginAt,
        public readonly ?string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) $row['id'],
            name:         (string) $row['name'],
            email:        (string) $row['email'],
            phone:        isset($row['phone']) ? (string) $row['phone'] ?: null : null,
            photo:        isset($row['photo']) ? (string) $row['photo'] ?: null : null,
            passwordHash: (string) $row['password_hash'],
            role:         (string) $row['role'],
            status:       (string) $row['status'],
            createdBy:    isset($row['created_by']) ? (int) $row['created_by'] : null,
            lastLoginAt:  isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            createdAt:    isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    public static function findById(int $id): ?self
    {
        $row = Database::selectOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);

        return $row === null ? null : self::fromRow($row);
    }

    public static function findByEmail(string $email): ?self
    {
        $row = Database::selectOne('SELECT * FROM users WHERE email = ? LIMIT 1', [mb_strtolower($email)]);

        return $row === null ? null : self::fromRow($row);
    }

    public static function findByEmailAndRole(string $email, string $role): ?self
    {
        $row = Database::selectOne(
            'SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1',
            [mb_strtolower($email), $role],
        );

        return $row === null ? null : self::fromRow($row);
    }

    public static function emailExists(string $email): bool
    {
        return Database::selectOne('SELECT id FROM users WHERE email = ? LIMIT 1', [mb_strtolower($email)]) !== null;
    }

    /**
     * Every account is created by somebody: Admin creates Managers, Managers
     * create Employees (spec s12). $createdBy is null only for the seeded Admin.
     */
    public static function create(
        string $name,
        string $email,
        string $phone,
        string $passwordHash,
        string $role,
        ?int $createdBy,
        string $status = self::STATUS_ACTIVE,
    ): int {
        Database::statement(
            'INSERT INTO users (name, email, phone, password_hash, role, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$name, mb_strtolower($email), $phone !== '' ? $phone : null, $passwordHash, $role, $status, $createdBy],
        );

        return Database::lastInsertId();
    }

    /**
     * Accounts a given user is responsible for, e.g. the Managers an Admin
     * created, or the Employees assigned to a Manager.
     *
     * @return list<self>
     */
    public static function managedBy(int $ownerId, string $role): array
    {
        $rows = Database::select(
            'SELECT * FROM users WHERE role = ? AND created_by = ? ORDER BY name ASC',
            [$role, $ownerId],
        );

        return array_map(self::fromRow(...), $rows);
    }

    /**
     * @return list<self>
     */
    public static function allOfRole(string $role): array
    {
        $rows = Database::select('SELECT * FROM users WHERE role = ? ORDER BY name ASC', [$role]);

        return array_map(self::fromRow(...), $rows);
    }

    /**
     * @return array<string, int> status => count
     */
    public static function statusCounts(string $role, ?int $ownerId = null): array
    {
        $sql      = 'SELECT status, COUNT(*) AS total FROM users WHERE role = ?';
        $bindings = [$role];

        if ($ownerId !== null) {
            $sql .= ' AND created_by = ?';
            $bindings[] = $ownerId;
        }

        $counts = [self::STATUS_ACTIVE => 0, self::STATUS_INACTIVE => 0, self::STATUS_SUSPENDED => 0];

        foreach (Database::select($sql . ' GROUP BY status', $bindings) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        Database::statement('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    /**
     * A user editing their own name, email and phone (spec-adjacent: profile
     * self-service, not account creation/management).
     */
    public static function updateDetails(int $id, string $name, string $email, string $phone): void
    {
        Database::statement(
            'UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?',
            [$name, mb_strtolower($email), $phone !== '' ? $phone : null, $id],
        );
    }

    public static function updatePhoto(int $id, ?string $photo): void
    {
        Database::statement('UPDATE users SET photo = ? WHERE id = ?', [$photo, $id]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::statement('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function touchLastLogin(int $id): void
    {
        Database::statement('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Message shown when a non-active account tries to log in (spec s17).
     */
    public function statusMessage(): string
    {
        return match ($this->status) {
            self::STATUS_INACTIVE  => 'Your account is inactive. Please contact your administrator.',
            self::STATUS_SUSPENDED => 'Your account has been suspended. Please contact your administrator.',
            default                => 'Your account cannot be used to sign in at the moment.',
        };
    }

    public function roleLabel(): string
    {
        return (string) Config::get('roles.' . $this->role . '.label', ucfirst($this->role));
    }

    public function dashboardPath(): string
    {
        return (string) Config::get('roles.' . $this->role . '.dashboard', '/');
    }

    public function loginPath(): string
    {
        return (string) Config::get('roles.' . $this->role . '.login', '/');
    }

    /**
     * The web path to this user's uploaded photo, or null if they have not
     * set one (callers fall back to initials()).
     */
    public function photoUrl(): ?string
    {
        return $this->photo !== null ? '/uploads/' . $this->photo : null;
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
