<?php

use App\Models\Photographer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Setting;

/**
 * Project Invoice/Bill - a full, printable statement for one project: the
 * company header, the photographer and project it is billed to, the project's
 * value, every payment collected against it and what remains outstanding.
 * Rendered inside the bare "invoice" layout (no sidebar) so it prints clean.
 *
 * @var Project      $project
 * @var Photographer|null $photographer
 * @var string       $baseUrl
 * @var string       $reference
 * @var array{totalValue: float, collected: float, outstanding: float, status: string, collectionPercentage: float, paymentsCount: int, lastPaymentDate: ?string, timeline: list<Payment>} $summary
 * @var string       $backUrl
 * @var string       $downloadUrl
 * @var ?string      $whatsappUrl
 * @var ?string      $whatsappName
 */
$company   = Setting::current();
$backLabel = 'Back to project';
?>
<?php require BASE_PATH . '/app/Views/partials/bill-actions.php'; ?>

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
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($photographer?->name ?? 'Unknown photographer') ?></p>
            <?php if ($photographer !== null): ?>
                <p class="mt-1 text-sm text-slate-600"><?= e($photographer->phone) ?></p>
                <p class="text-sm text-slate-600"><?= e($photographer->email) ?></p>
                <p class="text-sm text-slate-600"><?= e($photographer->address) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Customer</p>
            <p class="mt-2 text-sm font-semibold text-ink"><?= e($project->customerName) ?></p>
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
                        <p class="font-medium text-ink"><?= e($project->customerName) ?></p>
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
