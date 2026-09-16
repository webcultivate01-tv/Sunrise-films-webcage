<?php

use App\Models\Photographer;
use App\Models\Payment;
use App\Models\Project;

/**
 * Record Payment (module spec s2, s6, s10) - always creates a new
 * transaction; a payment is never edited once recorded.
 *
 * @var string           $baseUrl
 * @var list<Project>    $projects
 * @var list<Photographer>   $photographers
 * @var array<int, array{total: float, collected: float, outstanding: float}> $projectSummary
 * @var int|null         $preselectedId
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$selectedProject = old($old, 'project_id', $preselectedId !== null ? (string) $preselectedId : '');
$selectedPhotographer = '';

foreach ($projects as $project) {
    if ((string) $project->id === $selectedProject) {
        $selectedPhotographer = (string) $project->photographerId;

        break;
    }
}

$back            = $preselectedId !== null ? $baseUrl . '/history?project_id=' . $preselectedId : $baseUrl;
?>
<div class="mb-5 -mt-4">
    <a href="<?= e($back) ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Back
    </a>
    <div class="mt-3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Record Payment</h1>
        <p class="mt-0.5 text-sm text-slate-500">Log a payment received against a project. This cannot be edited once saved.</p>
    </div>
</div>

<div>
    <form method="post" action="<?= e($baseUrl) ?>" class="space-y-4" novalidate>
        <?= csrf_field() ?>

        <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Payment details</h2>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="field-photographer" class="mb-1.5 block text-sm font-medium text-slate-700">Photographer</label>
                    <select id="field-photographer" data-payment-photographer-filter
                            class="<?= input_classes($errors, 'project_id') ?>">
                        <option value="">Select a photographer</option>
                        <?php foreach ($photographers as $photographer): ?>
                            <option value="<?= (int) $photographer->id ?>" <?= $selectedPhotographer === (string) $photographer->id ? 'selected' : '' ?>>
                                <?= e($photographer->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">Narrows the project list below.</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="field-project" class="mb-1.5 block text-sm font-medium text-slate-700">Project</label>
                    <select id="field-project" name="project_id" data-payment-project-select
                            class="<?= input_classes($errors, 'project_id') ?>">
                        <option value="">Select a project</option>
                        <?php foreach ($projects as $project): ?>
                            <?php $summary = $projectSummary[$project->id] ?? ['total' => $project->totalPayment, 'collected' => 0.0, 'outstanding' => $project->totalPayment]; ?>
                            <option value="<?= (int) $project->id ?>"
                                    data-photographer="<?= (int) $project->photographerId ?>"
                                    data-total="<?= e(number_format($summary['total'], 2, '.', '')) ?>"
                                    data-collected="<?= e(number_format($summary['collected'], 2, '.', '')) ?>"
                                    data-outstanding="<?= e(number_format($summary['outstanding'], 2, '.', '')) ?>"
                                    <?= $selectedProject === (string) $project->id ? 'selected' : '' ?>>
                                <?= e($project->customerName) ?> - <?= e($project->photographerName ?? 'Unknown photographer') ?> (Total <?= e(money($project->totalPayment)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'project_id') ?>
                </div>

                <div class="sm:col-span-2 lg:col-span-3" data-payment-project-summary hidden>
                    <div class="grid grid-cols-3 gap-3 rounded-lg border border-line bg-slate-50 p-3.5">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Total value</p>
                            <p data-summary-total class="mt-0.5 text-sm font-semibold text-ink"></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Collected so far</p>
                            <p data-summary-collected class="mt-0.5 text-sm font-semibold text-ink"></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Outstanding</p>
                            <p data-summary-outstanding class="mt-0.5 text-sm font-semibold text-ink"></p>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="field-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" id="field-amount" name="amount" data-payment-amount-target
                           value="<?= old($old, 'amount') ?>"
                           class="<?= input_classes($errors, 'amount') ?> [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                           placeholder="0.00">
                    <p class="mt-1.5 text-xs text-slate-500">Defaults to the outstanding balance - change it for a partial payment.</p>
                    <?= field_error($errors, 'amount') ?>
                </div>

                <div>
                    <label for="field-type" class="mb-1.5 block text-sm font-medium text-slate-700">Payment type</label>
                    <select id="field-type" name="payment_type" class="<?= input_classes($errors, 'payment_type') ?>">
                        <option value="">Select a type</option>
                        <?php foreach (Payment::types() as $value): ?>
                            <option value="<?= e($value) ?>" <?= old($old, 'payment_type') === $value ? 'selected' : '' ?>>
                                <?= e(payment_type_label($value)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'payment_type') ?>
                </div>

                <div>
                    <label for="field-method" class="mb-1.5 block text-sm font-medium text-slate-700">Payment method</label>
                    <select id="field-method" name="payment_method" class="<?= input_classes($errors, 'payment_method') ?>">
                        <option value="">Select a method</option>
                        <?php foreach (Payment::methods() as $value): ?>
                            <option value="<?= e($value) ?>" <?= old($old, 'payment_method') === $value ? 'selected' : '' ?>>
                                <?= e(payment_method_label($value)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'payment_method') ?>
                </div>

                <div>
                    <label for="field-date" class="mb-1.5 block text-sm font-medium text-slate-700">Payment date</label>
                    <input type="date" id="field-date" name="payment_date"
                           value="<?= old($old, 'payment_date', date('Y-m-d')) ?>"
                           class="<?= input_classes($errors, 'payment_date') ?>">
                    <?= field_error($errors, 'payment_date') ?>
                </div>

                <div>
                    <label for="field-reference" class="mb-1.5 block text-sm font-medium text-slate-700">Reference / transaction no. (optional)</label>
                    <input type="text" id="field-reference" name="reference_no"
                           value="<?= old($old, 'reference_no') ?>"
                           class="<?= input_classes($errors, 'reference_no') ?>" placeholder="e.g. UTR1234567890">
                    <?= field_error($errors, 'reference_no') ?>
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="field-notes" class="mb-1.5 block text-sm font-medium text-slate-700">Notes (optional)</label>
                    <textarea id="field-notes" name="notes" rows="2"
                              class="<?= input_classes($errors, 'notes') ?>"
                              placeholder="Anything worth recording against this payment"><?= old($old, 'notes') ?></textarea>
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                Record Payment
            </button>
            <a href="<?= e($back) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>
