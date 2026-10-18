<?php

return [
    'mail' => [
        'subject' => '报告：:name',
        'body' => '附件：视图“:name”的 :count 条记录。',
        'stop' => '如需停止接收，请在视图中关闭此报告。',
        'action' => '打开视图',
        'too_large' => '文件（:size）太大，无法作为附件发送。',
        'download' => '下载报告',
        'expires' => '下载链接将于 :date 过期。',
        'unsubscribe' => '停止接收此报告',
    ],

    'schedule' => [
        'daily' => '每天 :time 发送。',
        'weekly' => '每周（:day）:time 发送。',
        'monthly' => '每月 :day 日 :time 发送。',
    ],
];
