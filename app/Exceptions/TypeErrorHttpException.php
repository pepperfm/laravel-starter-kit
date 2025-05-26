<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TypeErrorHttpException extends HttpException
{
    public function __construct(
        string $message = '',
        int $statusCode = 306,
        ?\Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }
}
