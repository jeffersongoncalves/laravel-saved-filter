<?php

namespace JeffersonGoncalves\SavedFilter\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\SavedFilter\Contracts\BuildsSavedFilterReports;
use JeffersonGoncalves\SavedFilter\Jobs\SendSavedFilterReport;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use JeffersonGoncalves\SavedFilter\Models\SavedFilterReport;

class SendReportsCommand extends Command
{
    protected $signature = 'saved-filter:send-reports';

    protected $description = 'Queue the saved filter email reports that are due';

    public function handle(): int
    {
        // Nothing can build them: leave them due instead of marking occurrences as sent.
        if (! app()->bound(BuildsSavedFilterReports::class)) {
            $this->components->warn('No '.BuildsSavedFilterReports::class.' is bound, no report sent.');

            return self::SUCCESS;
        }

        $sent = 0;

        SavedFilterReport::query()->active()->with(['savedFilter', 'user'])->each(function (SavedFilterReport $report) use (&$sent): void {
            $filter = $report->savedFilter;

            // Unshared since the user subscribed: keep the schedule, send nothing until it's visible again.
            if (! $filter || ! $report->user || ! $report->isDue() || ! $filter->isVisibleTo($report->user)) {
                return;
            }

            $report->update(['last_sent_at' => now()]);

            SendSavedFilterReport::dispatch(
                $report->user,
                $filter->only(SavedFilter::STATE),
                $filter->name,
                $report->format,
                [...$report->context ?? [], 'tenant_id' => $filter->tenant_id],
            );

            $sent++;
        });

        $this->components->info("Queued {$sent} report(s).");

        return self::SUCCESS;
    }
}
