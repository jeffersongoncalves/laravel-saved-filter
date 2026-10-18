<?php

return [
    'mail' => [
        'subject' => 'Relatório: :name',
        'body' => 'Em anexo: os :count registros da visualização ":name".',
        'stop' => 'Para não receber mais, desative este relatório na visualização.',
        'action' => 'Abrir a visualização',
        'too_large' => 'O arquivo (:size) é grande demais para ser anexado.',
        'download' => 'Baixar o relatório',
        'expires' => 'O link para download expira em :date.',
        'unsubscribe' => 'Parar de receber este relatório',
    ],

    'schedule' => [
        'daily' => 'Enviado todos os dias às :time.',
        'weekly' => 'Enviado toda semana (:day) às :time.',
        'monthly' => 'Enviado todo mês no dia :day às :time.',
    ],
];
