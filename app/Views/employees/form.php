<?php

use App\Models\User;

/**
 * Add / Edit a user account (module spec s7, s8, s10).
 *
 * The role select only ever contains the roles the signed-in user may assign,
 * so a Manager is never offered the Manager option (module spec s8). On an
 * edit the role is shown read-only - it is fixed at creation time.
 *
 * @var User|null             $account   Null when adding.
 * @var string                $baseUrl
 * @var array<string, string> $roles     role => label, assignable by this user.
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$isEdit = $account !== null;
$action = $isEdit ? $baseUrl . '/' . $account->id : $baseUrl;
$noun   = count($roles) > 1 ? 'User' : 'Employee';
?>
<div class="mb-5">
    <a href="<?= e($isEdit ? $baseUrl . '/' . $account->id : $baseUrl) ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Back
    </a>
    <div class="mt-3 flex items-center gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-gradient text-sm font-semibold text-white shadow-sm">
            <?= $isEdit ? e($account->initials()) : '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20v-1a5 5 0 0 1 5-5h3a5 5 0 0 1 5 5v1"/><path d="M17 8h5M19.5 5.5v5"/></svg>' ?>
        </span>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                <?= $isEdit ? 'Edit ' . e($account->name) : 'Add ' . e($noun) ?>
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
                <?= $isEdit ? 'Changing the email address changes the address they sign in with.' : 'Create a new account and assign its role.' ?>
            </p>
        </div>
    </div>
</div>

<div>
    <form method="post" action="<?= e($action) ?>" class="space-y-4" novalidate>
        <?= csrf_field() ?>

        <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Details</h2>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="field-name" class="mb-1.5 block text-sm font-medium text-slate-700">Full name</label>
                    <input type="text" id="field-name" name="name"
                           value="<?= old($old, 'name', $account->name ?? '') ?>"
                           class="<?= input_classes($errors, 'name') ?>" placeholder="Asha Menon" autofocus>
                    <?= field_error($errors, 'name') ?>
                </div>

                <div>
                    <label for="field-email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                    <input type="email" id="field-email" name="email"
                           value="<?= old($old, 'email', $account->email ?? '') ?>"
                           class="<?= input_classes($errors, 'email') ?>" placeholder="asha@sunrisefilms.com">
                    <?= field_error($errors, 'email') ?>
                </div>

                <div>
                    <label for="field-phone" class="mb-1.5 block text-sm font-medium text-slate-700">Mobile number</label>
                    <input type="tel" id="field-phone" name="phone"
                           value="<?= old($old, 'phone', $account->phone ?? '') ?>"
                           class="<?= input_classes($errors, 'phone') ?>" placeholder="+91 98765 43210">
                    <?= field_error($errors, 'phone') ?>
                </div>

                <div class="sm:col-span-1 lg:col-span-2">
                    <label for="field-address" class="mb-1.5 block text-sm font-medium text-slate-700">Address</label>
                    <textarea id="field-address" name="address" rows="2"
                              class="<?= input_classes($errors, 'address') ?>"
                              placeholder="Flat, street, city, PIN"><?= old($old, 'address', $account->address ?? '') ?></textarea>
                    <?= field_error($errors, 'address') ?>
                </div>

                <?php if (!$isEdit): ?>
                    <div class="sm:col-span-1 lg:col-span-1">
                        <label for="field-temporary-password" class="mb-1.5 block text-sm font-medium text-slate-700">Temporary password</label>
                        <input type="text" id="field-temporary-password" name="temporary_password"
                               value="<?= old($old, 'temporary_password', '') ?>"
                               class="<?= input_classes($errors, 'temporary_password') ?>" placeholder="Leave blank to auto-generate">
                        <?= field_error($errors, 'temporary_password') ?>
                        <p class="mt-1.5 text-xs text-slate-500">Set one so they can sign in right away, or leave blank to auto-generate and email one.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3 4 6.5V11c0 4.9 3.2 8.9 8 10 4.8-1.1 8-5.1 8-10V6.5Z"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-ink">Role &amp; access</h2>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="field-role" class="mb-1.5 block text-sm font-medium text-slate-700">Role</label>
                    <?php if ($isEdit): ?>
                        <p class="rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600">
                            <?= e($account->roleLabel()) ?>
                        </p>
                        <p class="mt-1.5 text-xs text-slate-500">The role is set when the account is created.</p>
                    <?php elseif (count($roles) === 1): ?>
                        <?php $onlyRole = array_key_first($roles); ?>
                        <input type="hidden" name="role" value="<?= e((string) $onlyRole) ?>">
                        <p class="rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600">
                            <?= e($roles[$onlyRole]) ?>
                        </p>
                        <p class="mt-1.5 text-xs text-slate-500">You can create <?= e(mb_strtolower($roles[$onlyRole])) ?> accounts.</p>
                        <?= field_error($errors, 'role') ?>
                    <?php else: ?>
                        <select id="field-role" name="role" class="<?= input_classes($errors, 'role') ?>">
                            <option value="">Select a role</option>
                            <?php foreach ($roles as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>" <?= ($old['role'] ?? '') === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'role') ?>
                    <?php endif; ?>
                </div>

                <?php if (!$isEdit): ?>
                    <div>
                        <label for="field-status" class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                        <select id="field-status" name="status" class="<?= input_classes($errors, 'status') ?>">
                            <option value="<?= User::STATUS_ACTIVE ?>">Active - can sign in immediately</option>
                            <option value="<?= User::STATUS_INACTIVE ?>" <?= ($old['status'] ?? '') === User::STATUS_INACTIVE ? 'selected' : '' ?>>
                                Inactive - cannot sign in yet
                            </option>
                        </select>
                        <?= field_error($errors, 'status') ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                <?= $isEdit ? 'Save changes' : 'Create account' ?>
            </button>
            <a href="<?= e($isEdit ? $baseUrl . '/' . $account->id : $baseUrl) ?>"
               class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>
