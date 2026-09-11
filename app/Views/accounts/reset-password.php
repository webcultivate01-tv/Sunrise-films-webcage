<?php

use App\Models\User;

/**
 * A superior setting a subordinate password: Admin for a Manager (spec s10),
 * Manager for an Employee (spec s11).
 *
 * @var User                  $account
 * @var string                $baseUrl
 * @var string                $policy
 * @var array<string, string> $errors
 */
?>
<div class="mb-8">
    <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 underline-offset-2 hover:underline">&larr; Back</a>
    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-ink">Reset password</h1>
    <p class="mt-1 text-sm text-slate-500">
        For <span class="font-medium text-slate-700"><?= e($account->name) ?></span> (<?= e($account->email) ?>).
    </p>
</div>

<div class="max-w-md rounded-xl border border-line bg-white p-6">
    <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
        Setting a new password signs this account out everywhere. Share the new password with them securely.
    </div>

    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/password" class="space-y-5" novalidate>
        <?= csrf_field() ?>

        <?php $pf = ['name' => 'password', 'label' => 'New password', 'autocomplete' => 'new-password', 'hint' => $policy]; ?>
        <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

        <?php $pf = ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'autocomplete' => 'new-password']; ?>
        <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                Reset password
            </button>
            <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>
