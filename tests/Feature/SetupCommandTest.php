<?php

declare(strict_types=1);

use App\Console\Commands\SetupCommand;
use App\Setup\StarterKitPackageRegistry;
use App\Setup\StarterKitPreset;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

it('uncomments env values when replacing them', function (): void {
    $command = app(SetupCommand::class);
    $replace = new ReflectionMethod($command, 'replaceOrAppendEnv');
    $replace->setAccessible(true);

    $content = implode(PHP_EOL, [
        'DB_CONNECTION=sqlite',
        '# DB_DATABASE=laravel',
        '# DB_PASSWORD=',
    ]);

    $content = $replace->invoke($command, $content, 'DB_DATABASE', 'example_app');
    $content = $replace->invoke($command, $content, 'DB_PASSWORD', 'example_app');

    expect($content)
        ->toContain('DB_DATABASE=example_app')
        ->toContain('DB_PASSWORD=example_app')
        ->not->toContain('# DB_DATABASE')
        ->not->toContain('# DB_PASSWORD');
});

it('reads usable values from commented env lines and ignores empty values', function (): void {
    $command = app(SetupCommand::class);
    $envValue = new ReflectionMethod($command, 'envValue');
    $envValue->setAccessible(true);

    $content = implode(PHP_EOL, [
        '# DB_DATABASE=example_app',
        '# DB_USERNAME=',
        '# DB_PASSWORD=null',
    ]);

    expect($envValue->invoke($command, $content, 'DB_DATABASE'))->toBe('example_app')
        ->and($envValue->invoke($command, $content, 'DB_USERNAME'))->toBeNull()
        ->and($envValue->invoke($command, $content, 'DB_PASSWORD'))->toBeNull();
});

it('ignores known database placeholders', function (): void {
    $command = app(SetupCommand::class);
    $envValue = new ReflectionMethod($command, 'envValue');
    $envValue->setAccessible(true);

    $content = 'DB_DATABASE=laravel_template';

    expect($envValue->invoke($command, $content, 'DB_DATABASE', ['laravel_template']))->toBeNull();
});

it('uses Sanctum as the default but keeps other API components optional', function (): void {
    Prompt::fake(['y', Key::ENTER]);

    $command = app(SetupCommand::class);
    $askApiSupport = new ReflectionMethod($command, 'askApiSupport');
    $askApiSupport->setAccessible(true);

    $askApiSupport->invoke($command);

    $installedPackages = new ReflectionProperty($command, 'installedPackages');
    $installedPackages->setAccessible(true);

    expect($installedPackages->getValue($command))->toBe(['laravel/sanctum']);
});

it('installs selected observability packages from extras', function (): void {
    Prompt::fake([
        Key::SPACE,
        Key::DOWN,
        Key::SPACE,
        Key::DOWN,
        Key::SPACE,
        Key::DOWN,
        Key::SPACE,
        Key::ENTER,
    ]);

    $command = app(SetupCommand::class);
    $askExtras = new ReflectionMethod($command, 'askExtras');
    $askExtras->setAccessible(true);

    $askExtras->invoke($command);

    $installedPackages = new ReflectionProperty($command, 'installedPackages');
    $installedPackages->setAccessible(true);

    $installedDevPackages = new ReflectionProperty($command, 'installedDevPackages');
    $installedDevPackages->setAccessible(true);

    expect($installedPackages->getValue($command))->toBe([
        'opcodesio/log-viewer',
        'laravel/horizon',
        'laravel/pulse',
    ])->and($installedDevPackages->getValue($command))->toBe([
        'laravel/telescope',
    ]);
});

it('applies the api preset with default api packages', function (): void {
    expect(StarterKitPreset::Api->packages())->toBe([
        'laravel/sanctum',
        'pepperfm/swagger-nuxt-ui-for-laravel',
        'spatie/laravel-data',
    ])
        ->and(StarterKitPreset::Api->devPackages())->toBe([])
        ->and(StarterKitPreset::Api->installsAdminFrontend())->toBeFalse();
});

it('applies the observability preset with telescope as a dev package', function (): void {
    expect(StarterKitPreset::Observability->packages())->toBe([
        'opcodesio/log-viewer',
        'laravel/horizon',
        'laravel/pulse',
    ])->and(StarterKitPreset::Observability->devPackages())->toBe([
        'laravel/telescope',
    ]);
});

it('applies the full preset with admin api and observability packages', function (): void {
    expect(StarterKitPreset::Full->installsAdminFrontend())->toBeTrue()
        ->and(StarterKitPreset::Full->packages())->toBe([
            'inertiajs/inertia-laravel:^3.0',
            'tightenco/ziggy:^2.5',
            'laravel/sanctum',
            'pepperfm/swagger-nuxt-ui-for-laravel',
            'spatie/laravel-data',
            'opcodesio/log-viewer',
            'laravel/horizon',
            'laravel/pulse',
        ])->and(StarterKitPreset::Full->devPackages())->toBe([
            'laravel/telescope',
        ]);
});

it('rejects unknown presets', function (): void {
    expect(StarterKitPreset::tryFrom('unknown'))->toBeNull()
        ->and(StarterKitPreset::values())->toBe([
            'api',
            'admin',
            'observability',
            'full',
        ]);
});

it('installs Sanctum through normal Composer and scaffolds its API without nested install:api', function (): void {
    expect(StarterKitPackageRegistry::postInstallCommandsFor('laravel/sanctum'))->toBe([]);
});

it('defines observability package post install commands', function (): void {
    expect(StarterKitPackageRegistry::postInstallCommandsFor('opcodesio/log-viewer'))->toBe([
        ['log-viewer:publish'],
    ])->and(StarterKitPackageRegistry::postInstallCommandsFor('laravel/horizon'))->toBe([
        ['horizon:install'],
    ])->and(StarterKitPackageRegistry::postInstallCommandsFor('laravel/telescope'))->toBe([
        ['telescope:install'],
        ['migrate'],
    ])->and(StarterKitPackageRegistry::postInstallCommandsFor('laravel/pulse'))->toBe([
        ['vendor:publish', '--provider=Laravel\\Pulse\\PulseServiceProvider'],
        ['migrate'],
    ]);
});

it('summarizes selected post install commands without duplicates', function (): void {
    expect(StarterKitPackageRegistry::selectedPostInstallCommands(
        packages: [
            'laravel/sanctum',
            'laravel/sanctum',
            'pepperfm/swagger-nuxt-ui-for-laravel',
            'opcodesio/log-viewer',
        ],
        devPackages: [
            'laravel/telescope',
        ],
    ))->toBe([
        'opcodesio/log-viewer' => [
            ['log-viewer:publish'],
        ],
        'laravel/telescope' => [
            ['telescope:install'],
        ],
        'database migrations' => [
            ['migrate'],
        ],
    ]);
});

it('adds sanctum api token support to the user model content', function (): void {
    $command = app(SetupCommand::class);
    $addSanctumTrait = new ReflectionMethod($command, 'addSanctumTraitToUserModel');
    $addSanctumTrait->setAccessible(true);

    $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
}
PHP;

    $updated = $addSanctumTrait->invoke($command, $content);

    expect($updated)
        ->toContain('use Laravel\\Sanctum\\HasApiTokens;')
        ->toContain('use HasApiTokens, HasFactory, Notifiable;');
});

it('registers api routes in the bootstrap routing definition', function (): void {
    $command = app(SetupCommand::class);
    $addApiRoutes = new ReflectionMethod($command, 'addApiRoutesToBootstrap');
    $addApiRoutes->setAccessible(true);

    $content = <<<'PHP'
<?php

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->create();
PHP;

    $updated = $addApiRoutes->invoke($command, $content);

    expect($updated)->toContain("        api: __DIR__ . '/../routes/api.php',");
});

it('runs migrations only once for multiple selected packages', function (): void {
    $commands = StarterKitPackageRegistry::selectedPostInstallCommands(
        packages: ['laravel/pulse', 'laravel/sanctum', 'spatie/laravel-medialibrary'],
        devPackages: ['laravel/telescope'],
    );

    expect($commands['database migrations'])->toBe([['migrate']])
        ->and(collect($commands)->flatten(2)->filter(fn($value) => $value === 'migrate')->count())->toBe(1);
});

it('prefers active env values and safely replaces dollar signs in passwords', function (): void {
    $command = app(SetupCommand::class);
    $read = new ReflectionMethod($command, 'envValue');
    $replace = new ReflectionMethod($command, 'replaceOrAppendEnv');
    $env = "# DB_HOST=pgsql\nDB_HOST=example.internal\nDB_PASSWORD=old";

    expect($read->invoke($command, $env, 'DB_HOST'))->toBe('example.internal');
    $updated = $replace->invoke($command, $env, 'DB_PASSWORD', 'foo$1\\bar');
    expect($updated)->toContain('DB_PASSWORD=foo$1\\bar')
        ->not->toContain('DB_PASSWORD=old');
});

it('resolves host and Docker database and Redis service names without rewriting custom addresses', function (): void {
    $command = app(SetupCommand::class);
    $resolve = new ReflectionMethod($command, 'resolveServiceHost');

    expect($resolve->invoke($command, 'pgsql', 'pgsql', false))->toBe('127.0.0.1')
        ->and($resolve->invoke($command, '127.0.0.1', 'pgsql', true))->toBe('pgsql')
        ->and($resolve->invoke($command, 'redis', 'redis', false))->toBe('127.0.0.1')
        ->and($resolve->invoke($command, 'remote.example', 'pgsql', false))->toBe('remote.example');
});


it('routes optional Composer requirements through Sail when selected', function (): void {
    $command = app(SetupCommand::class);
    $build = new ReflectionMethod($command, 'buildComposerRequireCommand');
    $sailCommand = (new ReflectionMethod($command, 'getSailCommand'))->invoke($command);

    expect($build->invoke($command, ['laravel/telescope'], true, true))->toBe([
        $sailCommand, 'composer', 'require', '--with-all-dependencies', '--dev', 'laravel/telescope',
    ])->and($build->invoke($command, ['laravel/horizon'], false, false))->toBe([
        'composer', 'require', '--with-all-dependencies', 'laravel/horizon',
    ]);
});

it('skips unchanged Composer requirements on repeated setup', function (): void {
    $command = app(SetupCommand::class);
    $method = new ReflectionMethod($command, 'missingPackages');

    expect($method->invoke($command, [
        'laravel/framework:^13.0',
        'laravel/tinker',
        'spatie/laravel-permission',
        'laravel/framework:^14.0',
    ]))->toBe([
        'spatie/laravel-permission',
        'laravel/framework:^14.0',
    ]);
});

it('declares a dedicated installer hook and defers migration and Node work to Installer', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['laravel']['installer']['post-create-project'])->toBe([
        '@php artisan starter:setup --installer-hook',
    ])->and($composer['scripts']['post-create-project-cmd'])->toBe([
        '@php scripts/post-create-project.php',
    ]);
});

it('keeps complete scripts for manual setup and avoids install api Composer recursion', function (): void {
    $commands = StarterKitPackageRegistry::selectedPostInstallCommands(
        packages: ['laravel/sanctum', 'laravel/pulse'],
        devPackages: [],
    );

    expect($commands)->not->toHaveKey('laravel/sanctum')
        ->and($commands['database migrations'])->toBe([['migrate']]);
});

it('honors the standalone launcher's PostgreSQL and Sail environment settings', function (): void {
    $previous = getenv('PEPPERFM_NEW_CONFIG');

    try {
        putenv('PEPPERFM_NEW_CONFIG=' . json_encode([
            'runtime' => 'sail',
            'database' => 'pgsql',
            'manager' => 'bun',
            'app_url' => 'http://localhost:8080',
            'db_host' => '127.0.0.1',
            'db_port' => '5433',
            'db_name' => 'sample_db',
            'db_user' => 'sample_user',
            'db_password' => 'test_pass',
            'db_forward_port' => '5433',
            'app_port' => '8080',
            'redis_port' => '6380',
        ], JSON_THROW_ON_ERROR));

        $apply = new ReflectionMethod(SetupCommand::class, 'applyLauncherEnvironment');
        $content = $apply->invoke(app(SetupCommand::class), implode(PHP_EOL, [
            'APP_URL=http://localhost',
            'DB_CONNECTION=pgsql',
            'DB_HOST=127.0.0.1',
            'DB_PORT=5432',
            'DB_DATABASE=laravel_template',
            'DB_USERNAME=laravel_template',
            'DB_PASSWORD=laravel_template',
            'REDIS_PORT=6379',
        ]));

        expect($content)->toContain('APP_URL=http://localhost:8080')
            ->toContain('DB_CONNECTION=pgsql')
            ->toContain('DB_PORT=5433')
            ->toContain('FORWARD_DB_PORT=5433')
            ->toContain('DB_DATABASE=sample_db')
            ->toContain('DB_USERNAME=sample_user')
            ->toContain('DB_PASSWORD=test_pass')
            ->toContain('APP_PORT=8080')
            ->toContain('REDIS_PORT=6380')
            ->toContain('FORWARD_REDIS_PORT=6380')
            ->toContain('COMPOSE_PROFILES=pgsql');
    } finally {
        $previous === false ? putenv('PEPPERFM_NEW_CONFIG') : putenv('PEPPERFM_NEW_CONFIG=' . $previous);
    }
});

it('uses the default SQLite database path when selected through the launcher', function (): void {
    $previous = getenv('PEPPERFM_NEW_CONFIG');

    try {
        putenv('PEPPERFM_NEW_CONFIG=' . json_encode([
            'runtime' => 'sail',
            'database' => 'sqlite',
            'app_url' => 'http://localhost:8080',
            'app_port' => '8080',
            'redis_port' => '6380',
        ], JSON_THROW_ON_ERROR));

        $apply = new ReflectionMethod(SetupCommand::class, 'applyLauncherEnvironment');
        $content = $apply->invoke(app(SetupCommand::class), implode(PHP_EOL, [
            'DB_CONNECTION=pgsql',
            'DB_HOST=127.0.0.1',
            'DB_PORT=5432',
            'DB_DATABASE=laravel_template',
            'DB_USERNAME=laravel_template',
            'DB_PASSWORD=laravel_template',
        ]));

        expect($content)->toContain('DB_CONNECTION=sqlite')
            ->toContain('# DB_HOST=127.0.0.1')
            ->toContain('# DB_DATABASE=laravel_template')
            ->not->toMatch('/^DB_DATABASE=/m')
            ->toContain('COMPOSE_PROFILES=');
    } finally {
        $previous === false ? putenv('PEPPERFM_NEW_CONFIG') : putenv('PEPPERFM_NEW_CONFIG=' . $previous);
    }
});

it('dry-runs the independent launcher without starting Laravel Installer', function (): void {
    $process = new \Symfony\Component\Process\Process(
        [PHP_BINARY, base_path('bin/pepperfm-new'), 'example-app', '--dry-run'],
        base_path(),
    );
    $process->setInput("1\n1\n1\n\n\n\n\n\n\n");
    $process->run();

    expect($process->isSuccessful())->toBeTrue()
        ->and($process->getOutput())->toContain('--database=pgsql --bun')
        ->toContain('Dry run: no project created.');
});
