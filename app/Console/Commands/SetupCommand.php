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

    protected $description = 'Optional project setup for API, Telegram bot, and debugging tools, etc.';

    /**
     * List of packages for installation and post-install scripts
     *
     * @var string[]
     */
    protected array $installedPackages = [];

    public function handle(): int
    {
        info('🔧 Laravel Starter Kit: Optional Setup');

        $adminNeeded = confirm('Will this app use an Admin Panel?');
        if ($adminNeeded) {
            $adminChoice = select(
                label: 'Which API packages would you like to install?',
                options: [
                    'filament/filament' => '✨ Filament — A collection of beautiful full-stack component',
                    'moonshine/moonshine' => '🌙 Moonshine — Comfortable admin panel for your Laravel project',
                ],
            );
            $this->installedPackages[] = $adminChoice;
        } else {
            note('⚠️ Skipping Admin Panel installation.');
        }

        $apiNeeded = confirm('Will this app be used as an API?');
        if ($apiNeeded) {
            info('API support requires Swagger, which will be installed automatically.');
            info('API support requires Swagger, which will be installed automatically.');

            $this->installedPackages[] = 'darkaonline/l5-swagger';

            $apiChoice = select(
                label: 'Which API packages would you like to install?',
                options: [
                    'spatie/laravel-data' => '📦 Laravel Data — powerful toolkit for complex data',
                    'pepperfm/api-responder-for-laravel' => '📦 API Responder — simple and lightweight for basic DTOs',
                ],
            );
            $this->installedPackages[] = $apiChoice;
        } else {
            note('⚠️ Skipping API support installation.');
        }

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
            $this->installedPackages[] = $selected;
        }

        note('✅ Setup complete. Don’t forget to publish providers or configure your services.');

        $this->configureEnvironment();

        $autoBuild = confirm(
            label: 'Would you like to automatically build and launch your app using Sail?'
        );
        if ($autoBuild) {
            $this->runPostSetupCommands();
        } else {
            warning('⚠️ Auto build skipped. Post-install commands were not executed.');
        }
        if (empty($this->installedPackages)) {
            note('⚠️ No packages selected for installation.');
        } else {
            $this->requirePackages($this->installedPackages);
            foreach ($this->installedPackages as $package) {
                $this->runPostInstallCommands($package);
            }
        }

        return static::SUCCESS;
    }

    /**
     * Run composer require for specified packages.
     *
     * @param array<string> $packages
     *
     * @return void
     */
    protected function requirePackages(array $packages): void
    {
        $isSail = $this->usingSail();

        $command = $isSail
            ? array_merge([$this->getSailCommand(), 'composer', 'require'], $packages)
            : array_merge(['composer', 'require'], $packages);

        info('→ Running: ' . implode(' ', $command));

        $process = Process::run($command);
        if ($process->successful()) {
            $this->output->write($process->output());
        } else {
            warning('⚠ Failed to install: ' . implode(', ', $packages));
            $this->output->write($process->errorOutput());
        }
    }

    /**
     * Configure a.env file for WWWUSER and WWWGROUP variables.
     *
     * @return void
     */
    protected function configureEnvironment(): void
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (!file_exists($envPath)) {
            copy($envExamplePath, $envPath);
            info('📄 .env file created from .env.example');
        }

        $autoDetect = confirm(
            label: 'Do you want to auto-detect your UID and GID for WWWUSER/WWWGROUP?',
        );
        $uid = $autoDetect ? getmyuid() : (int) text('Enter your user ID (UID)', default: (string) getmyuid());
        $gid = $autoDetect ? getmygid() : (int) text('Enter your group ID (GID)', default: (string) getmygid());

        $envContent = file_get_contents($envPath);
        $envContent = preg_replace('/^WWWUSER=.*$/m', "WWWUSER=$uid", $envContent);
        $envContent = preg_replace('/^WWWGROUP=.*$/m', "WWWGROUP=$gid", $envContent);

        file_put_contents($envPath, $envContent);

        info("🔐 Updated .env with WWWUSER=$uid and WWWGROUP=$gid");
    }

    /**
     * Run additional shell commands needed for post-setup.
     *
     * @return void
     */
    protected function runPostSetupCommands(): void
    {
        $commands = [
            'chmod 755 ./sail',
            './sail up -d --build',
            './sail composer install',
            './sail artisan key:gen',
            './sail artisan sto:li',
        ];

        foreach ($commands as $cmd) {
            info("→ Running: $cmd");

            $process = Process::timeout(300)->run(
                $cmd,
                function (string $type, string $output) {
                    $this->output->write($output);
                }
            );
            if (!$process->successful()) {
                error("❌ Command failed: $cmd");
            }
        }
    }

    /**
     * Run package-specific post-install artisan commands.
     *
     * @param string $package
     *
     * @return void
     */
    protected function runPostInstallCommands(string $package): void
    {
        $commands = [
            'moonshine/moonshine' => [
                ['moonshine:install'],
            ],
            'filament/filament' => [
                ['filament:install', '--panels'],
            ],
            'darkaonline/l5-swagger' => [
                ['install:api'],
            ],
            'defstudio/telegraph' => [
                ['vendor:publish', '--tag=telegraph-migrations'],
                ['migrate'],
            ],
            'spatie/laravel-medialibrary' => [
                ['vendor:publish', '--provider=Spatie\\MediaLibrary\\MediaLibraryServiceProvider', '--tag=medialibrary-migrations'],
                ['migrate'],
            ],
            'spatie/laravel-permission' => [
                ['vendor:publish', '--provider=Spatie\\Permission\\PermissionServiceProvider'],
                ['opt:cle'],
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
            $process = Process::path(base_path())->run($command);
            if ($process->successful()) {
                $this->output->write($process->output());
            } else {
                warning("⚠ Post-install command failed for $package: " . implode(' ', $command));
                $this->output->write($process->errorOutput());
            }
        }
    }

    /**
     * Check if a Sail script is available and executable.
     *
     * @return bool
     */
    protected function usingSail(): bool
    {
        return is_executable(base_path('sail')) || is_executable(base_path('vendor/bin/sail'));
    }

    /**
     * Get the correct Sail executable command.
     *
     * @return string
     */
    protected function getSailCommand(): string
    {
        if (is_executable(base_path('sail'))) {
            return './sail';
        }

        return './vendor/bin/sail';
    }
}
