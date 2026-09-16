<?php

use App\Models\Payment;
use App\Models\Project;

/**
 * One project, read only, with the actions available on it.
 *
 * @var Project $project
 * @var string  $baseUrl
 * @var bool    $canDelete
 * @var array{totalValue: float, collected: float, outstanding: float, status: string, collectionPercentage: float, paymentsCount: int, lastPaymentDate: ?string, timeline: list<Payment>} $paymentSummary
 * @var string  $paymentsBaseUrl
 */
$fields = [
    ['label' => 'Photographer', 'value' => $project->photographerName ?? 'Unknown photographer', 'icon' => 'user'],
    ['label' => 'Receivable folder', 'value' => $project->folderName, 'icon' => 'folder'],
    ['label' => 'Deadline', 'value' => date('j M Y', strtotime($project->deadline)), 'icon' => 'clock'],
    ['label' => 'Total payment', 'value' => money($project->totalPayment), 'icon' => 'cash'],
    ['label' => 'Registered by', 'value' => $project->createdByName ?? 'Unknown', 'icon' => 'badge'],
    ['label' => 'Registered', 'value' => pretty_date($project->createdAt, 'Unknown'), 'icon' => 'clock'],
];

$fieldIcons = [
    'user'   => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>',
    'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
    'clock'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    'cash'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
    'badge'  => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>',
];
?>
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="<?= e($baseUrl) ?>"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Work Management
        </a>
        <div class="mt-4">
            <h1 class="truncate text-2xl font-semibold tracking-tight text-ink"><?= e($project->customerName) ?></h1>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= project_status_badge($project->status) ?>">
                    <?= e(project_status_label($project->status)) ?>
                </span>
                <?php if ($project->isOverdue()): ?>
                    <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                        Overdue
                    </span>
                <?php endif; ?>
            </div>
            <p class="mt-2 max-w-2xl whitespace-pre-line text-sm text-slate-600"><?= e($project->description) ?></p>
        </div>
    </div>

    <a href="<?= e($baseUrl) ?>/<?= (int) $project->id ?>/edit"
       class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
        </svg>
        Edit project
    </a>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7 lg:col-span-2">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-ink">Project details</h2>
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
                        <path d="m9 14 2 2 4-4"/><rect x="3" y="4" width="18" height="17" rx="2"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Project status</h2>
            </div>
            <p class="mt-3 text-sm text-slate-500">Update this project's progress as work moves along.</p>

            <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $project->id ?>/status" class="mt-5 space-y-3">
                <?= csrf_field() ?>
                <select name="status" class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
                    <?php foreach (Project::statuses() as $value): ?>
                        <option value="<?= e($value) ?>" <?= $project->status === $value ? 'selected' : '' ?>>
                            <?= e(project_status_label($value)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Update status
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
                    Deleting removes this record for good. Marking it cancelled instead keeps its history.
                </p>
                <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $project->id ?>/delete" class="mt-5"
                      onsubmit="return confirm('Permanently delete <?= e(addslashes($project->customerName)) ?>? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="w-full rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100">
                        Delete project
                    </button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>

<section class="mt-5 rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-ink">Payment summary</h2>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= e($baseUrl) ?>/<?= (int) $project->id ?>/bill"
               class="rounded-lg border border-slate-300 px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                Generate Bill
            </a>
            <a href="<?= e($paymentsBaseUrl) ?>/history?project_id=<?= (int) $project->id ?>"
               class="rounded-lg border border-slate-300 px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                Full History
            </a>
            <?php if ($paymentSummary['outstanding'] > 0.0): ?>
                <a href="<?= e($paymentsBaseUrl) ?>/create?project_id=<?= (int) $project->id ?>"
                   class="rounded-lg bg-brand-gradient px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:brightness-110">
                    Record Payment
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-line bg-slate-50/60 p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Project value</p>
            <p class="mt-1 text-lg font-semibold text-ink"><?= e(money($paymentSummary['totalValue'])) ?></p>
        </div>
        <div class="rounded-xl border border-line bg-slate-50/60 p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total collected</p>
            <p class="mt-1 text-lg font-semibold text-ink"><?= e(money($paymentSummary['collected'])) ?></p>
        </div>
        <div class="rounded-xl border border-line bg-slate-50/60 p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Outstanding</p>
            <p class="mt-1 text-lg font-semibold text-ink"><?= e(money($paymentSummary['outstanding'])) ?></p>
        </div>
        <div class="rounded-xl border border-line bg-slate-50/60 p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Collection %</p>
            <p class="mt-1 text-lg font-semibold text-ink"><?= e(number_format($paymentSummary['collectionPercentage'], 1)) ?>%</p>
        </div>
        <div class="rounded-xl border border-line bg-slate-50/60 p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Payment status</p>
            <p class="mt-1.5">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= payment_status_badge($paymentSummary['status']) ?>">
                    <?= e(payment_status_label($paymentSummary['status'])) ?>
                </span>
            </p>
        </div>
    </div>

    <h3 class="mt-6 text-xs font-semibold uppercase tracking-wide text-slate-500">Payment timeline</h3>
    <?php if ($paymentSummary['timeline'] === []): ?>
        <p class="mt-2 text-sm text-slate-500">No payments recorded for this project yet.</p>
    <?php else: ?>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="py-2 pr-4 font-medium">Date</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Type</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Amount</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Collected</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Remaining</th>
                        <th scope="col" class="py-2 text-right font-medium">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $runningCollected = 0.0; ?>
                    <?php foreach ($paymentSummary['timeline'] as $entry): ?>
                        <?php
                            $runningCollected += $entry->amount;
                            $runningRemaining  = max(0.0, $paymentSummary['totalValue'] - $runningCollected);
                        ?>
                        <tr>
                            <td class="py-2.5 pr-4 text-slate-600"><?= e(date('j M Y', strtotime($entry->paymentDate))) ?></td>
                            <td class="py-2.5 pr-4 text-slate-600"><?= e(payment_type_label($entry->paymentType)) ?></td>
                            <td class="py-2.5 pr-4 font-medium text-ink"><?= e(money($entry->amount)) ?></td>
                            <td class="py-2.5 pr-4 text-slate-600"><?= e(money($runningCollected)) ?></td>
                            <td class="py-2.5 pr-4 text-slate-600"><?= e(money($runningRemaining)) ?></td>
                            <td class="py-2.5 text-right">
                                <a href="<?= e($paymentsBaseUrl) ?>/<?= (int) $entry->id ?>"
                                   class="text-xs font-semibold text-brand-700 hover:underline">View</a>
                                <span class="mx-1 text-slate-300">&middot;</span>
                                <a href="<?= e($paymentsBaseUrl) ?>/bills/<?= (int) $entry->id ?>"
                                   class="text-xs font-semibold text-brand-700 hover:underline">Bill</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
