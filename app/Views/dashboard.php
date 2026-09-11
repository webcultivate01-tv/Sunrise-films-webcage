<?php

use App\Models\User;

/**
 * The panel each role lands on (spec s4.5).
 *
 * @var User               $authUser
 * @var string|null        $manages
 * @var string|null        $managesLabel
 * @var string|null        $managesUrl
 * @var array<string, int> $statusCounts
 * @var int                $activeTokens
 */
$base = (string) config('roles.' . $authUser->role . '.login');
?>
<div class="mb-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= e($authUser->roleLabel()) ?> Panel</h1>
    <p class="mt-1 text-sm text-slate-500">
        Signed in as <?= e($authUser->email) ?> &middot; last sign-in <?= e(pretty_date($authUser->lastLoginAt, 'this is your first')) ?>
    </p>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php if ($manages !== null): ?>
        <div class="rounded-xl border border-line bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active <?= e((string) $managesLabel) ?>s</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= (int) $statusCounts[User::STATUS_ACTIVE] ?></p>
        </div>
        <div class="rounded-xl border border-line bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Inactive</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= (int) $statusCounts[User::STATUS_INACTIVE] ?></p>
        </div>
        <div class="rounded-xl border border-line bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Suspended</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= (int) $statusCounts[User::STATUS_SUSPENDED] ?></p>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">My active sessions</p>
        <p class="mt-2 text-3xl font-semibold tracking-tight text-ink"><?= (int) $activeTokens ?></p>
        <p class="mt-1 text-xs text-slate-500">Devices holding a valid token</p>
    </div>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    <?php if ($manages !== null && $managesUrl !== null): ?>
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Manage <?= e((string) $managesLabel) ?>s</h2>
            <p class="mt-1.5 text-sm text-slate-500">
                Create <?= e(mb_strtolower((string) $managesLabel)) ?> accounts, reset their passwords and control who
                is allowed to sign in.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="<?= e($managesUrl) ?>"
                   class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                    View <?= e((string) $managesLabel) ?>s
                </a>
                <a href="<?= e($managesUrl) ?>/create"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Create <?= e((string) $managesLabel) ?>
                </a>
            </div>
        </section>
    <?php else: ?>
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Your workspace</h2>
            <p class="mt-1.5 text-sm text-slate-500">
                Your account is managed by your manager. If you need access to something you cannot see here,
                ask them to update your account.
            </p>
        </section>
    <?php endif; ?>

    <section class="rounded-xl border border-line bg-white p-6">
        <h2 class="text-base font-semibold text-ink">Account security</h2>
        <p class="mt-1.5 text-sm text-slate-500">
            Change your password whenever you need to. Doing so signs out every other device immediately.
        </p>
        <a href="<?= e($base) ?>/profile"
           class="mt-5 inline-block rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Change my password
        </a>
    </section>
</div>
