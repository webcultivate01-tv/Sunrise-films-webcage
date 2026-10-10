<?php

use App\Models\User;

/**
 * Salary tab of Payment History - every salary transaction, numbered from 1.
 *
 * @var string                     $baseUrl
 * @var string                     $salaryBaseUrl
 * @var list<array<string, mixed>> $entries
 * @var array<string, string>      $filters
 * @var list<User>                 $employees
 */
$employeeId = $filters['employee_id'] ?? '';
$type       = $filters['type'] ?? '';
$month      = $filters['month'] ?? '';
$startDate  = $filters['start_date'] ?? '';
$endDate    = $filters['end_date'] ?? '';
$q          = $filters['q'] ?? '';

$hasFilters = $employeeId !== '' || $type !== '' || $month !== '' || $startDate !== '' || $endDate !== '' || $q !== '';
$field      = 'rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200';
?>
<form method="get" action="<?= e($baseUrl) ?>/history" class="mb-5 flex flex-wrap items-center gap-3">
    <input type="hidden" name="tab" value="salary">

    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by employee, reference or task"
           autocomplete="off" class="min-w-0 flex-1 sm:max-w-xs <?= $field ?>">

    <select name="employee_id" class="<?= $field ?>">
        <option value="">All employees</option>
        <?php foreach ($employees as $employee): ?>
            <option value="<?= (int) $employee->id ?>" <?= $employeeId === (string) $employee->id ? 'selected' : '' ?>><?= e($employee->name) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="type" class="<?= $field ?>">
        <option value="">All types</option>
        <option value="salary_earned" <?= $type === 'salary_earned' ? 'selected' : '' ?>>Salary Earned</option>
        <option value="settlement" <?= $type === 'settlement' ? 'selected' : '' ?>>Settlement</option>
    </select>

    <input type="month" name="month" value="<?= e($month) ?>" class="<?= $field ?>">
    <input type="date" name="start_date" value="<?= e($startDate) ?>" class="<?= $field ?>">
    <span class="text-sm text-slate-400">to</span>
    <input type="date" name="end_date" value="<?= e($endDate) ?>" class="<?= $field ?>">

    <button type="submit"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Search
    </button>
    <?php if ($hasFilters): ?>
        <a href="<?= e($baseUrl) ?>/history?tab=salary" class="text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>
    <?php endif; ?>
</form>

<?php if ($entries === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink"><?= $hasFilters ? 'No salary transactions match your filters' : 'No salary transactions yet' ?></p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters ? 'Try a different search term or clear the filters.' : 'Salary activity will appear here as it happens.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="w-12 px-5 py-3 font-medium">No</th>
                    <th scope="col" class="px-5 py-3 font-medium">Date</th>
                    <th scope="col" class="px-5 py-3 font-medium">Employee</th>
                    <th scope="col" class="px-5 py-3 font-medium">Type</th>
                    <th scope="col" class="px-5 py-3 font-medium">Reference</th>
                    <th scope="col" class="px-5 py-3 font-medium">Detail</th>
                    <th scope="col" class="px-5 py-3 font-medium">Amount</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($entries as $i => $entry): ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5 tabular-nums text-slate-500"><?= $i + 1 ?></td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(pretty_date($entry['date'])) ?></td>
                        <td class="px-5 py-3.5">
                            <a href="<?= e($salaryBaseUrl) ?>/<?= (int) $entry['employeeId'] ?>"
                               class="font-medium text-ink underline-offset-2 hover:underline"><?= e($entry['employeeName']) ?></a>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= $entry['type'] === 'settlement' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-sky-50 text-sky-700 ring-sky-600/20' ?>">
                                <?= $entry['type'] === 'settlement' ? 'Settlement' : 'Salary Earned' ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e($entry['reference'] ?? '-') ?></td>
                        <td class="px-5 py-3.5 text-slate-500"><?= e($entry['detail']) ?></td>
                        <td class="px-5 py-3.5 font-medium text-ink"><?= e(money($entry['amount'])) ?></td>
                        <td class="px-5 py-3.5 text-right">
                            <?php if ($entry['settlementId'] !== null): ?>
                                <a href="<?= e($salaryBaseUrl) ?>/bills/<?= (int) $entry['settlementId'] ?>"
                                   class="text-xs font-semibold text-brand-700 hover:underline">View Bill</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
