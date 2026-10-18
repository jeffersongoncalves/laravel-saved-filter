<?php

return [
    'mail' => [
        'subject' => 'Bericht: :name',
        'body' => 'Im Anhang: die :count Datensätze der Ansicht ":name".',
        'stop' => 'Um ihn nicht mehr zu erhalten, deaktivieren Sie diesen Bericht in der Ansicht.',
        'action' => 'Ansicht öffnen',
        'too_large' => 'Die Datei (:size) ist zu groß für einen Anhang.',
        'download' => 'Bericht herunterladen',
        'expires' => 'Der Download-Link läuft am :date ab.',
        'unsubscribe' => 'Diesen Bericht nicht mehr erhalten',
    ],

    'schedule' => [
        'daily' => 'Wird täglich um :time gesendet.',
        'weekly' => 'Wird wöchentlich (:day) um :time gesendet.',
        'monthly' => 'Wird monatlich am :day. um :time gesendet.',
    ],
];
