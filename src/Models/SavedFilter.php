<?php

namespace JeffersonGoncalves\SavedFilter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterCreated;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterDeleted;

/**
 * A saved table state. `owner` null = preset (shared with everyone, usually locked); `tenant_id` null = no tenant.
 *
 * @property int $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $tenant_id
 * @property string $resource table key, usually the Filament resource class
 * @property string $name
 * @property string|null $icon
 * @property string|null $color
 * @property array<string, mixed>|null $filters filter form state, keyed by filter name
 * @property string|null $sort_column
 * @property string|null $sort_direction asc|desc
 * @property array<string, bool>|null $toggled_columns column name => visible
 * @property string|null $search
 * @property array<string, mixed>|null $column_searches column name => search, nested for dotted names (author.name)
 * @property string|null $records_per_page a pagination option ("25", "all"), null = the table's default
 * @property string|null $grouping group name, null = not grouped
 * @property string|null $grouping_direction asc|desc
 * @property bool $is_default
 * @property bool $is_public
 * @property bool $is_locked
 * @property int $order_column
 */
class SavedFilter extends Model
{
    /** The attributes that describe a filter on its own: what export writes and import reads. */
    public const PORTABLE = [
        'resource', 'name', 'icon', 'color', 'filters', 'sort_column', 'sort_direction',
        'toggled_columns', 'search', 'column_searches', 'records_per_page', 'grouping', 'grouping_direction',
        'is_default', 'is_public', 'is_locked', 'order_column',
    ];

    /** The attributes that make up a table state: what a report is built from. */
    public const STATE = [
        'filters', 'sort_column', 'sort_direction', 'search', 'column_searches', 'toggled_columns',
        'records_per_page', 'grouping', 'grouping_direction',
    ];

    protected $guarded = [];

    protected $attributes = [
        'is_default' => false,
        'is_public' => false,
        'is_locked' => false,
        'order_column' => 0,
    ];

    protected $casts = [
        'owner_id' => 'string',
        'tenant_id' => 'string',
        'filters' => 'array',
        'toggled_columns' => 'array',
        'column_searches' => 'array',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'is_locked' => 'boolean',
        'order_column' => 'integer',
    ];

    protected $dispatchesEvents = [
        'created' => SavedFilterCreated::class,
        'deleted' => SavedFilterDeleted::class,
    ];

    public function getTable(): string
    {
        return (string) config('saved-filter.table', 'saved_filters');
    }

    protected static function booted(): void
    {
        // One default per owner, tenant and resource: the last one marked wins.
        static::saved(function (self $filter) {
            if ($filter->is_default && ($filter->wasRecentlyCreated || $filter->wasChanged('is_default'))) {
                static::query()
                    ->whereKeyNot($filter->getKey())
                    ->forResource($filter->resource)
                    ->forTenant($filter->tenant_id)
                    ->where('owner_type', $filter->owner_type)
                    ->where('owner_id', $filter->owner_id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<SavedFilterReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(SavedFilterReport::class);
    }

    public function isPreset(): bool
    {
        return $this->owner_id === null;
    }

    public function isOwnedBy(?Model $owner): bool
    {
        return $owner !== null
            && $this->owner_type === $owner->getMorphClass()
            && $this->owner_id === (string) $owner->getKey();
    }

    /** Same rule as the visibleTo scope: presets, public filters and the owner's own. */
    public function isVisibleTo(?Model $owner): bool
    {
        return $this->isPreset() || $this->is_public || $this->isOwnedBy($owner);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForResource(Builder $query, string $resource): void
    {
        $query->where('resource', $resource);
    }

    /**
     * Null = rows without a tenant (single-tenant apps and database-per-tenant setups).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForTenant(Builder $query, Model|string|int|null $tenant): void
    {
        $tenant = $tenant instanceof Model ? $tenant->getKey() : $tenant;

        $tenant === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', (string) $tenant);
    }

    /**
     * Null = presets.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, ?Model $owner): void
    {
        if ($owner === null) {
            $query->whereNull('owner_id');

            return;
        }

        $query->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getKey());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePresets(Builder $query): void
    {
        $query->whereNull('owner_id');
    }

    /**
     * The owner's own filters, the public ones and the presets.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, ?Model $owner): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('is_public', true)
            ->orWhereNull('owner_id')
            ->when($owner, fn (Builder $q, Model $owner) => $q->orWhere(fn (Builder $q) => $q->ownedBy($owner))));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('order_column')->orderBy('id');
    }

    /**
     * Mark as the owner's default for this resource (unmarks the previous one).
     */
    public function makeDefault(): static
    {
        $this->update(['is_default' => true]);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPortableArray(): array
    {
        return $this->only(self::PORTABLE);
    }

    /**
     * Create or update filters from exported rows, matched by resource + name for the given owner and tenant.
     * Accepts `{"saved_filters": [...]}` or a plain list. Rows without a resource or name are skipped.
     *
     * @param  array<mixed>  $payload
     * @return Collection<int, static>
     */
    public static function import(array $payload, ?Model $owner = null, Model|string|int|null $tenant = null, ?bool $locked = null): Collection
    {
        $tenant = $tenant instanceof Model ? $tenant->getKey() : $tenant;
        $rows = $payload['saved_filters'] ?? $payload;
        $imported = new Collection;

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row) || ! is_string($row['resource'] ?? null) || ! is_string($row['name'] ?? null) || blank($row['name'])) {
                continue;
            }

            $attributes = array_intersect_key($row, array_flip(self::PORTABLE));

            if ($locked !== null) {
                $attributes['is_locked'] = $locked;
            }

            /** @var static $filter */
            $filter = static::query()->firstOrNew([
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner === null ? null : (string) $owner->getKey(),
                'tenant_id' => $tenant === null ? null : (string) $tenant,
                'resource' => $attributes['resource'],
                'name' => $attributes['name'],
            ]);

            $filter->fill($attributes)->save();
            $imported->push($filter);
        }

        return $imported;
    }
}
