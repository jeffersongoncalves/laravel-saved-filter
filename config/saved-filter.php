<?php

use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

return [
    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    |
    | One row per saved filter: the owner (any model, null for presets), the
    | tenant, the resource it belongs to and the table state it restores.
    |
    */
    'table' => 'saved_filters',

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | Extend JeffersonGoncalves\SavedFilter\Models\SavedFilter to add
    | relations, policies or scopes, and point this key at your class.
    |
    */
    'model' => SavedFilter::class,
];
