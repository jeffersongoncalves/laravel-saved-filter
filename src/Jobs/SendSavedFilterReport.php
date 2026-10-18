<?php

namespace JeffersonGoncalves\SavedFilter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Traits\Localizable;
use JeffersonGoncalves\SavedFilter\Contracts\BuildsSavedFilterReports;
use JeffersonGoncalves\SavedFilter\Contracts\HostsReportFiles;
use JeffersonGoncalves\SavedFilter\Models\SavedFilterReport;
use JeffersonGoncalves\SavedFilter\Notifications\SavedFilterReportNotification;

/**
 * Builds a table state's report for a user with the bound BuildsSavedFilterReports and emails it to that user.
 */
class SendSavedFilterReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Localizable;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> seconds between the attempts */
    public array $backoff = [60, 300];

    /**
     * @param  array<string, mixed>  $state  the SavedFilter::STATE attributes
     * @param  array<string, mixed>  $context  passed to the builder as is
     * @param  int|null  $reportId  the scheduled report this send is for, null for a one-off send
     * @param  string|null  $previousSentAt  that report's last_sent_at before this occurrence was claimed
     */
    public function __construct(
        public Model $user,
        public array $state,
        public string $name,
        public string $format = 'csv',
        public array $context = [],
        public ?int $reportId = null,
        public ?string $previousSentAt = null,
    ) {}

    public function handle(): void
    {
        if (blank($this->user->getAttribute('email')) || ! app()->bound(BuildsSavedFilterReports::class)) {
            return;
        }

        // On-demand notifications don't read HasLocalePreference: apply it here, to the file, the schedule and the email.
        $locale = $this->user instanceof HasLocalePreference ? $this->user->preferredLocale() : null;

        $this->withLocale($locale, function () use ($locale): void {
            $file = app(BuildsSavedFilterReports::class)->build($this->user, $this->state, $this->name, $this->format, $this->context);

            if (! $file || ($file->count === 0 && ! config('saved-filter.reports_send_empty', true))) {
                return;
            }

            $tooLarge = strlen($file->content) > (int) config('saved-filter.reports_max_attachment_size', 10 * 1024 * 1024);
            $download = $tooLarge && app()->bound(HostsReportFiles::class) ? app(HostsReportFiles::class)->host($file, $this->user) : null;
            $schedule = $this->reportId === null ? null : SavedFilterReport::query()->find($this->reportId)?->describeSchedule();

            $notification = new SavedFilterReportNotification(
                $this->name, $file->count, $file->content, $file->fileName, $schedule, $file->url, $file->unsubscribeUrl, $tooLarge, $download,
            );

            // On demand: the user model doesn't need the Notifiable trait.
            Notification::route('mail', $this->user->getAttribute('email'))->notify($notification->locale($locale));
        });
    }

    /**
     * Out of attempts: give the occurrence back, so the next scheduled run sends it again.
     */
    public function failed(): void
    {
        if ($this->reportId !== null) {
            SavedFilterReport::query()->whereKey($this->reportId)->update(['last_sent_at' => $this->previousSentAt]);
        }
    }
}
