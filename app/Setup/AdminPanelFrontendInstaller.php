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

        // The frontend preset replaces a few default Laravel skeleton files on
        // first install. Everything else must be new: never overwrite user code
        // on a second setup run or in a partially configured application.
        $replaceableSkeletonFiles = [
            'package.json',
            'vite.config.js',
            'resources/css/app.css',
        ];
        $files = File::allFiles($source, true);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $target = base_path($relativePath);

            if (File::exists($target) && !in_array($relativePath, $replaceableSkeletonFiles, true)) {
                warning("⚠ Admin frontend installation would overwrite: {$relativePath}. Existing files were left unchanged.");

                return false;
            }
        }

        foreach ($files as $file) {
            $target = base_path($file->getRelativePathname());

            File::ensureDirectoryExists(dirname($target));

            if (!File::copy($file->getPathname(), $target)) {
                warning("⚠ Could not copy admin frontend preset file: {$file->getRelativePathname()}.");

                return false;
            }
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
