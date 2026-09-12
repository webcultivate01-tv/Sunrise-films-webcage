<?php

use App\Models\Customer;

/**
 * Customer Management - list, search, filter and row actions
 * (module spec s5, s13, s16).
 *
 * @var list<Customer>     $customers
 * @var string             $baseUrl
 * @var string             $search
 * @var string             $status
 * @var array<string, int> $counts
 * @var bool               $canDelete
 */
$hasFilters = $search !== '' || $status !== '';
$filters    = [
    ''                         => 'All customers',
    Customer::STATUS_ACTIVE    => 'Active (' . ($counts[Customer::STATUS_ACTIVE] ?? 0) . ')',
    Customer::STATUS_INACTIVE  => 'Inactive (' . ($counts[Customer::STATUS_INACTIVE] ?? 0) . ')',
];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Customer Management</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?>
            <?= $hasFilters ? 'matching your filters' : 'registered' ?>.
        </p>
    </div>
    <a href="<?= e($baseUrl) ?>/create"
       class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
        Add Customer
    </a>
</div>

<form method="get" action="<?= e($baseUrl) ?>" class="mb-5 flex flex-wrap items-center gap-3">
    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/suggest">
        <input type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search by name, email, mobile or address"
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

    <label for="filter-status" class="sr-only">Status</label>
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

<?php if ($customers === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">
            <?= $hasFilters ? 'No customers match your filters' : 'No customers yet' ?>
        </p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasFilters
                ? 'Try a different search term or clear the status filter.'
                : 'Register your first customer to get started.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Customer</th>
                    <th scope="col" class="px-5 py-3 font-medium">Contact</th>
                    <th scope="col" class="px-5 py-3 font-medium">Address</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($customers as $customer): ?>
                    <tr class="cursor-pointer hover:bg-slate-50/70" data-href="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>">
                        <td class="px-5 py-3.5">
                            <a href="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>"
                               class="font-medium text-ink underline-offset-2 hover:underline"><?= e($customer->name) ?></a>
                            <?php if ($customer->createdByName !== null): ?>
                                <p class="text-xs text-slate-500">Added by <?= e($customer->createdByName) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <p><?= e($customer->email) ?></p>
                            <p class="text-xs text-slate-500"><?= e($customer->phone) ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <p class="max-w-xs truncate"><?= e($customer->address) ?></p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= status_badge($customer->status) ?>">
                                <?= e(ucfirst($customer->status)) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>/edit"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Edit
                                </a>

                                <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>/status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status"
                                           value="<?= $customer->isActive() ? Customer::STATUS_INACTIVE : Customer::STATUS_ACTIVE ?>">
                                    <button type="submit"
                                            title="<?= $customer->isActive() ? 'Deactivate' : 'Activate' ?>"
                                            aria-label="<?= $customer->isActive() ? 'Deactivate' : 'Activate' ?> <?= e($customer->name) ?>"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full border transition <?= $customer->isActive()
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100'
                                                : 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100' ?>">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9"/>
                                            <line x1="5.5" y1="18.5" x2="18.5" y2="5.5"/>
                                        </svg>
                                    </button>
                                </form>

                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>/delete"
                                          onsubmit="return confirm('Permanently delete <?= e(addslashes($customer->name)) ?>? This cannot be undone.');">
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
