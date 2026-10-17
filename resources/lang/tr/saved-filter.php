<?php

return [
    'mail' => [
        'subject' => 'Rapor: :name',
        'body' => 'Ekte: ":name" görünümünün :count kaydı.',
        'stop' => 'Almayı durdurmak için bu raporu görünümde kapatın.',
        'action' => 'Görünümü aç',
    ],

    'schedule' => [
        'daily' => 'Her gün saat :time gönderilir.',
        'weekly' => 'Her hafta (:day) saat :time gönderilir.',
        'monthly' => 'Her ayın :day. günü saat :time gönderilir.',
    ],
];
