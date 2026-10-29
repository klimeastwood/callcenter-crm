<?php

return [
    [
        'key' => 'crypto_call_center',
        'name' => 'crypto_call_center::app.acl.crypto',
        'route' => 'admin.crypto_call_center.wallets.index',
        'sort' => 7,
    ], [
        'key' => 'call_logs',
        'name' => 'crypto_call_center::app.acl.call-logs',
        'route' => 'admin.crypto_call_center.call_logs.index',
        'sort' => 8,
    ], [
        'key' => 'withdrawals',
        'name' => 'crypto_call_center::app.acl.withdrawals',
        'route' => 'admin.crypto_call_center.withdrawals.index',
        'sort' => 9,
    ], [
        /**
         * The reveal is a child of the queue on purpose: a role that sees the
         * payout list does not automatically get the numbers it is paid to.
         *
         * The decision belongs to the same key: the desk that may read the whole
         * card is the desk that closes the request, and a "read the number but
         * decide nothing" role would be a role nobody assigns.
         */
        'key' => 'withdrawals.card',
        'name' => 'crypto_call_center::app.acl.withdrawals-card',
        'route' => [
            'admin.crypto_call_center.withdrawals.card',
            'admin.crypto_call_center.withdrawals.approve',
            'admin.crypto_call_center.withdrawals.reject',
            'admin.crypto_call_center.withdrawals.complete',
        ],
        'sort' => 1,
    ], [
        /**
         * The deposit queue. A deposit is money coming in, so the list itself is
         * harmless - it prints a masked card, a client and an amount.
         */
        'key' => 'deposits',
        'name' => 'crypto_call_center::app.acl.deposits',
        'route' => 'admin.crypto_call_center.deposits.index',
        'sort' => 10,
    ], [
        /**
         * Approving and rejecting are a child of the queue: they open the card
         * and move the balance, so a role that reads the list does not inherit
         * the right to decide.
         */
        'key' => 'deposits.review',
        'name' => 'crypto_call_center::app.acl.deposits-review',
        'route' => [
            'admin.crypto_call_center.deposits.show',
            'admin.crypto_call_center.deposits.approve',
            'admin.crypto_call_center.deposits.reject',
        ],
        'sort' => 1,
    ], [
        'key' => 'crypto_call_center.balance',
        'name' => 'crypto_call_center::app.acl.balance',
        'route' => 'admin.crypto_call_center.balance_adjustments.store',
        'sort' => 1,
    ],
];
