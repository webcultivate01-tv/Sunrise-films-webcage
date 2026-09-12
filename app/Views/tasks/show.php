<?php

use App\Models\Task;

/**
 * One task, read only, from the Admin/Manager side (task spec s4, s5, s6, s9).
 *
 * @var Task $task
 * @var string $baseUrl
 * @var bool $canReassign
 * @var bool $canCancel
 */
$fields = [
    ['label' => 'Project', 'value' => $task->projectName ?? 'Unknown project', 'icon' => 'folder'],
    ['label' => 'Customer', 'value' => $task->customerName ?? 'Unknown customer', 'icon' => 'user'],
    ['label' => 'Employee', 'value' => $task->employeeName ?? 'Unknown employee', 'icon' => 'user'],
    ['label' => 'Timeline', 'value' => date('j M Y', strtotime($task->startDate)) . ' - ' . date('j M Y', strtotime($task->endDate)), 'icon' => 'clock'],
    ['label' => 'Amount', 'value' => money($task->amount), 'icon' => 'cash'],
    ['label' => 'Assigned by', 'value' => $task->createdByName ?? 'Unknown', 'icon' => 'badge'],
];

if ($task->acceptedAt !== null) {
    $fields[] = ['label' => 'Accepted', 'value' => pretty_date($task->acceptedAt), 'icon' => 'clock'];
}

if ($task->completedAt !== null) {
    $fields[] = ['label' => 'Completed', 'value' => pretty_date($task->completedAt), 'icon' => 'clock'];
}

if ($task->exitedAt !== null) {
    $fields[] = ['label' => 'Exited', 'value' => pretty_date($task->exitedAt), 'icon' => 'clock'];
}

$fieldIcons = [
    'user'   => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>',
    'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
    'clock'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    'cash'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
    'badge'  => '<circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>',
];
?>
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="<?= e($baseUrl) ?>"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Task Management
        </a>
        <div class="mt-4">
            <h1 class="truncate text-2xl font-semibold tracking-tight text-ink"><?= e($task->title) ?></h1>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= task_status_badge($task->status) ?>">
                    <?= e(task_status_label($task->status)) ?>
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= task_priority_badge($task->priority) ?>">
                    <?= e(task_priority_label($task->priority)) ?> priority
                </span>
                <?php if ($task->isOverdue()): ?>
                    <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                        Overdue
                    </span>
                <?php endif; ?>
            </div>
            <p class="mt-2 max-w-2xl whitespace-pre-line text-sm text-slate-600"><?= e($task->description) ?></p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <?php if ($canCancel): ?>
            <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/cancel"
                  onsubmit="return confirm('Remove <?= e(addslashes($task->employeeName ?? 'the employee')) ?> from this task and reassign it to someone else?');">
                <?= csrf_field() ?>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200 focus:ring-offset-2">
                    Cancel &amp; reassign
                </button>
            </form>
        <?php endif; ?>
        <?php if ($canReassign): ?>
            <a href="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/reassign"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                Reassign task
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7 lg:col-span-2">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-ink">Task details</h2>
        </div>
        <dl class="mt-6 grid gap-x-6 gap-y-6 sm:grid-cols-2">
            <?php foreach ($fields as $field): ?>
                <div class="flex gap-3">
                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-50 text-slate-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= $fieldIcons[$field['icon']] ?>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= e($field['label']) ?></dt>
                        <dd class="mt-0.5 whitespace-pre-line break-words text-sm font-medium text-ink"><?= e((string) $field['value']) ?></dd>
                    </div>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <div class="space-y-5">
        <section class="rounded-2xl border border-line bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m9 14 2 2 4-4"/><rect x="3" y="4" width="18" height="17" rx="2"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Progress</h2>
            </div>
            <div class="mt-4">
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-brand-500" style="width: <?= (int) $task->progress ?>%"></div>
                </div>
                <p class="mt-2 text-sm text-slate-500"><?= (int) $task->progress ?>% complete</p>
            </div>
        </section>

        <?php if ($task->status === \App\Models\Task::STATUS_EXITED): ?>
            <section class="rounded-2xl border border-red-100 bg-red-50/40 p-6">
                <h2 class="text-base font-semibold text-ink">This task was exited</h2>
                <p class="mt-2 text-sm text-slate-600">
                    No salary credit was created for <?= e($task->employeeName ?? 'the employee') ?>.
                    Reassign this work to another employee to keep it moving.
                </p>
            </section>
        <?php endif; ?>

        <?php if ($task->status === \App\Models\Task::STATUS_COMPLETED): ?>
            <section class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-6">
                <h2 class="text-base font-semibold text-ink">Completed</h2>
                <p class="mt-2 text-sm text-slate-600">
                    <?= e(money($task->amount)) ?> is eligible for <?= e($task->employeeName ?? 'the employee') ?>'s monthly salary.
                </p>
            </section>
        <?php endif; ?>
    </div>
</div>
