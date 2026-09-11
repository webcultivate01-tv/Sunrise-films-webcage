<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Plain PHP templates. A view renders into $content and is then wrapped by an
 * optional layout, which keeps the markup free of framework syntax.
 */
final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $body = self::capture($template, $data);

        if ($layout === null) {
            return $body;
        }

        return self::capture('layouts/' . $layout, $data + ['content' => $body]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $template, array $data): string
    {
        $path = BASE_PATH . '/app/Views/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($path)) {
            throw HttpException::notFound(sprintf('View [%s] was not found.', $template));
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }
}
