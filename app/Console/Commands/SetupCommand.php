<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;
use App\Setup\StreamingCommandRunner;
use App\Setup\AdminPanelFrontendInstaller;
use App\Setup\StarterKitInstallationSummary;
use App\Setup\StarterKitPackageRegistry;
use App\Setup\StarterKitPreset;

use function Laravel\Prompts\{confirm, info, multiselect, note, select, text, warning};

#[Signature('starter:setup
    {--no-post : Skip running post-install artisan commands}
    {--no-migrate : Do not run migrations when setup is invoked manually}
    {--skip-node : Skip frontend dependency installation in manual setup}
    {--installer-hook : Called by Laravel Installer before its migration and Node stages}
    {--preset= : Install a predefined package set: api, admin, observability, full}')]
#[Description('Interactive setup for the PepperFM Laravel starter kit.')]
final class SetupCommand extends Command
{
    private const string COMMAND_RUNTIME_AUTO = 'auto';

    private const string COMMAND_RUNTIME_HOST = 'host';

    private const string COMMAND_RUNTIME_SAIL = 'sail';

    /**
     * @var array<int, string>
     */
    protected array $installedPackages = [];

    /**
     * @var array<int, string>
     */
    protected array $installedDevPackages = [];

    protected bool $adminFrontendRequested = false;

    protected ?StarterKitPreset $selectedPreset = null;

    protected string $commandRuntime = self::COMMAND_RUNTIME_AUTO;

    protected ?bool $sailProxyAvailable = null;

    public function handle(AdminPanelFrontendInstaller $adminPanelFrontendInstaller): int
    {
        info('🔧 Laravel Starter Kit: Optional Setup');

        $this->askCommandRuntime();

        if (!$this->applyPresetFromOption()) {
            return self::FAILURE;
        }

        if ($this->selectedPreset === null) {
            $this->askAdminFrontend();
            $this->askApiSupport();
            $this->askExtras();
        }

        if (!$this->confirmInstallationSummary()) {
            note('Setup cancelled before changing project files.');

            // A successful hook return would let the parent Installer continue
            // with migrations and Node even though the user cancelled.
            return $this->option('installer-hook') ? self::FAILURE : self::SUCCESS;
        }

        // Validate everything possible before the first Composer write.
        if ($this->adminFrontendRequested && !$adminPanelFrontendInstaller->canInstall()) {
            return self::FAILURE;
        }
        if (!$this->checkRuntimeRequirements()) {
            return self::FAILURE;
        }

        // Laravel Installer itself always has Composer on the host. Do this before
        // building a Sail image so the bind-mounted vendor directory is final.
        if (!$this->requirePackages($this->installedPackages, dev: false)
            || !$this->requirePackages($this->installedDevPackages, dev: true)) {
            warning('⚠ Setup stopped because package installation failed.');

            return self::FAILURE;
        }

        if ($this->adminFrontendRequested && !$adminPanelFrontendInstaller->publish()) {
            return self::FAILURE;
        }
        if (!$this->configureEnvironment() || !$this->prepareCommandRuntime()) {
            return self::FAILURE;
        }

        if (!$this->option('no-post') && !$this->runSelectedPostInstallCommands()) {
            warning('⚠ Setup stopped because a post-install command failed.');

            return self::FAILURE;
        }

        if ($this->option('installer-hook')) {
            // In v5.33.0 NewCommand invokes the hook BEFORE its own database and
            // package-manager stages. Never duplicate either operation here.
            note('Installer will handle database migrations and frontend dependencies next.');
        } else {
            if (!$this->option('no-post') && !$this->option('no-migrate')
                && confirm('Run pending database migrations now?', default: false)
                && !$this->runArtisanCommand(['migrate', '--force'], 'database migrations')) {
                return self::FAILURE;
            }

            if ($this->adminFrontendRequested && !$this->option('skip-node')
                && getenv('LARAVEL_INSTALLER_NO_NODE') !== '1'
                && confirm('Install admin frontend dependencies now?', default: true)
                && !$adminPanelFrontendInstaller->installDependencies(
                    usingSail: $this->usingSail(),
                    sailCommand: $this->getSailCommand(),
                    runner: $this->runner(),
                )) {
                return self::FAILURE;
            }
        }

        note('✅ Setup complete.');

        return self::SUCCESS;
    }

    protected function applyPresetFromOption(): bool
    {
        $inputPreset = $this->option('preset');
        if (!is_string($inputPreset) || trim($inputPreset) === '') {
            return true;
        }

        $preset = $inputPreset
                |> trim(...)
                |> strtolower(...)
                |> StarterKitPreset::tryFrom(...);
        if (!$preset instanceof StarterKitPreset) {
            warning('⚠ Unknown preset. Available presets: ' . implode(', ', StarterKitPreset::values()) . '.');

            return false;
        }

        info("Applying $preset->value preset.");

        $this->selectedPreset = $preset;
        $this->adminFrontendRequested = $preset->installsAdminFrontend();
        array_push($this->installedPackages, ...$preset->packages());
        array_push($this->installedDevPackages, ...$preset->devPackages());

        return true;
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
        if (!confirm('Add API components?', default: false)) {
            return;
        }

        // These are independent choices. An API may not need authentication or Swagger;
        // data objects and response helpers can also be installed together.
        $choices = multiselect(
            label: 'Choose API components',
            options: [
                'laravel/sanctum' => '🔐 Sanctum — token authentication and API routes',
                'pepperfm/swagger-nuxt-ui-for-laravel' => '📚 Swagger Nuxt UI — API documentation',
                'spatie/laravel-data' => '📦 Laravel Data — DTOs and validation',
                'pepperfm/api-responder-for-laravel' => '📤 API Responder — response helpers',
            ],
            default: ['laravel/sanctum'],
            required: false,
        );

        array_push($this->installedPackages, ...$choices);
    }

    protected function askExtras(): void
    {
        /** @var array<int, string> $otherChoices */
        $otherChoices = multiselect(
            label: 'Select additional features to install',
            options: [
                'opcodesio/log-viewer' => '📋 Log Viewer — browser UI for Laravel logs',
                'laravel/horizon' => '📈 Horizon — Redis queue dashboard',
                'laravel/telescope' => '🔭 Telescope — local debugging dashboard [dev]',
                'laravel/pulse' => '💓 Pulse — application performance and usage metrics',
                'defstudio/telegraph' => '🤖 Telegram Bot Integration (Telegraph)',
                'spatie/laravel-medialibrary' => '🖼  Spatie MediaLibrary (file uploads)',
                'spatie/laravel-permission' => '🔐 Spatie Permissions (roles & permissions)',
            ],
            scroll: 8,
        );

        foreach ($otherChoices as $selected) {
            if (StarterKitPackageRegistry::isDevPackage($selected)) {
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
        $packages = $this->missingPackages(StarterKitPackageRegistry::normalized($packages));

        if ($packages === []) {
            return true;
        }

        $command = ['composer', 'require', '--with-all-dependencies'];

        if ($dev) {
            $command[] = '--dev';
        }

        $command = array_merge($command, $packages);

        info('→ Running: ' . implode(' ', $command));

        if ($this->runner()->run($command)) {
            return true;
        }

        warning('⚠ Failed to install: ' . implode(', ', $packages));

        return false;
    }

    /**
     * @param array<int, string> $packages
     * @return array<int, string>
     */
    protected function missingPackages(array $packages): array
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $installed = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

        return array_values(array_filter($packages, static function (string $spec) use ($installed): bool {
            [$name, $constraint] = array_pad(explode(':', $spec, 2), 2, null);

            return !isset($installed[$name])
                || !\Composer\InstalledVersions::isInstalled($name)
                || ($constraint !== null && $installed[$name] !== $constraint);
        }));
    }

    protected function confirmInstallationSummary(): bool
    {
        return new StarterKitInstallationSummary(
            preset: $this->selectedPreset,
            commandRuntime: $this->commandRuntime,
            adminFrontendRequested: $this->adminFrontendRequested,
            packages: StarterKitPackageRegistry::normalized($this->installedPackages),
            devPackages: StarterKitPackageRegistry::normalized($this->installedDevPackages),
            skipPostInstall: (bool) $this->option('no-post'),
            postInstallCommands: StarterKitPackageRegistry::selectedPostInstallCommands(
                packages: $this->installedPackages,
                devPackages: $this->installedDevPackages,
                usingSail: $this->usingSail(),
            ),
        )->confirm($this);
    }

    protected function runSelectedPostInstallCommands(): bool
    {
        $selected = StarterKitPackageRegistry::normalized([...$this->installedPackages, ...$this->installedDevPackages]);
        if (in_array('laravel/sanctum', array_map(StarterKitPackageRegistry::packageName(...), $selected), true)
            && !$this->scaffoldSanctumApi()) {
            return false;
        }

        $commandsByPackage = StarterKitPackageRegistry::selectedPostInstallCommands(
            packages: $this->installedPackages,
            devPackages: $this->installedDevPackages,
        );

        foreach ($commandsByPackage as $package => $commands) {
            if ($package === 'database migrations') {
                continue;
            }

            foreach ($commands as $command) {
                if ($this->alreadyPublished($package, $command)) {
                    info("→ Skipping previously published files for $package.");
                    continue;
                }

                if (!$this->runArtisanCommand($command, $package)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function scaffoldSanctumApi(): bool
    {
        $path = base_path('routes/api.php');

        if (!file_exists($path)) {
            $content = <<<'PHP'
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());
PHP;
            if (file_put_contents($path, $content . PHP_EOL) === false) {
                warning('⚠ Could not publish routes/api.php.');

                return false;
            }

            info('→ Published routes/api.php without running Composer twice.');
        }

        return $this->ensureApiRoutesRegistered() && $this->ensureUserModelUsesSanctumTokens();
    }

    /**
     * Avoid rewriting files on repeat runs. Unfinished publications are retried.
     *
     * @param array<int, string> $command
     */
    protected function alreadyPublished(string $package, array $command): bool
    {
        if ($command[0] === 'optimize:clear') {
            return false;
        }

        return match ($package) {
            'laravel/horizon' => file_exists(config_path('horizon.php')),
            'laravel/telescope' => file_exists(config_path('telescope.php'))
                && (array) glob(database_path('migrations/*create_telescope_entries_table.php')) !== [],
            'laravel/pulse' => (array) glob(database_path('migrations/*create_pulse_*')) !== [],
            'opcodesio/log-viewer' => file_exists(config_path('log-viewer.php')),
            'defstudio/telegraph' => (array) glob(database_path('migrations/*telegraph*')) !== [],
            'spatie/laravel-medialibrary' => (array) glob(database_path('migrations/*create_media_table.php')) !== [],
            'spatie/laravel-permission' => file_exists(config_path('permission.php'))
                && (array) glob(database_path('migrations/*create_permission_tables.php')) !== [],
            default => false,
        };
    }

    /**
     * @param array<int, string> $cmd
     */
    protected function runArtisanCommand(array $cmd, string $package): bool
    {
        $command = $this->usingSail()
            ? [$this->getSailCommand(), 'php', 'artisan', ...$cmd]
            : ['php', 'artisan', ...$cmd];

        info("→ Running post-install command for $package: " . implode(' ', $command));

        if ($this->runner()->run($command)) {
            return true;
        }

        warning("⚠ Post-install command failed for $package.");

        return false;
    }

    protected function runner(): StreamingCommandRunner
    {
        return new StreamingCommandRunner($this->output);
    }

    protected function ensureUserModelUsesSanctumTokens(): bool
    {
        $userModelPath = app_path('Models/User.php');

        if (!file_exists($userModelPath)) {
            warning('⚠ Unable to add Sanctum HasApiTokens trait because app/Models/User.php was not found.');

            return false;
        }

        $content = (string) file_get_contents($userModelPath);
        $updated = $this->addSanctumTraitToUserModel($content);

        if (!str_contains($updated, 'use Laravel\Sanctum\HasApiTokens;')
            || !preg_match('/^    use .*\\bHasApiTokens\\b.*;$/m', $updated)) {
            warning('⚠ Unable to update the User model with Sanctum tokens.');

            return false;
        }

        if ($updated === $content) {
            return true;
        }

        file_put_contents($userModelPath, $updated);

        info('→ Added Laravel\\Sanctum\\HasApiTokens to app/Models/User.php');

        return true;
    }

    protected function ensureApiRoutesRegistered(): bool
    {
        $bootstrapPath = base_path('bootstrap/app.php');

        if (!file_exists($bootstrapPath)) {
            warning('⚠ Unable to register API routes because bootstrap/app.php was not found.');

            return false;
        }

        $content = (string) file_get_contents($bootstrapPath);
        $updated = $this->addApiRoutesToBootstrap($content);

        if (!str_contains($updated, "api: __DIR__ . '/../routes/api.php'")
            && !str_contains($updated, "api: __DIR__.'/../routes/api.php'")) {
            warning('⚠ Unable to register routes/api.php in bootstrap/app.php.');

            return false;
        }

        if ($updated === $content) {
            return true;
        }

        file_put_contents($bootstrapPath, $updated);

        info('→ Registered routes/api.php in bootstrap/app.php');

        return true;
    }

    protected function addApiRoutesToBootstrap(string $content): string
    {
        if (str_contains($content, "api: __DIR__ . '/../routes/api.php'") || str_contains($content, "api: __DIR__.'/../routes/api.php'")) {
            return $content;
        }

        return preg_replace(
            "/(web:\s*__DIR__\s*\.\s*'\/\.\.\/routes\/web\.php',\R)/",
            "$1        api: __DIR__ . '/../routes/api.php'," . PHP_EOL,
            $content,
            1
        );
    }

    protected function addSanctumTraitToUserModel(string $content): string
    {
        if (!str_contains($content, 'use Laravel\\Sanctum\\HasApiTokens;')) {
            $content = preg_replace(
                '/^use Illuminate\\\\Notifications\\\\Notifiable;\R/m',
                'use Illuminate\\Notifications\\Notifiable;' . PHP_EOL . 'use Laravel\\Sanctum\\HasApiTokens;' . PHP_EOL,
                $content,
                1
            );
        }

        if (!preg_match('/^    use .*\\bHasApiTokens\\b.*;$/m', $content)) {
            $content = (string) preg_replace(
                '/^    use (.*\\bHasFactory\\b.*);$/m',
                '    use HasApiTokens, $1;',
                $content,
                1
            );
        }

        return $content;
    }

    protected function configureEnvironment(): bool
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (!file_exists($envPath) && file_exists($envExamplePath)) {
            copy($envExamplePath, $envPath);
        }
        if (!file_exists($envPath)) {
            warning('⚠ .env file not found; cannot configure the application.');

            return false;
        }

        $envContent = (string) file_get_contents($envPath);

        $needsSail = $this->commandRuntime === self::COMMAND_RUNTIME_SAIL
            || $this->runningInsideSail()
            || ($this->commandRuntime === self::COMMAND_RUNTIME_AUTO && $this->usingSail());

        if ($needsSail) {
            $uidDefault = function_exists('posix_getuid') ? (string) posix_getuid() : '1000';
            $gidDefault = function_exists('posix_getgid') ? (string) posix_getgid() : '1000';
            $autoDetect = confirm('Auto-detect your UID and GID for Sail?', default: true);
            $uid = $autoDetect ? $uidDefault : text('Enter your user ID (UID)', default: $uidDefault);
            $gid = $autoDetect ? $gidDefault : text('Enter your group ID (GID)', default: $gidDefault);

            $envContent = $this->replaceOrAppendEnv($envContent, 'WWWUSER', $uid);
            $envContent = $this->replaceOrAppendEnv($envContent, 'WWWGROUP', $gid);
        }

        // NewCommand migrates on the HOST after the starter hook. Keep .env host
        // reachable. config/database.php maps loopback to Docker service names
        // when LARAVEL_SAIL=1. Never rewrite a custom remote DB_URL or host.
        if ($this->envValue($envContent, 'DB_CONNECTION') === 'pgsql'
            && $this->envValue($envContent, 'DB_URL') === null) {
            $configuredHost = $this->envValue($envContent, 'DB_HOST') ?? '127.0.0.1';
            $localDatabase = in_array($configuredHost, ['pgsql', '127.0.0.1', 'localhost'], true);
            // Never treat credentials for a custom remote host as placeholders.
            $placeholders = $localDatabase ? ['laravel_template'] : [];
            $database = $this->envValue($envContent, 'DB_DATABASE', $placeholders)
                ?? $this->defaultDatabaseName();
            $username = $this->envValue($envContent, 'DB_USERNAME', $placeholders)
                ?? $database;
            $password = $this->envValue($envContent, 'DB_PASSWORD', $placeholders)
                ?? $database;

            $envContent = $this->replaceOrAppendEnv(
                $envContent, 'DB_HOST', $this->resolveServiceHost(
                    $this->envValue($envContent, 'DB_HOST') ?? '127.0.0.1', 'pgsql', false,
                ),
            );
            // The installer migrates from the host; honor a non-default mapped port.
            $dbHost = $this->envValue($envContent, 'DB_HOST');
            $forwardDbPort = $this->envValue($envContent, 'FORWARD_DB_PORT');
            if ($dbHost === '127.0.0.1' && $forwardDbPort !== null
                && $this->envValue($envContent, 'DB_PORT') === '5432') {
                $envContent = $this->replaceOrAppendEnv($envContent, 'DB_PORT', $forwardDbPort);
            }

            $envContent = $this->replaceOrAppendEnv($envContent, 'DB_DATABASE', $database);
            $envContent = $this->replaceOrAppendEnv($envContent, 'DB_USERNAME', $username);
            $envContent = $this->replaceOrAppendEnv($envContent, 'DB_PASSWORD', $password);
        }

        $redisHost = $this->envValue($envContent, 'REDIS_HOST');
        if ($redisHost !== null) {
            $resolvedRedisHost = $this->resolveServiceHost($redisHost, 'redis', false);
            $envContent = $this->replaceOrAppendEnv($envContent, 'REDIS_HOST', $resolvedRedisHost);

            $forwardRedisPort = $this->envValue($envContent, 'FORWARD_REDIS_PORT');
            if ($resolvedRedisHost === '127.0.0.1' && $forwardRedisPort !== null
                && $this->envValue($envContent, 'REDIS_PORT') === '6379') {
                $envContent = $this->replaceOrAppendEnv($envContent, 'REDIS_PORT', $forwardRedisPort);
            }
        }

        if (file_put_contents($envPath, $envContent) === false) {
            warning('⚠ Unable to write .env.');

            return false;
        }

        info('→ Local environment configured (credentials not displayed).');

        return true;
    }

    protected function resolveServiceHost(string $configuredHost, string $dockerHost, bool $useDockerNetwork): string
    {
        if (!in_array($configuredHost, [$dockerHost, '127.0.0.1'], true)) {
            return $configuredHost;
        }

        return $useDockerNetwork ? $dockerHost : '127.0.0.1';
    }

    protected function askCommandRuntime(): void
    {
        if ($this->runningInsideSail()) {
            $this->commandRuntime = self::COMMAND_RUNTIME_HOST;

            note('🐳 Running inside Sail; install commands will run directly in this container.');

            return;
        }

        $this->commandRuntime = (string) select(
            label: 'Where should setup run install commands?',
            options: [
                self::COMMAND_RUNTIME_HOST => 'Host machine',
                self::COMMAND_RUNTIME_SAIL => 'Laravel Sail container',
                self::COMMAND_RUNTIME_AUTO => 'Auto-detect running Sail',
            ],
            default: $this->sailAppContainerRunning() ? self::COMMAND_RUNTIME_SAIL : self::COMMAND_RUNTIME_HOST,
            hint: 'Composer runs on the host; this controls Artisan and manual frontend commands.',
        );

        $this->sailProxyAvailable = null;
    }

    protected function checkRuntimeRequirements(): bool
    {
        if ((new ExecutableFinder())->find('composer') === null) {
            warning('⚠ Composer is required on the host by Laravel Installer and by starter:setup.');

            return false;
        }

        if ($this->commandRuntime !== self::COMMAND_RUNTIME_SAIL || $this->runningInsideSail()) {
            return true;
        }

        if (!$this->hasSailCommand()) {
            warning('⚠ Sail was selected, but the sail executable was not found.');

            return false;
        }

        try {
            $dockerAvailable = Process::path(base_path())->timeout(10)
                ->run(['docker', 'compose', 'version'])->successful();
        } catch (\Throwable) {
            $dockerAvailable = false;
        }

        if (!$dockerAvailable) {
            warning('⚠ Docker Compose is unavailable. No Composer packages have been changed.');

            return false;
        }

        if (!$this->sailAppContainerRunning() && !confirm(
            label: 'Start Sail after installing the Composer packages?',
            default: true,
            hint: 'The Docker build will happen after Composer and preset file publication.',
        )) {
            note('Setup cancelled before changing project files.');

            return false;
        }

        return true;
    }

    protected function prepareCommandRuntime(): bool
    {
        if ($this->runningInsideSail() || (
            $this->commandRuntime !== self::COMMAND_RUNTIME_SAIL
            && !($this->commandRuntime === self::COMMAND_RUNTIME_AUTO && $this->usingSail())
        )) {
            return true;
        }

        // --wait checks the pgsql and redis healthchecks; --progress belongs to
        // docker compose, not to "docker compose up".
        $command = $this->sailAppContainerRunning()
            ? ['docker', 'compose', 'up', '-d', '--wait', '--wait-timeout', '180']
            : ['docker', 'compose', '--progress', 'plain', 'up', '-d', '--build', '--wait', '--wait-timeout', '180'];

        info('→ Running: ' . implode(' ', $command));

        if (!$this->runner()->run($command, 1200)) {
            warning('⚠ Failed to start healthy Sail containers.');

            return false;
        }

        $this->sailProxyAvailable = null;
        if ($this->sailAppContainerRunning()) {
            return true;
        }

        warning('⚠ Docker Compose finished but the application container is not running.');

        return false;
    }

    protected function replaceOrAppendEnv(string $content, string $key, string $value): string
    {
        $pattern = '/^[ \t]*#?[ \t]*' . preg_quote($key, '/') . '=.*/m';

        if (preg_match($pattern, $content) === 1) {
            // Replace all duplicates, including commented placeholders, safely
            // (preg_replace's replacement string interprets $ and backslashes).
            return (string) preg_replace_callback(
                $pattern,
                static fn(): string => "$key=$value",
                $content,
            );
        }

        return rtrim($content) . PHP_EOL . "$key=$value" . PHP_EOL;
    }

    /**
     * @param array<int, string> $placeholderValues
     */
    protected function envValue(string $content, string $key, array $placeholderValues = []): ?string
    {
        $quotedKey = preg_quote($key, '/');
        // An active value must win over an old commented example.
        foreach (['/^[ \t]*' . $quotedKey . '=(.*)$/m', '/^[ \t]*#[ \t]*' . $quotedKey . '=(.*)$/m'] as $pattern) {
            if (preg_match($pattern, $content, $matches) !== 1) {
                continue;
            }

            $value = trim(trim($matches[1]), "\"'");
            if ($value === '' || strtolower($value) === 'null') {
                continue;
            }
            if (in_array(strtolower($value), array_map('strtolower', $placeholderValues), true)) {
                continue;
            }

            return $value;
        }

        return null;
    }

    protected function defaultDatabaseName(): string
    {
        $name = basename(base_path());
        $name = preg_replace('/\W/', '_', $name) ?: 'laravel';

        return strtolower(trim($name, '_')) ?: 'laravel';
    }

    protected function usingSail(): bool
    {
        if ($this->sailProxyAvailable !== null) {
            return $this->sailProxyAvailable;
        }
        if ($this->runningInsideSail() || !$this->hasSailCommand()) {
            return $this->sailProxyAvailable = false;
        }
        if ($this->commandRuntime === self::COMMAND_RUNTIME_HOST) {
            return $this->sailProxyAvailable = false;
        }
        if ($this->commandRuntime === self::COMMAND_RUNTIME_SAIL) {
            return $this->sailProxyAvailable = $this->sailAppContainerRunning();
        }

        return $this->sailProxyAvailable = $this->sailAppContainerRunning();
    }

    protected function sailAppContainerRunning(): bool
    {
        if (!$this->hasSailCommand()) {
            return false;
        }

        try {
            $process = Process::path(base_path())
                ->timeout(10)
                ->run(['docker', 'compose', 'ps', '--services', '--filter', 'status=running']);
        } catch (\Throwable) {
            return false;
        }

        if (!$process->successful()) {
            return false;
        }

        $runningServices = explode(PHP_EOL, $process->output())
                |> (static fn($x) => array_map('trim', $x))
                |> array_filter(...);

        return in_array((string) env('APP_SERVICE', 'app'), $runningServices, true);
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
