<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Add Task (task spec s3).
 *
 * @var string                $baseUrl
 * @var list<Project>         $projects
 * @var list<User>            $employees
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
?>
<div class="mb-5 -mt-4">
    <a href="<?= e($baseUrl) ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Back
    </a>
    <div class="mt-3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Add Task</h1>
        <p class="mt-0.5 text-sm text-slate-500">Assign a piece of work to an employee on one of your projects.</p>
    </div>
</div>

<div>
    <form method="post" action="<?= e($baseUrl) ?>" class="space-y-4" novalidate>
        <?= csrf_field() ?>

        <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Task details</h2>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="field-project" class="mb-1.5 block text-sm font-medium text-slate-700">Project (customer)</label>
                    <select id="field-project" name="project_id" data-project-description-source
                            class="<?= input_classes($errors, 'project_id') ?>">
                        <option value="">Select a project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project->id ?>" data-description="<?= e($project->description) ?>"
                                    <?= old($old, 'project_id') === (string) $project->id ? 'selected' : '' ?>>
                                <?= e($project->customerName) ?> - <?= e($project->photographerName ?? 'Unknown photographer') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'project_id') ?>
                </div>

                <div>
                    <label for="field-employee" class="mb-1.5 block text-sm font-medium text-slate-700">Employee</label>
                    <select id="field-employee" name="employee_id" class="<?= input_classes($errors, 'employee_id') ?>">
                        <option value="">Select an employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int) $employee->id ?>" <?= old($old, 'employee_id') === (string) $employee->id ? 'selected' : '' ?>>
                                <?= e($employee->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'employee_id') ?>
                    <?php if ($employees === []): ?>
                        <p class="mt-1.5 text-xs text-amber-600">You have no employees to assign work to yet.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="field-title" class="mb-1.5 block text-sm font-medium text-slate-700">Task title</label>
                    <input type="text" id="field-title" name="title"
                           value="<?= old($old, 'title') ?>"
                           class="<?= input_classes($errors, 'title') ?>" placeholder="Rough cut - Sharma Wedding" autofocus>
                    <?= field_error($errors, 'title') ?>
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="field-description" class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                    <textarea id="field-description" name="description" rows="3" data-project-description-target
                              class="<?= input_classes($errors, 'description') ?>"
                              placeholder="Filled in automatically from the selected project - edit as needed"><?= old($old, 'description') ?></textarea>
                    <p class="mt-1.5 text-xs text-slate-500">Auto-filled from the project's description when you select a project above; feel free to change it for this task.</p>
                    <?= field_error($errors, 'description') ?>
                </div>

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

                <div>
                    <label for="field-priority" class="mb-1.5 block text-sm font-medium text-slate-700">Priority</label>
                    <select id="field-priority" name="priority" class="<?= input_classes($errors, 'priority') ?>">
                        <option value="">Select a priority</option>
                        <?php foreach (Task::priorities() as $value): ?>
                            <option value="<?= e($value) ?>" <?= old($old, 'priority') === $value ? 'selected' : '' ?>>
                                <?= e(task_priority_label($value)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'priority') ?>
                </div>

                <div>
                    <label for="field-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Amount (₹)</label>
                    <input type="number" step="0.01" min="0" id="field-amount" name="amount"
                           value="<?= old($old, 'amount') ?>"
                           class="<?= input_classes($errors, 'amount') ?> [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                           placeholder="5000.00">
                    <p class="mt-1.5 text-xs text-slate-500">Visible to the assigned employee, and becomes salary-eligible once completed.</p>
                    <?= field_error($errors, 'amount') ?>
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                Save Task
            </button>
            <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>
