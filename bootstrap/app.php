<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: static function (): void {
            if (file_exists(base_path('routes/panel.php'))) {
                Route::middleware('web')->group(base_path('routes/panel.php'));
            }
        },
    )
    ->withMiddleware(new \App\Bootstrap\WithMiddleware())
    ->withExceptions(new \App\Bootstrap\WithExceptions())
    ->create();
