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

        if (
            class_exists(\Filament\Facades\Filament::class) &&
            array_key_exists(\Filament\FilamentServiceProvider::class, $this->app->getLoadedProviders())
        ) {
            $this->configureFilament();
        }
    }

    protected function configureFilament(): void
    {
        \Filament\Forms\Components\Select::configureUsing(
            static fn(\Filament\Forms\Components\Select $select) => $select->searchDebounce(500)->native(false)
        );
        \Filament\Tables\Filters\SelectFilter::configureUsing(
            static fn(\Filament\Tables\Filters\SelectFilter $select) => $select->native(false)
        );
        \Filament\Forms\Components\DatePicker::configureUsing(
            static fn(\Filament\Forms\Components\DatePicker $datePicker) => $datePicker
                ->native(false)
                ->displayFormat('d.m.Y')
                ->suffixIcon('heroicon-m-calendar')
        );
        \Filament\Forms\Components\DateTimePicker::configureUsing(
            static fn(\Filament\Forms\Components\DateTimePicker $datePicker) => $datePicker
                ->native(false)
                ->displayFormat('d.m.Y, H:i:s')
                ->suffixIcon('heroicon-m-calendar')
        );
        \Filament\Tables\Columns\Column::configureUsing(
            static fn(\Filament\Tables\Columns\Column $column) => $column
                ->placeholder('-')
                ->searchable(isIndividual: true, isGlobal: false)
        );

        \Filament\Forms\Components\Section::configureUsing(
            static fn(\Filament\Forms\Components\Section $section) => $section->maxWidth('xl')
        );

        \Filament\Tables\Actions\CreateAction::configureUsing(
            static fn(\Filament\Tables\Actions\CreateAction $action) => $action->createAnother(false)
        );
        \Filament\Tables\Actions\EditAction::configureUsing(
            static fn(\Filament\Tables\Actions\EditAction $action) => $action->iconButton()
        );
        \Filament\Tables\Actions\ViewAction::configureUsing(
            static fn(\Filament\Tables\Actions\ViewAction $action) => $action->iconButton()
        );
        \Filament\Tables\Actions\DeleteAction::configureUsing(
            static fn(\Filament\Tables\Actions\DeleteAction $action) => $action->iconButton()
        );

        \Filament\Tables\Table::configureUsing(
            static fn(\Filament\Tables\Table $table) => $table
                ->striped()
                ->paginated([10, 25, 50])
                ->deferLoading()
        );
    }
}
