<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\RateLimiter;

/**
 * @property \Illuminate\Foundation\Application $app
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \Illuminate\Support\Carbon::setLocale(config('app.locale'));
    }

    /**
     * Bootstrap any application services.
     *
     * @param UrlGenerator $url
     */
    public function boot(UrlGenerator $url): void
    {
        if (!$this->app->isLocal() && !$this->app->runningInConsole()) {
            $url->forceScheme('https');
        }

        Model::unguard();
        Model::shouldBeStrict($this->app->isLocal());

        RateLimiter::for(
            'api',
            static fn(Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
        );
    }
}
