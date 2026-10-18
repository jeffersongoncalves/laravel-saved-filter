<?php

return [
    'mail' => [
        'subject' => 'Rapport : :name',
        'body' => 'En pièce jointe : les :count enregistrements de la vue « :name ».',
        'stop' => 'Pour ne plus le recevoir, désactivez ce rapport dans la vue.',
        'action' => 'Ouvrir la vue',
        'too_large' => 'Le fichier (:size) est trop volumineux pour être joint.',
        'download' => 'Télécharger le rapport',
        'expires' => 'Le lien de téléchargement expire le :date.',
        'unsubscribe' => 'Ne plus recevoir ce rapport',
    ],

    'schedule' => [
        'daily' => 'Envoyé tous les jours à :time.',
        'weekly' => 'Envoyé chaque semaine (:day) à :time.',
        'monthly' => 'Envoyé chaque mois le :day à :time.',
    ],
];
