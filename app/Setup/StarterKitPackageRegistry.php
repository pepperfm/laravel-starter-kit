<?php

declare(strict_types=1);

namespace App\Setup;

final class StarterKitPackageRegistry
{
    /**
     * @param array<int, string> $packages
     *
     * @return array<int, string>
     */
    public static function normalized(array $packages): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $packages))));
    }

    public static function isDevPackage(string $package): bool
    {
        return in_array($package, [
            'laravel/telescope',
            'spatie/laravel-ray',
        ], true);
    }

    /**
     * @param string $package
     * @param bool $usingSail
     *
     * @return array<int, array<int, string>>
     */
    public static function postInstallCommandsFor(string $package, bool $usingSail = false): array
    {
        $commands = [
            'laravel/sanctum' => [
                ['install:api', '--without-migration-prompt'],
            ],
            'opcodesio/log-viewer' => [
                ['log-viewer:publish'],
            ],
            'laravel/horizon' => [
                ['horizon:install'],
            ],
            'laravel/telescope' => [
                ['telescope:install'],
                ['migrate'],
            ],
            'laravel/pulse' => [
                ['vendor:publish', '--provider=Laravel\\Pulse\\PulseServiceProvider'],
                ['migrate'],
            ],
            'defstudio/telegraph' => [
                ['vendor:publish', '--tag=telegraph-migrations'],
                ['migrate'],
            ],
            'spatie/laravel-ray' => [
                $usingSail ? ['ray:publish-config', '--docker'] : ['ray:publish-config'],
            ],
            'spatie/laravel-medialibrary' => [
                ['vendor:publish', '--provider=Spatie\\MediaLibrary\\MediaLibraryServiceProvider', '--tag=medialibrary-migrations'],
                ['migrate'],
            ],
            'spatie/laravel-permission' => [
                ['vendor:publish', '--provider=Spatie\\Permission\\PermissionServiceProvider'],
                ['optimize:clear'],
                ['migrate'],
            ],
        ];

        return $commands[$package] ?? [];
    }

    /**
     * @param array<int, string> $packages
     * @param array<int, string> $devPackages
     * @param bool $usingSail
     *
     * @return array<string, array<int, array<int, string>>>
     */
    public static function selectedPostInstallCommands(
        array $packages,
        array $devPackages,
        bool $usingSail = false,
    ): array {
        $commands = [];

        foreach (self::normalized([...$packages, ...$devPackages]) as $packageSpec) {
            $package = self::packageName($packageSpec);
            $packageCommands = self::postInstallCommandsFor($package, $usingSail);

            if ($packageCommands !== []) {
                $commands[$package] = $packageCommands;
            }
        }

        return $commands;
    }

    public static function packageName(string $packageSpec): string
    {
        [$package] = explode(':', $packageSpec, 2);

        return trim($package);
    }
}
