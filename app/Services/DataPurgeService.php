<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Permanent, unrecoverable deletion of a module's data (or all of it). Admin
 * only - the controller enforces that and re-checks the admin's password.
 *
 * Foreign keys decide what has to go with what: payments, tasks and projects
 * all RESTRICT their parents, so clearing a parent module necessarily clears
 * the records that depend on it. Each module's `tables` lists them in a safe
 * delete order, and `includes` says so on screen before anything is deleted.
 * Admin accounts and system settings are never touched.
 */
final class DataPurgeService
{
    public const ALL = 'all';

    /** Stands for "every non-admin row of users" in a module's table list. */
    private const NON_ADMIN_USERS = 'users';

    private const TASK_TABLES = ['task_descriptions', 'task_salary_credits', 'tasks'];

    /**
     * @return array<string, array{label:string, description:string, includes:string, tables:list<string>}>
     */
    public static function modules(): array
    {
        $tasks    = self::TASK_TABLES;
        $projects = ['payments', ...$tasks, 'projects'];

        return [
            'payments' => [
                'label'       => 'Payments',
                'description' => 'All payment records and their bills.',
                'includes'    => 'Payments only.',
                'tables'      => ['payments'],
            ],
            'salary' => [
                'label'       => 'Monthly Salary',
                'description' => 'All salary settlements and salary bills.',
                'includes'    => 'Salary settlements only. Employees and tasks are kept.',
                'tables'      => ['salary_settlements'],
            ],
            'tasks' => [
                'label'       => 'Tasks',
                'description' => 'All tasks, their descriptions and the salary credited for them.',
                'includes'    => 'Tasks, task descriptions and earned salary credits.',
                'tables'      => $tasks,
            ],
            'projects' => [
                'label'       => 'Work (Projects)',
                'description' => 'All projects.',
                'includes'    => 'Projects, plus the payments and tasks that belong to them.',
                'tables'      => $projects,
            ],
            'photographers' => [
                'label'       => 'Photographers',
                'description' => 'All photographers.',
                'includes'    => 'Photographers, plus their projects and the payments and tasks of those projects.',
                'tables'      => [...$projects, 'photographers'],
            ],
            'employees' => [
                'label'       => 'Employees & Managers',
                'description' => 'All employee and manager accounts (Admin accounts are kept).',
                'includes'    => 'Employee and manager accounts, their profile photos, salary settlements and tasks.',
                'tables'      => ['salary_settlements', ...$tasks, self::NON_ADMIN_USERS],
            ],
            self::ALL => [
                'label'       => 'Entire system',
                'description' => 'Every record in every module.',
                'includes'    => 'Everything above, plus login history. Only Admin accounts and system settings remain.',
                'tables'      => [
                    'payments', 'salary_settlements', ...$tasks, 'projects', 'photographers',
                    self::NON_ADMIN_USERS, 'login_attempts', 'password_resets',
                ],
            ],
        ];
    }

    public static function exists(string $module): bool
    {
        return array_key_exists($module, self::modules());
    }

    /**
     * Rows each module's deletion would remove, for the confirmation screen.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $counts = [];

        foreach (self::modules() as $id => $module) {
            $counts[$id] = array_sum(array_map([self::class, 'count'], $module['tables']));
        }

        return $counts;
    }

    /**
     * Delete the module's data. Returns the number of rows removed.
     */
    public static function purge(string $module): int
    {
        $definition = self::modules()[$module] ?? throw new \InvalidArgumentException('Unknown module.');
        $tables     = $definition['tables'];
        $photos     = in_array(self::NON_ADMIN_USERS, $tables, true) ? self::userPhotos() : [];

        $pdo     = Database::connection();
        $deleted = 0;

        $pdo->beginTransaction();

        try {
            foreach ($tables as $table) {
                $deleted += Database::statement(
                    $table === self::NON_ADMIN_USERS
                        ? "DELETE FROM users WHERE role <> 'admin'"
                        : "DELETE FROM {$table}",
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        // ALTER TABLE commits implicitly, so it runs after the transaction.
        // Fresh data then starts again from #1 and ID 1.
        foreach ($tables as $table) {
            if ($table === self::NON_ADMIN_USERS) {
                Database::resequence('users', "role <> 'admin'");
                continue;
            }

            Database::run("ALTER TABLE {$table} AUTO_INCREMENT = 1");
        }

        foreach ($photos as $photo) {
            PhotoUploadService::delete($photo);
        }

        return $deleted;
    }

    private static function count(string $table): int
    {
        $where = $table === self::NON_ADMIN_USERS ? "WHERE role <> 'admin'" : '';
        $row   = Database::selectOne("SELECT COUNT(*) AS total FROM {$table} {$where}");

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return list<string>
     */
    private static function userPhotos(): array
    {
        $rows = Database::select("SELECT photo FROM users WHERE role <> 'admin' AND photo IS NOT NULL AND photo <> ''");

        return array_map(static fn (array $row): string => (string) $row['photo'], $rows);
    }
}
