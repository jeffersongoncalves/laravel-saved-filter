<?php

namespace JeffersonGoncalves\SavedFilter\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

/** Dispatched by the UI (e.g. filament-saved-filter) when a user applies a saved filter to a table. */
class SavedFilterApplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public SavedFilter $savedFilter, public ?Model $user = null) {}
}
