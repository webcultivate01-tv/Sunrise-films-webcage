<?php

use App\Models\User;

/**
 * The signed-in user's own account details, photo and password change.
 *
 * @var User                  $authUser
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var string                $policy
 * @var string                $base
 */
?>
<div class="mb-8 flex items-center gap-4">
    <div class="relative shrink-0">
        <?php if ($authUser->photoUrl() !== null): ?>
            <img src="<?= e($authUser->photoUrl()) ?>" alt="" class="h-14 w-14 rounded-full object-cover ring-4 ring-white shadow-md">
        <?php else: ?>
            <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-gradient text-base font-semibold text-white ring-4 ring-white shadow-md"><?= e($authUser->initials()) ?></span>
        <?php endif; ?>
        <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full ring-2 ring-white <?= $authUser->isActive() ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
    </div>
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">My Account</h1>
        <p class="mt-0.5 text-sm text-slate-500"><?= e($authUser->name) ?> &middot; <?= e($authUser->roleLabel()) ?></p>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm lg:col-span-1">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="10.5" r="2"/><path d="m4.5 18 4-4.2a1.8 1.8 0 0 1 2.4-.1l1.1 1 3.4-3.6a1.8 1.8 0 0 1 2.5-.1l1.6 1.5"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-ink">Photo</h2>
        </div>

        <form method="post" action="<?= e($base) ?>/profile/photo" class="mt-5" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <label for="field-photo"
                   class="flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-4 py-6 text-center transition hover:border-brand-300 hover:bg-brand-50/40">
                <?php if ($authUser->photoUrl() !== null): ?>
                    <img src="<?= e($authUser->photoUrl()) ?>" alt="" class="h-14 w-14 rounded-full object-cover ring-1 ring-line">
                <?php else: ?>
                    <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-gradient text-lg font-semibold text-white"><?= e($authUser->initials()) ?></span>
                <?php endif; ?>
                <span class="text-sm font-medium text-brand-700">Choose a new photo</span>
                <span class="text-xs text-slate-500">JPG, PNG, GIF or WEBP</span>
                <input type="file" id="field-photo" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" required class="sr-only">
            </label>
            <?= field_error($errors, 'photo') ?>
            <button type="submit"
                    class="mt-4 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-slate-50">
                Upload photo
            </button>
        </form>

        <dl class="mt-6 space-y-3.5 border-t border-line pt-6 text-sm">
            <div class="flex items-center justify-between">
                <dt class="text-slate-500">Role</dt>
                <dd class="font-medium text-ink"><?= e($authUser->roleLabel()) ?></dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-slate-500">Status</dt>
                <dd>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= status_badge($authUser->status) ?>">
                        <?= e(ucfirst($authUser->status)) ?>
                    </span>
                </dd>
            </div>
            <div class="flex items-center justify-between">
                <dt class="text-slate-500">Member since</dt>
                <dd class="font-medium text-ink"><?= e(pretty_date($authUser->createdAt)) ?></dd>
            </div>
        </dl>
    </section>

    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm lg:col-span-2">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="8" r="3.2"/><path d="M5 20v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-ink">Details</h2>
        </div>
        <p class="mt-1.5 text-sm text-slate-500">Your name, email and phone number.</p>

        <form method="post" action="<?= e($base) ?>/profile" class="mt-6 max-w-md space-y-5" novalidate>
            <?= csrf_field() ?>

            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-ink">Full name</label>
                <input type="text" id="name" name="name" value="<?= old($old, 'name', $authUser->name) ?>"
                       class="<?= input_classes($errors, 'name') ?>" required>
                <?= field_error($errors, 'name') ?>
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink">Email</label>
                <input type="email" id="email" name="email" value="<?= old($old, 'email', $authUser->email) ?>"
                       class="<?= input_classes($errors, 'email') ?>" required>
                <?= field_error($errors, 'email') ?>
            </div>

            <div>
                <label for="phone" class="mb-1.5 block text-sm font-medium text-ink">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= old($old, 'phone', (string) $authUser->phone) ?>"
                       class="<?= input_classes($errors, 'phone') ?>">
                <?= field_error($errors, 'phone') ?>
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                Save details
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm lg:col-span-3">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-ink">Change password</h2>
        </div>
        <p class="mt-1.5 text-sm text-slate-500">
            You will stay signed in on this device; every other device is signed out.
        </p>

        <form method="post" action="<?= e($base) ?>/profile/password" class="mt-6 max-w-md space-y-5" novalidate>
            <?= csrf_field() ?>

            <?php $pf = ['name' => 'current_password', 'label' => 'Current password', 'autocomplete' => 'current-password']; ?>
            <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

            <?php $pf = ['name' => 'password', 'label' => 'New password', 'autocomplete' => 'new-password', 'hint' => $policy]; ?>
            <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

            <?php $pf = ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'autocomplete' => 'new-password']; ?>
            <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                Update password
            </button>
        </form>
    </section>
</div>
