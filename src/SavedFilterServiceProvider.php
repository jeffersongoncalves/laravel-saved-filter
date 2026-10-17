<?php

namespace JeffersonGoncalves\SavedFilter;

use Illuminate\Console\Scheduling\Schedule;
use JeffersonGoncalves\SavedFilter\Commands\ExportCommand;
use JeffersonGoncalves\SavedFilter\Commands\ImportCommand;
use JeffersonGoncalves\SavedFilter\Commands\PruneCommand;
use JeffersonGoncalves\SavedFilter\Commands\SendReportsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SavedFilterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-saved-filter')
            ->hasConfigFile('saved-filter')
            ->hasTranslations()
            ->hasMigrations([
                'create_saved_filters_table',
                'add_pagination_and_grouping_to_saved_filters_table',
                'add_column_searches_to_saved_filters_table',
                'create_saved_filter_reports_table',
            ])
            ->hasCommands([PruneCommand::class, ExportCommand::class, ImportCommand::class, SendReportsCommand::class]);
    }

    public function packageBooted(): void
    {
        // Cheap when nothing is due; the reports go out within 15 minutes of their time.
        // onOneServer needs a cache driver with locks (redis, database, memcached...) when several servers run the scheduler.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule
            ->command(SendReportsCommand::class)
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer());
    }
}
