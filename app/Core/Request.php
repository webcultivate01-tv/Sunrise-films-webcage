<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish view over the current HTTP request.
 */
final class Request
{
    /** @param array<string, mixed> $query
     *  @param array<string, mixed> $body
     *  @param array<string, mixed> $server
     *  @param array<string, string> $cookies
     *  @param array<string, mixed> $files */
    private function __construct(
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $cookies,
        private readonly array $files,
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        // Allow HTML forms to emulate verbs they cannot send natively.
        if ($method === 'POST' && isset($this->body['_method'])) {
            $override = strtoupper((string) $this->body['_method']);

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    public function path(): string
    {
        $uri  = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        // Normalise: no trailing slash, always a leading one.
        $path = '/' . trim(rawurldecode($path), '/');

        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Trimmed string input — what every form field in this app actually wants.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @param  list<string> $keys
     * @return array<string, string>
     */
    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->string($key);
        }

        return $result;
    }

    /**
     * A single uploaded file for $key, or null when none was sent.
     *
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || !isset($file['tmp_name'], $file['error']) || !is_string($file['tmp_name'])) {
            return null;
        }

        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name'     => (string) ($file['name'] ?? ''),
            'type'     => (string) ($file['type'] ?? ''),
            'tmp_name' => $file['tmp_name'],
            'error'    => (int) $file['error'],
            'size'     => (int) ($file['size'] ?? 0),
        ];
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->cookies[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function header(string $name): ?string
    {
        $key   = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');

        if ($header !== null && preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        return is_string($ip) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->header('User-Agent') ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }
}
