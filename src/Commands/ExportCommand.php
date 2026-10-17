<?php

namespace JeffersonGoncalves\SavedFilter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

class ExportCommand extends Command
{
    protected $signature = 'saved-filter:export
        {path? : File to write; prints to stdout when omitted}
        {--resource=* : Only these resources}
        {--tenant= : Only this tenant (default: rows without a tenant)}';

    protected $description = 'Export the saved filter presets to JSON (backup, or provisioning another environment)';

    public function handle(): int
    {
        /** @var class-string<SavedFilter> $model */
        $model = config('saved-filter.model', SavedFilter::class);

        $filters = $model::query()
            ->presets()
            ->forTenant($this->option('tenant'))
            ->when($this->option('resource'), fn ($query, array $resources) => $query->whereIn('resource', $resources))
            ->orderBy('resource')
            ->ordered()
            ->get();

        $json = json_encode(
            ['saved_filters' => $filters->map->toPortableArray()->all()],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (! $path = $this->argument('path')) {
            $this->line((string) $json);

            return self::SUCCESS;
        }

        File::put($path, $json.PHP_EOL);
        $this->components->info("Exported {$filters->count()} preset(s) to {$path}.");

        return self::SUCCESS;
    }
}
