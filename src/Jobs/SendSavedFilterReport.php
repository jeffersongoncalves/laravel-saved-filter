<?php

namespace JeffersonGoncalves\SavedFilter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\SavedFilter\Contracts\BuildsSavedFilterReports;
use JeffersonGoncalves\SavedFilter\Mail\SavedFilterReportMail;

/**
 * Builds a table state's report for a user with the bound BuildsSavedFilterReports and emails it to that user.
 */
class SendSavedFilterReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $state  the SavedFilter::STATE attributes
     * @param  array<string, mixed>  $context  passed to the builder as is
     */
    public function __construct(
        public Model $user,
        public array $state,
        public string $name,
        public string $format = 'csv',
        public array $context = [],
    ) {}

    public function handle(): void
    {
        if (blank($this->user->getAttribute('email')) || ! app()->bound(BuildsSavedFilterReports::class)) {
            return;
        }

        $file = app(BuildsSavedFilterReports::class)->build($this->user, $this->state, $this->name, $this->format, $this->context);

        if (! $file) {
            return;
        }

        Mail::to($this->user)->send(new SavedFilterReportMail($this->name, $file->count, $file->content, $file->fileName));
    }
}
