<?php

namespace JeffersonGoncalves\SavedFilter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SavedFilterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-saved-filter')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
