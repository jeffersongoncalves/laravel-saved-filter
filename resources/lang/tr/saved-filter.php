<?php

return [
    'mail' => [
        'subject' => 'Rapor: :name',
        'body' => 'Ekte: ":name" görünümünün :count kaydı.',
        'stop' => 'Almayı durdurmak için bu raporu görünümde kapatın.',
        'action' => 'Görünümü aç',
        'too_large' => 'Dosya (:size) eklenemeyecek kadar büyük.',
        'download' => 'Raporu indir',
        'expires' => 'İndirme bağlantısının süresi :date tarihinde doluyor.',
        'unsubscribe' => 'Bu raporu almayı durdur',
    ],

    'schedule' => [
        'daily' => 'Her gün saat :time gönderilir.',
        'weekly' => 'Her hafta (:day) saat :time gönderilir.',
        'monthly' => 'Her ayın :day. günü saat :time gönderilir.',
    ],
];
