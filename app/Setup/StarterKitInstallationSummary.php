<?php

declare(strict_types=1);

namespace App\Setup;

use Illuminate\Console\Command;

use function Laravel\Prompts\{confirm, note};

final readonly class StarterKitInstallationSummary
{
    /**
     * @param array<int, string> $packages
     * @param array<int, string> $devPackages
     * @param array<string, array<int, array<int, string>>> $postInstallCommands
     * @param ?StarterKitPreset $preset
     * @param string $commandRuntime
     * @param bool $adminFrontendRequested
     * @param bool $skipPostInstall
     */
    public function __construct(
        private ?StarterKitPreset $preset,
        private string $commandRuntime,
        private bool $adminFrontendRequested,
        private array $packages,
        private array $devPackages,
        private bool $skipPostInstall,
        private array $postInstallCommands,
    ) {
    }

    public function confirm(Command $command): bool
    {
        $this->writeTo($command);

        return confirm(label: 'Continue with this installation?');
    }

    public function writeTo(Command $command): void
    {
        note('Installation summary');

        if ($this->preset !== null) {
            $command->line("Preset: {$this->preset->value}");
        }

        $command->line("Command runtime: $this->commandRuntime");

        if ($this->adminFrontendRequested) {
            $command->line('Admin frontend preset: yes');
        }

        $this->writePackageSummary($command, 'Composer packages', $this->packages);
        $this->writePackageSummary($command, 'Composer dev packages', $this->devPackages);

        if ($this->skipPostInstall) {
            $command->line('Post-install artisan commands: skipped because --no-post is enabled');

            return;
        }

        $this->writePostInstallSummary($command);
    }

    /**
     * @param array<int, string> $packages
     * @param Command $command
     * @param string $label
     */
    private function writePackageSummary(Command $command, string $label, array $packages): void
    {
        $command->line($label . ':');

        if ($packages === []) {
            $command->line('  - none');

            return;
        }

        foreach ($packages as $package) {
            $command->line("  - $package");
        }
    }

    private function writePostInstallSummary(Command $command): void
    {
        $command->line('Post-install artisan commands:');

        if ($this->postInstallCommands === []) {
            $command->line('  - none');

            return;
        }

        foreach ($this->postInstallCommands as $package => $commands) {
            $command->line("  - $package:");

            foreach ($commands as $commandSpec) {
                $command->line('    - php artisan ' . implode(' ', $commandSpec));
            }
        }
    }
}
