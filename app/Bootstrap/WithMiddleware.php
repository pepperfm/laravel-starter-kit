<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

class WithMiddleware
{
    public function __invoke(Middleware $middleware): void
    {
        // Guests must never bounce back into the protected panel.
        // Other, non-panel auth routes keep their own login destination.
        $middleware->redirectGuestsTo(
            static fn(Request $request): string => $request->is('panel', 'panel/*')
                ? '/panel/login'
                : '/login',
        );

        $middleware->redirectUsersTo(
            static fn(Request $request): string => $request->is('panel', 'panel/*')
                ? '/panel'
                : '/dashboard',
        );
    }
}
