<?php

return [
    'mail' => [
        'subject' => 'Relatório: :name',
        'body' => 'Em anexo: os :count registos da vista ":name".',
        'stop' => 'Para deixar de o receber, desative este relatório na vista.',
        'action' => 'Abrir a vista',
        'too_large' => 'O ficheiro (:size) é demasiado grande para ser anexado.',
        'download' => 'Transferir o relatório',
        'expires' => 'A ligação de transferência expira em :date.',
        'unsubscribe' => 'Deixar de receber este relatório',
    ],

    'schedule' => [
        'daily' => 'Enviado todos os dias às :time.',
        'weekly' => 'Enviado todas as semanas (:day) às :time.',
        'monthly' => 'Enviado todos os meses no dia :day às :time.',
    ],
];
