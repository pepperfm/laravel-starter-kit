<?php

declare(strict_types=1);

namespace App\Setup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\{info, note, warning};

final class AdminPanelFrontendInstaller
{
    private const STUB_PATH = 'stubs/admin-panel';

    public function install(bool $usingSail, string $sailCommand): bool
    {
        if (!$this->publishPresetFiles()) {
            return false;
        }

        return $this->installFrontendDependencies($usingSail, $sailCommand);
    }

    private function publishPresetFiles(): bool
    {
        $source = base_path(self::STUB_PATH);

        if (!File::isDirectory($source)) {
            warning('⚠ Admin panel frontend preset stubs were not found.');

            return false;
        }

        foreach (File::allFiles($source, true) as $file) {
            $target = base_path($file->getRelativePathname());

            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getPathname(), $target);
        }

        note('✅ Admin panel frontend preset files published.');

        return true;
    }

    private function installFrontendDependencies(bool $usingSail, string $sailCommand): bool
    {
        $command = $this->nodeInstallCommand($usingSail, $sailCommand);

        info('→ Running: ' . implode(' ', $command));

        $process = Process::path(base_path())
            ->timeout(600)
            ->run($command);

        if ($process->successful()) {
            echo $process->output();

            return true;
        }

        warning('⚠ Failed to install admin frontend dependencies.');
        echo $process->errorOutput();

        return false;
    }

    /**
     * @param bool $usingSail
     * @param string $sailCommand
     *
     * @return array<int, string>
     */
    private function nodeInstallCommand(bool $usingSail, string $sailCommand): array
    {
        if ($usingSail) {
            return [$sailCommand, 'exec', 'app', 'bun', 'install'];
        }

        if ($this->hostCommandExists('bun')) {
            return ['bun', 'install'];
        }

        return ['npm', 'install'];
    }

    private function hostCommandExists(string $command): bool
    {
        return Process::path(base_path())
            ->timeout(10)
            ->run(['which', $command])
            ->successful();
    }
}
