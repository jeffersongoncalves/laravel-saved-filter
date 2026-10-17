<?php

return [
    'mail' => [
        'subject' => 'Bericht: :name',
        'body' => 'Im Anhang: die :count Datensätze der Ansicht ":name".',
        'stop' => 'Um ihn nicht mehr zu erhalten, deaktivieren Sie diesen Bericht in der Ansicht.',
        'action' => 'Ansicht öffnen',
    ],

    'schedule' => [
        'daily' => 'Wird täglich um :time gesendet.',
        'weekly' => 'Wird wöchentlich (:day) um :time gesendet.',
        'monthly' => 'Wird monatlich am :day. um :time gesendet.',
    ],
];
