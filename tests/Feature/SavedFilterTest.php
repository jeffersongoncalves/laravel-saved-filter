<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\SavedFilter\Contracts\BuildsSavedFilterReports;
use JeffersonGoncalves\SavedFilter\Contracts\HostsReportFiles;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterCreated;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterDeleted;
use JeffersonGoncalves\SavedFilter\Jobs\SendSavedFilterReport;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use JeffersonGoncalves\SavedFilter\Models\SavedFilterReport;
use JeffersonGoncalves\SavedFilter\Notifications\SavedFilterReportNotification;
use JeffersonGoncalves\SavedFilter\ReportFile;
use JeffersonGoncalves\SavedFilter\ReportLink;

class LocalizedUser extends User implements HasLocalePreference
{
    protected $table = 'users';

    public function preferredLocale(): string
    {
        return 'pt_BR';
    }
}

function user(string $name = 'Ana'): User
{
    return User::query()->forceCreate(['name' => $name, 'email' => strtolower($name).'@example.com']);
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
            'add_column_order_to_saved_filters_table.php',
            'create_saved_filter_reports_table.php',
            'add_timezone_to_saved_filter_reports_table.php',
        ]);
});

it('runs the published migrations on tables 1.0 and 1.1 already created', function () {
    $migration = fn (string $name) => require __DIR__."/../../database/migrations/{$name}.php.stub";
    $filter = savedFilter(['records_per_page' => '25']);

    // 1.1: table and columns already there.
    $migration('create_saved_filters_table')->up();
    $migration('add_pagination_and_grouping_to_saved_filters_table')->up();
    $migration('add_column_searches_to_saved_filters_table')->up();
    $migration('add_column_order_to_saved_filters_table')->up();
    $migration('create_saved_filter_reports_table')->up();
    $migration('add_timezone_to_saved_filter_reports_table')->up();

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

it('keeps the column order through storage, export and import', function () {
    $order = ['column:status', 'column:title', 'group:Details', 'group:Details:author.name', 'column:created_at'];
    $filter = savedFilter(['column_order' => $order])->refresh();

    expect($filter->column_order)->toBe($order)
        ->and(SavedFilter::STATE)->toContain('column_order');

    $exported = $filter->toPortableArray();
    $filter->delete();
    SavedFilter::import([$exported]);

    expect(SavedFilter::query()->sole()->column_order)->toBe($order);
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

it('prunes filters of deleted owners and gone resources, and reports of deleted users', function () {
    $ana = user();
    $bob = user('Bob');
    $kept = savedFilter([], $ana);
    $orphan = savedFilter([], $bob);
    $gone = savedFilter(['resource' => 'App\\Filament\\Resources\\GoneResource'], $ana);
    $discontinued = savedFilter(['resource' => 'legacy-table'], $ana);
    $preset = savedFilter();
    $anasReport = savedFilterReport($preset, $ana);
    savedFilterReport($preset, $bob);
    $bob->delete();

    $this->artisan('saved-filter:prune', ['--dry-run' => true])->expectsOutputToContain('Would delete 2 saved filter(s) and 1 report(s)');
    expect(SavedFilter::query()->count())->toBe(5)
        ->and(SavedFilterReport::query()->count())->toBe(2);

    $this->artisan('saved-filter:prune', ['--resource' => ['legacy-table']])->assertSuccessful();

    expect(SavedFilter::query()->pluck('id')->all())->toEqualCanonicalizing([$kept->id, $preset->id])
        ->and(SavedFilter::query()->whereKey([$orphan->id, $gone->id, $discontinued->id])->exists())->toBeFalse()
        ->and(SavedFilterReport::query()->pluck('id')->all())->toBe([$anasReport->id]);
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

/** Binds a builder that returns a fixed file and records what it was asked for. */
function fakeReportBuilder(?ReportFile $file = null): object
{
    $builder = new class($file ?? new ReportFile("title\nHello", 'published.csv', 1, 'https://app.test/posts')) implements BuildsSavedFilterReports
    {
        /** @var array<int, array<string, mixed>> */
        public array $calls = [];

        /** @var array<int, string> the app locale during each build */
        public array $locales = [];

        public function __construct(public ReportFile $file) {}

        public function build(Model $user, array $state, string $name, string $format, array $context): ?ReportFile
        {
            $this->calls[] = compact('user', 'state', 'name', 'format', 'context');
            $this->locales[] = app()->getLocale();

            return $this->file;
        }
    };

    app()->instance(BuildsSavedFilterReports::class, $builder);

    return $builder;
}

it('emails a due report once, with the file the builder made', function () {
    Notification::fake();
    $builder = fakeReportBuilder();
    $ana = user();
    $this->travelTo('2026-10-14 09:00:00');
    $report = savedFilterReport($filter = savedFilter(['tenant_id' => 7, 'search' => 'laravel']), $ana, ['context' => ['panel' => 'admin']]);

    $this->travelTo('2026-10-15 08:05:00');
    $this->artisan('saved-filter:send-reports')->expectsOutputToContain('Queued 1 report(s)');
    $this->artisan('saved-filter:send-reports')->expectsOutputToContain('Queued 0 report(s)');

    Notification::assertSentOnDemandTimes(SavedFilterReportNotification::class, 1);
    Notification::assertSentOnDemand(SavedFilterReportNotification::class, fn (SavedFilterReportNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'ana@example.com'
        && $notification->count === 1
        && $notification->schedule === 'Sent every day at 08:00.'
        && $notification->url === 'https://app.test/posts'
        && $notification->toMail($notifiable)->subject === 'Report: Published'
        && $notification->toMail($notifiable)->rawAttachments === [['data' => "title\nHello", 'name' => 'published.csv', 'options' => []]]);

    expect($builder->calls)->toHaveCount(1)
        ->and($builder->calls[0]['user']->is($ana))->toBeTrue()
        ->and($builder->calls[0]['state'])->toHaveKeys(SavedFilter::STATE)
        ->and($builder->calls[0]['state']['search'])->toBe('laravel')
        ->and($builder->calls[0]['format'])->toBe('csv')
        ->and($builder->calls[0]['context'])->toBe(['panel' => 'admin', 'tenant_id' => '7', 'saved_filter_id' => $filter->id, 'report_id' => $report->id]);
});

it('renders the report email with the schedule and a button, without markdown from the view name', function () {
    $render = fn (SavedFilterReportNotification $notification): string => (string) $notification->toMail(new AnonymousNotifiable)->render();
    $html = $render(new SavedFilterReportNotification('Hot [deals](https://evil.test) *now*', 3, 'csv', 'deals.csv', 'Sent every day at 08:00.', 'https://app.test/posts'));

    expect($html)->toContain('Attached: the 3 records of the view')
        ->toContain('Hot [deals](https://evil.test) *now*')
        ->toContain('href="https://app.test/posts"')
        ->toContain('Open the view')
        ->toContain('Sent every day at 08:00. To stop receiving it, turn this report off in the view.')
        ->not->toContain('href="https://evil.test"')
        ->not->toContain('<em>now</em>');

    $oneOff = $render(new SavedFilterReportNotification('Deals', 0, 'csv', 'deals.csv'));

    expect($oneOff)->not->toContain('Open the view')
        ->not->toContain('To stop receiving it');
});

it('runs the schedule on the wall clock of the report\'s timezone', function () {
    $report = fn (array $attributes) => new SavedFilterReport(['frequency' => 'weekly', 'day' => 4, 'time' => '08:00', 'timezone' => 'America/Sao_Paulo', ...$attributes]);
    $thursday = fn (string $time) => now()->setDate(2026, 10, 15)->setTimeFromTimeString($time); // the app runs in UTC

    expect($report([])->lastOccurrence($thursday('11:00'))->toDateTimeString())->toBe('2026-10-15 11:00:00')
        ->and($report([])->lastOccurrence($thursday('11:00'))->getTimezone()->getName())->toBe('UTC')
        ->and($report([])->lastOccurrence($thursday('10:59'))->toDateTimeString())->toBe('2026-10-08 11:00:00')
        ->and($report(['timezone' => 'Not/AZone'])->lastOccurrence($thursday('10:59'))->toDateTimeString())->toBe('2026-10-15 08:00:00')
        ->and($report(['timezone' => 'Not/AZone'])->scheduleTimezone())->toBe('UTC')
        ->and($report(['timezone' => null])->scheduleTimezone())->toBe('UTC')
        ->and($report(['timezone' => 'America/New_York'])->scheduleTimezone())->toBe('America/New_York');
});

it('sends once per day across a DST change, for a time the change skips or repeats', function () {
    // Lisbon jumps from 01:00 to 02:00 on 2026-03-29 (01:30 doesn't exist) and falls back from 02:00 to 01:00 on 2026-10-25 (01:30 happens twice).
    $cases = [
        ['08:00', 3, 28, ['2026-03-28 08:00:00', '2026-03-29 07:00:00']], // WET (UTC+0), then WEST (UTC+1)
        ['01:30', 3, 28, ['2026-03-28 01:30:00', '2026-03-29 01:30:00']], // 01:30 WET, then pushed to 02:30 WEST
        ['01:30', 10, 24, ['2026-10-24 00:30:00', '2026-10-25 00:30:00']], // the first 01:30 (WEST) only
    ];

    foreach ($cases as [$time, $month, $day, $expected]) {
        $report = new SavedFilterReport(['frequency' => 'daily', 'time' => $time, 'timezone' => 'Europe/Lisbon']);
        $report->created_at = $start = now()->setDate(2026, $month, $day)->startOfDay();
        $sends = [];

        // The scheduler runs every 15 minutes for two days (UTC).
        for ($now = $start->copy(); $now->lt($start->copy()->addDays(2)); $now = $now->copy()->addMinutes(15)) {
            if ($report->isDue($now)) {
                $report->last_sent_at = $now;
                $sends[] = $now->toDateTimeString();
            }
        }

        expect([$time => $sends])->toBe([$time => $expected]);
    }
});

it('describes the schedule in the app locale', function () {
    $report = fn (array $attributes) => new SavedFilterReport(['time' => '08:00', ...$attributes]);

    expect($report(['frequency' => 'daily'])->describeSchedule())->toBe('Sent every day at 08:00.')
        ->and($report(['frequency' => 'weekly', 'day' => 1])->describeSchedule())->toBe('Sent every week (Monday) at 08:00.')
        ->and($report(['frequency' => 'monthly', 'day' => 20])->describeSchedule())->toBe('Sent every month on day 20 at 08:00.');

    app()->setLocale('pt_BR');

    expect($report(['frequency' => 'weekly', 'day' => 5])->describeSchedule())->toBe('Enviado toda semana (sexta-feira) às 08:00.')
        ->and($report(['frequency' => 'daily', 'timezone' => 'America/Sao_Paulo'])->describeSchedule())->toBe('Enviado todos os dias às 08:00 (America/Sao_Paulo).')
        ->and($report(['frequency' => 'daily', 'timezone' => 'UTC'])->describeSchedule())->toBe('Enviado todos os dias às 08:00.');
});

it('builds and sends the report in the user\'s preferred locale', function () {
    Notification::fake();
    $builder = fakeReportBuilder();
    $this->travelTo('2026-10-14 09:00:00');
    $user = LocalizedUser::query()->forceCreate(['name' => 'Ana', 'email' => 'ana@example.com']);
    savedFilterReport(savedFilter(), $user);

    $this->travelTo('2026-10-15 08:05:00');
    $this->artisan('saved-filter:send-reports')->assertSuccessful();

    Notification::assertSentOnDemand(SavedFilterReportNotification::class, fn (SavedFilterReportNotification $notification) => $notification->locale === 'pt_BR'
        && $notification->schedule === 'Enviado todos os dias às 08:00.');

    expect($builder->locales)->toBe(['pt_BR'])
        ->and(app()->getLocale())->toBe('en');
});

it('links to files over the size limit instead of attaching them', function () {
    Notification::fake();
    config(['saved-filter.reports_max_attachment_size' => 5]);
    fakeReportBuilder();

    (new SendSavedFilterReport(user(), [], 'Published'))->handle();

    Notification::assertSentOnDemand(SavedFilterReportNotification::class, fn (SavedFilterReportNotification $notification) => $notification->tooLarge
        && $notification->download === null);

    app()->instance(HostsReportFiles::class, new class implements HostsReportFiles
    {
        public function host(ReportFile $file, Model $user): ?ReportLink
        {
            return new ReportLink('https://app.test/reports/abc', now()->addDays(7));
        }
    });
    (new SendSavedFilterReport(user('Bob'), [], 'Published'))->handle();

    Notification::assertSentOnDemand(SavedFilterReportNotification::class, fn (SavedFilterReportNotification $notification) => $notification->tooLarge
        && $notification->download?->url === 'https://app.test/reports/abc');
});

it('renders a download link, or only the count, for files too large to attach', function () {
    $this->travelTo('2026-10-15 08:00:00');
    $mail = fn (?ReportLink $download) => (new SavedFilterReportNotification('Deals', 3, str_repeat('x', 2 * 1024 * 1024), 'deals.xlsx', null, 'https://app.test/posts', null, true, $download))
        ->toMail(new AnonymousNotifiable);

    $linked = $mail(new ReportLink('https://app.test/reports/abc', now()->addDays(7)));
    $html = (string) $linked->render();

    expect($linked->rawAttachments)->toBe([])
        ->and($linked->actionUrl)->toBe('https://app.test/reports/abc')
        ->and($html)->toContain('The file (2 MB) is too large to attach.')
        ->toContain('Download the report')
        ->toContain('The download link expires on October 22, 2026 8:00 AM.')
        ->toContain('href="https://app.test/posts"');

    $countOnly = $mail(null);

    expect($countOnly->rawAttachments)->toBe([])
        ->and($countOnly->actionUrl)->toBe('https://app.test/posts')
        ->and((string) $countOnly->render())->toContain('The file (2 MB) is too large to attach.')
        ->not->toContain('Download the report');
});

it('skips empty reports when told to', function () {
    Notification::fake();
    fakeReportBuilder(new ReportFile('title', 'empty.csv', 0));

    (new SendSavedFilterReport(user(), [], 'Empty'))->handle();
    Notification::assertSentOnDemandTimes(SavedFilterReportNotification::class, 1);

    config(['saved-filter.reports_send_empty' => false]);
    (new SendSavedFilterReport(user('Bob'), [], 'Empty'))->handle();
    Notification::assertSentOnDemandTimes(SavedFilterReportNotification::class, 1);
});

it('links to the one-click unsubscribe when the builder gives one', function () {
    $html = (string) (new SavedFilterReportNotification('Deals', 1, 'csv', 'deals.csv', 'Sent every day at 08:00.', null, 'https://app.test/reports/1/unsubscribe?signature=a(b)'))
        ->toMail(new AnonymousNotifiable)
        ->render();

    expect($html)->toContain('Sent every day at 08:00.')
        ->toContain('href="https://app.test/reports/1/unsubscribe?signature=a%28b%29"')
        ->toContain('Stop receiving this report')
        ->not->toContain('turn this report off in the view');
});

it('sends nothing for paused, unshared or not yet due reports', function () {
    Notification::fake();
    fakeReportBuilder();
    $ana = user();
    $this->travelTo('2026-10-14 09:00:00');
    savedFilterReport(savedFilter(), $ana, ['is_active' => false]);
    savedFilterReport(savedFilter(['name' => 'Private'], user('Bob')), $ana);
    savedFilterReport(savedFilter(['name' => 'Later']), $ana, ['time' => '08:30']); // last occurrence predates it

    $this->travelTo('2026-10-15 08:05:00');
    $this->artisan('saved-filter:send-reports')->expectsOutputToContain('Queued 0 report(s)');

    Notification::assertNothingSent();
});

it('gives the occurrence back when the send fails for good', function () {
    Queue::fake();
    fakeReportBuilder();
    $this->travelTo('2026-10-13 09:00:00');
    $report = savedFilterReport(savedFilter(), user(), ['last_sent_at' => '2026-10-14 08:00:00']);

    $this->travelTo('2026-10-15 08:05:00');
    $this->artisan('saved-filter:send-reports')->expectsOutputToContain('Queued 1 report(s)');

    $job = Queue::pushed(SendSavedFilterReport::class)->sole();
    expect($job->reportId)->toBe($report->id)
        ->and($job->previousSentAt)->toBe('2026-10-14 08:00:00')
        ->and($report->refresh()->last_sent_at->toDateTimeString())->toBe('2026-10-15 08:05:00');

    $job->failed();

    expect($report->refresh()->last_sent_at->toDateTimeString())->toBe('2026-10-14 08:00:00');
    $this->artisan('saved-filter:send-reports')->expectsOutputToContain('Queued 1 report(s)');
});

it('leaves reports due when no builder is bound', function () {
    Notification::fake();
    $this->travelTo('2026-10-14 09:00:00');
    $report = savedFilterReport(savedFilter(), user());

    $this->travelTo('2026-10-15 08:05:00');
    $this->artisan('saved-filter:send-reports')->assertSuccessful();

    Notification::assertNothingSent();
    expect($report->refresh()->last_sent_at)->toBeNull();
});

it('schedules the reports every fifteen minutes', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'saved-filter:send-reports'));

    expect($event?->expression)->toBe('*/15 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
