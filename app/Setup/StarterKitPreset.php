<?php

declare(strict_types=1);

namespace App\Setup;

enum StarterKitPreset: string
{
    case Api = 'api';
    case Admin = 'admin';
    case Observability = 'observability';
    case Full = 'full';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $preset): string => $preset->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function packages(): array
    {
        return match ($this) {
            self::Api => [
                'laravel/sanctum',
                'pepperfm/swagger-nuxt-ui-for-laravel',
                'spatie/laravel-data',
            ],
            self::Admin => [
                'inertiajs/inertia-laravel:^3.0',
                'tightenco/ziggy:^2.5',
            ],
            self::Observability => [
                'opcodesio/log-viewer',
                'laravel/horizon',
                'laravel/pulse',
            ],
            self::Full => self::normalized([
                ...self::Admin->packages(),
                ...self::Api->packages(),
                ...self::Observability->packages(),
            ]),
        };
    }

    /**
     * @return array<int, string>
     */
    public function devPackages(): array
    {
        return match ($this) {
            self::Observability => [
                'laravel/telescope',
            ],
            self::Full => self::Observability->devPackages(),
            default => [],
        };
    }

    public function installsAdminFrontend(): bool
    {
        return $this === self::Admin || $this === self::Full;
    }

    /**
     * @param array<int, string> $packages
     *
     * @return array<int, string>
     */
    private static function normalized(array $packages): array
    {
        return array_map('trim', $packages)
                |> array_filter(...)
                |> array_unique(...)
                |> array_values(...);
    }
}
