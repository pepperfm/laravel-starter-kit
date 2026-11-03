<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\PanelSessionController;
use App\Http\Middleware\HandleInertiaPanelRequests;
use Illuminate\Support\Facades\Route;

Route::prefix('panel')
    ->name('panel.')
    ->middleware(HandleInertiaPanelRequests::class)
    ->group(static function (): void {
        Route::middleware('guest')->group(static function (): void {
            Route::get('login', [PanelSessionController::class, 'create'])->name('login');
            Route::post('login', [PanelSessionController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('login.store');
        });

        Route::middleware('auth')->group(static function (): void {
            Route::inertia('/', 'Panel/Dashboard')->name('dashboard');
            Route::post('logout', [PanelSessionController::class, 'destroy'])->name('logout');
        });
    });
