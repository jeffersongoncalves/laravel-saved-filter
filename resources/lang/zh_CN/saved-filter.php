<?php

return [
    'mail' => [
        'subject' => '报告：:name',
        'body' => '附件：视图“:name”的 :count 条记录。',
        'stop' => '如需停止接收，请在视图中关闭此报告。',
        'action' => '打开视图',
    ],

    'schedule' => [
        'daily' => '每天 :time 发送。',
        'weekly' => '每周（:day）:time 发送。',
        'monthly' => '每月 :day 日 :time 发送。',
    ],
];
