<?php

namespace JeffersonGoncalves\SavedFilter\Tests;

use JeffersonGoncalves\SavedFilter\SavedFilterServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SavedFilterServiceProvider::class,
        ];
    }
}
