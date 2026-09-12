<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

/**
 * The single row of system-level settings Admin Management edits: the company
 * profile printed on salary bills, and the security policy values that used
 * to be fixed by .env alone (password length, login lockout, whether Admin
 * gets self-service password recovery).
 *
 * There is exactly one row, id = 1 - enforced by the table's own CHECK
 * constraint, not just by convention here.
 */
final class Setting
{
    private static ?self $cached = null;

    public function __construct(
        public readonly string $companyName,
        public readonly string $companyAddress,
        public readonly string $companyPhone,
        public readonly string $companyEmail,
        public readonly string $companyWebsite,
        public readonly int $passwordMinLength,
        public readonly int $loginMaxAttempts,
        public readonly int $loginLockoutMinutes,
        public readonly bool $adminForgotPasswordEnabled,
        public readonly ?int $updatedBy,
        public readonly ?string $updatedAt,
    ) {
    }

    /**
     * Cached for the life of the request - every caller on a page reads the
     * same values a settings update mid-request would otherwise miss.
     */
    public static function current(): self
    {
        return self::$cached ??= self::load();
    }

    /**
     * @param array{
     *     company_name:string, company_address:string, company_phone:string, company_email:string, company_website:string,
     *     password_min_length:int, login_max_attempts:int, login_lockout_minutes:int,
     *     admin_forgot_password_enabled:bool,
     * } $data
     */
    public static function update(array $data, ?int $actorId): self
    {
        Database::statement(
            'INSERT INTO settings (
                id, company_name, company_address, company_phone, company_email, company_website,
                password_min_length, login_max_attempts, login_lockout_minutes,
                admin_forgot_password_enabled, updated_by
            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company_name = VALUES(company_name),
                company_address = VALUES(company_address),
                company_phone = VALUES(company_phone),
                company_email = VALUES(company_email),
                company_website = VALUES(company_website),
                password_min_length = VALUES(password_min_length),
                login_max_attempts = VALUES(login_max_attempts),
                login_lockout_minutes = VALUES(login_lockout_minutes),
                admin_forgot_password_enabled = VALUES(admin_forgot_password_enabled),
                updated_by = VALUES(updated_by)',
            [
                $data['company_name'],
                $data['company_address'],
                $data['company_phone'],
                $data['company_email'],
                $data['company_website'],
                $data['password_min_length'],
                $data['login_max_attempts'],
                $data['login_lockout_minutes'],
                $data['admin_forgot_password_enabled'] ? 1 : 0,
                $actorId,
            ],
        );

        self::$cached = null;

        return self::current();
    }

    private static function load(): self
    {
        $row = Database::selectOne('SELECT * FROM settings WHERE id = 1 LIMIT 1');

        return $row !== null ? self::fromRow($row) : self::defaults();
    }

    /**
     * Used before the settings table exists yet (a not-quite-migrated
     * install), so the rest of the app keeps working off .env in the meantime.
     */
    private static function defaults(): self
    {
        return new self(
            companyName:                 (string) Config::get('company.name', 'Sunrise Films'),
            companyAddress:              (string) Config::get('company.address', ''),
            companyPhone:                (string) Config::get('company.phone', ''),
            companyEmail:                (string) Config::get('company.email', ''),
            companyWebsite:              (string) Config::get('company.website', ''),
            passwordMinLength:           max(6, (int) Config::get('auth.password_min', 8)),
            loginMaxAttempts:            max(1, (int) Config::get('auth.max_attempts', 5)),
            loginLockoutMinutes:         max(1, (int) Config::get('auth.lockout_minutes', 15)),
            adminForgotPasswordEnabled:  Config::get('auth.admin_forgot_password') === true,
            updatedBy:                   null,
            updatedAt:                   null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): self
    {
        return new self(
            companyName:                 (string) $row['company_name'],
            companyAddress:              (string) $row['company_address'],
            companyPhone:                (string) $row['company_phone'],
            companyEmail:                (string) $row['company_email'],
            companyWebsite:              (string) ($row['company_website'] ?? ''),
            passwordMinLength:           max(6, (int) $row['password_min_length']),
            loginMaxAttempts:            max(1, (int) $row['login_max_attempts']),
            loginLockoutMinutes:         max(1, (int) $row['login_lockout_minutes']),
            adminForgotPasswordEnabled:  (bool) $row['admin_forgot_password_enabled'],
            updatedBy:                   isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            updatedAt:                   isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
