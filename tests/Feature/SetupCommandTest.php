<?php

declare(strict_types=1);

use App\Console\Commands\SetupCommand;
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

it('installs sanctum and swagger nuxt ui when api support is selected', function (): void {
    Prompt::fake(['y', Key::ENTER, Key::ENTER]);

    $command = app(SetupCommand::class);
    $askApiSupport = new ReflectionMethod($command, 'askApiSupport');
    $askApiSupport->setAccessible(true);

    $askApiSupport->invoke($command);

    $installedPackages = new ReflectionProperty($command, 'installedPackages');
    $installedPackages->setAccessible(true);

    expect($installedPackages->getValue($command))->toBe([
        'laravel/sanctum',
        'pepperfm/swagger-nuxt-ui-for-laravel',
        'spatie/laravel-data',
    ]);
});

it('defines sanctum api scaffolding as a post install command', function (): void {
    $command = app(SetupCommand::class);
    $postInstallCommands = new ReflectionMethod($command, 'postInstallCommandsForPackage');
    $postInstallCommands->setAccessible(true);

    expect($postInstallCommands->invoke($command, 'laravel/sanctum'))->toBe([
        ['install:api', '--without-migration-prompt'],
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
