<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The full panel navigation the sidebar was designed around, ported from the
 * retired static prototype. Anything not yet backed by a real controller
 * renders as a "not built yet" placeholder inside the authenticated shell.
 */
final class PanelModules
{
    /** @var array<string, array{label:string, description:string, icon:string}> */
    private const MODULES = [
        'customer-management' => [
            'label'       => 'Customer Management',
            'description' => 'Register new customers and maintain existing customer records.',
            'icon'        => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        ],
        'employee-management' => [
            'label'       => 'Employee Management',
            'description' => 'Add employees, manage their details and control their access.',
            'icon'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        ],
        'work-management' => [
            'label'       => 'Work Management',
            'description' => 'Create and track work orders across customers and teams.',
            'icon'        => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        ],
        'task-management' => [
            'label'       => 'Task Management',
            'description' => 'Assign, update and monitor day-to-day tasks.',
            'icon'        => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        ],
        'reports' => [
            'label'       => 'Reports',
            'description' => 'Operational and financial reporting across the organisation.',
            'icon'        => '<path d="M18 20V10M12 20V4M6 20v-6"/>',
        ],
        'payments-management' => [
            'label'       => 'Payments Management',
            'description' => 'Record customer payments and follow up on outstanding balances.',
            'icon'        => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        ],
        'monthly-salary' => [
            'label'       => 'Monthly Salary',
            'description' => 'Monthly salary statements, deductions and payout history.',
            'icon'        => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
        ],
    ];

    /**
     * Placeholder module ids shown on each role's panel, in sidebar order.
     * Modules already covered by a real page (Customer Management, Employee
     * Management, the dashboard and the profile) are left out here so the
     * sidebar never lists the same thing twice.
     *
     * @var array<string, list<string>>
     */
    private const PLACEHOLDERS = [
        'admin' => [
            'reports',
        ],
        'manager' => [
            'reports',
        ],
        'employee' => [
        ],
    ];

    /**
     * The modules that are built and routed, in sidebar order, for the roles
     * allowed to open them (module spec s2, s6). The sidebar renders these as
     * real links above the placeholders.
     *
     * @var array<string, list<array{id:string, path:string}>>
     */
    private const BUILT = [
        'admin' => [
            ['id' => 'customer-management', 'path' => '/customers'],
            ['id' => 'employee-management', 'path' => '/employees'],
            ['id' => 'work-management', 'path' => '/projects'],
            ['id' => 'task-management', 'path' => '/tasks'],
            ['id' => 'payments-management', 'path' => '/payments'],
            ['id' => 'monthly-salary', 'path' => '/monthly-salary'],
        ],
        'manager' => [
            ['id' => 'customer-management', 'path' => '/customers'],
            ['id' => 'employee-management', 'path' => '/employees'],
            ['id' => 'work-management', 'path' => '/projects'],
            ['id' => 'task-management', 'path' => '/tasks'],
            ['id' => 'payments-management', 'path' => '/payments'],
            ['id' => 'monthly-salary', 'path' => '/monthly-salary'],
        ],
        'employee' => [
            ['id' => 'task-management', 'path' => '/my-work'],
            ['id' => 'monthly-salary', 'path' => '/my-salary'],
        ],
    ];

    /**
     * @return list<array{id:string, path:string}>
     */
    public static function built(string $role): array
    {
        return self::BUILT[$role] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function placeholders(string $role): array
    {
        return self::PLACEHOLDERS[$role] ?? [];
    }

    /**
     * @return array{label:string, description:string, icon:string}
     */
    public static function module(string $id): array
    {
        return self::MODULES[$id] ?? throw new \InvalidArgumentException(sprintf('Unknown module [%s].', $id));
    }

    /**
     * Wraps a raw set of SVG path/shape elements (as stored above) in the
     * stroke-icon shell used throughout the panel.
     */
    public static function icon(string $svgShapes): string
    {
        return '<svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $svgShapes
            . '</svg>';
    }
}
