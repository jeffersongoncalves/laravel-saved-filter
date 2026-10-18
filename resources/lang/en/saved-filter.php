<?php

return [
    'mail' => [
        'subject' => 'Report: :name',
        'body' => 'Attached: the :count records of the view ":name".',
        'stop' => 'To stop receiving it, turn this report off in the view.',
        'action' => 'Open the view',
        'too_large' => 'The file (:size) is too large to attach.',
        'download' => 'Download the report',
        'expires' => 'The download link expires on :date.',
        'unsubscribe' => 'Stop receiving this report',
    ],

    'schedule' => [
        'daily' => 'Sent every day at :time.',
        'weekly' => 'Sent every week (:day) at :time.',
        'monthly' => 'Sent every month on day :day at :time.',
    ],
];
