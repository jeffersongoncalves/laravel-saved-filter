<?php

namespace JeffersonGoncalves\SavedFilter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\SavedFilter\SavedFilter
 */
class SavedFilter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-saved-filter';
    }
}
