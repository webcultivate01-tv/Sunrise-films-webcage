<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\User;

/**
 * The welcome email a new account receives (module spec s11). It goes out for
 * every account Employee Management creates - a Manager created by an Admin,
 * an Employee created by an Admin, an Employee created by a Manager - and
 * carries the one-time password the system generated for them.
 *
 * Delivery itself is MailService's job, so switching from the development
 * `log` driver to SMTP changes nothing here.
 */
final class WelcomeMailer
{
    public static function send(User $account, User $creator, string $temporaryPassword): bool
    {
        return MailService::send(
            $account->email,
            sprintf('Welcome to %s', (string) Config::get('app.name')),
            self::body($account, $creator, $temporaryPassword),
        );
    }

    private static function body(User $account, User $creator, string $temporaryPassword): string
    {
        $app       = self::escape((string) Config::get('app.name'));
        $name      = self::escape($account->name);
        $role      = self::escape($account->roleLabel());
        $creatorBy = self::escape($creator->name . ' (' . $creator->roleLabel() . ')');
        $email     = self::escape($account->email);
        $password  = self::escape($temporaryPassword);
        $link      = self::escape((string) Config::get('app.url') . $account->loginPath());

        return <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1f2937;line-height:1.6">
          <p>Hello {$name},</p>
          <p>
            An account has been created for you at <strong>{$app}</strong> by {$creatorBy}.
            You have been added as a <strong>{$role}</strong>.
          </p>
          <p>Sign in with these details:</p>
          <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;background:#f8fafc;border-radius:6px">
            <tr><td style="color:#6b7280">Sign-in page</td><td><a href="{$link}">{$link}</a></td></tr>
            <tr><td style="color:#6b7280">Email</td><td><strong>{$email}</strong></td></tr>
            <tr><td style="color:#6b7280">Temporary password</td><td><strong>{$password}</strong></td></tr>
          </table>
          <p>
            <a href="{$link}" style="display:inline-block;background:#ea580c;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none">Sign in</a>
          </p>
          <p>
            Please change this password from <em>My Account</em> as soon as you sign in, and do not share it
            with anyone. If you were not expecting this email, contact {$creatorBy}.
          </p>
          <p style="color:#6b7280">- The {$app} team</p>
        </div>
        HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
