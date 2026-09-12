<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;

/**
 * Add / Edit Project.
 *
 * @var Project|null          $project  Null when adding.
 * @var string                $baseUrl
 * @var list<Customer>        $customers
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$isEdit = $project !== null;
$action = $isEdit ? $baseUrl . '/' . $project->id : $baseUrl;
$back   = $isEdit ? $baseUrl . '/' . $project->id : $baseUrl;

$selectedCustomer = old($old, 'customer_id', $project !== null ? (string) $project->customerId : '');
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
        <h1 class="text-2xl font-semibold tracking-tight text-ink">
            <?= $isEdit ? 'Edit ' . e($project->name) : 'Add Project' ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500">
            <?= $isEdit ? 'Update the details on record for this project.' : 'Register a new project for a customer.' ?>
        </p>
    </div>
</div>

<div>
    <form method="post" action="<?= e($action) ?>" class="space-y-4" novalidate>
        <?= csrf_field() ?>

        <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Project details</h2>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="field-customer" class="mb-1.5 block text-sm font-medium text-slate-700">Customer</label>
                    <select id="field-customer" name="customer_id" class="<?= input_classes($errors, 'customer_id') ?>">
                        <option value="">Select a customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= (int) $customer->id ?>" <?= $selectedCustomer === (string) $customer->id ? 'selected' : '' ?>>
                                <?= e($customer->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'customer_id') ?>
                </div>

                <div>
                    <label for="field-name" class="mb-1.5 block text-sm font-medium text-slate-700">Project name</label>
                    <input type="text" id="field-name" name="name"
                           value="<?= old($old, 'name', $project->name ?? '') ?>"
                           class="<?= input_classes($errors, 'name') ?>" placeholder="Wedding Film - Sharma Family" autofocus>
                    <?= field_error($errors, 'name') ?>
                </div>

                <div>
                    <label for="field-description" class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                    <textarea id="field-description" name="description" rows="2"
                              class="<?= input_classes($errors, 'description') ?>"
                              placeholder="What this project covers"><?= old($old, 'description', $project->description ?? '') ?></textarea>
                    <?= field_error($errors, 'description') ?>
                </div>

                <div>
                    <label for="field-folder" class="mb-1.5 block text-sm font-medium text-slate-700">Receivable folder name</label>
                    <input type="text" id="field-folder" name="folder_name"
                           value="<?= old($old, 'folder_name', $project->folderName ?? '') ?>"
                           class="<?= input_classes($errors, 'folder_name') ?>" placeholder="e.g. Sharma_Wedding_2026">
                    <p class="mt-1.5 text-xs text-slate-500">Where this project's files are kept (Drive, NAS, etc.) - a reference, not an upload.</p>
                    <?= field_error($errors, 'folder_name') ?>
                </div>

                <div>
                    <label for="field-deadline" class="mb-1.5 block text-sm font-medium text-slate-700">Deadline</label>
                    <input type="date" id="field-deadline" name="deadline"
                           value="<?= old($old, 'deadline', $project->deadline ?? '') ?>"
                           class="<?= input_classes($errors, 'deadline') ?>">
                    <?= field_error($errors, 'deadline') ?>
                </div>

                <div>
                    <label for="field-total" class="mb-1.5 block text-sm font-medium text-slate-700">Total payment (₹)</label>
                    <input type="number" step="0.01" min="0" id="field-total" name="total_payment"
                           value="<?= old($old, 'total_payment', $project !== null ? (string) $project->totalPayment : '') ?>"
                           class="<?= input_classes($errors, 'total_payment') ?> [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                           placeholder="50000.00">
                    <?= field_error($errors, 'total_payment') ?>
                </div>
            </div>
        </section>

        <?php if (!$isEdit): ?>
            <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-ink">Advance payment (optional)</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Collected from the customer when the work is assigned. Recorded automatically in Payment
                            Management and a bill is generated for it as soon as you create the project.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="field-advance-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Advance amount (&#8377;)</label>
                        <input type="number" step="0.01" min="0" id="field-advance-amount" name="advance_amount"
                               value="<?= old($old, 'advance_amount', '') ?>"
                               class="<?= input_classes($errors, 'advance_amount') ?> [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                               placeholder="10000.00">
                        <?= field_error($errors, 'advance_amount') ?>
                    </div>

                    <div>
                        <label for="field-advance-method" class="mb-1.5 block text-sm font-medium text-slate-700">Payment method</label>
                        <select id="field-advance-method" name="advance_payment_method" class="<?= input_classes($errors, 'advance_payment_method') ?>">
                            <option value="">Select payment method</option>
                            <option value="<?= e(Payment::METHOD_CASH) ?>" <?= old($old, 'advance_payment_method', '') === Payment::METHOD_CASH ? 'selected' : '' ?>>Cash</option>
                            <option value="<?= e(Payment::METHOD_UPI) ?>" <?= old($old, 'advance_payment_method', '') === Payment::METHOD_UPI ? 'selected' : '' ?>>UPI</option>
                        </select>
                        <?= field_error($errors, 'advance_payment_method') ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                <?= $isEdit ? 'Save changes' : 'Create Work' ?>
            </button>
            <a href="<?= e($back) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>
