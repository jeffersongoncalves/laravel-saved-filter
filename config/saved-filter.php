<?php

use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

return [
    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    |
    | One row per saved filter: the owner (any model, null for presets), the
    | tenant, the resource it belongs to and the table state it restores.
    |
    */
    'table' => 'saved_filters',

    /*
    |--------------------------------------------------------------------------
    | Reports table
    |--------------------------------------------------------------------------
    |
    | One row per user subscribed to a scheduled email report of a saved
    | filter: frequency, time, format and when it was last sent.
    |
    */
    'reports_table' => 'saved_filter_reports',

    /*
    |--------------------------------------------------------------------------
    | Report emails
    |--------------------------------------------------------------------------
    |
    | Files larger than this many bytes are not attached: the email carries a
    | download link instead when a HostsReportFiles implementation is bound
    | (e.g. jeffersongoncalves/laravel-report-url), otherwise only the count
    | and the link to the view. Mail providers usually cap messages at 10-25 MB.
    |
    | Set send_empty to false to skip the email when the report has no records.
    |
    */
    'reports_max_attachment_size' => 10 * 1024 * 1024,

    'reports_send_empty' => true,

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | Extend JeffersonGoncalves\SavedFilter\Models\SavedFilter to add
    | relations, policies or scopes, and point this key at your class.
    |
    */
    'model' => SavedFilter::class,
];
