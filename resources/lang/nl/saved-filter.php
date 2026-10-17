<?php

return [
    'mail' => [
        'subject' => 'Rapport: :name',
        'body' => 'In de bijlage: de :count records van de weergave ":name".',
        'stop' => 'Wil je het niet meer ontvangen, zet dit rapport dan uit in de weergave.',
        'action' => 'Weergave openen',
    ],

    'schedule' => [
        'daily' => 'Wordt elke dag om :time verzonden.',
        'weekly' => 'Wordt elke week (:day) om :time verzonden.',
        'monthly' => 'Wordt elke maand op dag :day om :time verzonden.',
    ],
];
