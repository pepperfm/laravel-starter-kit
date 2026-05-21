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
