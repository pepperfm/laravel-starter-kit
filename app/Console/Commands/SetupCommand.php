<?php

declare(strict_types=1);

namespace App\Console\Commands;

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

    public function handle(): int
    {
        info('🔧 Laravel Starter Kit: Optional Setup');

        $this->configureEnvironment();

        $this->askApiSupport();
        $this->askExtras();

        if ($this->installedPackages === [] && $this->installedDevPackages === []) {
            note('⚠️ No packages selected for installation.');

            return self::SUCCESS;
        }

        $this->requirePackages($this->installedPackages, dev: false);
        $this->requirePackages($this->installedDevPackages, dev: true);

        if (!$this->option('no-post')) {
            $this->runSelectedPostInstallCommands();
        }

        note('✅ Setup complete.');

        return self::SUCCESS;
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
    protected function requirePackages(array $packages, bool $dev): void
    {
        $packages = array_values(array_unique(array_filter(array_map('trim', $packages))));
        if ($packages === []) {
            return;
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

            return;
        }

        warning('⚠ Failed to install: ' . implode(', ', $packages));
        $this->output->write($process->errorOutput());
    }

    protected function runSelectedPostInstallCommands(): void
    {
        $all = array_merge($this->installedPackages, $this->installedDevPackages);

        foreach ($all as $packageSpec) {
            $package = $this->packageName($packageSpec);
            $this->runPostInstallCommands($package);
        }
    }

    /*
     * Run package-specific post-install artisan commands.
     */
    protected function runPostInstallCommands(string $package): void
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
                $this->usingSail() ? ['ray:publish-config', '--docker'] : ['ray:publish-config'],
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
            return;
        }

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
        }
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
        return is_executable(base_path('sail')) || is_executable(base_path('vendor/bin/sail'));
    }

    protected function getSailCommand(): string
    {
        if (is_executable(base_path('sail'))) {
            return './sail';
        }

        return './vendor/bin/sail';
    }
}
