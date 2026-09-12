<?php

use App\Models\Customer;

/**
 * One customer, read only, with the actions available on it (module spec s5).
 *
 * @var Customer $customer
 * @var string   $baseUrl
 * @var bool     $canDelete
 */
$fields = [
    ['label' => 'Customer name', 'value' => $customer->name, 'icon' => 'user'],
    ['label' => 'Email address', 'value' => $customer->email, 'icon' => 'mail'],
    ['label' => 'Mobile number', 'value' => $customer->phone, 'icon' => 'phone'],
    ['label' => 'Address', 'value' => $customer->address, 'icon' => 'pin'],
    ['label' => 'Registered by', 'value' => $customer->createdByName ?? 'Unknown', 'icon' => 'badge'],
    ['label' => 'Registered', 'value' => pretty_date($customer->createdAt, 'Unknown'), 'icon' => 'clock'],
    ['label' => 'Last updated', 'value' => pretty_date($customer->updatedAt, 'Never'), 'icon' => 'clock'],
];

$fieldIcons = [
    'user'  => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>',
    'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
    'phone' => '<path d="M6.5 4h2.7l1.3 4-2 1.4a11 11 0 0 0 5.1 5.1l1.4-2 4 1.3v2.7a2 2 0 0 1-2.2 2A17 17 0 0 1 4.5 6.2 2 2 0 0 1 6.5 4Z"/>',
    'pin'   => '<path d="M12 21s-6.5-5.6-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/>',
    'badge' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>',
    'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
];
?>
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="<?= e($baseUrl) ?>"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Customer Management
        </a>
        <div class="mt-4 flex items-center gap-4">
            <div class="relative shrink-0">
                <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-gradient text-base font-semibold text-white ring-4 ring-white shadow-md"><?= e($customer->initials()) ?></span>
                <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full ring-2 ring-white <?= $customer->isActive() ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold tracking-tight text-ink"><?= e($customer->name) ?></h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= status_badge($customer->status) ?>">
                        <?= e(ucfirst($customer->status)) ?>
                    </span>
                    <span class="text-xs text-slate-400">Customer since <?= e(pretty_date($customer->createdAt, 'Unknown')) ?></span>
                </div>
            </div>
        </div>
    </div>

    <a href="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>/edit"
       class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
        </svg>
        Edit customer
    </a>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7 lg:col-span-2">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-ink">Customer details</h2>
        </div>
        <dl class="mt-6 grid gap-x-6 gap-y-6 sm:grid-cols-2">
            <?php foreach ($fields as $field): ?>
                <div class="flex gap-3">
                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-50 text-slate-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= $fieldIcons[$field['icon']] ?>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= e($field['label']) ?></dt>
                        <dd class="mt-0.5 whitespace-pre-line break-words text-sm font-medium text-ink"><?= e((string) $field['value']) ?></dd>
                    </div>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <div class="space-y-5">
        <section class="rounded-2xl border border-line bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3 4 6.5V11c0 4.9 3.2 8.9 8 10 4.8-1.1 8-5.1 8-10V6.5Z"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Record status</h2>
            </div>
            <p class="mt-3 text-sm text-slate-500">
                <?= $customer->isActive()
                    ? 'This customer is active and available for new work.'
                    : 'This customer is inactive. Their record is kept for history.' ?>
            </p>

            <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>/status" class="mt-5">
                <?= csrf_field() ?>
                <input type="hidden" name="status"
                       value="<?= $customer->isActive() ? Customer::STATUS_INACTIVE : Customer::STATUS_ACTIVE ?>">
                <button type="submit"
                        class="w-full rounded-lg border px-4 py-2.5 text-sm font-semibold transition <?= $customer->isActive()
                            ? 'border-amber-200 text-amber-700 hover:bg-amber-50'
                            : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' ?>">
                    <?= $customer->isActive() ? 'Deactivate customer' : 'Activate customer' ?>
                </button>
            </form>
        </section>

        <?php if ($canDelete): ?>
            <section class="rounded-2xl border border-red-100 bg-red-50/40 p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-red-100 text-red-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 7h16"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2m1.5 0-.8 12.1A2 2 0 0 1 16.7 21H7.3a2 2 0 0 1-2-1.9L4.5 7Z"/>
                        </svg>
                    </span>
                    <h2 class="text-base font-semibold text-ink">Danger zone</h2>
                </div>
                <p class="mt-3 text-sm text-slate-600">
                    Deleting removes this record for good. Deactivating instead keeps their history and can be undone.
                </p>
                <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $customer->id ?>/delete" class="mt-5"
                      onsubmit="return confirm('Permanently delete <?= e(addslashes($customer->name)) ?>? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="w-full rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100">
                        Delete customer
                    </button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
