<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Reusable mail sender. The SMTP provider is out of scope for the auth spec
 * (s22), so `log` is the default driver: messages are written to
 * storage/mail/ where they can be read during development. Swapping in
 * PHPMailer later means adding one branch to send().
 */
final class MailService
{
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $driver = (string) Config::get('mail.driver', 'log');

        return match ($driver) {
            'mail'  => self::sendWithPhpMail($to, $subject, $htmlBody),
            default => self::writeToDisk($to, $subject, $htmlBody),
        };
    }

    private static function sendWithPhpMail(string $to, string $subject, string $htmlBody): bool
    {
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . Config::get('mail.from_name') . ' <' . Config::get('mail.from_address') . '>',
        ]);

        return mail($to, $subject, $htmlBody, $headers);
    }

    private static function writeToDisk(string $to, string $subject, string $htmlBody): bool
    {
        $directory = (string) Config::get('mail.log_path');

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $file = sprintf(
            '%s/%s-%s.html',
            $directory,
            date('Ymd-His'),
            substr(preg_replace('/[^a-z0-9]+/i', '-', $to) ?? 'mail', 0, 40),
        );

        $contents = "<!-- To: {$to} -->\n<!-- Subject: {$subject} -->\n<!-- Sent: "
            . date('c') . " -->\n" . $htmlBody;

        return file_put_contents($file, $contents) !== false;
    }
}
