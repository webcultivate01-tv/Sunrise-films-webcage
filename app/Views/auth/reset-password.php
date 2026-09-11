<?php
/**
 * Forgot Password, steps 4-6 (spec s8). Reached only with a valid, unused,
 * unexpired reset token.
 *
 * @var string                $role
 * @var array<string, mixed>  $roleConfig
 * @var array<string, string> $errors
 * @var string                $token
 * @var string                $email
 * @var string                $policy
 */
?>
<div class="rounded-2xl border border-line bg-white p-7 shadow-sm sm:p-9">
    <div class="mb-7">
        <h2 class="text-2xl font-semibold tracking-tight text-ink">Create a new password</h2>
        <p class="mt-1.5 text-sm text-slate-500">
            Setting a new password for <span class="font-medium text-slate-700"><?= e($email) ?></span>.
        </p>
    </div>

    <form method="post" action="<?= e((string) $roleConfig['login']) ?>/reset-password" class="space-y-5" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <?php $pf = ['name' => 'password', 'label' => 'New password', 'autocomplete' => 'new-password', 'hint' => $policy]; ?>
        <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

        <?php $pf = ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'autocomplete' => 'new-password']; ?>
        <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

        <button type="submit"
                class="w-full rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2">
            Save new password
        </button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="<?= e((string) $roleConfig['login']) ?>" class="font-medium text-slate-600 underline-offset-2 hover:underline">
            &larr; Back to <?= e((string) $roleConfig['label']) ?> login
        </a>
    </p>
</div>
