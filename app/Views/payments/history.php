<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;

/**
 * Payment History (module spec s2, s5) - every transaction across every
 * project, filterable and sortable.
 *
 * @var list<Payment>        $payments
 * @var string               $baseUrl
 * @var array<string, string> $filters
 * @var list<Customer>       $customers
 * @var Project|null         $filterProject
 */
$hasFilters = array_filter($filters, static fn (string $value): bool => $value !== '') !== [];

$types   = ['' => 'All types'];
$methods = ['' => 'All methods'];

foreach (Payment::types() as $value) {
    $types[$value] = payment_type_label($value);
}

foreach (Payment::methods() as $value) {
    $methods[$value] = payment_method_label($value);
}

$sorts = [
    ''            => 'Newest payment',
    'oldest'      => 'Oldest payment',
    'amount_high' => 'Highest amount',
    'amount_low'  => 'Lowest amount',
];

$total = array_sum(array_map(static fn (Payment $p): float => $p->amount, $payments));
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 underline-offset-2 hover:underline">&larr; Back to Payment Management</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink">Payment History</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($payments) ?> transaction<?= count($payments) === 1 ? '' : 's' ?>
            <?= $hasFilters ? 'matching your filters' : 'on record' ?>
            &middot; <?= e(money($total)) ?> total
        </p>
        <?php if ($filterProject !== null): ?>
            <p class="mt-1 text-xs font-medium text-brand-700">Filtered to <?= e($filterProject->name) ?></p>
        <?php endif; ?>
    </div>
    <a href="<?= e($baseUrl) ?>/create"
       class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
        Record Payment
    </a>
</div>

<form method="get" action="<?= e($baseUrl) ?>/history" class="mb-5 flex flex-wrap items-center gap-3">
    <?php if ($filters['project_id'] !== ''): ?>
        <input type="hidden" name="project_id" value="<?= e($filters['project_id']) ?>">
    <?php endif; ?>

    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/history/suggest">
        <input type="search" name="q" value="<?= e($filters['q']) ?>"
               placeholder="Search by project, client or reference"
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

    <select name="customer_id" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <option value="">All customers</option>
        <?php foreach ($customers as $customer): ?>
            <option value="<?= (int) $customer->id ?>" <?= $filters['customer_id'] === (string) $customer->id ? 'selected' : '' ?>>
                <?= e($customer->name) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="type" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <?php foreach ($types as $value => $label): ?>
            <option value="<?= e((string) $value) ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <select name="method" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <?php foreach ($methods as $value => $label): ?>
            <option value="<?= e((string) $value) ?>" <?= $filters['method'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <input type="date" name="start_date" value="<?= e($filters['start_date']) ?>"
           class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
    <span class="text-sm text-slate-400">to</span>
    <input type="date" name="end_date" value="<?= e($filters['end_date']) ?>"
           class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">

    <select name="sort" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <?php foreach ($sorts as $value => $label): ?>
            <option value="<?= e((string) $value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Search
    </button>
    <?php if ($hasFilters): ?>
        <a href="<?= e($baseUrl) ?>/history" class="text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>
    <?php endif; ?>
</form>

<?php if ($payments === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink"><?= $hasFilters ? 'No payments match your filters' : 'No payments recorded yet' ?></p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters ? 'Try a different search term or clear the filters.' : 'Record your first payment to get started.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Date</th>
                    <th scope="col" class="px-5 py-3 font-medium">Project / Client</th>
                    <th scope="col" class="px-5 py-3 font-medium">Type</th>
                    <th scope="col" class="px-5 py-3 font-medium">Method</th>
                    <th scope="col" class="px-5 py-3 font-medium">Reference</th>
                    <th scope="col" class="px-5 py-3 font-medium">Amount</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($payments as $payment): ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5 text-slate-600"><?= e(date('j M Y', strtotime($payment->paymentDate))) ?></td>
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-ink"><?= e($payment->projectName ?? 'Unknown project') ?></p>
                            <p class="text-xs text-slate-500"><?= e($payment->customerName ?? 'Unknown customer') ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(payment_type_label($payment->paymentType)) ?></td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e(payment_method_label($payment->paymentMethod)) ?></td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e($payment->referenceNo ?? '-') ?></td>
                        <td class="px-5 py-3.5 font-medium text-ink"><?= e(money($payment->amount)) ?></td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="<?= e($baseUrl) ?>/<?= (int) $payment->id ?>"
                               class="text-xs font-semibold text-brand-700 hover:underline">View</a>
                            <span class="mx-1.5 text-slate-300">&middot;</span>
                            <a href="<?= e($baseUrl) ?>/bills/<?= (int) $payment->id ?>"
                               class="text-xs font-semibold text-brand-700 hover:underline">Bill</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
