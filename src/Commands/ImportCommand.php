<?php

namespace JeffersonGoncalves\SavedFilter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

class ImportCommand extends Command
{
    protected $signature = 'saved-filter:import
        {path : JSON file written by saved-filter:export}
        {--tenant= : Import for this tenant (default: no tenant)}
        {--locked : Lock every imported preset, whatever the file says}';

    protected $description = 'Import saved filter presets from JSON, updating the ones with the same resource and name';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $payload = File::exists($path) ? json_decode(File::get($path), true) : null;

        if (! is_array($payload)) {
            $this->components->error("{$path} is not a readable JSON file.");

            return self::FAILURE;
        }

        /** @var class-string<SavedFilter> $model */
        $model = config('saved-filter.model', SavedFilter::class);
        $imported = $model::import($payload, tenant: $this->option('tenant'), locked: $this->option('locked') ?: null);

        $this->components->info("Imported {$imported->count()} preset(s).");

        return self::SUCCESS;
    }
}
