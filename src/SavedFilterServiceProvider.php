<?php

namespace JeffersonGoncalves\SavedFilter;

use JeffersonGoncalves\SavedFilter\Commands\ExportCommand;
use JeffersonGoncalves\SavedFilter\Commands\ImportCommand;
use JeffersonGoncalves\SavedFilter\Commands\PruneCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SavedFilterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-saved-filter')
            ->hasConfigFile('saved-filter')
            ->hasMigrations([
                'create_saved_filters_table',
                'add_pagination_and_grouping_to_saved_filters_table',
                'add_column_searches_to_saved_filters_table',
            ])
            ->hasCommands([PruneCommand::class, ExportCommand::class, ImportCommand::class]);
    }
}
