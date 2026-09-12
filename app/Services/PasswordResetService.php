<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;
use App\Models\AuthToken;
use App\Models\PasswordReset;
use App\Models\Setting;
use App\Models\User;

/**
 * Self-service Forgot Password (spec s7, s8).
 *
 * Step 3 of the spec checks whether the email belongs to an account, but the
 * response the visitor sees is the same either way — confirming which
 * addresses are registered would hand an attacker a user directory
 * (spec s18: "must not expose unnecessary account information").
 */
final class PasswordResetService
{
    /**
     * Begin a reset. Returns the reset link only when the app is running
     * locally with the `log` mail driver, so development can continue before
     * SMTP is configured; in every other environment the link is emailed and
     * never returned.
     *
     * @return array{accepted:bool, message:string, link:?string}
     */
    public static function request(Request $request, string $email, string $role): array
    {
        $neutral = [
            'accepted' => true,
            'message'  => 'If an account exists for that email address, password reset instructions have been sent to it.',
            'link'     => null,
        ];

        $key = RateLimiter::key('reset:' . $role, $email, $request->ip());

        if (RateLimiter::tooManyAttempts($key)) {
            return [
                'accepted' => false,
                'message'  => RateLimiter::lockoutMessage(),
                'link'     => null,
            ];
        }

        RateLimiter::hit($key, $request->ip());

        $user = User::findByEmail($email);

        // Wrong role, unknown address or a non-active account all end here,
        // with the same neutral response.
        if ($user === null || $user->role !== $role || !$user->isActive()) {
            return $neutral;
        }

        // Admin recovery is available only when policy allows it (spec s7).
        if ($user->role === User::ROLE_ADMIN && !Setting::current()->adminForgotPasswordEnabled) {
            return $neutral;
        }

        PasswordReset::invalidateAllForUser($user->id);

        $selector  = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        PasswordReset::create(
            userId:        $user->id,
            selector:      $selector,
            validatorHash: self::hashValidator($validator),
            expiresAt:     date('Y-m-d H:i:s', time() + (self::lifetimeMinutes() * 60)),
            ip:            $request->ip(),
        );

        $token = $selector . '.' . $validator;
        $link  = sprintf(
            '%s%s/reset-password?token=%s',
            (string) Config::get('app.url'),
            (string) Config::get('roles.' . $role . '.login'),
            rawurlencode($token),
        );

        MailService::send(
            $user->email,
            Config::get('app.name') . ' — password reset',
            self::emailBody($user, $link),
        );

        if (Config::get('app.env') === 'local' && Config::get('mail.driver') === 'log') {
            $neutral['link'] = $link;
        }

        return $neutral;
    }

    /**
     * Step 4 — password reset authorisation. Resolves a reset token to the
     * account it belongs to, or null when it is unknown, used or expired.
     */
    public static function authorise(?string $token, string $role): ?User
    {
        $parts = self::split($token);

        if ($parts === null) {
            return null;
        }

        [$selector, $validator] = $parts;

        $row = PasswordReset::findUsable($selector);

        if ($row === null) {
            return null;
        }

        if (!hash_equals((string) $row['validator_hash'], self::hashValidator($validator))) {
            PasswordReset::markUsed((int) $row['id']);

            return null;
        }

        $user = User::findById((int) $row['user_id']);

        // The link is bound to the role whose entry point issued it, so a
        // Manager's link cannot be redeemed on the Employee page.
        if ($user === null || $user->role !== $role || !$user->isActive()) {
            return null;
        }

        return $user;
    }

    /**
     * Step 5/6 — set the new password and burn the link.
     */
    public static function complete(string $token, string $role, string $password): bool
    {
        $user = self::authorise($token, $role);

        if ($user === null) {
            return false;
        }

        $parts = self::split($token);

        if ($parts === null) {
            return false;
        }

        $row = PasswordReset::findUsable($parts[0]);

        if ($row === null) {
            return false;
        }

        User::updatePassword($user->id, PasswordPolicy::hash($password));
        PasswordReset::markUsed((int) $row['id']);

        // Anyone still holding a token for this account loses it.
        AuthToken::revokeAllForUser($user->id);

        return true;
    }

    public static function lifetimeMinutes(): int
    {
        return max(5, (int) Config::get('auth.reset_lifetime', 60));
    }

    public static function expiredMessage(): string
    {
        return 'This password reset request is no longer valid. Please start the process again.';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function split(?string $token): ?array
    {
        if ($token === null || !str_contains($token, '.')) {
            return null;
        }

        [$selector, $validator] = explode('.', $token, 2);

        if (strlen($selector) !== 32 || strlen($validator) !== 64) {
            return null;
        }

        if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
            return null;
        }

        return [$selector, $validator];
    }

    private static function hashValidator(string $validator): string
    {
        return hash_hmac('sha256', $validator, (string) Config::get('app.key', ''));
    }

    private static function emailBody(User $user, string $link): string
    {
        $name    = htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8');
        $safe    = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $minutes = self::lifetimeMinutes();
        $app     = htmlspecialchars((string) Config::get('app.name'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1f2937;line-height:1.6">
          <p>Hello {$name},</p>
          <p>We received a request to reset the password for your {$app} account.</p>
          <p><a href="{$safe}" style="display:inline-block;background:#ea580c;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none">Reset your password</a></p>
          <p>Or paste this link into your browser:<br><span style="color:#6b7280">{$safe}</span></p>
          <p>This link expires in {$minutes} minutes and can be used once.</p>
          <p>If you did not request a password reset, you can ignore this email — your password will not change.</p>
        </div>
        HTML;
    }
}
