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
            ->hasCommands([PruneCommand::class, ExportCommand::class, ImportCommand::class]);
    }

    public function packageBooted(): void
    {
        $migrations = __DIR__.'/../database/migrations';

        $this->loadMigrationsFrom($migrations);
        $this->publishes([$migrations => database_path('migrations')], 'saved-filter-migrations');
    }
}
