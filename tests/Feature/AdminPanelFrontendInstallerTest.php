<?php

declare(strict_types=1);

use App\Setup\AdminPanelFrontendInstaller;
use Illuminate\Support\Facades\File;

it('refuses to overwrite an existing admin frontend file before copying anything', function (): void {
    $file = Mockery::mock();
    $file->shouldReceive('getRelativePathname')
        ->once()
        ->andReturn('resources/js/layouts/Panel/panel.vue');

    File::shouldReceive('isDirectory')->once()->andReturnTrue();
    File::shouldReceive('allFiles')->once()->andReturn([$file]);
    File::shouldReceive('exists')
        ->once()
        ->with(base_path('resources/js/layouts/Panel/panel.vue'))
        ->andReturnTrue();
    File::shouldNotReceive('copy');
    File::shouldNotReceive('ensureDirectoryExists');

    $method = new ReflectionMethod(AdminPanelFrontendInstaller::class, 'publishPresetFiles');

    expect($method->invoke(new AdminPanelFrontendInstaller()))->toBeFalse();
});

it('allows replacing only known Laravel skeleton files on initial admin installation', function (): void {
    $file = Mockery::mock();
    $file->shouldReceive('getRelativePathname')->twice()->andReturn('package.json');
    $file->shouldReceive('getPathname')->once()->andReturn('/tmp/admin-preset/package.json');

    File::shouldReceive('isDirectory')->once()->andReturnTrue();
    File::shouldReceive('allFiles')->once()->andReturn([$file]);
    File::shouldReceive('exists')
        ->once()
        ->with(base_path('package.json'))
        ->andReturnTrue();
    File::shouldReceive('ensureDirectoryExists')
        ->once()
        ->with(base_path());
    File::shouldReceive('copy')
        ->once()
        ->with('/tmp/admin-preset/package.json', base_path('package.json'))
        ->andReturnTrue();

    $method = new ReflectionMethod(AdminPanelFrontendInstaller::class, 'publishPresetFiles');

    expect($method->invoke(new AdminPanelFrontendInstaller()))->toBeTrue();
});

it('uses supported Nuxt UI and Inertia versions in the admin preset', function (): void {
    $dependencies = json_decode(
        (string) file_get_contents(base_path('stubs/admin-panel/package.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($dependencies['dependencies']['@nuxt/ui'])->toBe('^4.12.0')
        ->and($dependencies['dependencies']['@inertiajs/vue3'])->toBe('^3.9.1')
        ->and($dependencies['devDependencies']['@vitejs/plugin-vue'])->toBe('^6.0.10');
});
