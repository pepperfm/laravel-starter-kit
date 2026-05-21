<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaPanelRequests;
use Illuminate\Support\Facades\Route;

Route::prefix('panel')
    ->name('panel.')
    ->middleware(HandleInertiaPanelRequests::class)
    ->group(static function (): void {
        Route::inertia('/', 'Panel/Dashboard')->name('dashboard');
    });
