<?php

declare(strict_types=1);

use App\Console\Commands\SetupCommand;

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
