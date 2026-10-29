<?php

return [
    [
        'key' => 'crypto_call_center',
        'name' => 'crypto_call_center::app.menu.crypto',
        'route' => 'admin.crypto_call_center.wallets.index',
        'sort' => 7,
        'icon-class' => 'icon-dollar',
    ], [
        'key' => 'call_logs',
        'name' => 'crypto_call_center::app.menu.call-logs',
        'route' => 'admin.crypto_call_center.call_logs.index',
        'sort' => 8,
        'icon-class' => 'icon-call',
    ], [
        'key' => 'withdrawals',
        'name' => 'crypto_call_center::app.menu.withdrawals',
        'route' => 'admin.crypto_call_center.withdrawals.index',
        'sort' => 9,
        'icon-class' => 'icon-download',
    ], [
        'key' => 'deposits',
        'name' => 'crypto_call_center::app.menu.deposits',
        'route' => 'admin.crypto_call_center.deposits.index',
        'sort' => 10,
        'icon-class' => 'icon-stats-up',
    ],
];
