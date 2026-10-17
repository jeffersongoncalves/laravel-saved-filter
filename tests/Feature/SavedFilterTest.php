<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterCreated;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterDeleted;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use JeffersonGoncalves\SavedFilter\Models\SavedFilterReport;

function user(string $name = 'Ana'): User
{
    return User::query()->forceCreate(['name' => $name]);
}

function savedFilter(array $attributes = [], ?User $owner = null): SavedFilter
{
    return SavedFilter::query()->create([
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
        'resource' => 'posts',
        'name' => 'Published',
        'filters' => ['status' => ['value' => 'published']],
        ...$attributes,
    ]);
}

it('publishes the migration stubs in order', function () {
    $this->artisan('vendor:publish', ['--tag' => 'saved-filter-migrations'])->assertSuccessful();

    $published = File::glob(database_path('migrations/*_saved_filter*_table.php'));
    File::delete($published);

    expect(array_map(fn (string $file) => preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($file)), $published))
        ->toBe([
            'create_saved_filters_table.php',
            'add_pagination_and_grouping_to_saved_filters_table.php',
            'add_column_searches_to_saved_filters_table.php',
            'create_saved_filter_reports_table.php',
        ]);
});

it('runs the published migrations on tables 1.0 and 1.1 already created', function () {
    $migration = fn (string $name) => require __DIR__."/../../database/migrations/{$name}.php.stub";
    $filter = savedFilter(['records_per_page' => '25']);

    // 1.1: table and columns already there.
    $migration('create_saved_filters_table')->up();
    $migration('add_pagination_and_grouping_to_saved_filters_table')->up();
    $migration('add_column_searches_to_saved_filters_table')->up();
    $migration('create_saved_filter_reports_table')->up();

    expect($filter->refresh()->records_per_page)->toBe('25');

    // 1.0: table without the pagination and grouping columns.
    Schema::table('saved_filters', fn (Blueprint $table) => $table->dropColumn(['records_per_page', 'grouping', 'grouping_direction']));
    $migration('add_pagination_and_grouping_to_saved_filters_table')->up();

    expect(Schema::hasColumns('saved_filters', ['records_per_page', 'grouping', 'grouping_direction']))->toBeTrue()
        ->and(SavedFilter::query()->count())->toBe(1);
});

it('stores the table state with casts', function () {
    $filter = savedFilter(['sort_column' => 'title', 'sort_direction' => 'desc', 'toggled_columns' => ['body' => false]], user())->refresh();

    expect($filter->filters)->toBe(['status' => ['value' => 'published']])
        ->and($filter->toggled_columns)->toBe(['body' => false])
        ->and($filter->owner_id)->toBe('1')
        ->and($filter->owner)->toBeInstanceOf(User::class)
        ->and($filter->is_default)->toBeFalse()
        ->and($filter->isPreset())->toBeFalse();
});

it('stores pagination and grouping, and exports them', function () {
    $filter = savedFilter(['records_per_page' => '25', 'grouping' => 'status', 'grouping_direction' => 'desc'])->refresh();

    expect($filter->toPortableArray())->toMatchArray([
        'records_per_page' => '25',
        'grouping' => 'status',
        'grouping_direction' => 'desc',
    ]);
});

it('stores per-column searches and round-trips them through export and import', function () {
    $searches = ['title' => 'laravel', 'author' => ['name' => 'Ana']];
    $filter = savedFilter(['column_searches' => $searches])->refresh();

    expect($filter->column_searches)->toBe($searches)
        ->and($filter->toPortableArray()['column_searches'])->toBe($searches);

    $exported = $filter->toPortableArray();
    $filter->delete();
    SavedFilter::import([$exported]);

    expect(SavedFilter::query()->sole()->column_searches)->toBe($searches);
});

it('scopes by resource, tenant and owner', function () {
    $ana = user();
    $bob = user('Bob');

    $own = savedFilter(['tenant_id' => 1], $ana);
    $bobsPrivate = savedFilter(['tenant_id' => 1], $bob);
    $bobsPublic = savedFilter(['tenant_id' => 1, 'is_public' => true], $bob);
    $preset = savedFilter(['tenant_id' => 1, 'is_locked' => true]);
    savedFilter(['tenant_id' => 2], $ana);
    savedFilter(['resource' => 'Other', 'tenant_id' => 1], $ana);

    $visible = SavedFilter::query()
        ->forResource('posts')
        ->forTenant(1)
        ->visibleTo($ana)
        ->pluck('id')
        ->all();

    expect($visible)->toEqualCanonicalizing([$own->id, $bobsPublic->id, $preset->id])
        ->and($visible)->not->toContain($bobsPrivate->id)
        ->and(SavedFilter::query()->ownedBy($ana)->count())->toBe(3)
        ->and(SavedFilter::query()->presets()->pluck('id')->all())->toBe([$preset->id])
        ->and($own->isOwnedBy($ana))->toBeTrue()
        ->and($own->isOwnedBy($bob))->toBeFalse();
});

it('keeps one default per owner, tenant and resource', function () {
    $ana = user();
    $first = savedFilter(['is_default' => true], $ana);
    $bobs = savedFilter(['is_default' => true], user('Bob'));
    $second = savedFilter(['name' => 'Drafts'], $ana)->makeDefault();

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue()
        ->and($bobs->refresh()->is_default)->toBeTrue();
});

it('dispatches created and deleted events', function () {
    Event::fake([SavedFilterCreated::class, SavedFilterDeleted::class]);

    savedFilter()->delete();

    Event::assertDispatched(SavedFilterCreated::class);
    Event::assertDispatched(SavedFilterDeleted::class);
});

it('imports exported rows, updating by resource and name', function () {
    $ana = user();
    savedFilter(['name' => 'Published', 'search' => 'old'], $ana);

    $imported = SavedFilter::import(['saved_filters' => [
        ['resource' => 'posts', 'name' => 'Published', 'search' => 'new', 'owner_id' => 99],
        ['resource' => 'posts', 'name' => 'Drafts', 'filters' => ['status' => ['value' => 'draft']]],
        ['name' => 'No resource'],
        'garbage',
    ]], $ana, locked: true);

    expect($imported)->toHaveCount(2)
        ->and(SavedFilter::query()->count())->toBe(2)
        ->and(SavedFilter::query()->where('name', 'Published')->value('search'))->toBe('new')
        ->and(SavedFilter::query()->ownedBy($ana)->where('is_locked', true)->count())->toBe(2);
});

it('exports and imports presets through the commands', function () {
    savedFilter(['is_locked' => true, 'icon' => 'heroicon-o-star']);
    savedFilter(['name' => 'Mine'], user());
    $path = sys_get_temp_dir().'/saved-filters-'.uniqid().'.json';

    $this->artisan('saved-filter:export', ['path' => $path])->assertSuccessful();

    $rows = json_decode(File::get($path), true)['saved_filters'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['name' => 'Published', 'icon' => 'heroicon-o-star', 'is_locked' => true])
        ->and($rows[0])->not->toHaveKey('owner_id');

    SavedFilter::query()->presets()->delete();
    $this->artisan('saved-filter:import', ['path' => $path, '--tenant' => 7])->assertSuccessful();

    expect(SavedFilter::query()->presets()->forTenant(7)->value('name'))->toBe('Published');

    File::delete($path);
});

it('rejects an unreadable import file', function () {
    $this->artisan('saved-filter:import', ['path' => 'missing.json'])->assertFailed();
});

it('prunes filters of deleted owners and gone resources', function () {
    $ana = user();
    $bob = user('Bob');
    $kept = savedFilter([], $ana);
    $orphan = savedFilter([], $bob);
    $gone = savedFilter(['resource' => 'App\\Filament\\Resources\\GoneResource'], $ana);
    $discontinued = savedFilter(['resource' => 'legacy-table'], $ana);
    $preset = savedFilter();
    $bob->delete();

    $this->artisan('saved-filter:prune', ['--dry-run' => true])->expectsOutputToContain('Would delete 2');
    expect(SavedFilter::query()->count())->toBe(5);

    $this->artisan('saved-filter:prune', ['--resource' => ['legacy-table']])->assertSuccessful();

    expect(SavedFilter::query()->pluck('id')->all())->toEqualCanonicalizing([$kept->id, $preset->id])
        ->and(SavedFilter::query()->whereKey([$orphan->id, $gone->id, $discontinued->id])->exists())->toBeFalse();
});

function savedFilterReport(SavedFilter $filter, User $user, array $attributes = []): SavedFilterReport
{
    return SavedFilterReport::query()->create([
        'saved_filter_id' => $filter->id,
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->getKey(),
        'frequency' => SavedFilterReport::DAILY,
        'time' => '08:00',
        'format' => 'csv',
        ...$attributes,
    ]);
}

it('finds the last occurrence of each frequency', function () {
    $now = now()->setDate(2026, 10, 14)->setTime(10, 0); // a Wednesday
    $report = fn (array $attributes) => new SavedFilterReport(['time' => '08:00', ...$attributes]);

    expect($report(['frequency' => 'daily'])->lastOccurrence($now)->toDateTimeString())->toBe('2026-10-14 08:00:00')
        ->and($report(['frequency' => 'daily', 'time' => '11:00'])->lastOccurrence($now)->toDateTimeString())->toBe('2026-10-13 11:00:00')
        ->and($report(['frequency' => 'weekly', 'day' => 1])->lastOccurrence($now)->toDateTimeString())->toBe('2026-10-12 08:00:00')
        ->and($report(['frequency' => 'weekly', 'day' => 5])->lastOccurrence($now)->toDateTimeString())->toBe('2026-10-09 08:00:00')
        ->and($report(['frequency' => 'monthly', 'day' => 20])->lastOccurrence($now)->toDateTimeString())->toBe('2026-09-20 08:00:00');
});

it('is due once per occurrence, never before the schedule existed and never when inactive', function () {
    $this->travelTo('2026-10-14 09:00:00');
    $report = savedFilterReport(savedFilter(), user(), ['context' => ['panel' => 'admin']]);

    expect($report->isDue())->toBeFalse() // today 08:00 is older than the schedule
        ->and($report->refresh()->context)->toBe(['panel' => 'admin'])
        ->and($report->is_active)->toBeTrue();

    $this->travelTo('2026-10-15 08:00:00.400');
    expect($report->isDue())->toBeTrue();

    // Stored without the fraction: 08:00:00, the occurrence itself.
    $report->update(['last_sent_at' => now()]);
    $this->travelTo('2026-10-15 08:01:00');
    expect($report->refresh()->isDue())->toBeFalse();

    $this->travelTo('2026-10-16 08:00:00');
    expect($report->isDue())->toBeTrue()
        ->and(tap($report)->update(['is_active' => false])->isDue())->toBeFalse()
        ->and(SavedFilterReport::query()->active()->count())->toBe(0);
});

it('scopes reports by user and deletes them with their saved filter', function () {
    $ana = user();
    $filter = savedFilter();
    savedFilterReport($filter, $ana);
    savedFilterReport($filter, user('Bob'));

    expect($filter->reports()->count())->toBe(2)
        ->and(SavedFilterReport::query()->forUser($ana)->sole()->savedFilter->is($filter))->toBeTrue();

    $filter->delete();

    expect(SavedFilterReport::query()->count())->toBe(0);
});

it('tells whether a filter is visible to a user', function () {
    $ana = user();
    $bob = user('Bob');

    expect(savedFilter()->isVisibleTo($ana))->toBeTrue()
        ->and(savedFilter(['is_public' => true], $bob)->isVisibleTo($ana))->toBeTrue()
        ->and(savedFilter([], $ana)->isVisibleTo($ana))->toBeTrue()
        ->and(savedFilter([], $bob)->isVisibleTo($ana))->toBeFalse()
        ->and(savedFilter([], $bob)->isVisibleTo(null))->toBeFalse();
});
