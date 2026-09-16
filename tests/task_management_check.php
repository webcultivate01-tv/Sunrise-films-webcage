<?php

declare(strict_types=1);

/**
 * End-to-end check of the Task Management acceptance criteria, run against
 * the real database.
 *
 * It creates a throwaway Manager, three Employees (two under that Manager,
 * one under the Admin directly, to prove the Manager's reach now matches the
 * Admin's), a Photographer and a Project, then exercises assignment, acceptance,
 * 10% progress steps, completion, salary crediting, exit and reassignment -
 * then deletes everything it created.
 *
 * Usage:  php tests/task_management_check.php
 */

use App\Core\Database;
use App\Models\Photographer;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDescription;
use App\Models\TaskSalaryCredit;
use App\Models\User;
use App\Services\PhotographerService;
use App\Services\PasswordPolicy;
use App\Services\ProjectService;
use App\Services\TaskService;
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
$managerEmail  = 'tm-manager-' . $suffix . '@sunrisefilms.test';
$employee1Mail = 'tm-employee1-' . $suffix . '@sunrisefilms.test';
$employee2Mail = 'tm-employee2-' . $suffix . '@sunrisefilms.test';
$employee3Mail = 'tm-employee3-' . $suffix . '@sunrisefilms.test';
$photographerEmail = 'tm-photographer-' . $suffix . '@sunrisefilms.test';

/** @var list<int> $userIds */
$userIds = [];
/** @var list<int> $photographerIds */
$photographerIds = [];
/** @var list<int> $projectIds */
$projectIds = [];
/** @var list<int> $taskIds */
$taskIds = [];

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
        'name' => 'Task Mgmt Manager', 'email' => $managerEmail, 'phone' => '+91 98765 43230',
        'address' => '1 MG Road, Bengaluru 560001', 'role' => User::ROLE_MANAGER, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $manager->id;

    $employee1 = UserService::createAccount($manager, [
        'name' => 'Task Mgmt Employee One', 'email' => $employee1Mail, 'phone' => '+91 98765 43231',
        'address' => '2 Church Street, Bengaluru 560001', 'role' => User::ROLE_EMPLOYEE, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee1->id;

    $employee2 = UserService::createAccount($manager, [
        'name' => 'Task Mgmt Employee Two', 'email' => $employee2Mail, 'phone' => '+91 98765 43232',
        'address' => '3 Brigade Road, Bengaluru 560001', 'role' => User::ROLE_EMPLOYEE, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee2->id;

    // Created directly by the Admin, so out of the Manager's scope.
    $employee3 = UserService::createAccount($admin, [
        'name' => 'Task Mgmt Employee Three', 'email' => $employee3Mail, 'phone' => '+91 98765 43233',
        'address' => '4 Residency Road, Bengaluru 560025', 'role' => User::ROLE_EMPLOYEE, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee3->id;

    $photographer = PhotographerService::create($admin, [
        'name' => 'Task Mgmt Photographer', 'email' => $photographerEmail, 'phone' => '+91 99999 66666',
        'address' => '5 Koramangala, Bengaluru 560034',
    ]);
    $photographerIds[] = $photographer->id;

    $project = ProjectService::create($admin, [
        'photographer_id' => (string) $photographer->id, 'customer_name' => 'Task Mgmt Customer',
        'description' => 'A project to hang tasks off of.', 'folder_name' => 'TMPhotographer_Project_2026',
        'deadline' => '2026-12-31', 'total_payment' => '100000',
    ]);
    $projectIds[] = $project->id;

    // =======================================================================
    echo PHP_EOL . 'Task Management - access and scope' . PHP_EOL;

    check('admin can access task management', TaskService::canAccessManagement($admin));
    check('manager can access task management', TaskService::canAccessManagement($manager));
    check('employee cannot access task management', !TaskService::canAccessManagement($employee1));
    check('employee is refused the task list', refused(static fn () => TaskService::list($employee1, [])));

    $managerScope = array_map(static fn (User $u): int => $u->id, TaskService::assignableEmployees($manager));
    check('manager may assign work to every employee, matching admin\'s reach', in_array($employee1->id, $managerScope, true)
        && in_array($employee2->id, $managerScope, true) && in_array($employee3->id, $managerScope, true));

    $adminScope = array_map(static fn (User $u): int => $u->id, TaskService::assignableEmployees($admin));
    check('admin may assign work to every employee', in_array($employee1->id, $adminScope, true)
        && in_array($employee2->id, $adminScope, true) && in_array($employee3->id, $adminScope, true));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Task Management - validation' . PHP_EOL;

    $blank = TaskService::validate($admin, [
        'project_id' => '', 'employee_id' => '', 'title' => '', 'description' => '',
        'start_date' => '', 'end_date' => '', 'priority' => '', 'amount' => '',
    ]);

    check('every required field is enforced', count(array_intersect(
        ['project_id', 'employee_id', 'title', 'description', 'start_date', 'end_date', 'priority', 'amount'],
        array_keys($blank->errors()),
    )) === 8);

    check('a manager can assign work to an employee they did not create', !array_key_exists('employee_id', TaskService::validate($manager, [
        'project_id' => (string) $project->id, 'employee_id' => (string) $employee3->id, 'title' => 'X',
        'description' => 'Y', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'priority' => Task::PRIORITY_HIGH, 'amount' => '1000',
    ])->errors()));

    check('an end date before the start date is refused', array_key_exists('end_date', TaskService::validate($admin, [
        'project_id' => (string) $project->id, 'employee_id' => (string) $employee1->id, 'title' => 'X',
        'description' => 'Y', 'start_date' => '2026-02-01', 'end_date' => '2026-01-01',
        'priority' => Task::PRIORITY_HIGH, 'amount' => '1000',
    ])->errors()));

    check('a zero amount is refused', array_key_exists('amount', TaskService::validate($admin, [
        'project_id' => (string) $project->id, 'employee_id' => (string) $employee1->id, 'title' => 'X',
        'description' => 'Y', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'priority' => Task::PRIORITY_HIGH, 'amount' => '0',
    ])->errors()));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Task Management - assignment workflow' . PHP_EOL;

    $task = TaskService::create($manager, [
        'project_id' => (string) $project->id, 'employee_id' => (string) $employee1->id,
        'title' => 'Rough cut', 'description' => 'Cut the raw footage down to a rough edit.',
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'priority' => Task::PRIORITY_HIGH,
        'amount' => '8000',
    ]);
    $taskIds[] = $task->id;

    check('manager can create and assign a task', $task->id > 0);
    check('the task defaults to assigned with 0% progress', $task->status === Task::STATUS_ASSIGNED && $task->progress === 0);
    check('the assigning user is recorded', $task->createdBy === $manager->id);

    check('the task appears in the employee\'s My Work', in_array($task->id, array_map(
        static fn (Task $t): int => $t->id,
        TaskService::myWork($employee1, []),
    ), true));

    check('a different employee cannot see this task', refused(static fn () => TaskService::findForEmployee($employee2, $task->id)));
    check('a different employee cannot accept this task', refused(static fn () => TaskService::accept($employee2, $task)));

    check('progress cannot be updated before acceptance', refused(static fn () => TaskService::updateProgress($employee1, $task, 10)));

    $task = TaskService::accept($employee1, $task);
    check('employee can accept the task', $task->status === Task::STATUS_ACCEPTED);
    check('accepting twice is refused', refused(static fn () => TaskService::accept($employee1, $task)));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Task Management - the description thread' . PHP_EOL;

    $thread = TaskService::descriptions($manager, $task);

    check('a new task opens its own description thread', count($thread) === 1);
    check('the opening round is the task description', $thread[0]->body === 'Cut the raw footage down to a rough edit.');
    check('the opening round records who wrote it', $thread[0]->createdBy === $manager->id);

    check('an empty description is refused', array_key_exists(
        'body',
        TaskService::validateDescription(['body' => '   '])->errors(),
    ));

    TaskService::addDescription($manager, $task, ['body' => 'Client wants the drone shots in as well.']);
    TaskService::addDescription($admin, $task, ['body' => 'And keep the interview audio clean.']);

    $thread = TaskService::descriptions($manager, $task);

    check('later rounds are appended, not overwritten', count($thread) === 3);
    check('the thread stays in the order it was sent', $thread[0]->body === 'Cut the raw footage down to a rough edit.'
        && $thread[1]->body === 'Client wants the drone shots in as well.'
        && $thread[2]->body === 'And keep the interview audio clean.');
    check('the original description is never rewritten',
        Task::findById($task->id)?->description === 'Cut the raw footage down to a rough edit.');

    check('the assigned employee reads the whole thread', count(TaskService::descriptionsForEmployee($employee1, $task)) === 3);
    check('another employee cannot read the thread', refused(
        static fn () => TaskService::descriptionsForEmployee($employee2, $task),
    ));
    check('an employee cannot add to the thread', refused(
        static fn () => TaskService::addDescription($employee1, $task, ['body' => 'Not mine to send.']),
    ));

    check('the thread count is reported for the task lists',
        (TaskService::descriptionCounts([$task]))[$task->id] === 3);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Task Management - progress in 10% steps' . PHP_EOL;

    check('an invalid percentage is refused', refused(static fn () => TaskService::updateProgress($employee1, $task, 15)));
    check('a percentage above 100 is refused', refused(static fn () => TaskService::updateProgress($employee1, $task, 110)));

    $task = TaskService::updateProgress($employee1, $task, 30);
    check('progress can be set to a valid 10% step', $task->progress === 30);
    check('accepting a task moves it to in progress once work starts', $task->status === Task::STATUS_IN_PROGRESS);

    check('progress cannot move backwards', refused(static fn () => TaskService::updateProgress($employee1, $task, 20)));
    check('completing before 100% is refused', refused(static fn () => TaskService::complete($employee1, $task)));

    $task = TaskService::updateProgress($employee1, $task, 100);
    check('progress can reach 100%', $task->progress === 100);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Task Management - completion and salary eligibility' . PHP_EOL;

    $task = TaskService::complete($employee1, $task);
    check('employee can mark the task completed at 100%', $task->status === Task::STATUS_COMPLETED);
    check('a salary credit is recorded for the completed task', TaskSalaryCredit::existsForTask($task->id));
    check('completing again is refused', refused(static fn () => TaskService::complete($employee1, $task)));

    $creditCountBefore = (int) (Database::selectOne(
        'SELECT COUNT(*) AS total FROM task_salary_credits WHERE task_id = ?',
        [$task->id],
    )['total'] ?? 0);

    // Simulates a duplicate submission (double click / page refresh) reaching
    // the model layer directly - it must still not create a second credit.
    TaskSalaryCredit::creditForTask($task->id, $task->employeeId, $task->projectId, $task->amount);

    $creditCountAfter = (int) (Database::selectOne(
        'SELECT COUNT(*) AS total FROM task_salary_credits WHERE task_id = ?',
        [$task->id],
    )['total'] ?? 0);

    check('a repeated credit for the same task never duplicates', $creditCountBefore === 1 && $creditCountAfter === 1);
    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Task Management - exit and reassignment' . PHP_EOL;

    $exitTask = TaskService::create($manager, [
        'project_id' => (string) $project->id, 'employee_id' => (string) $employee1->id,
        'title' => 'Colour grade', 'description' => 'Grade the final export.',
        'start_date' => '2026-02-01', 'end_date' => '2026-02-15', 'priority' => Task::PRIORITY_MEDIUM,
        'amount' => '5000',
    ]);
    $taskIds[] = $exitTask->id;

    check('a task cannot be exited before acceptance', refused(static fn () => TaskService::exitWork($employee1, $exitTask)));

    $exitTask = TaskService::accept($employee1, $exitTask);
    $exitTask = TaskService::exitWork($employee1, $exitTask);

    check('employee can exit an accepted task', $exitTask->status === Task::STATUS_EXITED);
    check('an exited task creates no salary credit', !TaskSalaryCredit::existsForTask($exitTask->id));

    check('the exited task appears in Exited Work', in_array($exitTask->id, array_map(
        static fn (Task $t): int => $t->id,
        TaskService::exitedWork($manager),
    ), true));

    check('an employee a manager did not create can still be the reassignment target', !array_key_exists(
        'employee_id',
        TaskService::validateReassignment($manager, [
            'employee_id' => (string) $employee3->id, 'start_date' => '2026-02-16', 'end_date' => '2026-02-28',
        ])->errors(),
    ));

    check('a task that has not been exited cannot be reassigned', refused(
        static fn () => TaskService::reassign($manager, $task, [
            'employee_id' => (string) $employee2->id, 'start_date' => '2026-02-16', 'end_date' => '2026-02-28',
        ]),
    ));

    $reassigned = TaskService::reassign($manager, $exitTask, [
        'employee_id' => (string) $employee2->id, 'start_date' => '2026-02-16', 'end_date' => '2026-02-28',
    ]);
    $taskIds[] = $reassigned->id;

    check('reassignment creates a new task for the new employee', $reassigned->id !== $exitTask->id
        && $reassigned->employeeId === $employee2->id);
    check('the reassigned task starts the lifecycle over', $reassigned->status === Task::STATUS_ASSIGNED && $reassigned->progress === 0);
    check('the reassigned task links back to the original', $reassigned->parentTaskId === $exitTask->id);
    check('the original task is marked reassigned, not left exited', Task::findById($exitTask->id)?->status === Task::STATUS_REASSIGNED);
    check('the new task appears in the new employee\'s My Work', in_array($reassigned->id, array_map(
        static fn (Task $t): int => $t->id,
        TaskService::myWork($employee2, []),
    ), true));
    check('the amount and title carried over to the reassigned task', $reassigned->amount === $exitTask->amount
        && $reassigned->title === $exitTask->title);
    check('the description thread carried over to the reassigned task', array_map(
        static fn (TaskDescription $d): string => $d->body,
        TaskService::descriptions($manager, $reassigned),
    ) === array_map(
        static fn (TaskDescription $d): string => $d->body,
        TaskService::descriptions($manager, Task::findById($exitTask->id)),
    ));
    check('a closed task takes no further instructions', refused(
        static fn () => TaskService::addDescription($manager, Task::findById($exitTask->id), ['body' => 'Too late.']),
    ));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Task Management - manager reach on the task list (widened to match admin)' . PHP_EOL;

    $adminOnlyTask = TaskService::create($admin, [
        'project_id' => (string) $project->id, 'employee_id' => (string) $employee3->id,
        'title' => 'Admin-only task', 'description' => 'Assigned to an employee the manager did not create.',
        'start_date' => '2026-03-01', 'end_date' => '2026-03-10', 'priority' => Task::PRIORITY_LOW,
        'amount' => '2000',
    ]);
    $taskIds[] = $adminOnlyTask->id;

    $managerList = array_map(static fn (Task $t): int => $t->id, TaskService::list($manager, []));

    check('manager task list includes their own team\'s tasks', in_array($task->id, $managerList, true));
    check('manager task list also includes tasks the admin assigned', in_array($adminOnlyTask->id, $managerList, true));
    check('manager can view a task assigned outside their own team directly',
        TaskService::findOrFail($manager, $adminOnlyTask->id)->id === $adminOnlyTask->id);

    $adminList = array_map(static fn (Task $t): int => $t->id, TaskService::list($admin, []));
    check('admin task list includes every task', in_array($task->id, $adminList, true) && in_array($adminOnlyTask->id, $adminList, true));

    $hits = TaskService::list($admin, ['q' => 'Rough cut']);
    check('tasks can be searched by title', count($hits) === 1 && $hits[0]->id === $task->id);

    $suggested = TaskService::suggest($admin, 'Rough cut');
    check('suggest returns the same match', count($suggested) === 1 && $suggested[0]->id === $task->id);
    check('an empty search suggests nothing', TaskService::suggest($admin, '') === []);

    $suggestedManagerScope = TaskService::suggest($manager, 'Admin-only task');
    check('manager suggestions surface tasks system-wide', count($suggestedManagerScope) === 1 && $suggestedManagerScope[0]->id === $adminOnlyTask->id);

    $employeeSuggested = TaskService::suggestForEmployee($employee1, 'Rough cut');
    check('an employee gets suggestions for their own My Work', count($employeeSuggested) === 1 && $employeeSuggested[0]->id === $task->id);
} finally {
    foreach ($taskIds as $id) {
        Database::statement('DELETE FROM tasks WHERE id = ?', [$id]);
    }

    foreach ($projectIds as $id) {
        Database::statement('DELETE FROM projects WHERE id = ?', [$id]);
    }

    foreach ($photographerIds as $id) {
        Database::statement('DELETE FROM photographers WHERE id = ?', [$id]);
    }

    foreach (array_reverse($userIds) as $id) {
        Database::statement('DELETE FROM users WHERE id = ?', [$id]);
    }

    Database::statement('DELETE FROM login_attempts WHERE attempt_key LIKE ?', ['%sunrisefilms.test%']);
}

echo PHP_EOL . str_repeat('-', 52) . PHP_EOL;
echo sprintf('%d passed, %d failed', $passed, $failed) . PHP_EOL;

exit($failed === 0 ? 0 : 1);
