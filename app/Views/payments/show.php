<?php

use App\Models\Payment;
use App\Models\Project;
use App\Services\PaymentService;

/**
 * Payment Details (module spec s7) - one transaction, fully traceable: what
 * came before it and what the project's running totals were immediately
 * after it landed.
 *
 * @var Payment      $payment
 * @var Project|null $project
 * @var string|null  $projectUrl
 * @var Payment|null $previousPayment
 * @var float        $collectedAfter
 * @var float        $remainingAfter
 * @var string       $baseUrl
 */
$status = $project !== null ? PaymentService::statusFor($project, $collectedAfter) : null;

$fields = [
    ['label' => 'Client', 'value' => $payment->customerName ?? 'Unknown customer'],
    ['label' => 'Project', 'value' => $payment->projectName ?? 'Unknown project'],
    ['label' => 'Amount', 'value' => money($payment->amount)],
    ['label' => 'Payment type', 'value' => payment_type_label($payment->paymentType)],
    ['label' => 'Payment date', 'value' => date('j M Y', strtotime($payment->paymentDate))],
    ['label' => 'Payment method', 'value' => payment_method_label($payment->paymentMethod)],
    ['label' => 'Reference / transaction no.', 'value' => $payment->referenceNo ?? 'Not recorded'],
    ['label' => 'Received by', 'value' => $payment->receivedByName ?? 'Unknown'],
    ['label' => 'Recorded on', 'value' => pretty_date($payment->createdAt, 'Unknown')],
];
?>
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="<?= e($baseUrl) ?>/history"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Payment History
        </a>
        <div class="mt-4">
            <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= e(money($payment->amount)) ?></h1>
            <p class="mt-1 text-sm text-slate-500">
                <?= e(payment_type_label($payment->paymentType)) ?> for <?= e($payment->projectName ?? 'Unknown project') ?>
            </p>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <a href="<?= e($baseUrl) ?>/bills/<?= (int) $payment->id ?>"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            View Bill
        </a>
        <?php if ($project !== null): ?>
            <a href="<?= e($baseUrl) ?>/create?project_id=<?= (int) $project->id ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                Record Another Payment
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7 lg:col-span-2">
        <h2 class="text-base font-semibold text-ink">Transaction details</h2>
        <dl class="mt-6 grid gap-x-6 gap-y-5 sm:grid-cols-2">
            <?php foreach ($fields as $field): ?>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= e($field['label']) ?></dt>
                    <dd class="mt-0.5 whitespace-pre-line break-words text-sm font-medium text-ink"><?= e((string) $field['value']) ?></dd>
                </div>
            <?php endforeach; ?>
            <?php if ($payment->notes !== null): ?>
                <div class="sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Notes</dt>
                    <dd class="mt-0.5 whitespace-pre-line break-words text-sm text-ink"><?= e($payment->notes) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </section>

    <div class="space-y-5">
        <section class="rounded-2xl border border-line bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-ink">Running totals</h2>
            <dl class="mt-4 space-y-4">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Previous payment</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink">
                        <?= $previousPayment !== null
                            ? e(money($previousPayment->amount) . ' on ' . date('j M Y', strtotime($previousPayment->paymentDate)))
                            : 'None - this was the first payment' ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Total collected after this payment</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e(money($collectedAfter)) ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Remaining after this payment</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e(money($remainingAfter)) ?></dd>
                </div>
                <?php if ($status !== null): ?>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Current project status</dt>
                        <dd class="mt-1.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= payment_status_badge($status) ?>">
                                <?= e(payment_status_label($status)) ?>
                            </span>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>

        <?php if ($projectUrl !== null): ?>
            <a href="<?= e($projectUrl) ?>"
               class="block rounded-2xl border border-line bg-white p-6 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-slate-50">
                View project &rarr;
            </a>
        <?php endif; ?>
    </div>
</div>
