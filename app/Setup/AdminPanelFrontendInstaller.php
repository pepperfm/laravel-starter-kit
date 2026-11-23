<?php

declare(strict_types=1);

namespace App\Setup;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;

use function Laravel\Prompts\{info, note, select, warning};

final class AdminPanelFrontendInstaller
{
    private const STUB_PATH = 'stubs/admin-panel';

    public function publish(): bool
    {
        return $this->publishPresetFiles();
    }

    public function installDependencies(bool $usingSail, string $sailCommand, StreamingCommandRunner $runner): bool
    {
        $manager = select(
            label: 'Which frontend package manager should be used?',
            options: [
                'bun' => 'Bun',
                'npm' => 'npm',
                'pnpm' => 'pnpm',
                'yarn' => 'Yarn',
            ],
            default: $this->determinePackageManager(),
        );
        $command = $this->nodeInstallCommand($usingSail, $sailCommand, $manager);

        info('→ Running: ' . implode(' ', $command));

        if (!$usingSail && !$this->hostCommandExists($command[0])) {
            warning("⚠ Package manager {$command[0]} is not available on the host. Use Sail or install it first.");

            return false;
        }

        if (!$runner->run($command)) {
            warning('⚠ Failed to install admin frontend dependencies.');

            return false;
        }

        $build = $usingSail
            ? [$sailCommand, 'exec', '-T', '-u', 'sail', (string) env('APP_SERVICE', 'app'), $manager, 'run', 'build']
            : [$manager, 'run', 'build'];

        info('→ Running: ' . implode(' ', $build));

        if (!$runner->run($build)) {
            warning('⚠ Failed to build admin frontend assets.');

            return false;
        }

        $this->removeOtherLockfiles($manager);

        return true;
    }

    /**
     * SHA-256 of the three pristine Laravel skeleton files this preset can replace.
     * Customized files are never overwritten. Already published files are safe to retry.
     *
     * @var array<string, string>
     */
    private const array REPLACEABLE_SKELETON_HASHES = [
        'package.json' => 'faa8b9989e837f0c2243b029ba361c08965393c860bd4211d4c2a7c7592dab5e',
        'bun.lock' => '7f53325a981f3be9bbade839873ebffbf189492a7fd7fb59439af866fc0d6769',
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

        if ($this->previouslyPublished()) {
            return true;
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

        if ($this->previouslyPublished()) {
            note('Admin frontend already installed; preserving existing customizations.');

            return true;
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

    /**
     * A complete existing admin installation is not a file-conflict error, even
     * when its files were subsequently customized by the application owner.
     */
    private function previouslyPublished(): bool
    {
        if (!is_file(config_path('panel.php')) || !is_file(base_path('routes/panel.php'))
            || !is_file(base_path('resources/js/app.ts')) || !is_file(base_path('package.json'))) {
            return false;
        }

        $package = json_decode((string) file_get_contents(base_path('package.json')), true);

        if (!isset($package['dependencies']['@nuxt/ui'], $package['dependencies']['@inertiajs/vue3'])) {
            return false;
        }

        foreach (File::allFiles(base_path(self::STUB_PATH), true) as $file) {
            if (!File::exists(base_path($file->getRelativePathname()))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function nodeInstallCommand(bool $usingSail, string $sailCommand, ?string $manager = null): array
    {
        $manager ??= $this->determinePackageManager();

        if ($usingSail) {
            return [$sailCommand, 'exec', '-T', '-u', 'sail', (string) env('APP_SERVICE', 'app'), $manager, 'install'];
        }

        return [$manager, 'install'];
    }

    private function determinePackageManager(): string
    {
        $manager = match (true) {
            file_exists(base_path('pnpm-lock.yaml')) => 'pnpm',
            file_exists(base_path('bun.lock')), file_exists(base_path('bun.lockb')) => 'bun',
            file_exists(base_path('yarn.lock')) => 'yarn',
            default => 'bun',
        };

        return $manager;
    }

    private function removeOtherLockfiles(string $manager): void
    {
        $lockfiles = [
            'bun' => ['bun.lock', 'bun.lockb'],
            'npm' => ['package-lock.json'],
            'pnpm' => ['pnpm-lock.yaml'],
            'yarn' => ['yarn.lock'],
        ];

        foreach ($lockfiles as $owner => $names) {
            if ($owner === $manager) {
                continue;
            }

            foreach ($names as $name) {
                File::delete(base_path($name));
            }
        }
    }

    private function hostCommandExists(string $command): bool
    {
        return (new ExecutableFinder())->find($command) !== null;
    }
}
