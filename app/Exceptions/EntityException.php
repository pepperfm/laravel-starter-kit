<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** @phpstan-consistent-constructor */
abstract class EntityException extends HttpException
{
    /**
     * The stores.
     *
     * @var array<array-key, mixed>
     */
    protected array $data = [];

    public function __construct(
        int $statusCode = 500,
        string $message = '',
        ?\Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    final public static function make(string $message): static
    {
        return new static($message, ...func_get_args());
    }
}
