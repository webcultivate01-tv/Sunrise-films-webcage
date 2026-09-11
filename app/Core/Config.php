<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only access to config/config.php using "dot" notation.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $path): void
    {
        /** @var array<string, mixed> $items */
        $items = require $path;
        self::$items = $items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function role(string $role): array
    {
        /** @var array<string, mixed>|null $config */
        $config = self::get('roles.' . $role);

        if ($config === null) {
            throw new \InvalidArgumentException(sprintf('Unknown role [%s].', $role));
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    public static function roleNames(): array
    {
        /** @var array<string, mixed> $roles */
        $roles = self::get('roles', []);

        return array_keys($roles);
    }
}
