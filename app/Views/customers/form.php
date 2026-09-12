<?php

use App\Models\Customer;

/**
 * Customer Registration / Edit (module spec s3, s4).
 *
 * @var Customer|null         $customer  Null when registering.
 * @var string                $baseUrl
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$isEdit = $customer !== null;
$action = $isEdit ? $baseUrl . '/' . $customer->id : $baseUrl;
$back   = $isEdit ? $baseUrl . '/' . $customer->id : $baseUrl;
?>
<div class="mb-5">
    <a href="<?= e($back) ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Back
    </a>
    <div class="mt-3 flex items-center gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-gradient text-sm font-semibold text-white shadow-sm">
            <?= $isEdit ? e($customer->initials()) : '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20v-1a5 5 0 0 1 5-5h3a5 5 0 0 1 5 5v1"/><path d="M17 8h5M19.5 5.5v5"/></svg>' ?>
        </span>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                <?= $isEdit ? 'Edit ' . e($customer->name) : 'Register Customer' ?>
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
                <?= $isEdit ? 'Update the details on record for this customer.' : 'Add a new customer to the system.' ?>
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
                <h2 class="text-base font-semibold text-ink">Customer details</h2>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="field-name" class="mb-1.5 block text-sm font-medium text-slate-700">Customer name</label>
                    <input type="text" id="field-name" name="name"
                           value="<?= old($old, 'name', $customer->name ?? '') ?>"
                           class="<?= input_classes($errors, 'name') ?>" placeholder="Ravi Kulkarni" autofocus>
                    <?= field_error($errors, 'name') ?>
                </div>

                <div>
                    <label for="field-email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                    <input type="email" id="field-email" name="email"
                           value="<?= old($old, 'email', $customer->email ?? '') ?>"
                           class="<?= input_classes($errors, 'email') ?>" placeholder="ravi@example.com">
                    <?= field_error($errors, 'email') ?>
                </div>

                <div>
                    <label for="field-phone" class="mb-1.5 block text-sm font-medium text-slate-700">Mobile number</label>
                    <input type="tel" id="field-phone" name="phone"
                           value="<?= old($old, 'phone', $customer->phone ?? '') ?>"
                           class="<?= input_classes($errors, 'phone') ?>" placeholder="+91 98765 43210">
                    <?= field_error($errors, 'phone') ?>
                </div>

                <div class="sm:col-span-2 <?= $isEdit ? 'lg:col-span-3' : '' ?>">
                    <label for="field-address" class="mb-1.5 block text-sm font-medium text-slate-700">Address</label>
                    <textarea id="field-address" name="address" rows="2"
                              class="<?= input_classes($errors, 'address') ?>"
                              placeholder="Flat, street, city, PIN"><?= old($old, 'address', $customer->address ?? '') ?></textarea>
                    <?= field_error($errors, 'address') ?>
                </div>

                <?php if (!$isEdit): ?>
                    <div>
                        <label for="field-status" class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                        <select id="field-status" name="status" class="<?= input_classes($errors, 'status') ?>">
                            <option value="<?= Customer::STATUS_ACTIVE ?>">Active</option>
                            <option value="<?= Customer::STATUS_INACTIVE ?>" <?= ($old['status'] ?? '') === Customer::STATUS_INACTIVE ? 'selected' : '' ?>>
                                Inactive
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
                <?= $isEdit ? 'Save changes' : 'Save Customer' ?>
            </button>
            <a href="<?= e($back) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
        </div>
    </form>
</div>
