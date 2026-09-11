<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Terminal helpers for sending a response. Every one of these exits, which
 * keeps controller code free of "return $this->redirect(...)" ceremony.
 */
final class Response
{
    public static function redirect(string $path, int $status = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $path, true, $status);
        }

        exit;
    }

    public static function html(string $body, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $body;

        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Security headers applied to every response.
     */
    public static function applySecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}
