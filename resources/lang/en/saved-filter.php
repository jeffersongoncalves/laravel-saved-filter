<?php

return [
    'mail' => [
        'subject' => 'Report: :name',
        'body' => 'Attached: the :count records of the view ":name".',
        'stop' => 'To stop receiving it, turn this report off in the view.',
        'action' => 'Open the view',
    ],

    'schedule' => [
        'daily' => 'Sent every day at :time.',
        'weekly' => 'Sent every week (:day) at :time.',
        'monthly' => 'Sent every month on day :day at :time.',
    ],
];
