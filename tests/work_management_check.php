<?php

declare(strict_types=1);

/**
 * End-to-end check of the Work Management acceptance criteria, run against
 * the real database.
 *
 * It creates a throwaway Manager, Employee and Customer, exercises the role
 * rules and validation on projects, then deletes everything it created.
 *
 * Usage:  php tests/work_management_check.php
 */

use App\Core\Database;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\PasswordPolicy;
use App\Services\ProjectService;
use App\Services\UserService;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS  " . $label . PHP_EOL;

        return;
    }

    $failed++;
    echo "  FAIL  " . $label . PHP_EOL;
}

/**
 * Run $callback and report whether it was refused with an HTTP error.
 */
function refused(callable $callback): bool
{
    try {
        $callback();
    } catch (Throwable $e) {
        return true;
    }

    return false;
}

$suffix        = bin2hex(random_bytes(4));
$managerEmail  = 'wm-manager-' . $suffix . '@sunrisefilms.test';
$employeeEmail = 'wm-employee-' . $suffix . '@sunrisefilms.test';
$customerEmail = 'wm-customer-' . $suffix . '@sunrisefilms.test';

/** @var list<int> $userIds */
$userIds = [];
/** @var list<int> $customerIds */
$customerIds = [];
/** @var list<int> $projectIds */
$projectIds = [];

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

try {
    $admin = User::findByEmail('admin@gmail.com');

    if ($admin === null) {
        fwrite(STDERR, 'The seeded admin is missing. Run: php database/seed.php' . PHP_EOL);
        exit(1);
    }

    $manager = UserService::createAccount($admin, [
        'name'     => 'Work Mgmt Manager',
        'email'    => $managerEmail,
        'phone'    => '+91 98765 43220',
        'address'  => '1 Residency Road, Bengaluru 560025',
        'role'     => User::ROLE_MANAGER,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $manager->id;

    $employee = UserService::createAccount($manager, [
        'name'     => 'Work Mgmt Employee',
        'email'    => $employeeEmail,
        'phone'    => '+91 98765 43221',
        'address'  => '2 Church Street, Bengaluru 560001',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee->id;

    $customer = CustomerService::create($admin, [
        'name'    => 'Work Mgmt Customer',
        'email'   => $customerEmail,
        'phone'   => '+91 99999 55555',
        'address' => '5 Brigade Road, Bengaluru 560001',
    ]);
    $customerIds[] = $customer->id;

    // =======================================================================
    echo PHP_EOL . 'Work Management - access' . PHP_EOL;

    check('admin can access work management', ProjectService::canAccess($admin));
    check('manager can access work management', ProjectService::canAccess($manager));
    check('employee cannot access work management', !ProjectService::canAccess($employee));
    check('employee is refused the project list', refused(static fn () => ProjectService::list($employee)));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Work Management - validation' . PHP_EOL;

    $blank = ProjectService::validate([
        'customer_id' => '', 'name' => '', 'description' => '', 'folder_name' => '',
        'deadline' => '', 'total_payment' => '',
    ]);

    check(
        'every required field is enforced',
        count(array_intersect(
            ['customer_id', 'name', 'description', 'folder_name', 'deadline', 'total_payment'],
            array_keys($blank->errors()),
        )) === 6,
    );

    check('a non-existent customer is refused', array_key_exists('customer_id', ProjectService::validate([
        'customer_id' => '999999999', 'name' => 'X', 'description' => 'Y', 'folder_name' => 'Z',
        'deadline' => '2026-12-31', 'total_payment' => '100',
    ])->errors()));

    check('a malformed deadline is refused', array_key_exists('deadline', ProjectService::validate([
        'customer_id' => (string) $customer->id, 'name' => 'X', 'description' => 'Y', 'folder_name' => 'Z',
        'deadline' => 'not-a-date', 'total_payment' => '100',
    ])->errors()));

    check('a malformed payment amount is refused', array_key_exists('total_payment', ProjectService::validate([
        'customer_id' => (string) $customer->id, 'name' => 'X', 'description' => 'Y', 'folder_name' => 'Z',
        'deadline' => '2026-12-31', 'total_payment' => 'lots',
    ])->errors()));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Work Management - add and capture' . PHP_EOL;

    $project = ProjectService::create($admin, [
        'customer_id'     => (string) $customer->id,
        'name'            => 'Wedding Film',
        'description'     => 'Full day coverage and edit.',
        'folder_name'     => 'WMCustomer_Wedding_2026',
        'deadline'        => '2026-12-31',
        'total_payment'   => '50000',
    ]);
    $projectIds[] = $project->id;

    check('admin can add a project', $project->id > 0);
    check('the project defaults to pending', $project->status === Project::STATUS_PENDING);
    check('the registering user is recorded', $project->createdBy === $admin->id);

    $stored = Project::findById($project->id);

    check('project name is stored', $stored?->name === 'Wedding Film');
    check('project folder name is stored', $stored?->folderName === 'WMCustomer_Wedding_2026');
    check('project deadline is stored', $stored?->deadline === '2026-12-31');
    check('total payment is stored', $stored?->totalPayment === 50000.0);

    $managerProject = ProjectService::create($manager, [
        'customer_id'     => (string) $customer->id,
        'name'            => 'Product Shoot',
        'description'     => 'Half day studio shoot.',
        'folder_name'     => 'WMCustomer_Shoot_2026',
        'deadline'        => '2026-11-30',
        'total_payment'   => '15000',
    ]);
    $projectIds[] = $managerProject->id;

    check('manager can add a project', $managerProject->createdBy === $manager->id);

    check('employee cannot add a project', refused(static fn () => ProjectService::create($employee, [
        'customer_id'     => (string) $customer->id,
        'name'            => 'Should Not Exist',
        'description'     => 'N/A',
        'folder_name'     => 'N/A',
        'deadline'        => '2026-12-31',
        'total_payment'   => '100',
    ])));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Work Management - view, search, edit, status, remove' . PHP_EOL;

    check('a manager sees the projects an admin registered', in_array($project->id, array_map(
        static fn (Project $p): int => $p->id,
        ProjectService::list($manager),
    ), true));

    $hits = ProjectService::list($admin, 'Product Shoot');
    check('projects can be searched by name', count($hits) === 1 && $hits[0]->id === $managerProject->id);

    $suggested = ProjectService::suggest($admin, 'Product Shoot');
    check('suggest returns the same match', count($suggested) === 1 && $suggested[0]->id === $managerProject->id);
    check('an empty search suggests nothing', ProjectService::suggest($admin, '') === []);

    $byCustomer = ProjectService::list($admin, '', '', $customer->id);
    check('projects can be filtered by customer', count($byCustomer) === 2);

    $updated = ProjectService::update($manager, $project, [
        'customer_id'     => (string) $customer->id,
        'name'            => 'Wedding Film - Renamed',
        'description'     => 'Full day coverage, edit and highlight reel.',
        'folder_name'     => 'WMCustomer_Wedding_2026_v2',
        'deadline'        => '2027-01-15',
        'total_payment'   => '60000',
    ]);

    check('manager can edit a project', $updated->name === 'Wedding Film - Renamed'
        && $updated->totalPayment === 60000.0);

    ProjectService::setStatus($manager, $project, Project::STATUS_IN_PROGRESS);
    check('manager can update project status', Project::findById($project->id)?->status === Project::STATUS_IN_PROGRESS);

    check('an unrecognised status is refused', refused(
        static fn () => ProjectService::setStatus($admin, $project, 'not-a-status'),
    ));

    check('a manager cannot delete a project outright', refused(
        static fn () => ProjectService::delete($manager, $project),
    ));

    check(
        'a customer with projects on record cannot be deleted',
        refused(static fn () => CustomerService::delete($admin, $customer)),
    );

    ProjectService::delete($admin, $managerProject);
    check('admin can delete a project', Project::findById($managerProject->id) === null);
    $projectIds = array_values(array_filter($projectIds, static fn (int $id): bool => $id !== $managerProject->id));
} finally {
    foreach ($projectIds as $id) {
        Database::statement('DELETE FROM projects WHERE id = ?', [$id]);
    }

    foreach ($customerIds as $id) {
        Database::statement('DELETE FROM customers WHERE id = ?', [$id]);
    }

    foreach (array_reverse($userIds) as $id) {
        Database::statement('DELETE FROM users WHERE id = ?', [$id]);
    }

    Database::statement('DELETE FROM login_attempts WHERE attempt_key LIKE ?', ['%sunrisefilms.test%']);
}

echo PHP_EOL . str_repeat('-', 52) . PHP_EOL;
echo sprintf('%d passed, %d failed', $passed, $failed) . PHP_EOL;

exit($failed === 0 ? 0 : 1);
