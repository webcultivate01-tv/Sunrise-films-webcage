<?php

use App\Models\SalarySettlement;
use App\Models\Setting;

/**
 * View Bill (spec s9): a read-only rendering of one settlement's frozen
 * figures, shared by the Admin/Manager and Employee sides - only $backUrl and
 * $downloadUrl differ between them.
 *
 * @var SalarySettlement $settlement
 * @var string           $backUrl
 * @var string           $downloadUrl
 */
$company = Setting::current();
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
            <span class="font-semibold text-ink">Bill No:</span>
            <span class="text-slate-600"><?= e($settlement->referenceNo) ?></span>
        </div>
        <div>
            <span class="font-semibold text-ink">Bill Date:</span>
            <span class="text-slate-600"><?= e(date('j M Y', strtotime($settlement->settledAt))) ?></span>
        </div>
    </div>

    <!-- ============ Employee ============ -->
    <div class="grid gap-6 border-b border-line px-8 py-6 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Employee</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($settlement->employeeNameSnapshot) ?></p>
            <p class="mt-1 text-sm text-slate-600"><?= e($settlement->employeePhoneSnapshot ?? 'Not recorded') ?></p>
            <p class="text-sm text-slate-600"><?= e($settlement->employeeEmailSnapshot) ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Employee ID</p>
            <p class="mt-2 text-sm font-semibold text-ink">EMP-<?= e(str_pad((string) $settlement->employeeId, 4, '0', STR_PAD_LEFT)) ?></p>
        </div>
    </div>

    <!-- ============ Settlement details ============ -->
    <div class="px-8 py-6">
        <dl class="divide-y divide-slate-100">
            <?php
            $rows = [
                'Salary month'                  => pretty_month($settlement->salaryMonth),
                'Total earnings for the month'  => money($settlement->monthEarned),
                'Previously settled'            => money($settlement->previousSettled),
                'This settlement'               => money($settlement->amount),
                'Remaining outstanding'         => money($settlement->outstandingAfter),
                'Settlement date'               => pretty_date($settlement->settledAt),
                'Settled by'                    => $settlement->settledByName ?? 'System',
            ];
            ?>
            <?php foreach ($rows as $label => $value): ?>
                <div class="flex items-center justify-between gap-4 py-2.5 text-sm">
                    <dt class="text-slate-500"><?= e($label) ?></dt>
                    <dd class="<?= $label === 'This settlement' ? 'text-base font-bold text-ink' : 'font-medium text-ink' ?>"><?= e($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <?php if ($settlement->notes !== null && $settlement->notes !== ''): ?>
            <div class="mt-5 border-t border-line pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink"><?= e($settlement->notes) ?></p>
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
