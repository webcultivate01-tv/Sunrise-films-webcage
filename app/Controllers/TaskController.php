<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;

/**
 * Task Management - the Admin/Manager side (task spec s2-s4, s9, s12).
 *
 * Admin and Manager share this controller; TaskService decides which
 * employees and tasks are in scope for whichever of the two is signed in, so
 * a Manager never sees or assigns work outside their own team.
 */
final class TaskController extends Controller
{
    /**
     * GET /{panel}/tasks - list, search and filter, with Exited Work on top
     * (task spec s9, s12).
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user    = $this->user();
        $filters = $request->only(['q', 'project_id', 'employee_id', 'priority', 'status', 'start_date', 'end_date']);

        $this->view('tasks.index', [
            'title'      => 'Task Management',
            'baseUrl'    => $this->baseUrl($user),
            'tasks'      => TaskService::list($user, $filters),
            'exited'     => TaskService::exitedWork($user),
            'filters'    => $filters,
            'projects'   => Project::all(),
            'employees'  => TaskService::assignableEmployees($user),
            'counts'     => TaskService::statusCounts($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/tasks/create - the Add Task form (task spec s3).
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): never
    {
        $user = $this->user();

        TaskService::assertAccessManagement($user);

        $this->view('tasks.form', [
            'title'     => 'Add Task',
            'baseUrl'   => $this->baseUrl($user),
            'projects'  => Project::all(),
            'employees' => TaskService::assignableEmployees($user),
        ], 'panel');
    }

    /**
     * POST /{panel}/tasks
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): never
    {
        $user  = $this->user();
        $base  = $this->baseUrl($user);
        $input = $request->only([
            'project_id', 'employee_id', 'title', 'description', 'start_date', 'end_date', 'priority', 'amount',
        ]);

        TaskService::assertAccessManagement($user);

        $validator = TaskService::validate($user, $input);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/create', $validator->errors(), $input);
        }

        $task = TaskService::create($user, $input);

        $this->redirectWithFlash(
            $base . '/' . $task->id,
            'success',
            sprintf('%s has been assigned to %s.', $task->title, $task->employeeName ?? 'the employee'),
        );
    }

    /**
     * GET /{panel}/tasks/suggest - live suggestions for the search box.
     *
     * @param array<string, string> $params
     */
    public function suggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (Task $task): array => [
                'id'   => $task->id,
                'name' => $task->title,
                'sub'  => ($task->projectName ?? 'Unknown project') . ' · ' . ($task->employeeName ?? 'Unassigned'),
                'url'  => $base . '/' . $task->id,
            ],
            TaskService::suggest($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/tasks/{id}
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user = $this->user();
        $task = TaskService::findOrFail($user, (int) $params['id']);

        $this->view('tasks.show', [
            'title'        => $task->title,
            'baseUrl'      => $this->baseUrl($user),
            'task'         => $task,
            'canReassign'  => TaskService::canReassign($task),
            'canCancel'    => TaskService::canCancel($task),
        ], 'panel');
    }

    /**
     * POST /{panel}/tasks/{id}/cancel - Admin/Manager pulls a task back from
     * its employee without waiting for them to exit it, freeing it up for
     * reassignment (task spec s9 extended).
     *
     * @param array<string, string> $params
     */
    public function cancel(Request $request, array $params): never
    {
        $user = $this->user();
        $task = TaskService::findOrFail($user, (int) $params['id']);
        $base = $this->baseUrl($user);

        if (!TaskService::canCancel($task)) {
            $this->redirectWithFlash($base . '/' . $task->id, 'error', 'This task cannot be cancelled from its current status.');
        }

        $cancelled = TaskService::cancel($user, $task);

        $this->redirectWithFlash(
            $base . '/' . $cancelled->id . '/reassign',
            'success',
            sprintf('%s has been removed from %s and is ready to reassign.', $cancelled->employeeName ?? 'The employee', $cancelled->title),
        );
    }

    /**
     * GET /{panel}/tasks/{id}/reassign - task spec s9.
     *
     * @param array<string, string> $params
     */
    public function reassignForm(Request $request, array $params): never
    {
        $user = $this->user();
        $task = TaskService::findOrFail($user, (int) $params['id']);

        if (!TaskService::canReassign($task)) {
            $this->redirectWithFlash($this->baseUrl($user) . '/' . $task->id, 'error', 'Only exited tasks can be reassigned.');
        }

        $this->view('tasks.reassign', [
            'title'     => 'Reassign ' . $task->title,
            'baseUrl'   => $this->baseUrl($user),
            'task'      => $task,
            'employees' => array_values(array_filter(
                TaskService::assignableEmployees($user),
                static fn (User $employee): bool => $employee->id !== $task->employeeId,
            )),
        ], 'panel');
    }

    /**
     * POST /{panel}/tasks/{id}/reassign
     *
     * @param array<string, string> $params
     */
    public function reassign(Request $request, array $params): never
    {
        $user  = $this->user();
        $task  = TaskService::findOrFail($user, (int) $params['id']);
        $base  = $this->baseUrl($user);
        $input = $request->only(['employee_id', 'start_date', 'end_date']);

        $validator = TaskService::validateReassignment($user, $input);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $task->id . '/reassign', $validator->errors(), $input);
        }

        $reassigned = TaskService::reassign($user, $task, $input);

        $this->redirectWithFlash(
            $base . '/' . $reassigned->id,
            'success',
            sprintf('%s has been reassigned to %s.', $reassigned->title, $reassigned->employeeName ?? 'the new employee'),
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/tasks';
    }
}
