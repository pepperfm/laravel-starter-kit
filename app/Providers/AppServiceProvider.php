<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * @property \Illuminate\Foundation\Application $app
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
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
        if (!$this->app->isLocal() && !$this->app->runningUnitTests()) {
            $url->forceScheme('https');
        }

        Model::unguard();
        Model::shouldBeStrict($this->app->isLocal());

        RateLimiter::for(
            'api',
            static fn(Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
        );

        $this->configureFilament();
    }

    protected function configureFilament(): void
    {
        Select::configureUsing(
            static fn(Select $select) => $select->searchDebounce(500)->native(false)
        );
        SelectFilter::configureUsing(
            static fn(SelectFilter $select) => $select->native(false)
        );
        DatePicker::configureUsing(
            static fn(DatePicker $datePicker) => $datePicker
                ->native(false)
                ->displayFormat('d.m.Y')
                ->suffixIcon('heroicon-m-calendar')
        );
        DateTimePicker::configureUsing(
            static fn(DateTimePicker $datePicker) => $datePicker
                ->native(false)
                ->displayFormat('d.m.Y, H:i:s')
                ->suffixIcon('heroicon-m-calendar')
        );
        Column::configureUsing(
            static fn(Column $column) => $column
                ->placeholder('-')
                ->searchable(isIndividual: true, isGlobal: false)
        );

        Section::configureUsing(static fn(Section $section) => $section->maxWidth('xl'));

        CreateAction::configureUsing(static fn(CreateAction $action) => $action->createAnother(false));
        EditAction::configureUsing(static fn(EditAction $action) => $action->iconButton());
        ViewAction::configureUsing(static fn(ViewAction $action) => $action->iconButton());
        DeleteAction::configureUsing(static fn(DeleteAction $action) => $action->iconButton());

        Table::configureUsing(
            static fn(Table $table) => $table
                ->striped()
                ->paginated([10, 25, 50])
                ->deferLoading()
        );
    }
}
