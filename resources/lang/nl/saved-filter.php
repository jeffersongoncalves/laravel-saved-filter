<?php

return [
    'mail' => [
        'subject' => 'Rapport: :name',
        'body' => 'In de bijlage: de :count records van de weergave ":name".',
        'stop' => 'Wil je het niet meer ontvangen, zet dit rapport dan uit in de weergave.',
        'action' => 'Weergave openen',
        'too_large' => 'Het bestand (:size) is te groot om bij te voegen.',
        'download' => 'Rapport downloaden',
        'expires' => 'De downloadlink verloopt op :date.',
        'unsubscribe' => 'Dit rapport niet meer ontvangen',
    ],

    'schedule' => [
        'daily' => 'Wordt elke dag om :time verzonden.',
        'weekly' => 'Wordt elke week (:day) om :time verzonden.',
        'monthly' => 'Wordt elke maand op dag :day om :time verzonden.',
    ],
];
