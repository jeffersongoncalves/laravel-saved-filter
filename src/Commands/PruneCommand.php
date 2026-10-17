<?php

namespace JeffersonGoncalves\SavedFilter\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

class PruneCommand extends Command
{
    protected $signature = 'saved-filter:prune
        {--resource=* : Also delete every filter of these resources (discontinued tables)}
        {--dry-run : Only count what would be deleted}';

    protected $description = 'Delete saved filters whose owner no longer exists or whose resource class is gone';

    public function handle(): int
    {
        /** @var class-string<SavedFilter> $model */
        $model = config('saved-filter.model', SavedFilter::class);

        $ids = collect()
            ->merge($this->orphanedOwnerIds($model))
            ->merge($model::query()
                ->whereIn('resource', $this->staleResources($model))
                ->pluck('id'))
            ->unique()
            ->values();

        if (! $this->option('dry-run')) {
            // Through the model, so SavedFilterDeleted fires for audit logs.
            $ids->chunk(500)->each(fn ($chunk) => $model::query()->whereKey($chunk->all())->get()->each->delete());
        }

        $this->components->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$ids->count()} saved filter(s).");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<SavedFilter>  $model
     * @return array<int, int>
     */
    protected function orphanedOwnerIds(string $model): array
    {
        $ids = [];

        foreach ($model::query()->whereNotNull('owner_id')->distinct()->pluck('owner_type') as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;
            $filters = $model::query()->where('owner_type', $type)->get(['id', 'owner_id']);

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                array_push($ids, ...$filters->pluck('id'));

                continue;
            }

            $owner = new $class;
            $existing = $filters->pluck('owner_id')->unique()->chunk(500)
                ->flatMap(fn ($keys) => $owner->newQuery()->whereKey($keys->all())->pluck($owner->getKeyName()))
                ->map(fn ($key) => (string) $key)
                ->all();

            array_push($ids, ...$filters->reject(fn (SavedFilter $filter) => in_array($filter->owner_id, $existing, true))->pluck('id'));
        }

        return $ids;
    }

    /**
     * Resources passed with --resource, plus the ones that look like a class name but no longer exist.
     *
     * @param  class-string<SavedFilter>  $model
     * @return array<int, string>
     */
    protected function staleResources(string $model): array
    {
        $gone = $model::query()->distinct()->pluck('resource')
            ->filter(fn (string $resource) => str_contains($resource, '\\') && ! class_exists($resource));

        return $gone->merge((array) $this->option('resource'))->unique()->values()->all();
    }
}
