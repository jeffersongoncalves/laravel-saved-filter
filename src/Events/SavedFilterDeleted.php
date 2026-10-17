<?php

namespace JeffersonGoncalves\SavedFilter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

class SavedFilterDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public SavedFilter $savedFilter) {}
}
