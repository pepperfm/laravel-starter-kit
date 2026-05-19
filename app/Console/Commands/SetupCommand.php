<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Setup\AdminPanelFrontendInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\{confirm, info, multiselect, note, select, text, warning};

final class SetupCommand extends Command
{
    protected $signature = 'starter:setup {--no-post : Skip running post-install artisan commands}';

    protected $description = 'Interactive setup for the PepperFM Laravel starter kit.';

    /**
     * @var array<int, string>
     */
    protected array $installedPackages = [];

    /**
     * @var array<int, string>
     */
    protected array $installedDevPackages = [];

    protected bool $adminFrontendRequested = false;

    protected ?bool $sailProxyAvailable = null;

    public function handle(AdminPanelFrontendInstaller $adminPanelFrontendInstaller): int
    {
        info('🔧 Laravel Starter Kit: Optional Setup');

        $this->configureEnvironment();

        $this->askAdminFrontend();
        $this->askApiSupport();
        $this->askExtras();

        if ($this->installedPackages === [] && $this->installedDevPackages === []) {
            note('⚠️ No packages selected for installation.');

            return self::SUCCESS;
        }

        $packagesInstalled = $this->requirePackages($this->installedPackages, dev: false);
        $devPackagesInstalled = $this->requirePackages($this->installedDevPackages, dev: true);

        if (!$packagesInstalled || !$devPackagesInstalled) {
            warning('⚠ Setup stopped because package installation failed.');

            return self::FAILURE;
        }

        if ($this->adminFrontendRequested) {
            $adminFrontendInstalled = $adminPanelFrontendInstaller->install(
                usingSail: $this->usingSail(),
                sailCommand: $this->getSailCommand(),
            );

            if (!$adminFrontendInstalled) {
                warning('⚠ Setup stopped because admin frontend installation failed.');

                return self::FAILURE;
            }
        }

        if (!$this->option('no-post') && !$this->runSelectedPostInstallCommands()) {
            warning('⚠ Setup stopped because a post-install command failed.');

            return self::FAILURE;
        }

        note('✅ Setup complete.');

        return self::SUCCESS;
    }

    protected function askAdminFrontend(): void
    {
        $this->adminFrontendRequested = confirm(
            label: 'Install custom admin frontend foundation?',
            default: false,
            hint: 'Publishes the Inertia + Vue + Nuxt UI panel preset.',
        );

        if (!$this->adminFrontendRequested) {
            note('⚠️ Skipping admin frontend foundation.');

            return;
        }

        $this->installedPackages[] = 'inertiajs/inertia-laravel:^3.0';
        $this->installedPackages[] = 'tightenco/ziggy:^2.5';
    }

    protected function askApiSupport(): void
    {
        $apiNeeded = confirm('Will this app be used as an API?', default: false);

        if (!$apiNeeded) {
            note('⚠️ Skipping API support installation.');

            return;
        }

        info('API support: installing Swagger UI (L5 Swagger) for documentation.');

        $this->installedPackages[] = 'darkaonline/l5-swagger';

        $apiChoice = select(
            label: 'Which additional API helper package would you like to install?',
            options: [
                'spatie/laravel-data' => '📦 Laravel Data — data objects / DTOs',
                'pepperfm/api-responder-for-laravel' => '📦 API Responder — lightweight response helpers',
            ],
            default: 'spatie/laravel-data',
        );

        $this->installedPackages[] = (string) $apiChoice;
    }

    protected function askExtras(): void
    {
        /** @var array<int, string> $otherChoices */
        $otherChoices = multiselect(
            label: 'Select additional features to install',
            options: [
                'defstudio/telegraph' => '🤖 Telegram Bot Integration (Telegraph)',
                'spatie/laravel-ray' => '🛠 Ray Debugger (requires license) [dev]',
                'spatie/laravel-medialibrary' => '🖼  Spatie MediaLibrary (file uploads)',
                'spatie/laravel-permission' => '🔐 Spatie Permissions (roles & permissions)',
            ],
        );

        foreach ($otherChoices as $selected) {
            if ($selected === 'spatie/laravel-ray') {
                $this->installedDevPackages[] = $selected;

                continue;
            }

            $this->installedPackages[] = $selected;
        }
    }

    /**
     * Run composer require for specified packages.
     *
     * @param array<int, string> $packages
     * @param bool $dev
     */
    protected function requirePackages(array $packages, bool $dev): bool
    {
        $packages = array_values(array_unique(array_filter(array_map('trim', $packages))));
        if ($packages === []) {
            return true;
        }

        $isSail = $this->usingSail();

        $command = $isSail
            ? [$this->getSailCommand(), 'composer', 'require']
            : ['composer', 'require'];

        // Important: allow updating locked transitive dependencies (helps when upgrading major versions).
        $command[] = '--with-all-dependencies';

        if ($dev) {
            $command[] = '--dev';
        }

        $command = array_merge($command, $packages);

        info('→ Running: ' . implode(' ', $command));

        $process = Process::path(base_path())
            ->timeout(600)
            ->run($command);

        if ($process->successful()) {
            $this->output->write($process->output());

            return true;
        }

        warning('⚠ Failed to install: ' . implode(', ', $packages));
        $this->output->write($process->errorOutput());

        return false;
    }

    protected function runSelectedPostInstallCommands(): bool
    {
        $all = array_merge($this->installedPackages, $this->installedDevPackages);
        $successful = true;

        foreach ($all as $packageSpec) {
            $package = $this->packageName($packageSpec);
            $successful = $this->runPostInstallCommands($package) && $successful;
        }

        return $successful;
    }

    /*
     * Run package-specific post-install artisan commands.
     */
    protected function runPostInstallCommands(string $package): bool
    {
        $commands = [
            'darkaonline/l5-swagger' => [
                ['vendor:publish', '--provider=L5Swagger\\L5SwaggerServiceProvider'],
                ['l5-swagger:generate'],
            ],
            'defstudio/telegraph' => [
                ['vendor:publish', '--tag=telegraph-migrations'],
                ['migrate'],
            ],
            'spatie/laravel-ray' => [
                $this->hasSailCommand() ? ['ray:publish-config', '--docker'] : ['ray:publish-config'],
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

        if (!isset($commands[$package])) {
            return true;
        }

        $successful = true;

        foreach ($commands[$package] as $cmd) {
            $isSail = $this->usingSail();

            $command = $isSail
                ? array_merge([$this->getSailCommand(), 'php', 'artisan'], $cmd)
                : array_merge(['php', 'artisan'], $cmd);

            info("→ Running post-install command for $package: " . implode(' ', $command));

            $process = Process::path(base_path())
                ->timeout(600)
                ->run($command);

            if ($process->successful()) {
                $this->output->write($process->output());

                continue;
            }

            warning("⚠ Post-install command failed for $package: " . implode(' ', $command));
            $this->output->write($process->errorOutput());
            $successful = false;
        }

        return $successful;
    }

    protected function configureEnvironment(): void
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (!file_exists($envPath) && file_exists($envExamplePath)) {
            copy($envExamplePath, $envPath);
        }

        if (!file_exists($envPath)) {
            warning('⚠️ .env file not found, skipping environment configuration.');

            return;
        }

        $autoDetect = confirm(
            label: 'Auto-detect your UID and GID for WWWUSER/WWWGROUP?',
        );

        $uidDefault = function_exists('getmyuid') ? (string) getmyuid() : '1000';
        $gidDefault = function_exists('getmygid') ? (string) getmygid() : '1000';

        $uid = $autoDetect ? $uidDefault : text('Enter your user ID (UID)', default: $uidDefault);
        $gid = $autoDetect ? $gidDefault : text('Enter your group ID (GID)', default: $gidDefault);

        $envContent = (string) file_get_contents($envPath);

        $envContent = $this->replaceOrAppendEnv($envContent, 'WWWUSER', $uid);
        $envContent = $this->replaceOrAppendEnv($envContent, 'WWWGROUP', $gid);

        file_put_contents($envPath, $envContent);

        info("🔐 Updated .env with WWWUSER=$uid and WWWGROUP=$gid");
    }

    protected function replaceOrAppendEnv(string $content, string $key, string $value): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        if (preg_match($pattern, $content) === 1) {
            return preg_replace($pattern, "$key=$value", $content);
        }

        $content = rtrim($content) . PHP_EOL;

        return $content . "$key=$value" . PHP_EOL;
    }

    protected function packageName(string $packageSpec): string
    {
        $parts = explode(':', $packageSpec, 2);

        return trim($parts[0]);
    }

    protected function usingSail(): bool
    {
        if ($this->sailProxyAvailable !== null) {
            return $this->sailProxyAvailable;
        }

        if ($this->runningInsideSail() || !$this->hasSailCommand()) {
            return $this->sailProxyAvailable = false;
        }

        $process = Process::path(base_path())
            ->timeout(10)
            ->run(['docker', 'compose', 'ps', '--services', '--filter', 'status=running']);

        if (!$process->successful()) {
            return $this->sailProxyAvailable = false;
        }

        $runningServices = array_filter(array_map('trim', explode(PHP_EOL, $process->output())));

        return $this->sailProxyAvailable = in_array('app', $runningServices, true);
    }

    protected function getSailCommand(): string
    {
        if (is_executable(base_path('sail'))) {
            return './sail';
        }

        return './vendor/bin/sail';
    }

    protected function hasSailCommand(): bool
    {
        return is_executable(base_path('sail')) || is_executable(base_path('vendor/bin/sail'));
    }

    protected function runningInsideSail(): bool
    {
        $value = $_ENV['LARAVEL_SAIL'] ?? getenv('LARAVEL_SAIL');

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
