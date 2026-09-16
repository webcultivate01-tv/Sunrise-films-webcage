<?php

use App\Models\Task;
use App\Models\User;

/**
 * Reassign an exited task to a new employee (task spec s9).
 *
 * @var Task                 $task
 * @var string               $baseUrl
 * @var list<User>           $employees
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
?>
<div class="mb-5 -mt-4">
    <a href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Back
    </a>
    <div class="mt-3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Reassign <?= e($task->title) ?></h1>
        <p class="mt-0.5 text-sm text-slate-500">
            Exited by <?= e($task->employeeName ?? 'the previous employee') ?>. Choosing a new employee starts the
            assignment over: Assigned &rarr; Accepted &rarr; Progress &rarr; Completed / Exited.
        </p>
    </div>
</div>

<div>
    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/reassign" class="space-y-4" novalidate>
        <?= csrf_field() ?>

        <section class="rounded-2xl border border-line bg-slate-50/60 p-5">
            <h2 class="text-sm font-semibold text-ink">Carrying over to the new employee</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Customer</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e($task->customerName ?? 'Unknown customer') ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Amount</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e(money($task->amount)) ?></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Title</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e($task->title) ?></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Description</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-ink"><?= e($task->description) ?></dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="mt-1 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="field-employee" class="mb-1.5 block text-sm font-medium text-slate-700">New employee</label>
                    <select id="field-employee" name="employee_id" class="<?= input_classes($errors, 'employee_id') ?>">
                        <option value="">Select an employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int) $employee->id ?>" <?= old($old, 'employee_id') === (string) $employee->id ? 'selected' : '' ?>>
                                <?= e($employee->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'employee_id') ?>
                </div>

                <div></div>

                <div>
                    <label for="field-start" class="mb-1.5 block text-sm font-medium text-slate-700">Start date</label>
                    <input type="date" id="field-start" name="start_date"
                           value="<?= old($old, 'start_date') ?>"
                           class="<?= input_classes($errors, 'start_date') ?>">
                    <?= field_error($errors, 'start_date') ?>
                </div>

                <div>
                    <label for="field-end" class="mb-1.5 block text-sm font-medium text-slate-700">End date / deadline</label>
                    <input type="date" id="field-end" name="end_date"
                           value="<?= old($old, 'end_date') ?>"
                           class="<?= input_classes($errors, 'end_date') ?>">
                    <?= field_error($errors, 'end_date') ?>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                Title, description, priority and amount carry over unchanged from the original task.
            </p>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                Reassign task
            </button>
            <a href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>
