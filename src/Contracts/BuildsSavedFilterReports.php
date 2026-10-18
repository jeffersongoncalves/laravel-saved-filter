<?php

namespace JeffersonGoncalves\SavedFilter\Contracts;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\SavedFilter\ReportFile;

/**
 * Builds the file of a scheduled report. This package decides when and to whom a report goes; the UI that knows
 * the table (e.g. filament-saved-filter) binds an implementation and decides what goes in it.
 */
interface BuildsSavedFilterReports
{
    /**
     * The report file of a table state for a user, or null when that user may not get it.
     *
     * @param  array<string, mixed>  $state  the SavedFilter::STATE attributes
     * @param  array<string, mixed>  $context  the report's context plus 'tenant_id', and for scheduled sends 'saved_filter_id' (link to the view) and 'report_id' (e.g. a signed unsubscribe route)
     */
    public function build(Model $user, array $state, string $name, string $format, array $context): ?ReportFile;
}
