<?php

declare(strict_types=1);

it('ships a guarded panel with a functioning session login/logout flow', function (): void {
    $routes = (string) file_get_contents(base_path('stubs/admin-panel/routes/panel.php'));
    $controller = (string) file_get_contents(base_path('stubs/admin-panel/app/Http/Controllers/Panel/PanelSessionController.php'));
    $middleware = (string) file_get_contents(base_path('app/Bootstrap/WithMiddleware.php'));
    $command = (string) file_get_contents(base_path('stubs/admin-panel/app/Console/Commands/CreatePanelUserCommand.php'));

    expect($routes)
        ->toContain("Route::middleware('guest')")
        ->toContain("Route::middleware('auth')")
        ->toContain("->middleware('throttle:5,1')")
        ->toContain("->name('logout')")
        ->and($controller)
        ->toContain("Auth::guard('web')->attempt")
        ->toContain('session()->regenerate()')
        ->toContain("Auth::guard('web')->logout()")
        ->toContain('session()->invalidate()')
        ->and($middleware)->toContain("'/panel/login'")
        ->and($command)->toContain('protected $signature = \'panel:user\'')
        ->toContain('$this->secret(');
});

it('does not ship legacy Filament or Hasnayeen public assets', function (): void {
    expect(is_dir(public_path('css/filament')))->toBeFalse()
        ->and(is_dir(public_path('css/hasnayeen')))->toBeFalse()
        ->and(is_dir(public_path('js/filament')))->toBeFalse();
});

it('targets the intended Laravel and PHP major versions', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require']['laravel/framework'])->toBe('^13.0');
});
