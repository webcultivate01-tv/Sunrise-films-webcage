<?php

use App\Models\User;

/**
 * One account, read only, with the actions available on it (module spec s16).
 *
 * @var User      $account
 * @var User|null $createdBy
 * @var string    $baseUrl
 * @var bool      $canDelete
 */
$fields = [
    'Full name'     => $account->name,
    'Email address' => $account->email,
    'Mobile number' => $account->phone ?? 'Not recorded',
    'Address'       => $account->address ?? 'Not recorded',
    'Role'          => $account->roleLabel(),
    'Created by'    => $createdBy !== null
        ? $createdBy->name . ' (' . $createdBy->roleLabel() . ')'
        : 'System',
    'Created'       => pretty_date($account->createdAt, 'Unknown'),
    'Last sign-in'  => pretty_date($account->lastLoginAt),
];
?>
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 underline-offset-2 hover:underline">&larr; Back to Employee Management</a>
        <div class="mt-3 flex items-center gap-4">
            <?php if ($account->photoUrl() !== null): ?>
                <img src="<?= e($account->photoUrl()) ?>" alt="" class="h-12 w-12 shrink-0 rounded-full object-cover ring-1 ring-line">
            <?php else: ?>
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-gradient text-sm font-semibold text-white"><?= e($account->initials()) ?></span>
            <?php endif; ?>
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold tracking-tight text-ink"><?= e($account->name) ?></h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= role_badge($account->role) ?>">
                        <?= e($account->roleLabel()) ?>
                    </span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= status_badge($account->status) ?>">
                        <?= e(ucfirst($account->status)) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/edit"
           class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
            Edit
        </a>
        <a href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/password"
           class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Reset password
        </a>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-xl border border-line bg-white p-6 lg:col-span-2">
        <h2 class="text-base font-semibold text-ink">Account details</h2>
        <dl class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2">
            <?php foreach ($fields as $label => $value): ?>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500"><?= e($label) ?></dt>
                    <dd class="mt-1 whitespace-pre-line break-words text-sm text-ink"><?= e((string) $value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <section class="rounded-xl border border-line bg-white p-6">
        <h2 class="text-base font-semibold text-ink">Access</h2>
        <p class="mt-1.5 text-sm text-slate-500">
            <?= $account->isActive()
                ? 'This account can sign in right now.'
                : 'This account cannot sign in while it is ' . e($account->status) . '.' ?>
        </p>

        <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/status" class="mt-5">
            <?= csrf_field() ?>
            <input type="hidden" name="status"
                   value="<?= $account->isActive() ? User::STATUS_INACTIVE : User::STATUS_ACTIVE ?>">
            <button type="submit"
                    class="w-full rounded-lg border px-4 py-2 text-sm font-semibold transition <?= $account->isActive()
                        ? 'border-amber-200 text-amber-700 hover:bg-amber-50'
                        : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' ?>">
                <?= $account->isActive() ? 'Deactivate account' : 'Activate account' ?>
            </button>
        </form>

        <?php if ($canDelete): ?>
            <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/delete" class="mt-3"
                  onsubmit="return confirm('Permanently delete <?= e(addslashes($account->name)) ?>? This cannot be undone.');">
                <?= csrf_field() ?>
                <button type="submit"
                        class="w-full rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50">
                    Delete account
                </button>
            </form>
            <p class="mt-2 text-xs text-slate-500">
                Deleting removes the record for good. Deactivating keeps their history and can be undone.
            </p>
        <?php endif; ?>
    </section>
</div>
