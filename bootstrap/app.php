<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(new \App\Bootstrap\WithMiddleware())
    ->withExceptions(new \App\Bootstrap\WithExceptions())
    ->create();
