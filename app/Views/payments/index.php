<?php

use App\Models\Photographer;

/**
 * Payment Management - the Payment Dashboard and Project Payment Overview
 * (module spec s1, s4, s9).
 *
 * @var array{totalProjectValue: float, totalCollected: float, totalOutstanding: float, advanceCollected: float, dueThisMonth: float, overduePayments: float, fullyPaid: int, partiallyPaid: int} $summary
 * @var list<array{project: \App\Models\Project, collected: float, outstanding: float, status: string, paymentsCount: int, lastPaymentDate: ?string}> $rows
 * @var string         $baseUrl
 * @var string         $search
 * @var string         $status
 * @var string         $photographerId
 * @var list<Photographer> $photographers
 */
$hasFilters = $search !== '' || $status !== '' || $photographerId !== '';

$filters = ['' => 'All statuses'];

foreach (\App\Services\PaymentService::statuses() as $value) {
    $filters[$value] = payment_status_label($value);
}

$tiles = [
    ['label' => 'Total Project Value', 'value' => $summary['totalProjectValue'], 'note' => 'Across every project'],
    ['label' => 'Total Collected', 'value' => $summary['totalCollected'], 'note' => 'Received so far'],
    ['label' => 'Total Outstanding', 'value' => $summary['totalOutstanding'], 'note' => 'Still receivable'],
    ['label' => 'Advance Collected', 'value' => $summary['advanceCollected'], 'note' => 'Advance payments to date'],
    ['label' => 'Due This Month', 'value' => $summary['dueThisMonth'], 'note' => 'Deadline falls this month'],
    ['label' => 'Overdue Payments', 'value' => $summary['overduePayments'], 'note' => 'Past deadline, still owed'],
];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Payment Management</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($rows) ?> project<?= count($rows) === 1 ? '' : 's' ?>
            <?= $hasFilters ? 'matching your filters' : 'on record' ?>.
            <?php if ($status === ''): ?>
                Fully paid projects are hidden here - select "Fully Paid" in the status filter to see them.
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= e($baseUrl) ?>/history"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Payment History
        </a>
        <a href="<?= e($baseUrl) ?>/create"
           class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
            Record Payment
        </a>
    </div>
</div>

<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7">
    <?php foreach ($tiles as $tile): ?>
        <div class="rounded-xl border border-line bg-white p-3">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500"><?= e($tile['label']) ?></p>
            <p class="mt-1 text-base font-semibold text-ink"><?= e(money((float) $tile['value'])) ?></p>
            <p class="mt-0.5 truncate text-[11px] text-slate-500"><?= e($tile['note']) ?></p>
        </div>
    <?php endforeach; ?>
    <div class="rounded-xl border border-line bg-white p-3">
        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Project Status</p>
        <p class="mt-1 text-xs text-ink">
            <span class="font-semibold"><?= (int) $summary['fullyPaid'] ?></span> fully paid
        </p>
        <p class="text-xs text-ink">
            <span class="font-semibold"><?= (int) $summary['partiallyPaid'] ?></span> partially paid
        </p>
    </div>
</div>

<form method="get" action="<?= e($baseUrl) ?>" class="mb-5 flex flex-wrap items-center gap-3">
    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/suggest">
        <input type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search by customer or photographer name"
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

    <label for="filter-photographer" class="sr-only">Photographer</label>
    <select id="filter-photographer" name="photographer_id"
            class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <option value="">All photographers</option>
        <?php foreach ($photographers as $photographer): ?>
            <option value="<?= (int) $photographer->id ?>" <?= $photographerId === (string) $photographer->id ? 'selected' : '' ?>>
                <?= e($photographer->name) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="filter-status" class="sr-only">Payment status</label>
    <select id="filter-status" name="status"
            class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <?php foreach ($filters as $value => $label): ?>
            <option value="<?= e((string) $value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
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

<?php if ($rows === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">
            <?= $hasFilters ? 'No projects match your filters' : 'No projects yet' ?>
        </p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters
                ? 'Try a different search term or clear the filters.'
                : 'Projects registered in Work Management will appear here.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Customer</th>
                    <th scope="col" class="px-5 py-3 font-medium">Total value</th>
                    <th scope="col" class="px-5 py-3 font-medium">Collected</th>
                    <th scope="col" class="px-5 py-3 font-medium">Outstanding</th>
                    <th scope="col" class="px-5 py-3 font-medium">Last payment</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($rows as $row): ?>
                    <?php $project = $row['project']; ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-ink"><?= e($project->customerName) ?></p>
                            <p class="text-xs text-slate-500"><?= e($project->photographerName ?? 'Unknown photographer') ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(money($project->totalPayment)) ?></td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(money($row['collected'])) ?></td>
                        <td class="px-5 py-3.5 font-medium text-ink"><?= e(money($row['outstanding'])) ?></td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <?= $row['lastPaymentDate'] !== null ? e(date('j M Y', strtotime($row['lastPaymentDate']))) : '-' ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= payment_status_badge($row['status']) ?>">
                                <?= e(payment_status_label($row['status'])) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e($baseUrl) ?>/history?project_id=<?= (int) $project->id ?>"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    History
                                </a>
                                <?php if ($row['outstanding'] > 0.0): ?>
                                    <a href="<?= e($baseUrl) ?>/create?project_id=<?= (int) $project->id ?>"
                                       class="rounded-lg bg-brand-gradient px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:brightness-110">
                                        Record Payment
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
