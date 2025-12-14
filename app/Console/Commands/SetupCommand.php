<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\{info, select, multiselect, note, warning, error, confirm, text};

/*
 * Class SetupCommand
 *
 * Interactive setup command for installing optional packages
 * like API support, Telegram bot integration, and Ray debugger.
 */

class SetupCommand extends Command
{
    protected $signature = 'starter:setup';

    protected $description = 'Interactive starter kit setup (optional packages + optional Sail bootstrapping).';

    /**
     * Composer "require" arguments (production deps).
     *
     * @var array<int, string>
     */
    protected array $packages = [];

    /**
     * Composer "require --dev" arguments (dev deps).
     *
     * @var array<int, string>
     */
    protected array $devPackages = [];

    protected bool $wantsSail = false;

    protected bool $startedSail = false;

    public function handle(): int
    {
        info('🔧 Laravel Starter Kit: Optional Setup');

        $this->wantsSail = confirm(
            label: 'Are you going to use Laravel Sail for this project?',
        );

        if ($this->wantsSail) {
            $this->configureEnvironment();

            $this->startedSail = confirm(
                label: 'Would you like to automatically build and start Sail containers now?',
                default: false,
            );

            if ($this->startedSail) {
                $this->runSailUp();
            } else {
                note('⚠️ Sail auto-build skipped.');
            }
        }

        $this->selectAdminPanel();
        $this->selectApiStack();
        $this->selectExtras();

        if (empty($this->packages) && empty($this->devPackages)) {
            note('⚠️ No packages selected for installation.');
            note('✅ Setup complete.');

            return static::SUCCESS;
        }

        note('📦 Installing selected packages…');

        if (!$this->composerRequire($this->packages, dev: false)) {
            return static::FAILURE;
        }

        if (!$this->composerRequire($this->devPackages, dev: true)) {
            return static::FAILURE;
        }

        foreach (array_merge($this->packages, $this->devPackages) as $packageArg) {
            $this->runPostInstallCommands($this->normalizePackageName($packageArg));
        }

        $this->maybeCreateFilamentUser();

        note('✅ Setup complete. Don’t forget to configure services and commit your changes.');

        return static::SUCCESS;
    }

    protected function selectAdminPanel(): void
    {
        $adminNeeded = confirm(
            label: 'Will this app use an admin panel?',
            default: true,
        );

        if (!$adminNeeded) {
            note('⚠️ Skipping admin panel installation.');
            return;
        }

        $adminChoice = select(
            label: 'Which admin panel would you like to install?',
            options: [
                'filament/filament:^4.0' => '✨ Filament v4 — Panel builder + components (Livewire)',
                'moonshine/moonshine' => '🌙 Moonshine — admin panel for Laravel',
            ],
            default: 'filament/filament:^4.0',
        );

        $this->packages[] = $adminChoice;
    }

    protected function selectApiStack(): void
    {
        $apiNeeded = confirm(
            label: 'Will this app be used as an API?',
            default: false,
        );

        if (!$apiNeeded) {
            note('⚠️ Skipping API support installation.');
            return;
        }

        info('API setup will install Swagger (L5-Swagger) automatically.');
        $this->packages[] = 'darkaonline/l5-swagger';

        $apiChoice = select(
            label: 'Which DTO / response toolkit would you like to install?',
            options: [
                'spatie/laravel-data' => '📦 Spatie Laravel Data — rich DTOs',
                'pepperfm/api-responder-for-laravel' => '📦 API Responder — lightweight DTOs + response helpers',
            ],
            default: 'spatie/laravel-data',
        );

        $this->packages[] = $apiChoice;
    }

    protected function selectExtras(): void
    {
        $otherChoices = multiselect(
            label: 'Select additional features to install',
            options: [
                'defstudio/telegraph' => '🤖 Telegram Bot Integration',
                'spatie/laravel-ray' => '🛠 Ray Debugger (requires license)',
                'spatie/laravel-medialibrary' => '🖼  Spatie MediaLibrary (file uploads)',
                'spatie/laravel-permission' => '🔐 Spatie Permissions (roles & permissions)',
            ],
        );

        foreach ($otherChoices as $selected) {
            if ($selected === 'spatie/laravel-ray') {
                $this->devPackages[] = $selected;
                continue;
            }

            $this->packages[] = $selected;
        }
    }

    /**
     * Run `composer require` for specified packages.
     *
     * @param array<int, string> $packages
     */
    protected function composerRequire(array $packages, bool $dev): bool
    {
        if (empty($packages)) {
            return true;
        }

        $command = [
            'composer',
            'require',
            '--no-interaction',
        ];

        if ($dev) {
            $command[] = '--dev';
        }

        $command = array_merge($command, $packages);

        info('→ Running: ' . implode(' ', $command));

        $process = Process::timeout(600)->path(base_path())->run(
            $command,
            function (string $type, string $output): void {
                $this->output->write($output);
            }
        );

        if ($process->successful()) {
            return true;
        }

        warning('⚠ Failed to install: ' . implode(', ', $packages));
        $this->output->write($process->errorOutput());

        return false;
    }

    protected function normalizePackageName(string $composerArg): string
    {
        return explode(':', $composerArg, 2)[0];
    }

    /*
     * Configure .env file for WWWUSER and WWWGROUP variables.
     */
    protected function configureEnvironment(): void
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (!file_exists($envPath) && file_exists($envExamplePath)) {
            copy($envExamplePath, $envPath);
            info('📄 .env file created from .env.example');
        }

        if (!file_exists($envPath)) {
            warning('⚠️ .env file not found. Skipping Sail environment configuration.');
            return;
        }

        $autoDetect = confirm(
            label: 'Do you want to auto-detect your UID and GID for WWWUSER/WWWGROUP?',
        );

        $defaultUid = function_exists('getmyuid') ? (string) getmyuid() : '1000';
        $defaultGid = function_exists('getmygid') ? (string) getmygid() : '1000';

        $uid = $autoDetect ? (int) $defaultUid : (int) text('Enter your user ID (UID)', default: $defaultUid);
        $gid = $autoDetect ? (int) $defaultGid : (int) text('Enter your group ID (GID)', default: $defaultGid);

        $envContent = file_get_contents($envPath) ?: '';

        $envContent = $this->replaceOrAppendEnvValue($envContent, key: 'WWWUSER', value: (string) $uid);
        $envContent = $this->replaceOrAppendEnvValue($envContent, key: 'WWWGROUP', value: (string) $gid);

        file_put_contents($envPath, $envContent);

        info("🔐 Updated .env with WWWUSER=$uid and WWWGROUP=$gid");
    }

    protected function replaceOrAppendEnvValue(string $content, string $key, string $value): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

        if (preg_match($pattern, $content) === 1) {
            return (string) preg_replace($pattern, $key . '=' . $value, $content);
        }

        return rtrim($content) . PHP_EOL . $key . '=' . $value . PHP_EOL;
    }

    /*
     * Start Sail containers (best-effort).
     */
    protected function runSailUp(): void
    {
        $sail = $this->getSailCommand();
        if ($sail === null) {
            warning('⚠️ Sail command not found (expected ./sail or ./vendor/bin/sail).');

            return;
        }

        if (!file_exists(base_path('docker-compose.yml'))) {
            warning('⚠️ docker-compose.yml not found. Did you run `php artisan sail:install`? Skipping Sail boot.');

            return;
        }

        if ($sail === './sail' && file_exists(base_path('sail')) && !is_executable(base_path('sail'))) {
            Process::path(base_path())->run(['chmod', '755', 'sail']);
        }

        $commands = [
            [$sail, 'up', '-d', '--build'],
            [$sail, 'artisan', 'storage:link'],
        ];

        foreach ($commands as $command) {
            info('→ Running: ' . implode(' ', $command));

            $process = Process::timeout(600)->path(base_path())->run(
                $command,
                function (string $type, string $output): void {
                    $this->output->write($output);
                }
            );

            if (!$process->successful()) {
                warning('⚠ Command failed: ' . implode(' ', $command));
                $this->output->write($process->errorOutput());

                return;
            }
        }
    }

    /*
     * Get the correct Sail executable command.
     */
    protected function getSailCommand(): ?string
    {
        if (is_executable(base_path('sail'))) {
            return './sail';
        }

        if (is_executable(base_path('vendor/bin/sail'))) {
            return './vendor/bin/sail';
        }

        return null;
    }

    /*
     * Run package-specific post-install Artisan commands.
     */
    protected function runPostInstallCommands(string $package): void
    {
        match ($package) {
            'moonshine/moonshine' => $this->callChecked('moonshine:install'),
            'filament/filament' => $this->callChecked('filament:install', ['--panels' => true]),
            'darkaonline/l5-swagger' => $this->installSwagger(),
            'defstudio/telegraph' => $this->installTelegraph(),
            'spatie/laravel-ray' => $this->installRay(),
            'spatie/laravel-medialibrary' => $this->installMediaLibrary(),
            'spatie/laravel-permission' => $this->installPermission(),
            default => null,
        };
    }

    protected function installSwagger(): void
    {
        $this->callChecked('install:api');

        // Publish config so `config/l5-swagger.php` exists.
        $this->callChecked('vendor:publish', [
            '--provider' => 'L5Swagger\\L5SwaggerServiceProvider',
        ]);
    }

    protected function installTelegraph(): void
    {
        $this->callChecked('vendor:publish', ['--tag' => 'telegraph-migrations']);
        $this->callChecked('migrate');
    }

    protected function installRay(): void
    {
        $options = $this->wantsSail ? ['--docker' => true] : [];
        $this->callChecked('ray:publish-config', $options);
    }

    protected function installMediaLibrary(): void
    {
        $this->callChecked('vendor:publish', [
            '--provider' => 'Spatie\\MediaLibrary\\MediaLibraryServiceProvider',
            '--tag' => 'medialibrary-migrations',
        ]);
        $this->callChecked('migrate');
    }

    protected function installPermission(): void
    {
        $this->callChecked('vendor:publish', [
            '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
        ]);
        $this->callChecked('optimize:clear');
        $this->callChecked('migrate');
    }

    /**
     * Run an Artisan command and report failures.
     *
     * @param array<string, mixed> $options
     */
    protected function callChecked(string $command, array $options = []): void
    {
        info('→ Running: php artisan ' . $command);

        $exitCode = $this->call($command, $options);

        if ($exitCode !== static::SUCCESS) {
            error('❌ Command failed: ' . $command);
        }
    }

    protected function maybeCreateFilamentUser(): void
    {
        if (!$this->wasPackageSelected('filament/filament')) {
            return;
        }

        $createUser = confirm(
            label: 'Would you like to run migrations and create a Filament user now?',
        );

        if (!$createUser) {
            note('ℹ️ Skipping Filament user creation. You can run `php artisan make:filament-user` later.');

            return;
        }

        $this->callChecked('migrate');
        $this->callChecked('make:filament-user');

        $this->ensureFilamentPanelProviderRegistered();
    }

    protected function ensureFilamentPanelProviderRegistered(): void
    {
        $providersPath = base_path('bootstrap/providers.php');
        if (!file_exists($providersPath)) {
            return;
        }

        $providerClass = 'App\\Providers\\Filament\\AdminPanelProvider::class';

        $contents = file_get_contents($providersPath) ?: '';

        if (str_contains($contents, $providerClass)) {
            return;
        }

        // Best-effort: add before the closing array bracket.
        $updated = preg_replace(
            '/\n];\s*$/',
            "\n    {$providerClass},\n];\n",
            $contents,
        );

        if (!is_string($updated) || $updated === $contents) {
            return;
        }

        file_put_contents($providersPath, $updated);
        info('🧩 Registered Filament AdminPanelProvider in bootstrap/providers.php');
    }

    protected function wasPackageSelected(string $package): bool
    {
        $all = array_merge($this->packages, $this->devPackages);

        foreach ($all as $arg) {
            if ($this->normalizePackageName($arg) === $package) {
                return true;
            }
        }

        return false;
    }
}
