<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Illuminate\Foundation\Configuration\Middleware;

class WithMiddleware
{
    public function __invoke(Middleware $middleware): void
    {
        $middleware->redirectGuestsTo('/admin');
    }
}
