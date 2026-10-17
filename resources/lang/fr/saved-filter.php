<?php

return [
    'mail' => [
        'subject' => 'Rapport : :name',
        'body' => 'En pièce jointe : les :count enregistrements de la vue « :name ».',
        'stop' => 'Pour ne plus le recevoir, désactivez ce rapport dans la vue.',
        'action' => 'Ouvrir la vue',
    ],

    'schedule' => [
        'daily' => 'Envoyé tous les jours à :time.',
        'weekly' => 'Envoyé chaque semaine (:day) à :time.',
        'monthly' => 'Envoyé chaque mois le :day à :time.',
    ],
];
