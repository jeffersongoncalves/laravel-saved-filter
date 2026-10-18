<?php

namespace JeffersonGoncalves\SavedFilter\Contracts;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\SavedFilter\ReportFile;
use JeffersonGoncalves\SavedFilter\ReportLink;

/**
 * Keeps a report file that is too large to attach and gives back a download link for it.
 * Bind an implementation (e.g. jeffersongoncalves/laravel-report-url) to email links instead of dropping the file.
 */
interface HostsReportFiles
{
    /**
     * A download link for the file, meant for that user only, or null when it can't be hosted.
     */
    public function host(ReportFile $file, Model $user): ?ReportLink;
}
