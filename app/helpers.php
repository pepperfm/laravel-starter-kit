<?php

declare(strict_types=1);

if (!function_exists('user')) {
    /**
     * @param ?string $guard
     *
     * @return \App\Models\User|null
     */
    function user(?string $guard = null): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return auth($guard)->user();
    }
}
