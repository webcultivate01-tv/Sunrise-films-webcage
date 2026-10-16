<?php

use App\Models\Task;
use App\Models\TaskDescription;

/**
 * One of my tasks: full detail plus whichever action its status allows
 * (task spec s5-s8).
 *
 * The description is a thread, not a field: whatever the photographer has
 * sent since this was assigned is listed underneath the original brief, so
 * the latest instructions never arrive without the ones they amend.
 *
 * @var Task                  $task
 * @var string                $baseUrl
 * @var list<TaskDescription> $descriptions
 */
$nextSteps = array_filter(Task::progressSteps(), static fn (int $step): bool => $step > $task->progress);
?>
<div class="mb-8">
    <a href="<?= e($baseUrl) ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        My Work
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
    </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <section class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7">
            <h2 class="text-base font-semibold text-ink">Task details</h2>

            <dl class="mt-6 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Customer</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e($task->customerName ?? 'Unknown customer') ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Photographer</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e($task->photographerName ?? 'Unknown photographer') ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Amount</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e(money($task->amount)) ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Start date</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e(date('j M Y', strtotime($task->startDate))) ?></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">End date / deadline</dt>
                    <dd class="mt-0.5 text-sm font-medium text-ink"><?= e(date('j M Y', strtotime($task->endDate))) ?></dd>
                </div>
            </dl>

            <div class="mt-6">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-ink">Progress</span>
                    <span class="text-slate-500"><?= (int) $task->progress ?>%</span>
                </div>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-brand-500" style="width: <?= (int) $task->progress ?>%"></div>
                </div>
            </div>
        </section>

        <?php require BASE_PATH . '/app/Views/partials/description-thread.php'; ?>
    </div>

    <div class="space-y-5">
        <?php if ($task->status === Task::STATUS_ASSIGNED): ?>
            <section class="rounded-2xl border border-line bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">Accept this task</h2>
                <p class="mt-2 text-sm text-slate-500">Accept to start working on it. Its details cannot be changed once you do.</p>
                <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/accept" class="mt-4">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="w-full rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                        Accept task
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <?php if (in_array($task->status, [Task::STATUS_ACCEPTED, Task::STATUS_IN_PROGRESS], true)): ?>
            <section class="rounded-2xl border border-line bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">Update progress</h2>
                <p class="mt-2 text-sm text-slate-500">Progress moves in 10% steps and cannot go backwards.</p>

                <?php if ($nextSteps !== []): ?>
                    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/progress" class="mt-4 flex gap-2">
                        <?= csrf_field() ?>
                        <select name="progress" class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-ink focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
                            <?php foreach ($nextSteps as $step): ?>
                                <option value="<?= $step ?>"><?= $step ?>%</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit"
                                class="shrink-0 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Update
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($task->progress >= 100): ?>
                    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/complete" class="mt-3">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="w-full rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                            Mark as Completed
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-red-100 bg-red-50/40 p-6">
                <h2 class="text-base font-semibold text-ink">Can't continue this task?</h2>
                <p class="mt-2 text-sm text-slate-600">Exiting moves it to Exited Work for your manager to reassign. No amount is credited to you.</p>
                <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $task->id ?>/exit" class="mt-4"
                      onsubmit="return confirm('Exit this task? It will be reassigned to someone else.');">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="w-full rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100">
                        Exit work
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($task->status === Task::STATUS_COMPLETED): ?>
            <section class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-6">
                <h2 class="text-base font-semibold text-ink">Completed</h2>
                <p class="mt-2 text-sm text-slate-600">
                    <?= e(money($task->amount)) ?> is now eligible for your monthly salary.
                </p>
            </section>
        <?php endif; ?>

        <?php if ($task->status === Task::STATUS_EXITED): ?>
            <section class="rounded-2xl border border-line bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-ink">You exited this task</h2>
                <p class="mt-2 text-sm text-slate-500">It has been sent back for reassignment. No amount was credited to you.</p>
            </section>
        <?php endif; ?>
    </div>
</div>
