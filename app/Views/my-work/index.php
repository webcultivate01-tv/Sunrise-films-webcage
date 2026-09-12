<?php

use App\Models\Task;

/**
 * My Work - the Employee's own tasks: active/completed/exited views, search
 * and filters (task spec s10, s11, s13).
 *
 * @var list<Task>            $tasks
 * @var array<string, string> $filters
 * @var string                $baseUrl
 * @var array<string, int>    $counts
 * @var array<int, string>    $projects project_id => name
 */
$q         = $filters['q'] ?? '';
$projectId = $filters['project_id'] ?? '';
$priority  = $filters['priority'] ?? '';
$status    = $filters['status'] ?? '';
$view      = $filters['view'] ?? '';

$hasFilters = $q !== '' || $projectId !== '' || $priority !== '' || $status !== '';

$pendingCount   = $counts[Task::STATUS_ASSIGNED] ?? 0;
$activeCount    = ($counts[Task::STATUS_ASSIGNED] ?? 0) + ($counts[Task::STATUS_ACCEPTED] ?? 0) + ($counts[Task::STATUS_IN_PROGRESS] ?? 0);
$completedCount = $counts[Task::STATUS_COMPLETED] ?? 0;
$exitedCount    = $counts[Task::STATUS_EXITED] ?? 0;

$tabs = [
    ''          => 'All (' . array_sum($counts) . ')',
    'pending'   => 'Pending (' . $pendingCount . ')',
    'active'    => 'Active (' . $activeCount . ')',
    'completed' => 'Completed (' . $completedCount . ')',
    'exited'    => 'Exited (' . $exitedCount . ')',
];

$tabUrl = static function (array $query, string $view) use ($baseUrl): string {
    $query['view'] = $view;

    return $baseUrl . '?' . http_build_query(array_filter($query, static fn ($v) => $v !== ''));
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">My Work</h1>
    <p class="mt-1 text-sm text-slate-500">Tasks assigned to you, with their timeline, priority and amount.</p>
</div>

<div class="mb-5 flex flex-wrap gap-2 border-b border-line">
    <?php foreach ($tabs as $value => $label): ?>
        <a href="<?= e($tabUrl($filters, $value)) ?>"
           class="rounded-t-lg border-b-2 px-3.5 py-2.5 text-sm font-medium transition <?= $view === $value
               ? 'border-brand-600 text-brand-700'
               : 'border-transparent text-slate-500 hover:text-ink' ?>">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e($baseUrl) ?>" class="mb-5 flex flex-wrap items-center gap-3">
    <input type="hidden" name="view" value="<?= e($view) ?>">

    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/suggest">
        <input type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search by task or project"
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
        <?php foreach ($projects as $id => $name): ?>
            <option value="<?= (int) $id ?>" <?= $projectId === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
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
            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e(task_status_label($value)) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Search
    </button>
    <?php if ($hasFilters): ?>
        <a href="<?= e($tabUrl([], $view)) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>
    <?php endif; ?>
</form>

<?php if ($tasks === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">No tasks here</p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters ? 'Try a different search term or clear the filters.' : 'Nothing has been assigned to you yet.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Task</th>
                    <th scope="col" class="px-5 py-3 font-medium">Priority</th>
                    <th scope="col" class="px-5 py-3 font-medium">Timeline</th>
                    <th scope="col" class="px-5 py-3 font-medium">Amount</th>
                    <th scope="col" class="px-5 py-3 font-medium">Progress</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($tasks as $task): ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5">
                            <a href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>"
                               class="font-medium text-ink underline-offset-2 hover:underline"><?= e($task->title) ?></a>
                            <p class="text-xs text-slate-500"><?= e($task->projectName ?? 'Unknown project') ?></p>
                        </td>
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
