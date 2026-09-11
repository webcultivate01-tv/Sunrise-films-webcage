<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'Page not found.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You are not authorized to access this page.'): self
    {
        return new self(403, $message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
