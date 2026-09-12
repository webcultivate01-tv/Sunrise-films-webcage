<?php

use App\Models\User;

/**
 * Settle Salary (spec s7): pick the month, enter and confirm the amount.
 *
 * @var User                                                                                       $employee
 * @var string                                                                                      $baseUrl
 * @var list<array{month: string, earned: float, paid: float, outstanding: float, status: string}> $breakdown
 * @var string                                                                                      $preselectMonth
 * @var string                                                                                      $idempotencyKey
 * @var array<string, string>                                                                       $errors
 * @var array<string, string>                                                                       $old
 */
$settleable = array_values(array_filter($breakdown, static fn (array $row): bool => $row['outstanding'] > 0.0));
?>
<div class="mb-5 -mt-4">
    <a href="<?= e($baseUrl) ?>/<?= (int) $employee->id ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Back
    </a>
    <div class="mt-3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Settle Salary</h1>
        <p class="mt-0.5 text-sm text-slate-500">Recording a settlement for <?= e($employee->name) ?>.</p>
    </div>
</div>

<?php if ($settleable === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">Nothing outstanding</p>
        <p class="mt-1 text-sm text-slate-500">Every month has already been fully settled for this employee.</p>
    </div>
<?php else: ?>
    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $employee->id ?>/settle" class="max-w-xl space-y-4" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">

        <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="field-month" class="mb-1.5 block text-sm font-medium text-slate-700">Salary month</label>
                    <select id="field-month" name="salary_month" class="<?= input_classes($errors, 'salary_month') ?>">
                        <option value="">Select a month</option>
                        <?php foreach ($settleable as $row): ?>
                            <?php $selected = old($old, 'salary_month', $preselectMonth) === $row['month']; ?>
                            <option value="<?= e($row['month']) ?>" data-outstanding="<?= e((string) $row['outstanding']) ?>" <?= $selected ? 'selected' : '' ?>>
                                <?= e(pretty_month($row['month'])) ?> - outstanding <?= e(money($row['outstanding'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'salary_month') ?>
                </div>

                <div>
                    <label for="field-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Settlement amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" id="field-amount" name="amount"
                           value="<?= old($old, 'amount') ?>"
                           class="<?= input_classes($errors, 'amount') ?> [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                           placeholder="0.00">
                    <?= field_error($errors, 'amount') ?>
                </div>

                <div class="sm:col-span-2">
                    <label for="field-notes" class="mb-1.5 block text-sm font-medium text-slate-700">Notes (optional)</label>
                    <textarea id="field-notes" name="notes" rows="2"
                              class="<?= input_classes($errors, 'notes') ?>"
                              placeholder="Anything worth recording against this settlement"><?= old($old, 'notes') ?></textarea>
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                Confirm Settlement
            </button>
            <a href="<?= e($baseUrl) ?>/<?= (int) $employee->id ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
<?php endif; ?>
