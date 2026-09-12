<?php

use App\Models\Customer;
use App\Models\Project;

/**
 * Work Management - list, search, filter and row actions.
 *
 * @var list<Project>      $projects
 * @var string             $baseUrl
 * @var string             $search
 * @var string             $status
 * @var string             $customerId
 * @var string             $sort
 * @var list<Customer>     $customers
 * @var array<string, int> $counts
 * @var bool               $canDelete
 */
$hasFilters = $search !== '' || $status !== '' || $customerId !== '';
$filters    = ['' => 'All statuses'];

foreach (Project::statuses() as $value) {
    $filters[$value] = project_status_label($value) . ' (' . ($counts[$value] ?? 0) . ')';
}

$sorts = [
    ''               => 'Deadline (soonest first)',
    'deadline_desc'  => 'Deadline (latest first)',
    'newest'         => 'Recently added',
    'oldest'         => 'Oldest added',
];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Work Management</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($projects) ?> project<?= count($projects) === 1 ? '' : 's' ?>
            <?= $hasFilters ? 'matching your filters' : 'on record' ?>.
            <?php if ($status === ''): ?>
                Completed projects are hidden here - select "Completed" in the status filter to see them.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e($baseUrl) ?>/create"
       class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
        Add Project
    </a>
</div>

<form method="get" action="<?= e($baseUrl) ?>" class="mb-5 flex flex-wrap items-center gap-3">
    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/suggest">
        <input type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search by project, folder or customer name"
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

    <label for="filter-customer" class="sr-only">Customer</label>
    <select id="filter-customer" name="customer_id"
            class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <option value="">All customers</option>
        <?php foreach ($customers as $customer): ?>
            <option value="<?= (int) $customer->id ?>" <?= $customerId === (string) $customer->id ? 'selected' : '' ?>>
                <?= e($customer->name) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="filter-status" class="sr-only">Status</label>
    <select id="filter-status" name="status"
            class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <?php foreach ($filters as $value => $label): ?>
            <option value="<?= e((string) $value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="filter-sort" class="sr-only">Sort by</label>
    <select id="filter-sort" name="sort"
            class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <?php foreach ($sorts as $value => $label): ?>
            <option value="<?= e((string) $value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Search
    </button>
    <?php if ($hasFilters): ?>
        <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>
    <?php endif; ?>
</form>

<?php if ($projects === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">
            <?= $hasFilters ? 'No projects match your filters' : 'No projects yet' ?>
        </p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters
                ? 'Try a different search term or clear the filters.'
                : 'Add your first project to get started.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Project</th>
                    <th scope="col" class="px-5 py-3 font-medium">Deadline</th>
                    <th scope="col" class="px-5 py-3 font-medium">Total payment</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($projects as $project): ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5">
                            <a href="<?= e($baseUrl) ?>/<?= (int) $project->id ?>"
                               class="font-medium text-ink underline-offset-2 hover:underline"><?= e($project->name) ?></a>
                            <p class="text-xs text-slate-500"><?= e($project->customerName ?? 'Unknown customer') ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <p><?= e(date('j M Y', strtotime($project->deadline))) ?></p>
                            <?php if ($project->isOverdue()): ?>
                                <p class="text-xs font-medium text-red-600">Overdue</p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <p><?= e(money($project->totalPayment)) ?></p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= project_status_badge($project->status) ?>">
                                <?= e(project_status_label($project->status)) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e($baseUrl) ?>/<?= (int) $project->id ?>/bill"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Bill
                                </a>
                                <a href="<?= e($baseUrl) ?>/<?= (int) $project->id ?>/edit"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Edit
                                </a>

                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $project->id ?>/delete"
                                          onsubmit="return confirm('Permanently delete <?= e(addslashes($project->name)) ?>? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
