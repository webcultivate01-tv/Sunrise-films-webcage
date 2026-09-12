<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Setting;

/**
 * View Bill - a read-only rendering of one payment's receipt, generated
 * automatically for an advance collected at project creation (Work
 * Management) and reachable from Payment Management for any payment.
 *
 * @var Payment       $payment
 * @var Project|null  $project
 * @var Customer|null $customer
 * @var string       $reference
 * @var float        $collected
 * @var string       $backUrl
 * @var string       $downloadUrl
 */
$company     = Setting::current();
$total       = $project?->totalPayment ?? $payment->projectTotalPayment ?? 0.0;
$outstanding = max(0.0, $total - $collected);
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
    <a href="<?= e($backUrl) ?>" class="text-sm font-medium text-slate-500 underline-offset-2 hover:underline">&larr; Back</a>
    <div class="flex items-center gap-2">
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Print
        </button>
        <a href="<?= e($downloadUrl) ?>"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>
            </svg>
            Download PDF
        </a>
    </div>
</div>

<div class="invoice-card mx-auto max-w-2xl overflow-hidden rounded-2xl border-2 border-ink/20 bg-white shadow-sm">
    <!-- ============ Header banner ============ -->
    <?php require BASE_PATH . '/app/Views/partials/invoice-header.php'; ?>

    <!-- ============ Reference strip ============ -->
    <div class="flex flex-wrap items-center justify-between gap-4 bg-brand-50 px-8 py-4 text-sm">
        <div>
            <span class="font-semibold text-ink">Receipt No:</span>
            <span class="text-slate-600"><?= e($reference) ?></span>
        </div>
        <div>
            <span class="font-semibold text-ink">Receipt Date:</span>
            <span class="text-slate-600"><?= e(date('j M Y', strtotime($payment->paymentDate))) ?></span>
        </div>
    </div>

    <!-- ============ Customer / Project ============ -->
    <div class="grid gap-6 border-b border-line px-8 py-6 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Customer</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($payment->customerName ?? 'Unknown customer') ?></p>
            <?php if ($customer !== null): ?>
                <p class="mt-1 text-sm text-slate-600"><?= e($customer->phone) ?></p>
                <p class="text-sm text-slate-600"><?= e($customer->email) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Project</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($payment->projectName ?? 'Unknown project') ?></p>
        </div>
    </div>

    <!-- ============ Payment details ============ -->
    <div class="px-8 py-6">
        <dl class="divide-y divide-slate-100">
            <?php
            $rows = [
                'Payment type'             => payment_type_label($payment->paymentType),
                'Payment method'           => payment_method_label($payment->paymentMethod),
                'Amount received'          => money($payment->amount),
                'Project total value'      => money($total),
                'Total collected to date'  => money($collected),
            ];
            ?>
            <?php foreach ($rows as $label => $value): ?>
                <div class="flex items-center justify-between gap-4 py-2.5 text-sm">
                    <dt class="text-slate-500"><?= e($label) ?></dt>
                    <dd class="<?= $label === 'Amount received' ? 'text-base font-bold text-ink' : 'font-medium text-ink' ?>"><?= e($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <div class="mt-4 flex items-center justify-between gap-4 rounded-lg bg-brand-50 px-4 py-3">
            <span class="text-sm font-semibold text-ink">Outstanding Balance</span>
            <span class="text-base font-bold <?= $outstanding > 0.0 ? 'text-red-600' : 'text-emerald-600' ?>">
                <?= e(money($outstanding)) ?>
            </span>
        </div>

        <?php if ($payment->notes !== null && $payment->notes !== ''): ?>
            <div class="mt-5 border-t border-line pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink"><?= e($payment->notes) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============ Footer ============ -->
    <div class="border-t border-line px-8 py-8">
        <div class="flex justify-end">
            <div class="text-center">
                <p class="text-sm text-slate-500">For <?= e($company->companyName) ?></p>
                <div class="mt-10 w-48 border-t border-slate-300 pt-1.5">
                    <p class="text-xs text-slate-500">Authorised Signatory</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>
