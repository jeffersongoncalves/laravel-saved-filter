<?php

namespace JeffersonGoncalves\SavedFilter\Models;

use Carbon\CarbonInterface;
use DateTimeZone;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One user's email schedule for one saved filter: the report goes to that user only, built with their access.
 * Any user may subscribe to a filter they see (their own, a shared one or a preset).
 *
 * @property int $id
 * @property int $saved_filter_id
 * @property string $user_type
 * @property string $user_id
 * @property string $frequency daily|weekly|monthly
 * @property int|null $day ISO weekday (1 = Monday) for weekly, day of the month (1-28) for monthly
 * @property string $time HH:MM, in $timezone
 * @property string|null $timezone IANA name, null = the app timezone
 * @property string $format csv|xlsx
 * @property array<string, mixed>|null $context consumer data, e.g. the Filament panel and page the report is built from
 * @property bool $is_active
 * @property CarbonInterface|null $last_sent_at
 * @property CarbonInterface $created_at
 */
class SavedFilterReport extends Model
{
    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'user_id' => 'string',
        'day' => 'integer',
        'context' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('saved-filter.reports_table', 'saved_filter_reports');
    }

    /** @return BelongsTo<SavedFilter, $this> */
    public function savedFilter(): BelongsTo
    {
        /** @var class-string<SavedFilter> $model */
        $model = config('saved-filter.model', SavedFilter::class);

        return $this->belongsTo($model);
    }

    /** @return MorphTo<Model, $this> */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForUser(Builder $query, Model $user): void
    {
        $query->where('user_type', $user->getMorphClass())->where('user_id', (string) $user->getKey());
    }

    /** The zone the schedule runs in: the report's own, or the app's when it's null or not a zone PHP knows. */
    public function scheduleTimezone(): string
    {
        $appTimezone = (string) config('app.timezone', 'UTC');

        if (blank($this->timezone)) {
            return $appTimezone;
        }

        try {
            return (new DateTimeZone($this->timezone))->getName();
        } catch (Exception) {
            // ponytail: a bad row falls back silently rather than breaking the command for every report.
            return $appTimezone;
        }
    }

    /** "Sent every Monday at 08:00." in the app locale, with the zone when it isn't the app's. */
    public function describeSchedule(): string
    {
        $timezone = $this->scheduleTimezone();
        $time = $timezone === config('app.timezone') ? $this->time : "{$this->time} ({$timezone})";

        return match ($this->frequency) {
            self::WEEKLY => __('saved-filter::saved-filter.schedule.weekly', [
                'day' => now()->startOfWeek(CarbonInterface::MONDAY)->addDays(max(1, min(7, (int) $this->day)) - 1)->locale(app()->getLocale())->dayName,
                'time' => $time,
            ]),
            self::MONTHLY => __('saved-filter::saved-filter.schedule.monthly', ['day' => max(1, min(28, (int) $this->day)), 'time' => $time]),
            default => __('saved-filter::saved-filter.schedule.daily', ['time' => $time]),
        };
    }

    /**
     * The latest scheduled moment at or before now. Computed on the wall clock of the report's timezone
     * (so 08:00 stays 08:00 across DST), returned in the app timezone.
     */
    public function lastOccurrence(?CarbonInterface $now = null): CarbonInterface
    {
        $now = ($now ?? now())->copy()->setTimezone($this->scheduleTimezone());

        return $this->lastOccurrenceAt($now)->setTimezone((string) config('app.timezone', 'UTC'));
    }

    protected function lastOccurrenceAt(CarbonInterface $now): CarbonInterface
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->time) + [1 => 0]);

        $date = match ($this->frequency) {
            self::WEEKLY => $now->copy()->startOfWeek(CarbonInterface::MONDAY)->addDays(max(1, min(7, (int) $this->day)) - 1),
            self::MONTHLY => $now->copy()->startOfMonth()->addDays(max(1, min(28, (int) $this->day)) - 1),
            default => $now->copy()->startOfDay(),
        };
        $at = $date->copy()->setTime($hour, $minute);

        if ($at->lte($now)) {
            return $at;
        }

        // Step the date back and set the time again: stepping $at back would carry a DST shift
        // (01:30 pushed to 02:30 on the change day) into the previous period.
        return (match ($this->frequency) {
            self::WEEKLY => $date->subWeek(),
            self::MONTHLY => $date->subMonthNoOverflow(),
            default => $date->subDay(),
        })->setTime($hour, $minute);
    }

    /**
     * Sent once per occurrence, and never for an occurrence older than the schedule itself.
     * Strictly after last_sent_at: the column drops fractions, so a send at 08:00:00.4 is stored as 08:00:00.
     */
    public function isDue(?CarbonInterface $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $occurrence = $this->lastOccurrence($now);

        return $this->last_sent_at === null
            ? $occurrence->gte($this->created_at)
            : $occurrence->gt($this->last_sent_at);
    }
}
