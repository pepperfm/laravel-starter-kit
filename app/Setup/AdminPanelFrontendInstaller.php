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

    /**
     * SHA-256 of the three pristine Laravel skeleton files this preset can replace.
     * Customized files are never overwritten. Already published files are safe to retry.
     *
     * @var array<string, string>
     */
    private const array REPLACEABLE_SKELETON_HASHES = [
        'package.json' => 'f528ccaa1fcffb50a4a16579408935f12ab3c5ca9167a3218e574d007fac185e',
        'vite.config.js' => 'b79dffc57362f908a5f6a451c4c4bc1543a7f43b596300691f754f5c8b320312',
        'resources/css/app.css' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    ];

    public function canInstall(): bool
    {
        $source = base_path(self::STUB_PATH);
        if (!File::isDirectory($source)) {
            warning('⚠ Admin panel frontend preset stubs were not found.');

            return false;
        }

        foreach (File::allFiles($source, true) as $file) {
            $relative = $file->getRelativePathname();
            $target = base_path($relative);

            if (!File::exists($target)) {
                continue;
            }

            $targetHash = File::hash($target, 'sha256');
            if ($targetHash === File::hash($file->getPathname(), 'sha256')) {
                continue; // Previously published file: idempotent setup.
            }

            if ($targetHash === (self::REPLACEABLE_SKELETON_HASHES[$relative] ?? null)) {
                continue; // Original, unmodified Laravel skeleton file.
            }

            warning("⚠ Admin frontend installation would overwrite: {$relative}. Existing files were left unchanged.");

            return false;
        }

        return true;
    }

    private function publishPresetFiles(): bool
    {
        if (!$this->canInstall()) {
            return false;
        }

        $created = [];
        $replaced = [];

        foreach (File::allFiles(base_path(self::STUB_PATH), true) as $file) {
            $relative = $file->getRelativePathname();
            $target = base_path($relative);

            if (File::exists($target)) {
                if (File::hash($target, 'sha256') === File::hash($file->getPathname(), 'sha256')) {
                    continue;
                }

                $replaced[$target] = File::get($target);
            } else {
                $created[] = $target;
            }

            File::ensureDirectoryExists(dirname($target));
            if (!File::copy($file->getPathname(), $target)) {
                foreach ($created as $createdPath) {
                    File::delete($createdPath);
                }
                foreach ($replaced as $replacedPath => $previous) {
                    File::put($replacedPath, $previous);
                }

                warning("⚠ Could not copy admin frontend preset file: {$relative}.");

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
            return [$sailCommand, 'exec', '-T', '-u', 'sail', (string) env('APP_SERVICE', 'app'), 'bun', 'install'];
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
