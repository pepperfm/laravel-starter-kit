<?php

declare(strict_types=1);

namespace App\Exceptions;

class EntityStoreException extends EntityException
{
    public function __construct(string $message = '')
    {
        parent::__construct(message: $message);
    }
}
