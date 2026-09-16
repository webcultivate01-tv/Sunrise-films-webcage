<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Task Management - Exited Work on top, then the full list, search and
 * filters (task spec s9, s12, s15).
 *
 * @var list<Task>          $tasks
 * @var list<Task>          $exited
 * @var array<string, string> $filters
 * @var string              $baseUrl
 * @var list<Project>       $projects
 * @var list<User>          $employees
 * @var array<string, int>  $counts
 */
$q          = $filters['q'] ?? '';
$projectId  = $filters['project_id'] ?? '';
$employeeId = $filters['employee_id'] ?? '';
$priority   = $filters['priority'] ?? '';
$status     = $filters['status'] ?? '';
$startDate  = $filters['start_date'] ?? '';
$endDate    = $filters['end_date'] ?? '';

$hasFilters = $q !== '' || $projectId !== '' || $employeeId !== '' || $priority !== '' || $status !== ''
    || $startDate !== '' || $endDate !== '';
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Task Management</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?>
            <?= $hasFilters ? 'matching your filters' : 'on record' ?>.
        </p>
    </div>
    <a href="<?= e($baseUrl) ?>/create"
       class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
        Add Task
    </a>
</div>

<?php if ($exited !== []): ?>
    <section class="mb-6 rounded-2xl border border-red-100 bg-red-50/40 p-5">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-red-100 text-red-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>
                </svg>
            </span>
            <div>
                <h2 class="text-base font-semibold text-ink">Exited Work</h2>
                <p class="text-sm text-slate-500">Tasks an employee has exited - reassign them to keep the work moving.</p>
            </div>
        </div>
        <div class="mt-4 overflow-x-auto rounded-xl border border-white bg-white">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Task</th>
                        <th scope="col" class="px-5 py-3 font-medium">Customer</th>
                        <th scope="col" class="px-5 py-3 font-medium">Previous employee</th>
                        <th scope="col" class="px-5 py-3 font-medium">Amount</th>
                        <th scope="col" class="px-5 py-3 font-medium">Priority</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($exited as $task): ?>
                        <tr class="cursor-pointer hover:bg-slate-50/70" data-href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>">
                            <td class="px-5 py-3.5">
                                <a href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>"
                                   class="font-medium text-ink underline-offset-2 hover:underline"><?= e($task->title) ?></a>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600"><?= e($task->customerName ?? 'Unknown customer') ?></td>
                            <td class="px-5 py-3.5 text-slate-600"><?= e($task->employeeName ?? 'Unknown employee') ?></td>
                            <td class="px-5 py-3.5 text-slate-600"><?= e(money($task->amount)) ?></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= task_priority_badge($task->priority) ?>">
                                    <?= e(task_priority_label($task->priority)) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/reassign"
                                   class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Reassign
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<form method="get" action="<?= e($baseUrl) ?>" class="mb-5 flex flex-wrap items-center gap-3">
    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/suggest">
        <input type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search by task, customer or employee"
               autocomplete="off"
               class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3.5 text-sm text-ink placeholder:text-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
               data-suggest-input>
        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <ul data-suggest-list
            class="absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-72 overflow-y-auto rounded-lg border border-line bg-white py-1 text-sm shadow-lg"></ul>
    </div>

    <select name="project_id" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <option value="">All projects</option>
        <?php foreach ($projects as $project): ?>
            <option value="<?= (int) $project->id ?>" <?= $projectId === (string) $project->id ? 'selected' : '' ?>><?= e($project->customerName) ?> - <?= e($project->photographerName ?? 'Unknown photographer') ?></option>
        <?php endforeach; ?>
    </select>

    <select name="employee_id" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <option value="">All employees</option>
        <?php foreach ($employees as $employee): ?>
            <option value="<?= (int) $employee->id ?>" <?= $employeeId === (string) $employee->id ? 'selected' : '' ?>><?= e($employee->name) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="priority" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <option value="">All priorities</option>
        <?php foreach (Task::priorities() as $value): ?>
            <option value="<?= e($value) ?>" <?= $priority === $value ? 'selected' : '' ?>><?= e(task_priority_label($value)) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="status" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <option value="">All statuses</option>
        <?php foreach (Task::statuses() as $value): ?>
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
                <?= e(task_status_label($value)) ?> (<?= (int) ($counts[$value] ?? 0) ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label class="flex items-center gap-1.5 text-sm text-slate-500">
        From
        <input type="date" name="start_date" value="<?= e($startDate) ?>"
               class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
    </label>
    <label class="flex items-center gap-1.5 text-sm text-slate-500">
        To
        <input type="date" name="end_date" value="<?= e($endDate) ?>"
               class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
    </label>

    <button type="submit"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Search
    </button>
    <?php if ($hasFilters): ?>
        <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>
    <?php endif; ?>
</form>

<?php if ($tasks === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">
            <?= $hasFilters ? 'No tasks match your filters' : 'No tasks yet' ?>
        </p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters
                ? 'Try a different search term or clear the filters.'
                : 'Add your first task to get started.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Task</th>
                    <th scope="col" class="px-5 py-3 font-medium">Employee</th>
                    <th scope="col" class="px-5 py-3 font-medium">Priority</th>
                    <th scope="col" class="px-5 py-3 font-medium">Timeline</th>
                    <th scope="col" class="px-5 py-3 font-medium">Amount</th>
                    <th scope="col" class="px-5 py-3 font-medium">Progress</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($tasks as $task): ?>
                    <tr class="cursor-pointer hover:bg-slate-50/70" data-href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>">
                        <td class="px-5 py-3.5">
                            <a href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>"
                               class="font-medium text-ink underline-offset-2 hover:underline"><?= e($task->title) ?></a>
                            <p class="text-xs text-slate-500"><?= e($task->customerName ?? 'Unknown customer') ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e($task->employeeName ?? 'Unknown employee') ?></td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= task_priority_badge($task->priority) ?>">
                                <?= e(task_priority_label($task->priority)) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <p><?= e(date('j M Y', strtotime($task->endDate))) ?></p>
                            <?php if ($task->isOverdue()): ?>
                                <p class="text-xs font-medium text-red-600">Overdue</p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(money($task->amount)) ?></td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500" style="width: <?= (int) $task->progress ?>%"></div>
                                </div>
                                <span class="text-xs text-slate-500"><?= (int) $task->progress ?>%</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= task_status_badge($task->status) ?>">
                                <?= e(task_status_label($task->status)) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
