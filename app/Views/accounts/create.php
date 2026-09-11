<?php

use App\Models\User;

/**
 * Create an account one level down (spec s12). There is no public sign-up, so
 * this form is the only way a new account comes into existence.
 *
 * @var string                $managesLabel
 * @var string                $baseUrl
 * @var string                $loginPath
 * @var string                $policy
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
?>
<div class="mb-8">
    <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 underline-offset-2 hover:underline">&larr; Back to <?= e($managesLabel) ?>s</a>
    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-ink">Create <?= e($managesLabel) ?></h1>
    <p class="mt-1 text-sm text-slate-500">
        The account is usable straight away: they sign in at <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs"><?= e($loginPath) ?></code>
        with the email and password you set here.
    </p>
</div>

<form method="post" action="<?= e($baseUrl) ?>" class="max-w-2xl space-y-6" novalidate>
    <?= csrf_field() ?>

    <section class="rounded-xl border border-line bg-white p-6">
        <h2 class="text-base font-semibold text-ink">Account details</h2>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="field-name" class="mb-1.5 block text-sm font-medium text-slate-700">Full name</label>
                <input type="text" id="field-name" name="name" value="<?= old($old, 'name') ?>"
                       class="<?= input_classes($errors, 'name') ?>" placeholder="Asha Menon" autofocus>
                <?= field_error($errors, 'name') ?>
            </div>

            <div>
                <label for="field-email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                <input type="email" id="field-email" name="email" value="<?= old($old, 'email') ?>"
                       class="<?= input_classes($errors, 'email') ?>" placeholder="asha@sunrisefilms.com">
                <?= field_error($errors, 'email') ?>
            </div>

            <div>
                <label for="field-phone" class="mb-1.5 block text-sm font-medium text-slate-700">
                    Phone <span class="font-normal text-slate-400">(optional)</span>
                </label>
                <input type="text" id="field-phone" name="phone" value="<?= old($old, 'phone') ?>"
                       class="<?= input_classes($errors, 'phone') ?>" placeholder="+91 98765 43210">
                <?= field_error($errors, 'phone') ?>
            </div>

            <div class="sm:col-span-2">
                <label for="field-status" class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                <select id="field-status" name="status" class="<?= input_classes($errors, 'status') ?>">
                    <option value="<?= User::STATUS_ACTIVE ?>">Active - can sign in immediately</option>
                    <option value="<?= User::STATUS_INACTIVE ?>" <?= ($old['status'] ?? '') === User::STATUS_INACTIVE ? 'selected' : '' ?>>
                        Inactive - cannot sign in yet
                    </option>
                </select>
                <?= field_error($errors, 'status') ?>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-white p-6">
        <h2 class="text-base font-semibold text-ink">Initial password</h2>
        <p class="mt-1.5 text-sm text-slate-500">
            Share this with them securely. They can change it from their own panel at any time.
        </p>

        <div class="mt-5 max-w-md space-y-5">
            <?php $pf = ['name' => 'password', 'label' => 'Password', 'autocomplete' => 'new-password', 'hint' => $policy]; ?>
            <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

            <?php $pf = ['name' => 'password_confirmation', 'label' => 'Confirm password', 'autocomplete' => 'new-password']; ?>
            <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>
        </div>
    </section>

    <div class="flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
            Create <?= e($managesLabel) ?>
        </button>
        <a href="<?= e($baseUrl) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
    </div>
</form>
