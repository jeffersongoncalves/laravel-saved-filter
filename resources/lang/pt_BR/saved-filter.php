<?php

return [
    'mail' => [
        'subject' => 'Relatório: :name',
        'body' => 'Em anexo: os :count registros da visualização ":name".',
        'stop' => 'Para não receber mais, desative este relatório na visualização.',
        'action' => 'Abrir a visualização',
    ],

    'schedule' => [
        'daily' => 'Enviado todos os dias às :time.',
        'weekly' => 'Enviado toda semana (:day) às :time.',
        'monthly' => 'Enviado todo mês no dia :day às :time.',
    ],
];
