## Laravel Saved Filter

Saved table states (filters, sort, search, toggled columns) per owner, tenant and resource, with presets, JSON export/import, pruning and events.

### Installation

@verbatim
<code-snippet name="Install" lang="bash">
composer require jeffersongoncalves/laravel-saved-filter
php artisan vendor:publish --tag="saved-filter-migrations"
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
- `filters` is keyed by filter name, `toggled_columns` is a flat `name => bool` map, `column_order` is a list of column names in display order (a list because MySQL JSON objects lose key order),`column_searches` is `column => term` (nested for dotted names); `records_per_page`, `grouping` and `grouping_direction` are nullable strings (null = table default).
- `saved-filter:export` / `saved-filter:import` move presets as JSON; `saved-filter:prune` removes filters of deleted owners and missing resource classes.
- `SavedFilterReport` (`saved_filter_reports`) stores one user's email schedule per filter (`daily`/`weekly`/`monthly`, `time`, `day`, `format`, `context` JSON); `saved-filter:send-reports` (scheduled every 15 minutes) queues `SendSavedFilterReport` for the due ones the user still sees and mails the file. The file comes from a `BuildsSavedFilterReports` implementation bound in the container (returns a `ReportFile` or null); without one nothing is sent. `SavedFilter::STATE` lists the table-state attributes passed to it. The email is `SavedFilterReportNotification` (a `MailMessage`), sent on demand in the user's `HasLocalePreference` locale. Files over `reports_max_attachment_size` aren't attached: a bound `HostsReportFiles` returns a `ReportLink` (url + expiry) for a download button. `reports_send_empty = false` skips empty reports.
- Events:`SavedFilterCreated`, `SavedFilterDeleted`, `SavedFilterApplied` (dispatched by the UI).
