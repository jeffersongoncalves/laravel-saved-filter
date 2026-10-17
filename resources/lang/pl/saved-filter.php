<?php

return [
    'mail' => [
        'subject' => 'Raport: :name',
        'body' => 'W załączniku: :count rekordów widoku „:name”.',
        'stop' => 'Aby przestać go otrzymywać, wyłącz ten raport w widoku.',
        'action' => 'Otwórz widok',
    ],

    'schedule' => [
        'daily' => 'Wysyłany codziennie o :time.',
        'weekly' => 'Wysyłany co tydzień (:day) o :time.',
        'monthly' => 'Wysyłany co miesiąc w dniu :day o :time.',
    ],
];
