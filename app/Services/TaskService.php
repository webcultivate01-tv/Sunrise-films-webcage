<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDescription;
use App\Models\TaskSalaryCredit;
use App\Models\User;

/**
 * Task Management (task spec).
 *
 * Admin and Manager share the assignment side of this service, and both now
 * have the same system-wide reach (module spec update): a Manager sees and
 * may assign work to every Employee, not just the ones they created. The
 * Employee side is a hard wall - every read and write there is proven to
 * belong to the signed-in Employee before anything happens to it.
 */
final class TaskService
{
    // === Admin / Manager: assignment ======================================

    public static function canAccessManagement(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_MANAGER], true);
    }

    public static function assertAccessManagement(User $actor): void
    {
        if (!self::canAccessManagement($actor)) {
            throw HttpException::forbidden('You are not authorized to access Task Management.');
        }
    }

    /**
     * The Employees $actor may assign a task to (task spec s3): a Manager's
     * scope was widened to match Admin, so both see and may assign work to
     * every Employee in the system.
     *
     * @return list<User>
     */
    public static function assignableEmployees(User $actor): array
    {
        self::assertAccessManagement($actor);

        return User::allOfRole(User::ROLE_EMPLOYEE);
    }

    /**
     * The employee ids a Manager's task list, filters and assignments are
     * confined to, or null for "no scope". Always null now that a Manager's
     * reach matches Admin's (module spec update).
     *
     * @return list<int>|null
     */
    private static function scopeEmployeeIds(User $actor): ?array
    {
        return null;
    }

    /**
     * @param  array<string, string> $filters
     * @return list<Task>
     */
    public static function list(User $actor, array $filters): array
    {
        self::assertAccessManagement($actor);

        return Task::all($filters, self::scopeEmployeeIds($actor));
    }

    /**
     * Live suggestions for the search box.
     *
     * @return list<Task>
     */
    public static function suggest(User $actor, string $search): array
    {
        self::assertAccessManagement($actor);

        if (trim($search) === '') {
            return [];
        }

        return array_slice(Task::all(['q' => $search], self::scopeEmployeeIds($actor)), 0, 8);
    }

    /**
     * Exited Work (task spec s9): tasks in $actor's scope waiting to be
     * reassigned.
     *
     * @return list<Task>
     */
    public static function exitedWork(User $actor): array
    {
        self::assertAccessManagement($actor);

        return Task::all(['status' => Task::STATUS_EXITED], self::scopeEmployeeIds($actor));
    }

    /**
     * @return array<string, int>
     */
    public static function statusCounts(User $actor): array
    {
        self::assertAccessManagement($actor);

        return Task::statusCounts(self::scopeEmployeeIds($actor));
    }

    public static function findOrFail(User $actor, int $id): Task
    {
        self::assertAccessManagement($actor);

        $task = Task::findById($id);

        if ($task === null) {
            throw HttpException::notFound('That task could not be found.');
        }

        $scope = self::scopeEmployeeIds($actor);

        if ($scope !== null && !in_array($task->employeeId, $scope, true)) {
            throw HttpException::forbidden('You are not authorized to view that task.');
        }

        return $task;
    }

    /**
     * Validate the Add Task form (task spec s3).
     *
     * @param array<string, string> $input
     */
    public static function validate(User $actor, array $input): Validator
    {
        $validator = new Validator();

        $projectId   = trim($input['project_id'] ?? '');
        $employeeId  = trim($input['employee_id'] ?? '');
        $title       = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $startDate   = trim($input['start_date'] ?? '');
        $endDate     = trim($input['end_date'] ?? '');
        $priority    = trim($input['priority'] ?? '');
        $amount      = trim($input['amount'] ?? '');

        $validator->require('project_id', $projectId, 'Please select a project.');

        if ($projectId !== '' && Project::findById((int) $projectId) === null) {
            $validator->add('project_id', 'That project could not be found.');
        }

        $validator->require('employee_id', $employeeId, 'Please select an employee.');

        if ($employeeId !== '') {
            $allowedIds = array_map(static fn (User $e): int => $e->id, self::assignableEmployees($actor));

            if (!in_array((int) $employeeId, $allowedIds, true)) {
                $validator->add('employee_id', 'You are not authorized to assign work to that employee.');
            }
        }

        $validator->require('title', $title, 'Please enter a task title.');
        $validator->maxLength('title', $title, 150, 'Title must be 150 characters or fewer.');

        $validator->require('description', $description, 'Please enter a task description.');

        $validator->require('start_date', $startDate, 'Please choose a start date.');
        $validator->date('start_date', $startDate);

        $validator->require('end_date', $endDate, 'Please choose an end date / deadline.');
        $validator->date('end_date', $endDate);

        if ($startDate !== '' && $endDate !== ''
            && !array_key_exists('start_date', $validator->errors())
            && !array_key_exists('end_date', $validator->errors())
            && strtotime($endDate) < strtotime($startDate)
        ) {
            $validator->add('end_date', 'The end date cannot be before the start date.');
        }

        $validator->require('priority', $priority, 'Please select a priority.');

        if ($priority !== '') {
            $validator->in('priority', $priority, Task::priorities(), 'That priority is not recognised.');
        }

        $validator->require('amount', $amount, 'Please enter the task amount.');
        $validator->decimal('amount', $amount, 'Please enter a valid amount.');

        if ($amount !== '' && !array_key_exists('amount', $validator->errors()) && (float) $amount <= 0) {
            $validator->add('amount', 'The task amount must be greater than zero.');
        }

        return $validator;
    }

    /**
     * @param array<string, string> $input
     */
    public static function create(User $actor, array $input): Task
    {
        self::assertAccessManagement($actor);

        $id = Task::create(
            projectId:   (int) $input['project_id'],
            employeeId:  (int) $input['employee_id'],
            title:       trim($input['title']),
            description: trim($input['description']),
            startDate:   trim($input['start_date']),
            endDate:     trim($input['end_date']),
            priority:    trim($input['priority']),
            amount:      (float) $input['amount'],
            createdBy:   $actor->id,
        );

        // Row one of the task's own thread, so every later round the
        // photographer sends lands underneath the original brief.
        TaskDescription::create($id, trim($input['description']), $actor->id);

        ProjectService::recomputeStatusFromTasks((int) $input['project_id']);

        return Task::findById($id) ?? throw new \RuntimeException('The task could not be created.');
    }

    public static function canReassign(Task $task): bool
    {
        return $task->status === Task::STATUS_EXITED;
    }

    /**
     * Whether $actor may cancel this task themselves (task spec s9 extended):
     * an Admin/Manager no longer has to wait for the employee to exit it -
     * any task still short of completed can be pulled back and freed up for
     * reassignment, the same way an employee exit does.
     */
    public static function canCancel(Task $task): bool
    {
        return in_array($task->status, [Task::STATUS_ASSIGNED, Task::STATUS_ACCEPTED, Task::STATUS_IN_PROGRESS], true);
    }

    /**
     * Cancel a task on the employee's behalf and free it up for reassignment.
     * Reuses the same 'exited' status Exit Work uses, so Exited Work,
     * reassignment and the no-salary-credit guarantee all apply identically
     * whether the employee exited or an Admin/Manager cancelled it for them.
     */
    public static function cancel(User $actor, Task $task): Task
    {
        self::assertAccessManagement($actor);

        if (!self::canCancel($task)) {
            throw HttpException::forbidden('This task cannot be cancelled from its current status.');
        }

        Task::markExited($task->id);
        ProjectService::recomputeStatusFromTasks($task->projectId);

        return self::findOrFail($actor, $task->id);
    }

    /**
     * Validate the Reassign form (task spec s9): a new employee within scope,
     * plus a fresh timeline for the new assignment.
     *
     * @param array<string, string> $input
     */
    public static function validateReassignment(User $actor, array $input): Validator
    {
        $validator = new Validator();

        $employeeId = trim($input['employee_id'] ?? '');
        $startDate  = trim($input['start_date'] ?? '');
        $endDate    = trim($input['end_date'] ?? '');

        $validator->require('employee_id', $employeeId, 'Please select an employee.');

        if ($employeeId !== '') {
            $allowedIds = array_map(static fn (User $e): int => $e->id, self::assignableEmployees($actor));

            if (!in_array((int) $employeeId, $allowedIds, true)) {
                $validator->add('employee_id', 'You are not authorized to assign work to that employee.');
            }
        }

        $validator->require('start_date', $startDate, 'Please choose a start date.');
        $validator->date('start_date', $startDate);

        $validator->require('end_date', $endDate, 'Please choose an end date / deadline.');
        $validator->date('end_date', $endDate);

        if ($startDate !== '' && $endDate !== ''
            && !array_key_exists('start_date', $validator->errors())
            && !array_key_exists('end_date', $validator->errors())
            && strtotime($endDate) < strtotime($startDate)
        ) {
            $validator->add('end_date', 'The end date cannot be before the start date.');
        }

        return $validator;
    }

    /**
     * Reassign an exited task to a new employee (task spec s9). The original
     * row becomes 'reassigned' - permanent history - and a brand new task is
     * created for the new employee, starting the normal lifecycle over.
     *
     * @param array<string, string> $input
     */
    public static function reassign(User $actor, Task $task, array $input): Task
    {
        self::assertAccessManagement($actor);

        if (!self::canReassign($task)) {
            throw HttpException::forbidden('Only exited tasks can be reassigned.');
        }

        Task::markReassigned($task->id);

        $id = Task::create(
            projectId:    $task->projectId,
            employeeId:   (int) $input['employee_id'],
            title:        $task->title,
            description:  $task->description,
            startDate:    trim($input['start_date']),
            endDate:      trim($input['end_date']),
            priority:     $task->priority,
            amount:       $task->amount,
            createdBy:    $actor->id,
            parentTaskId: $task->id,
        );

        // The new employee inherits the whole brief, not just its opening
        // round - every correction sent so far still applies to the work.
        TaskDescription::copyThread($task->id, $id);

        ProjectService::recomputeStatusFromTasks($task->projectId);

        return Task::findById($id) ?? throw new \RuntimeException('The reassigned task could not be created.');
    }

    // === Admin / Manager: the description thread ===========================

    /**
     * The whole brief for a task, oldest round first (see TaskDescription).
     *
     * @return list<TaskDescription>
     */
    public static function descriptions(User $actor, Task $task): array
    {
        self::assertAccessManagement($actor);

        return TaskDescription::forTask($task->id);
    }

    /**
     * Whether more instructions may still be added to this task. A task that
     * is finished or has been handed on is history - anything new belongs on
     * the task that superseded it, not on the closed one.
     */
    public static function canAddDescription(Task $task): bool
    {
        return in_array(
            $task->status,
            [Task::STATUS_ASSIGNED, Task::STATUS_ACCEPTED, Task::STATUS_IN_PROGRESS],
            true,
        );
    }

    /**
     * @param array<string, string> $input
     */
    public static function validateDescription(array $input): Validator
    {
        $validator = new Validator();
        $body      = trim($input['body'] ?? '');

        $validator->require('body', $body, 'Please enter the new description.');
        $validator->maxLength('body', $body, 5000, 'A description must be 5000 characters or fewer.');

        return $validator;
    }

    /**
     * Send another round of instructions to the assigned employee. The task's
     * opening description is never rewritten - this is appended to the thread,
     * so the employee sees what changed as well as what it changed from.
     *
     * @param array<string, string> $input
     */
    public static function addDescription(User $actor, Task $task, array $input): Task
    {
        self::assertAccessManagement($actor);

        if (!self::canAddDescription($task)) {
            throw HttpException::forbidden('This task is closed - no further instructions can be sent to it.');
        }

        TaskDescription::create($task->id, trim($input['body']), $actor->id);

        return self::findOrFail($actor, $task->id);
    }

    /**
     * How many rounds each task in $tasks carries, for the task lists.
     *
     * @param  list<Task> $tasks
     * @return array<int, int>
     */
    public static function descriptionCounts(array $tasks): array
    {
        return TaskDescription::countsForTasks(array_map(static fn (Task $t): int => $t->id, $tasks));
    }
    // === Employee: My Work =================================================

    public static function findForEmployee(User $employee, int $id): Task
    {
        $task = Task::findById($id);

        if ($task === null || $task->employeeId !== $employee->id) {
            throw HttpException::notFound('That task could not be found.');
        }

        return $task;
    }

    /**
     * @param  array<string, string> $filters
     * @return list<Task>
     */
    public static function myWork(User $employee, array $filters): array
    {
        return Task::allForEmployee($employee->id, $filters);
    }

    /**
     * Live suggestions for the My Work search box.
     *
     * @return list<Task>
     */
    public static function suggestForEmployee(User $employee, string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        return array_slice(self::myWork($employee, ['q' => $search]), 0, 8);
    }

    /**
     * @return array<string, int>
     */
    public static function myStatusCounts(User $employee): array
    {
        $counts = array_fill_keys(Task::statuses(), 0);

        foreach (Task::allForEmployee($employee->id, []) as $task) {
            $counts[$task->status]++;
        }

        return $counts;
    }

    public static function accept(User $employee, Task $task): Task
    {
        self::assertOwnedByEmployee($employee, $task);

        if ($task->status !== Task::STATUS_ASSIGNED) {
            throw HttpException::forbidden('This task has already been accepted.');
        }

        Task::accept($task->id);

        return self::findForEmployee($employee, $task->id);
    }

    /**
     * Progress moves in 10% steps only (task spec s6) and never backwards -
     * there is no requirement to un-progress a task, and allowing it would
     * make "100% then Mark Completed" an unreliable gate.
     */
    public static function updateProgress(User $employee, Task $task, int $progress): Task
    {
        self::assertOwnedByEmployee($employee, $task);

        if (!in_array($task->status, [Task::STATUS_ACCEPTED, Task::STATUS_IN_PROGRESS], true)) {
            throw HttpException::forbidden('Accept this task before updating its progress.');
        }

        if (!in_array($progress, Task::progressSteps(), true)) {
            throw HttpException::forbidden('Progress must be updated in 10% increments.');
        }

        if ($progress <= $task->progress) {
            throw HttpException::forbidden('Progress cannot be moved backwards.');
        }

        $status = $progress > 0 ? Task::STATUS_IN_PROGRESS : $task->status;

        Task::updateProgress($task->id, $progress, $status);

        return self::findForEmployee($employee, $task->id);
    }

    /**
     * Mark Completed (task spec s7): only reachable at 100% progress, and the
     * amount is credited to salary exactly once no matter how many times this
     * is submitted.
     */
    public static function complete(User $employee, Task $task): Task
    {
        self::assertOwnedByEmployee($employee, $task);

        if ($task->status !== Task::STATUS_IN_PROGRESS) {
            throw HttpException::forbidden('This task cannot be marked completed from its current status.');
        }

        if ($task->progress < 100) {
            throw HttpException::forbidden('Progress must reach 100% before this task can be marked completed.');
        }

        Task::markCompleted($task->id);
        TaskSalaryCredit::creditForTask($task->id, $task->employeeId, $task->projectId, $task->amount);
        ProjectService::recomputeStatusFromTasks($task->projectId);

        return self::findForEmployee($employee, $task->id);
    }

    /**
     * Exit Work (task spec s8): available once the task has been accepted,
     * before it is completed. No salary credit is ever created for it.
     */
    public static function exitWork(User $employee, Task $task): Task
    {
        self::assertOwnedByEmployee($employee, $task);

        if (!in_array($task->status, [Task::STATUS_ACCEPTED, Task::STATUS_IN_PROGRESS], true)) {
            throw HttpException::forbidden('This task cannot be exited from its current status.');
        }

        Task::markExited($task->id);
        ProjectService::recomputeStatusFromTasks($task->projectId);

        return self::findForEmployee($employee, $task->id);
    }


    /**
     * The brief an employee is working to, read only: every round the
     * Admin/Manager has sent on their own task, oldest first.
     *
     * @return list<TaskDescription>
     */
    public static function descriptionsForEmployee(User $employee, Task $task): array
    {
        self::assertOwnedByEmployee($employee, $task);

        return TaskDescription::forTask($task->id);
    }
    private static function assertOwnedByEmployee(User $employee, Task $task): void
    {
        if ($task->employeeId !== $employee->id) {
            throw HttpException::forbidden('You are not authorized to act on that task.');
        }
    }
}
