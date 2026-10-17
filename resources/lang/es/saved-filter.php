<?php

return [
    'mail' => [
        'subject' => 'Informe: :name',
        'body' => 'Adjunto: los :count registros de la vista ":name".',
        'stop' => 'Para dejar de recibirlo, desactiva este informe en la vista.',
        'action' => 'Abrir la vista',
    ],

    'schedule' => [
        'daily' => 'Se envía todos los días a las :time.',
        'weekly' => 'Se envía cada semana (:day) a las :time.',
        'monthly' => 'Se envía cada mes el día :day a las :time.',
    ],
];
