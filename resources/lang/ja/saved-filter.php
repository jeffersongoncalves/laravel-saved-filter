<?php

return [
    'mail' => [
        'subject' => 'レポート: :name',
        'body' => 'ビュー「:name」の :count 件のレコードを添付しました。',
        'stop' => '受信を停止するには、ビューでこのレポートをオフにしてください。',
        'action' => 'ビューを開く',
        'too_large' => 'ファイル（:size）が大きすぎるため添付できません。',
        'download' => 'レポートをダウンロード',
        'expires' => 'ダウンロードリンクの有効期限は :date です。',
        'unsubscribe' => 'このレポートの受信を停止',
    ],

    'schedule' => [
        'daily' => '毎日 :time に送信されます。',
        'weekly' => '毎週（:day）:time に送信されます。',
        'monthly' => '毎月 :day 日の :time に送信されます。',
    ],
];
