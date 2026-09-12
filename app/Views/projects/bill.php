<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Setting;

/**
 * Project Invoice/Bill - a full, printable statement for one project: the
 * company header, the customer and project it is billed to, the project's
 * value, every payment collected against it and what remains outstanding.
 * Rendered inside the bare "invoice" layout (no sidebar) so it prints clean.
 *
 * @var Project      $project
 * @var Customer|null $customer
 * @var string       $baseUrl
 * @var string       $reference
 * @var array{totalValue: float, collected: float, outstanding: float, status: string, collectionPercentage: float, paymentsCount: int, lastPaymentDate: ?string, timeline: list<Payment>} $summary
 * @var string       $backUrl
 * @var string       $downloadUrl
 */
$company = Setting::current();
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
    <a href="<?= e($backUrl) ?>" class="text-sm font-medium text-slate-500 underline-offset-2 hover:underline">&larr; Back to project</a>
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

<div class="invoice-card overflow-hidden rounded-2xl border-2 border-ink/20 bg-white shadow-sm">
    <!-- ============ Header banner ============ -->
    <?php require BASE_PATH . '/app/Views/partials/invoice-header.php'; ?>

    <!-- ============ Reference strip ============ -->
    <div class="flex flex-wrap items-center justify-between gap-4 bg-brand-50 px-8 py-4 text-sm">
        <div>
            <span class="font-semibold text-ink">Invoice No:</span>
            <span class="text-slate-600"><?= e($reference) ?></span>
        </div>
        <div>
            <span class="font-semibold text-ink">Invoice Date:</span>
            <span class="text-slate-600"><?= e(date('j M Y')) ?></span>
        </div>
        <div>
            <span class="font-semibold text-ink">Project Ref:</span>
            <span class="text-slate-600">#<?= (int) $project->id ?></span>
        </div>
    </div>

    <!-- ============ Bill To / Project ============ -->
    <div class="grid gap-6 border-b border-line px-8 py-6 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bill To</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($customer?->name ?? 'Unknown customer') ?></p>
            <?php if ($customer !== null): ?>
                <p class="mt-1 text-sm text-slate-600"><?= e($customer->phone) ?></p>
                <p class="text-sm text-slate-600"><?= e($customer->email) ?></p>
                <p class="text-sm text-slate-600"><?= e($customer->address) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Project</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($project->name) ?></p>
            <p class="mt-1 text-sm text-slate-600">Folder: <?= e($project->folderName) ?></p>
            <p class="text-sm text-slate-600">Deadline: <?= e(date('j M Y', strtotime($project->deadline))) ?></p>
            <p class="mt-1.5">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= project_status_badge($project->status) ?>">
                    <?= e(project_status_label($project->status)) ?>
                </span>
            </p>
        </div>
    </div>

    <!-- ============ Item table ============ -->
    <div class="px-8 py-6">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-brand-700 text-left text-xs font-semibold uppercase tracking-wide text-white">
                    <th class="rounded-l-lg py-3 pl-4">Description</th>
                    <th class="py-3 text-center">Qty</th>
                    <th class="py-3 text-right">Rate</th>
                    <th class="rounded-r-lg py-3 pr-4 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-line">
                    <td class="py-4 pl-4">
                        <p class="font-medium text-ink"><?= e($project->name) ?></p>
                        <p class="mt-0.5 whitespace-pre-line text-xs text-slate-500"><?= e($project->description) ?></p>
                    </td>
                    <td class="py-4 text-center text-slate-600">1</td>
                    <td class="py-4 text-right text-slate-600"><?= e(money($project->totalPayment)) ?></td>
                    <td class="py-4 pr-4 text-right font-medium text-ink"><?= e(money($project->totalPayment)) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4 flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Total Amount</span>
                    <span class="font-semibold text-ink"><?= e(money($project->totalPayment)) ?></span>
                </div>
                <div class="flex items-center justify-between border-t border-line pt-2">
                    <span class="text-slate-500">Total Paid</span>
                    <span class="font-semibold text-emerald-600"><?= e(money($summary['collected'])) ?></span>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-brand-50 px-3 py-2">
                    <span class="font-semibold text-ink">Balance Due</span>
                    <span class="text-base font-bold <?= $summary['outstanding'] > 0.0 ? 'text-red-600' : 'text-emerald-600' ?>">
                        <?= e(money($summary['outstanding'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            Amount in Words: <span class="font-medium text-ink"><?= e(amount_in_words($project->totalPayment)) ?></span>
        </p>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-slate-50/70 px-4 py-3">
            <span class="text-sm font-medium text-slate-600">Payment Status</span>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= payment_status_badge($summary['status']) ?>">
                <?= e(payment_status_label($summary['status'])) ?>
            </span>
        </div>
    </div>

    <!-- ============ Payments received ============ -->
    <div class="border-t border-line px-8 py-6">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payments Received</h3>
        <?php if ($summary['timeline'] === []): ?>
            <p class="mt-2 text-sm text-slate-500">No payments recorded for this project yet.</p>
        <?php else: ?>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="py-2 pr-4 font-medium">Date</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Type</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Method</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Reference</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Received By</th>
                            <th scope="col" class="py-2 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($summary['timeline'] as $entry): ?>
                            <tr>
                                <td class="py-2.5 pr-4 text-slate-600"><?= e(date('j M Y', strtotime($entry->paymentDate))) ?></td>
                                <td class="py-2.5 pr-4 text-slate-600"><?= e(payment_type_label($entry->paymentType)) ?></td>
                                <td class="py-2.5 pr-4 text-slate-600"><?= e(payment_method_label($entry->paymentMethod)) ?></td>
                                <td class="py-2.5 pr-4 text-slate-600"><?= e($entry->referenceNo ?? '-') ?></td>
                                <td class="py-2.5 pr-4 text-slate-600"><?= e($entry->receivedByName ?? 'Unknown') ?></td>
                                <td class="py-2.5 text-right font-medium text-ink"><?= e(money($entry->amount)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-line">
                            <td colspan="5" class="py-2.5 pr-4 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total Received</td>
                            <td class="py-2.5 text-right font-semibold text-ink"><?= e(money($summary['collected'])) ?></td>
                        </tr>
                    </tfoot>
                </table>
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
        <p class="mt-8 text-center text-xs text-slate-400">This is a system-generated invoice and does not require a signature.</p>
    </div>
</div>
