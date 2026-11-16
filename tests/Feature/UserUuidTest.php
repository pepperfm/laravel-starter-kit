<?php

declare(strict_types=1);

use App\Models\User;

it('uses uuid primary keys for users', function (): void {
    $user = new User();

    expect($user->getKeyName())->toBe('id')
        ->and($user->getKeyType())->toBe('string')
        ->and($user->getIncrementing())->toBeFalse()
        ->and($user->uniqueIds())->toBe(['id']);
});

it('only mass assigns explicit user profile fields', function (): void {
    expect((new User())->getFillable())->toBe([
        'name',
        'email',
        'password',
    ]);
});
