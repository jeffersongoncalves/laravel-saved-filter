<?php

return [
    'mail' => [
        'subject' => 'Report: :name',
        'body' => 'In allegato: i :count record della vista ":name".',
        'stop' => 'Per non riceverlo più, disattiva questo report nella vista.',
        'action' => 'Apri la vista',
    ],

    'schedule' => [
        'daily' => 'Inviato ogni giorno alle :time.',
        'weekly' => 'Inviato ogni settimana (:day) alle :time.',
        'monthly' => 'Inviato ogni mese il giorno :day alle :time.',
    ],
];
