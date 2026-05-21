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

if (!function_exists('when')) {
    function when(bool $condition, callable $true, ?callable $false = null)
    {
        if ($condition) {
            return $true();
        }
        if ($false) {
            return $false();
        }

        return null;
    }
}

if (!function_exists('valueOrDefault')) {
    function valueOrDefault(mixed $value = null, mixed $default = null, ...$args)
    {
        if (blank($args) && blank($value)) {
            return $default;
        }

        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('db')) {
    function db(?string $connection = null): \Illuminate\Database\ConnectionInterface
    {
        return app('db')->connection($connection);
    }
}
