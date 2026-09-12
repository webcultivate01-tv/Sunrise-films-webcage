<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;

/**
 * My Work - the Employee side of Task Management (task spec s10, s11, s13).
 *
 * Every method here proves the task belongs to the signed-in Employee before
 * doing anything with it (TaskService::findForEmployee / assertOwnedByEmployee),
 * so a tampered URL can never reach somebody else's task.
 */
final class MyWorkController extends Controller
{
    /**
     * GET /employee/my-work - active/completed/exited views, search and
     * filters (task spec s11, s13).
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user    = $this->user();
        $filters = $request->only(['q', 'project_id', 'priority', 'status', 'view']);

        $allTasks = TaskService::myWork($user, []);
        $projects = [];

        foreach ($allTasks as $task) {
            $projects[$task->projectId] = $task->projectName ?? ('Project #' . $task->projectId);
        }

        $this->view('my-work.index', [
            'title'    => 'My Work',
            'baseUrl'  => $this->baseUrl($user),
            'tasks'    => TaskService::myWork($user, $filters),
            'filters'  => $filters,
            'counts'   => TaskService::myStatusCounts($user),
            'projects' => $projects,
        ], 'panel');
    }

    /**
     * GET /employee/my-work/suggest - live suggestions for the search box.
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
                'sub'  => $task->projectName ?? 'Unknown project',
                'url'  => $base . '/' . $task->id,
            ],
            TaskService::suggestForEmployee($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /employee/my-work/{id}
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user = $this->user();
        $task = TaskService::findForEmployee($user, (int) $params['id']);

        $this->view('my-work.show', [
            'title'   => $task->title,
            'baseUrl' => $this->baseUrl($user),
            'task'    => $task,
        ], 'panel');
    }

    /**
     * POST /employee/my-work/{id}/accept - task spec s5.
     *
     * @param array<string, string> $params
     */
    public function accept(Request $request, array $params): never
    {
        $user = $this->user();
        $task = TaskService::findForEmployee($user, (int) $params['id']);

        TaskService::accept($user, $task);

        $this->redirectWithFlash($this->baseUrl($user) . '/' . $task->id, 'success', 'Task accepted.');
    }

    /**
     * POST /employee/my-work/{id}/progress - task spec s6.
     *
     * @param array<string, string> $params
     */
    public function progress(Request $request, array $params): never
    {
        $user     = $this->user();
        $task     = TaskService::findForEmployee($user, (int) $params['id']);
        $progress = (int) $request->string('progress');

        TaskService::updateProgress($user, $task, $progress);

        $this->redirectWithFlash(
            $this->baseUrl($user) . '/' . $task->id,
            'success',
            sprintf('Progress updated to %d%%.', $progress),
        );
    }

    /**
     * POST /employee/my-work/{id}/complete - task spec s7.
     *
     * @param array<string, string> $params
     */
    public function complete(Request $request, array $params): never
    {
        $user = $this->user();
        $task = TaskService::findForEmployee($user, (int) $params['id']);

        TaskService::complete($user, $task);

        $this->redirectWithFlash(
            $this->baseUrl($user) . '/' . $task->id,
            'success',
            'Task marked completed. The amount is now eligible for your monthly salary.',
        );
    }

    /**
     * POST /employee/my-work/{id}/exit - task spec s8.
     *
     * @param array<string, string> $params
     */
    public function exit(Request $request, array $params): never
    {
        $user = $this->user();
        $task = TaskService::findForEmployee($user, (int) $params['id']);

        TaskService::exitWork($user, $task);

        $this->redirectWithFlash($this->baseUrl($user) . '/' . $task->id, 'success', 'You have exited this task.');
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/my-work';
    }
}
