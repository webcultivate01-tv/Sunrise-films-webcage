<?php

use App\Models\Photographer;
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
 * @var Photographer|null $photographer
 * @var string       $reference
 * @var float        $collected
 * @var string       $backUrl
 * @var string       $downloadUrl
 * @var ?string      $whatsappUrl
 * @var ?string      $whatsappName
 */
$company     = Setting::current();
$total       = $project?->totalPayment ?? $payment->projectTotalPayment ?? 0.0;
$outstanding = max(0.0, $total - $collected);
$backLabel   = 'Back';
?>
<?php require BASE_PATH . '/app/Views/partials/bill-actions.php'; ?>

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

    <!-- ============ Photographer / Project ============ -->
    <div class="grid gap-6 border-b border-line px-8 py-6 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Photographer</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($payment->photographerName ?? 'Unknown photographer') ?></p>
            <?php if ($photographer !== null): ?>
                <p class="mt-1 text-sm text-slate-600"><?= e($photographer->phone) ?></p>
                <p class="text-sm text-slate-600"><?= e($photographer->email) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Customer</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($payment->customerName ?? 'Unknown customer') ?></p>
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

<style>
    /* The receipt prints on its own - the panel layout already drops the
       sidebar, top bar and action bar, and these rules give the card the
       whole sheet. Printing only ever happens when the admin asks for it
       with the Print button; opening the bill never starts a print. */
    @media print {
        @page { margin: 0; }
        #panel-main { padding: 12mm !important; }
        .invoice-card { box-shadow: none !important; margin: 0 auto !important; }
    }
</style>
