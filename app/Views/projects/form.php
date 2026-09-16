<?php

use App\Models\Photographer;
use App\Models\Payment;
use App\Models\Project;

/**
 * Add / Edit Project.
 *
 * @var Project|null          $project  Null when adding.
 * @var string                $baseUrl
 * @var list<Photographer>        $photographers
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$isEdit = $project !== null;
$action = $isEdit ? $baseUrl . '/' . $project->id : $baseUrl;
$back   = $isEdit ? $baseUrl . '/' . $project->id : $baseUrl;

$selectedPhotographer = old($old, 'photographer_id', $project !== null ? (string) $project->photographerId : '');
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
            <?= $isEdit ? 'Edit ' . e($project->customerName) : 'Add Project' ?>
        </h1>
        <p class="mt-0.5 text-sm text-slate-500">
            <?= $isEdit ? 'Update the details on record for this project.' : 'Register a new project for a photographer.' ?>
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
                    <label for="field-photographer" class="mb-1.5 block text-sm font-medium text-slate-700">Photographer</label>
                    <select id="field-photographer" name="photographer_id" class="<?= input_classes($errors, 'photographer_id') ?>">
                        <option value="">Select a photographer</option>
                        <?php foreach ($photographers as $photographer): ?>
                            <option value="<?= (int) $photographer->id ?>" <?= $selectedPhotographer === (string) $photographer->id ? 'selected' : '' ?>>
                                <?= e($photographer->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'photographer_id') ?>
                </div>

                <div>
                    <label for="field-customer" class="mb-1.5 block text-sm font-medium text-slate-700">Customer name</label>
                    <input type="text" id="field-customer" name="customer_name"
                           value="<?= old($old, 'customer_name', $project->customerName ?? '') ?>"
                           class="<?= input_classes($errors, 'customer_name') ?>" placeholder="Sharma Family - Wedding" autofocus>
                    <p class="mt-1.5 text-xs text-slate-500">Who the shoot is for - the photographer's own client. This is what identifies the project everywhere.</p>
                    <?= field_error($errors, 'customer_name') ?>
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
                        <h2 class="text-base font-semibold text-ink">Payment received (optional)</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Collected from the photographer when the work is handed over - either an advance against the
                            total, or the whole amount paid up front. Recorded automatically in Payment Management and a
                            bill is generated for it as soon as you create the project.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="field-payment-type" class="mb-1.5 block text-sm font-medium text-slate-700">Payment type</label>
                        <select id="field-payment-type" name="payment_type" class="<?= input_classes($errors, 'payment_type') ?>">
                            <option value="">No payment collected yet</option>
                            <?php foreach (Payment::upfrontTypes() as $type): ?>
                                <option value="<?= e($type) ?>" <?= old($old, 'payment_type', '') === $type ? 'selected' : '' ?>>
                                    <?= e(payment_type_label($type)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'payment_type') ?>
                    </div>

                    <div>
                        <label for="field-payment-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Amount received (&#8377;)</label>
                        <input type="number" step="0.01" min="0" id="field-payment-amount" name="payment_amount"
                               value="<?= old($old, 'payment_amount', '') ?>"
                               class="<?= input_classes($errors, 'payment_amount') ?> [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                               placeholder="10000.00">
                        <p id="payment-amount-hint" class="mt-1.5 text-xs text-slate-500"></p>
                        <?= field_error($errors, 'payment_amount') ?>
                    </div>

                    <div>
                        <label for="field-payment-method" class="mb-1.5 block text-sm font-medium text-slate-700">Payment method</label>
                        <select id="field-payment-method" name="payment_method" class="<?= input_classes($errors, 'payment_method') ?>">
                            <option value="">Select payment method</option>
                            <?php foreach (Payment::upfrontMethods() as $method): ?>
                                <option value="<?= e($method) ?>" <?= old($old, 'payment_method', '') === $method ? 'selected' : '' ?>>
                                    <?= e(payment_method_label($method)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'payment_method') ?>
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

<?php if (!$isEdit): ?>
<script>
    /**
     * Keeps the "Payment received" block honest: a Full Payment is by
     * definition the whole total payment, so the amount follows the total
     * field and is locked - the admin cannot accidentally file a part payment
     * under the wrong type, which the server would refuse anyway. An Advance
     * Payment is typed in freely.
     *
     * A readonly field still submits its value, so the server sees the same
     * amount the admin was shown.
     */
    (function () {
        var typeField   = document.getElementById('field-payment-type');
        var amountField = document.getElementById('field-payment-amount');
        var methodField = document.getElementById('field-payment-method');
        var totalField  = document.getElementById('field-total');
        var hint        = document.getElementById('payment-amount-hint');

        if (!typeField || !amountField || !methodField || !totalField || !hint) {
            return;
        }

        var lockedClasses = ['bg-slate-50', 'text-slate-500', 'cursor-not-allowed'];

        function total() {
            var value = parseFloat(totalField.value);

            return isNaN(value) || value < 0 ? 0 : value;
        }

        function sync() {
            if (typeField.value === '<?= e(\App\Models\Payment::TYPE_FULL) ?>') {
                amountField.value = total() > 0 ? total().toFixed(2) : '';
                amountField.setAttribute('readonly', 'readonly');
                lockedClasses.forEach(function (name) { amountField.classList.add(name); });
                hint.textContent = 'The whole total payment, paid up front - this follows the total above.';

                return;
            }

            amountField.removeAttribute('readonly');
            lockedClasses.forEach(function (name) { amountField.classList.remove(name); });

            hint.textContent = typeField.value === '<?= e(\App\Models\Payment::TYPE_ADVANCE) ?>'
                ? 'Part of the total payment, collected now. The rest stays outstanding.'
                : 'Leave this block alone if nothing has been collected yet.';
        }

        typeField.addEventListener('change', sync);
        totalField.addEventListener('input', sync);
        sync();
    })();
</script>
<?php endif; ?>
