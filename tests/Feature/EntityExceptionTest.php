<?php

declare(strict_types=1);

use App\Exceptions\EntityNotFoundException;
use App\Exceptions\InvalidParameterException;

it('creates typed http exceptions from their message factories', function (): void {
    $notFound = EntityNotFoundException::make('Missing');
    $invalid = InvalidParameterException::make('Invalid');

    expect($notFound->getStatusCode())->toBe(404)
        ->and($notFound->getMessage())->toBe('Missing')
        ->and($invalid->getStatusCode())->toBe(422)
        ->and($invalid->getMessage())->toBe('Invalid');
});
