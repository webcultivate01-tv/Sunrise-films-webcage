<?php

use App\Models\PaymentBill;

/**
 * View Bill - a read-only rendering of one payment's receipt, generated
 * automatically when a payment is recorded (including the advance or full
 * payment collected while adding a work in Work Management) and reachable
 * from Payment Management for any payment.
 *
 * Every value comes from the frozen PaymentBill, never from the live project,
 * photographer or company rows - an issued bill does not change.
 *
 * @var PaymentBill  $bill
 * @var string       $backUrl
 * @var string       $downloadUrl
 * @var ?string      $whatsappUrl
 * @var ?string      $whatsappName
 */
$company     = $bill;
$outstanding = $bill->outstanding;
$backLabel   = 'Back';
$showDownload = false; // Print and Share on WhatsApp only
?>
<?php require BASE_PATH . '/app/Views/partials/bill-actions.php'; ?>

<div class="invoice-card mx-auto max-w-2xl overflow-hidden rounded-2xl border-2 border-ink/20 bg-white shadow-sm">
    <!-- ============ Header banner ============ -->
    <?php require BASE_PATH . '/app/Views/partials/invoice-header.php'; ?>

    <!-- ============ Reference strip ============ -->
    <div class="flex flex-wrap items-center justify-between gap-4 bg-brand-50 px-8 py-4 text-sm">
        <div>
            <span class="font-semibold text-ink">Receipt No:</span>
            <span class="text-slate-600"><?= e($bill->reference) ?></span>
        </div>
        <div>
            <span class="font-semibold text-ink">Receipt Date:</span>
            <span class="text-slate-600"><?= e(date('j M Y', strtotime($bill->billDate))) ?></span>
        </div>
    </div>

    <!-- ============ Photographer / Project ============ -->
    <div class="grid gap-6 border-b border-line px-8 py-6 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Photographer</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($bill->photographerName ?? 'Unknown photographer') ?></p>
            <?php if ($bill->photographerPhone !== null): ?>
                <p class="mt-1 text-sm text-slate-600"><?= e($bill->photographerPhone) ?></p>
            <?php endif; ?>
            <?php if ($bill->photographerEmail !== null): ?>
                <p class="text-sm text-slate-600"><?= e($bill->photographerEmail) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Customer</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($bill->customerName ?? 'Unknown customer') ?></p>
        </div>
    </div>

    <!-- ============ Payment details ============ -->
    <div class="px-8 py-6">
        <dl class="divide-y divide-slate-100">
            <?php
            $rows = [
                'Payment type'             => payment_type_label($bill->paymentType),
                'Payment method'           => payment_method_label($bill->paymentMethod),
                'Amount received'          => money($bill->amount),
                'Project total value'      => money($bill->projectTotal),
                'Total collected to date'  => money($bill->collectedToDate),
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

        <?php if ($bill->notes !== null && $bill->notes !== ''): ?>
            <div class="mt-5 border-t border-line pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink"><?= e($bill->notes) ?></p>
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
