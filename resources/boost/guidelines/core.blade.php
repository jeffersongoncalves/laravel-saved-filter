## Laravel Saved Filter

Saved table states (filters, sort, search, toggled columns) per owner, tenant and resource, with presets, JSON export/import, pruning and events.

### Installation

@verbatim
<code-snippet name="Install" lang="bash">
composer require jeffersongoncalves/laravel-saved-filter
php artisan migrate
</code-snippet>
@endverbatim

### Usage

@verbatim
<code-snippet name="Query the filters a user sees" lang="php">
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

SavedFilter::query()
    ->forResource(PostResource::class)
    ->forTenant($team)
    ->visibleTo($user)
    ->ordered()
    ->get();
</code-snippet>
@endverbatim

### Rules
- `owner_type`/`owner_id` null = preset (visible to everyone in the tenant); `is_locked` = read-only in the UI.
- `tenant_id` is a string column; `forTenant(null)` matches rows without a tenant (also the database-per-tenant case).
- One default per owner, tenant and resource: set `is_default` through the model (`makeDefault()`), not a query-builder update.
- `filters` is keyed by filter name, `toggled_columns` is a flat `name => bool` map.
- `saved-filter:export` / `saved-filter:import` move presets as JSON; `saved-filter:prune` removes filters of deleted owners and missing resource classes.
- Events: `SavedFilterCreated`, `SavedFilterDeleted`, `SavedFilterApplied` (dispatched by the UI).
