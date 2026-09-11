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
<div class="mb-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">My Account</h1>
    <p class="mt-1 text-sm text-slate-500">Your details, photo and password.</p>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <section class="rounded-xl border border-line bg-white p-6 lg:col-span-1">
        <h2 class="text-base font-semibold text-ink">Photo</h2>

        <div class="mt-4 flex items-center gap-4">
            <?php if ($authUser->photoUrl() !== null): ?>
                <img src="<?= e($authUser->photoUrl()) ?>" alt="" class="h-16 w-16 shrink-0 rounded-full object-cover ring-1 ring-line">
            <?php else: ?>
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-brand-gradient text-lg font-semibold text-white"><?= e($authUser->initials()) ?></span>
            <?php endif; ?>
            <p class="text-sm text-slate-500">JPG, PNG, GIF or WEBP.</p>
        </div>

        <form method="post" action="<?= e($base) ?>/profile/photo" class="mt-5 space-y-3" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" required
                   class="<?= input_classes($errors, 'photo') ?> text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">
            <?= field_error($errors, 'photo') ?>
            <button type="submit"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink transition hover:bg-slate-50">
                Upload photo
            </button>
        </form>

        <dl class="mt-6 space-y-3 border-t border-line pt-6 text-sm">
            <div>
                <dt class="text-slate-500">Role</dt>
                <dd class="font-medium text-ink"><?= e($authUser->roleLabel()) ?></dd>
            </div>
            <div>
                <dt class="text-slate-500">Status</dt>
                <dd>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= status_badge($authUser->status) ?>">
                        <?= e(ucfirst($authUser->status)) ?>
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Member since</dt>
                <dd class="font-medium text-ink"><?= e(pretty_date($authUser->createdAt)) ?></dd>
            </div>
        </dl>
    </section>

    <section class="rounded-xl border border-line bg-white p-6 lg:col-span-2">
        <h2 class="text-base font-semibold text-ink">Details</h2>
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
                    class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                Save details
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-line bg-white p-6 lg:col-span-3">
        <h2 class="text-base font-semibold text-ink">Change password</h2>
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
                    class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                Update password
            </button>
        </form>
    </section>
</div>
