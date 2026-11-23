<?php

declare(strict_types=1);

use App\Setup\AdminPanelFrontendInstaller;
use Illuminate\Support\Facades\File;

it('refuses to overwrite an existing admin frontend file before copying anything', function (): void {
    $file = Mockery::mock();
    $file->shouldReceive('getRelativePathname')->once()->andReturn('resources/js/layouts/Panel/panel.vue');
    $file->shouldReceive('getPathname')->once()->andReturn('/tmp/admin-preset/panel.vue');

    File::shouldReceive('isDirectory')->once()->andReturnTrue();
    File::shouldReceive('allFiles')->once()->andReturn([$file]);
    File::shouldReceive('exists')->once()->andReturnTrue();
    File::shouldReceive('hash')->twice()->andReturn('user-customized', 'preset');
    File::shouldNotReceive('copy');

    expect((new AdminPanelFrontendInstaller())->canInstall())->toBeFalse();
});

it('keeps the replaceable package hash aligned with the starter skeleton', function (): void {
    $hashes = (new ReflectionClass(AdminPanelFrontendInstaller::class))
        ->getReflectionConstant('REPLACEABLE_SKELETON_HASHES')
        ->getValue();

    expect($hashes['package.json'])->toBe(hash_file('sha256', base_path('package.json')));
});

it('allows replacing pristine Laravel skeleton files on first installation', function (): void {
    $file = Mockery::mock();
    $file->shouldReceive('getRelativePathname')->twice()->andReturn('package.json');
    $file->shouldReceive('getPathname')->times(3)->andReturn('/tmp/admin-preset/package.json');

    File::shouldReceive('isDirectory')->once()->andReturnTrue();
    File::shouldReceive('allFiles')->twice()->andReturn([$file]);
    File::shouldReceive('exists')->twice()->andReturnTrue();
    File::shouldReceive('hash')->andReturnUsing(
        static fn(string $path): string => $path === base_path('package.json')
            ? 'faa8b9989e837f0c2243b029ba361c08965393c860bd4211d4c2a7c7592dab5e'
            : 'admin-stub-hash',
    );
    File::shouldReceive('get')->once()->andReturn('original package.json');
    File::shouldReceive('ensureDirectoryExists')->once()->with(base_path());
    File::shouldReceive('copy')->once()->andReturnTrue();

    $method = new ReflectionMethod(AdminPanelFrontendInstaller::class, 'publishPresetFiles');

    expect($method->invoke(new AdminPanelFrontendInstaller()))->toBeTrue();
});

it('does not overwrite an already published admin frontend file', function (): void {
    $file = Mockery::mock();
    $file->shouldReceive('getRelativePathname')->twice()->andReturn('package.json');
    $file->shouldReceive('getPathname')->twice()->andReturn('/tmp/admin-preset/package.json');

    File::shouldReceive('isDirectory')->once()->andReturnTrue();
    File::shouldReceive('allFiles')->twice()->andReturn([$file]);
    File::shouldReceive('exists')->twice()->andReturnTrue();
    File::shouldReceive('hash')->andReturn('same-hash');
    File::shouldNotReceive('copy');
    File::shouldNotReceive('get');

    $method = new ReflectionMethod(AdminPanelFrontendInstaller::class, 'publishPresetFiles');

    expect($method->invoke(new AdminPanelFrontendInstaller()))->toBeTrue();
});

it('uses supported Nuxt UI and Inertia versions in the admin preset', function (): void {
    $dependencies = json_decode(
        (string) file_get_contents(base_path('stubs/admin-panel/package.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $root = json_decode(
        (string) file_get_contents(base_path('package.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($dependencies['dependencies']['@nuxt/ui'])->toBe('^4.12.0')
        ->and($dependencies['dependencies']['@inertiajs/vue3'])->toBe('^3.9.1')
        ->and($dependencies['devDependencies']['@vitejs/plugin-vue'])->toBe('^6.0.10')
        ->and($dependencies['devDependencies']['laravel-vite-plugin'])->toBe('^2.0.1')
        ->and($root['devDependencies']['laravel-vite-plugin'])->toBe('^2.0.1');
});

it('uses the frontend manager selected for manual installation', function (): void {
    $method = new ReflectionMethod(AdminPanelFrontendInstaller::class, 'nodeInstallCommand');
    $installer = new AdminPanelFrontendInstaller();

    expect($method->invoke($installer, false, './sail', 'pnpm'))->toBe(['pnpm', 'install'])
        ->and(array_slice($method->invoke($installer, true, './sail', 'npm'), -2))->toBe(['npm', 'install']);
});

it('installs frontend dependencies through Sail without a TTY or root-owned files', function (): void {
    $method = new ReflectionMethod(AdminPanelFrontendInstaller::class, 'nodeInstallCommand');
    $cmd = $method->invoke(new AdminPanelFrontendInstaller(), true, './sail');

    expect($cmd[0])->toBe('./sail')
        ->and($cmd[1])->toBe('exec')
        ->and($cmd[2])->toBe('-T')
        ->and($cmd[3])->toBe('-u')
        ->and($cmd[4])->toBe('sail')
        ->and(array_slice($cmd, -2))->toBe(['bun', 'install']);
});
