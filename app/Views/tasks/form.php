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
                    <label for="project-search" class="mb-1.5 block text-sm font-medium text-slate-700">Project (customer)</label>
                    <div class="relative" id="project-wrapper">
                        <input type="text" id="project-search" autocomplete="off"
                               placeholder="Search project…"
                               class="<?= input_classes($errors, 'project_id') ?>"
                               value="<?php
                                   $oldPid = old($old, 'project_id');
                                   foreach ($projects as $p) {
                                       if ((string)$p->id === $oldPid) {
                                           echo e($p->customerName . ' - ' . ($p->photographerName ?? 'Unknown photographer'));
                                           break;
                                       }
                                   }
                               ?>">
                        <ul id="project-list"
                            class="absolute z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-line bg-white py-1 shadow-lg text-sm">
                            <?php foreach ($projects as $project): ?>
                            <li class="project-option cursor-pointer px-3 py-2 hover:bg-brand-50 hover:text-brand-700"
                                data-id="<?= (int) $project->id ?>"
                                data-name="<?= e($project->customerName . ' - ' . ($project->photographerName ?? 'Unknown photographer')) ?>"
                                data-description="<?= e($project->description) ?>"
                                data-deadline="<?= e(substr($project->deadline ?? '', 0, 10)) ?>">
                                <?= e($project->customerName) ?> - <?= e($project->photographerName ?? 'Unknown photographer') ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <input type="hidden" id="field-project" name="project_id" value="<?= e(old($old, 'project_id')) ?>">
                    <?= field_error($errors, 'project_id') ?>
                </div>

                <div>
                    <label for="employee-search" class="mb-1.5 block text-sm font-medium text-slate-700">Editor</label>
                    <div class="relative" id="employee-wrapper">
                        <input type="text" id="employee-search" autocomplete="off"
                               placeholder="Search editor…"
                               class="<?= input_classes($errors, 'employee_id') ?>"
                               value="<?php
                                   $oldEid = old($old, 'employee_id');
                                   foreach ($employees as $emp) {
                                       if ((string)$emp->id === $oldEid) {
                                           $pctLabel = $emp->percentage !== null ? ' (' . rtrim(rtrim(number_format($emp->percentage, 2, '.', ''), '0'), '.') . '%)' : ' (no percentage set)';
                                           echo e($emp->name . $pctLabel);
                                           break;
                                       }
                                   }
                               ?>">
                        <ul id="employee-list"
                            class="absolute z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border border-line bg-white py-1 shadow-lg text-sm">
                            <?php foreach ($employees as $emp): ?>
                            <?php $pctLabel = $emp->percentage !== null ? ' (' . rtrim(rtrim(number_format($emp->percentage, 2, '.', ''), '0'), '.') . '%)' : ' (no percentage set)'; ?>
                            <li class="employee-option cursor-pointer px-3 py-2 hover:bg-brand-50 hover:text-brand-700"
                                data-id="<?= (int) $emp->id ?>"
                                data-name="<?= e($emp->name . $pctLabel) ?>"
                                data-percentage="<?= $emp->percentage !== null ? e((string) $emp->percentage) : '' ?>">
                                <?= e($emp->name) ?><span class="text-slate-400"><?= e($pctLabel) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <input type="hidden" id="field-employee" name="employee_id" value="<?= e(old($old, 'employee_id')) ?>">
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
                    <label for="field-cost" class="mb-1.5 block text-sm font-medium text-slate-700">Total project cost (₹)</label>
                    <input type="number" step="0.01" min="0" id="field-cost" name="project_cost"
                           value="<?= old($old, 'project_cost') ?>"
                           class="<?= input_classes($errors, 'project_cost') ?> [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                           placeholder="20000.00">
                    <?= field_error($errors, 'project_cost') ?>
                </div>

                <div>
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Task amount (₹)</span>
                    <p id="task-amount-preview" class="rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-ink">-</p>
                    <p class="mt-1.5 text-xs text-slate-500">The employee's percentage of the total project cost. Visible to the assigned employee, and becomes salary-eligible once completed.</p>
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

<script>
(function () {
    /* ── generic searchable dropdown ── */
    function makeSearchable(wrapperId, listId, searchId, hiddenId, onPick) {
        var wrapper = document.getElementById(wrapperId);
        var list    = document.getElementById(listId);
        var search  = document.getElementById(searchId);
        var hidden  = document.getElementById(hiddenId);
        if (!wrapper || !list || !search || !hidden) return;
        var items = Array.from(list.querySelectorAll('li'));

        function show() { list.classList.remove('hidden'); }
        function hide() { list.classList.add('hidden'); }

        search.addEventListener('focus', show);
        search.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            items.forEach(function (li) {
                li.style.display = li.dataset.name.toLowerCase().includes(q) ? '' : 'none';
            });
            show();
        });
        items.forEach(function (li) {
            li.addEventListener('mousedown', function (e) {
                e.preventDefault();
                search.value = li.dataset.name;
                hidden.value = li.dataset.id;
                hide();
                if (onPick) onPick(li);
            });
        });
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) hide();
        });
    }

    /* ── project: auto-fill description + deadline ── */
    var descField     = document.querySelector('[data-project-description-target]');
    var endDateField  = document.getElementById('field-end');

    makeSearchable('project-wrapper', 'project-list', 'project-search', 'field-project', function (li) {
        if (descField && li.dataset.description && !descField.value) {
            descField.value = li.dataset.description;
        }
        if (endDateField && li.dataset.deadline && !endDateField.value) {
            endDateField.value = li.dataset.deadline;
        }
    });

    /* ── employee: update task-amount preview ── */
    var cost    = document.getElementById('field-cost');
    var preview = document.getElementById('task-amount-preview');
    var empHidden = document.getElementById('field-employee');

    function updateAmount(pct) {
        if (!preview || !cost) return;
        var total = parseFloat(cost.value);
        if (isNaN(pct) || isNaN(total)) { preview.textContent = '-'; return; }
        preview.textContent = '₹' + (Math.round(total * pct) / 100).toLocaleString('en-IN', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    var _currentPct = NaN;
    makeSearchable('employee-wrapper', 'employee-list', 'employee-search', 'field-employee', function (li) {
        _currentPct = parseFloat(li.dataset.percentage);
        updateAmount(_currentPct);
    });

    if (cost) cost.addEventListener('input', function () { updateAmount(_currentPct); });

    /* seed preview if old value was repopulated */
    (function () {
        if (!empHidden || !empHidden.value) return;
        var li = document.querySelector('#employee-list [data-id="' + empHidden.value + '"]');
        if (li) { _currentPct = parseFloat(li.dataset.percentage); updateAmount(_currentPct); }
    })();
})();
</script>
