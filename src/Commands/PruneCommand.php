<?php

namespace JeffersonGoncalves\SavedFilter\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use JeffersonGoncalves\SavedFilter\Models\SavedFilterReport;

class PruneCommand extends Command
{
    protected $signature = 'saved-filter:prune
        {--resource=* : Also delete every filter of these resources (discontinued tables)}
        {--dry-run : Only count what would be deleted}';

    protected $description = 'Delete saved filters whose owner no longer exists or whose resource class is gone, and reports whose user no longer exists';

    public function handle(): int
    {
        /** @var class-string<SavedFilter> $model */
        $model = config('saved-filter.model', SavedFilter::class);

        $ids = collect()
            ->merge($this->orphanedIds($model::query()->whereNotNull('owner_id'), 'owner_type', 'owner_id'))
            ->merge($model::query()
                ->whereIn('resource', $this->staleResources($model))
                ->pluck('id'))
            ->unique()
            ->values();

        // A deleted filter takes its reports along (foreign key cascade).
        $reportIds = collect($this->orphanedIds(SavedFilterReport::query(), 'user_type', 'user_id'));

        if (! $this->option('dry-run')) {
            // Through the model, so SavedFilterDeleted fires for audit logs.
            $ids->chunk(500)->each(fn ($chunk) => $model::query()->whereKey($chunk->all())->get()->each->delete());
            $reportIds->chunk(500)->each(fn ($chunk) => SavedFilterReport::query()->whereKey($chunk->all())->delete());
        }

        $this->components->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$ids->count()} saved filter(s) and {$reportIds->count()} report(s).");

        return self::SUCCESS;
    }

    /**
     * Ids of the rows whose polymorphic model (class or record) no longer exists.
     *
     * @param  Builder<covariant Model>  $rows
     * @return array<int, int>
     */
    protected function orphanedIds(Builder $rows, string $typeColumn, string $keyColumn): array
    {
        $ids = [];

        foreach ((clone $rows)->distinct()->pluck($typeColumn) as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;
            $matches = (clone $rows)->where($typeColumn, $type)->get(['id', $keyColumn]);

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                array_push($ids, ...$matches->pluck('id'));

                continue;
            }

            $model = new $class;
            $existing = $matches->pluck($keyColumn)->unique()->chunk(500)
                ->flatMap(fn ($keys) => $model->newQuery()->whereKey($keys->all())->pluck($model->getKeyName()))
                ->map(fn ($key) => (string) $key)
                ->all();

            array_push($ids, ...$matches->reject(fn (Model $row) => in_array((string) $row->getAttribute($keyColumn), $existing, true))->pluck('id'));
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
