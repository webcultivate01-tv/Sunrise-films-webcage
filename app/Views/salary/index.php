<?php

use App\Models\User;

/**
 * Monthly Salary - the Employee Salary List (spec s3).
 *
 * @var list<array{employee: User, earned: float, paid: float, outstanding: float, status: string, currentMonthEarned: float, periodPaid: float, periodOutstanding: float, periodStatus: string}> $rows
 * @var string $baseUrl
 * @var string $search
 * @var string $month
 * @var array{earned: float, paid: float, toPay: float, month: string} $summary
 */
$hasSearch  = $search !== '';
$hasMonth   = \App\Services\SalaryService::isValidMonth($month);
$hasFilters = $hasSearch || $hasMonth;
$monthLabel = pretty_month($summary['month']);
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Monthly Salary</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($rows) ?> employee<?= count($rows) === 1 ? '' : 's' ?>
            <?= $hasFilters ? 'matching your filters' : 'in scope' ?>.
        </p>
    </div>
    <a href="<?= e($baseUrl) ?>/history"
       class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Global History
    </a>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Earned <?= $hasMonth ? 'in' : 'this' ?> month</p>
        <p class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= e(money($summary['earned'])) ?></p>
        <p class="mt-1 text-xs text-slate-500"><?= e($monthLabel) ?></p>
    </div>
    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Paid <?= $hasMonth ? 'in' : 'this' ?> month</p>
        <p class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= e(money($summary['paid'])) ?></p>
        <p class="mt-1 text-xs text-slate-500">Already settled</p>
    </div>
    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total to pay</p>
        <p class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= e(money($summary['toPay'])) ?></p>
        <p class="mt-1 text-xs text-slate-500">Still outstanding <?= $hasMonth ? 'for ' . e($monthLabel) : 'this month' ?></p>
    </div>
</div>

<form method="get" action="<?= e($baseUrl) ?>" class="mb-5 flex flex-wrap items-center gap-3">
    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/suggest">
        <input type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search by name, ID or email"
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

    <label for="filter-month" class="sr-only">Month</label>
    <input type="month" id="filter-month" name="month" value="<?= e($month) ?>"
           class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">

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
            <?= $hasFilters ? 'No matches for your filters' : 'No employees in scope yet' ?>
        </p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters ? 'Try a different name, ID, email or month.' : 'Employees you manage will appear here.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Employee</th>
                    <th scope="col" class="px-5 py-3 font-medium"><?= $hasMonth ? e($monthLabel) . ' earned' : 'This month' ?></th>
                    <th scope="col" class="px-5 py-3 font-medium"><?= $hasMonth ? e($monthLabel) . ' paid' : 'Total paid' ?></th>
                    <th scope="col" class="px-5 py-3 font-medium"><?= $hasMonth ? e($monthLabel) . ' outstanding' : 'Outstanding' ?></th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($rows as $row): ?>
                    <?php
                        $employee    = $row['employee'];
                        $rowPaid     = $hasMonth ? $row['periodPaid'] : $row['paid'];
                        $rowOutst    = $hasMonth ? $row['periodOutstanding'] : $row['outstanding'];
                        $rowStatus   = $hasMonth ? $row['periodStatus'] : $row['status'];
                    ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5">
                            <a href="<?= e($baseUrl) ?>/<?= (int) $employee->id ?>"
                               class="font-medium text-ink underline-offset-2 hover:underline"><?= e($employee->name) ?></a>
                            <p class="text-xs text-slate-500"><?= e($employee->email) ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(money($row['currentMonthEarned'])) ?></td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(money($rowPaid)) ?></td>
                        <td class="px-5 py-3.5 font-medium text-ink"><?= e(money($rowOutst)) ?></td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= salary_status_badge($rowStatus) ?>">
                                <?= e(salary_status_label($rowStatus)) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e($baseUrl) ?>/<?= (int) $employee->id ?><?= $hasMonth ? '?month=' . e($month) : '' ?>"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    View
                                </a>
                                <?php if ($rowOutst > 0.0): ?>
                                    <a href="<?= e($baseUrl) ?>/<?= (int) $employee->id ?>/settle<?= $hasMonth ? '?month=' . e($month) : '' ?>"
                                       class="rounded-lg bg-brand-gradient px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:brightness-110">
                                        Settle
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
