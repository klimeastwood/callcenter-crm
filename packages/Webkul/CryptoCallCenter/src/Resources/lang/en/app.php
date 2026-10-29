<?php

return [
    'menu' => [
        'crypto' => 'Crypto',
        'call-logs' => 'Call history',
        'withdrawals' => 'Withdrawals',
        'deposits' => 'Deposits',
    ],

    'acl' => [
        'crypto' => 'Crypto',
        'call-logs' => 'Call history',
        'balance' => 'Balance changes',
        'withdrawals' => 'Withdrawals',
        'withdrawals-card' => 'Reveal the card number',
        'deposits' => 'Deposits',
        'deposits-review' => 'Approve or reject a deposit',
    ],

    'branding' => [
        'name' => 'CRYPTOISK',

        /**
         * Single source of truth for the "powered by" line: the admin layout
         * footer (through the `core_config` default) and the login screens
         * (through the `admin::app.components.layouts.powered-by.description`
         * translation line) both read this key.
         */
        'powered-by' => 'Powered by KLIMIATA, for educational purposes of the Institute of the Investigative Committee',
    ],

    'wallets' => [
        'index' => [
            'title' => 'Crypto wallets',

            'datagrid' => [
                'id' => 'ID',
                'wallet-address' => 'Wallet address',
                'network' => 'Network',
                'lead' => 'Lead',
                'created-at' => 'Created at',
                'view-lead' => 'View lead',
            ],
        ],
    ],

    'call-logs' => [
        'index' => [
            'title' => 'Call history',

            'datagrid' => [
                'id' => 'ID',
                'call-date' => 'Call date',
                'phone-number' => 'Phone number',
                'lead' => 'Lead',
                'manager' => 'Manager',
                'duration' => 'Duration',
                'status' => 'Status',
                'recording' => 'Recording',
                'listen' => 'Listen',
                'transcript' => 'Transcript',
                'view-lead' => 'View lead',
            ],
        ],

        'status' => [
            'completed' => 'Completed',
            'missed' => 'Missed',
            'failed' => 'Failed',
        ],
    ],

    'withdrawals' => [
        'index' => [
            'title' => 'Withdrawals',

            'unavailable' => 'The trading application has not deployed its payout tables yet, so there is nothing to show. The queue appears as soon as the `withdrawal_requests` and `withdrawal_cards` tables exist.',

            'datagrid' => [
                'id' => 'ID',
                'created-at' => 'Requested at',
                'client' => 'Client',
                'email' => 'Email',
                'amount' => 'Amount',
                'method' => 'Method',
                'requisites' => 'Requisites',
                'status' => 'Status',
                'open' => 'Open the request',
                'empty' => '—',
            ],
        ],

        'status' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'paid' => 'Paid',
        ],

        'methods' => [
            'card' => 'Card',
            'crypto' => 'Crypto wallet',
        ],

        'brands' => [
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'mir' => 'Mir',
            'unknown' => 'Card',
        ],

        'card' => [
            'title' => 'Payout to :client',
            'back' => 'Back to the payout queue',
            'request' => 'Request',
            'client' => 'Client',
            'email' => 'Email',
            'amount' => 'Amount',
            'method' => 'Method',
            'created' => 'Requested at',
            'request-id' => 'Request',
            'requisites' => 'Requisites',
            'wallet' => 'Wallet address',
            'holder' => 'Holder',
            'brand' => 'Payment system',
            'expiry' => 'Valid until',
            'number' => 'Card number',
            'cvv' => 'CVV',
            'unreadable' => 'The number is sealed with a key this CRM does not hold, so it cannot be opened. Set the same WITHDRAWAL_CARD_KEY on both applications.',
            'not-configured' => 'WITHDRAWAL_CARD_KEY is not set, so the full number cannot be opened. The queue shows the last four digits only.',
            'warning' => 'Pay out only to the requisites above. The full number is opened here and nowhere else: it is never stored by the CRM, never written to the log and never exported.',
            'review' => 'Review',
            'reviewed-by' => 'Reviewed by',
            'reviewed-at' => 'Reviewed at',
            'review-note' => 'Note',
            'no-review' => 'Nobody has reviewed this request yet: it is waiting for the payout desk.',
            'waiting' => 'The request is waiting for an operator with the right to decide payouts.',
            'completed' => 'The payout is completed and the request is closed. The client sees «Выплачено» in the cabinet.',
        ],

        'review' => [
            'approve-title' => 'Approve the payout',
            'approve-hint' => 'Approving closes the request in the cabinet. The back office still pays the money; the trader balance is debited later, when the payout is marked as completed.',
            'complete-title' => 'Mark the payout as completed',
            'complete-hint' => 'Confirm only after the money has actually left the account. The request is closed as paid, the trading side debits the amount from the trader\'s balance, and the client sees «Выплачено». If the balance no longer covers it, the request stays approved.',
            'reject-title' => 'Reject the request',
            'reject-hint' => 'The request is closed and the client reads the reason in the cabinet. Nothing is paid.',
            'note' => 'Note (optional)',
            'reason' => 'Reason',
            'approve' => 'Approve',
            'complete' => 'Mark as completed',
            'reject' => 'Reject',
            'approved' => 'The payout of $:amount is approved. The client sees the decision in the cabinet.',
            'completed' => 'The payout of $:amount is marked as completed. The client sees «Выплачено» in the cabinet.',
            'rejected' => 'The payout request is rejected. The client sees the reason in the cabinet.',
        ],

        'errors' => [
            'unavailable' => 'The trading application has not deployed its payout tables, so the request cannot be decided.',
            'not-found' => 'The payout request was not found: it may have been deleted in the trading application.',
            'already-reviewed' => 'The request is already reviewed (:status), so it cannot be decided twice.',
            'not-approved' => 'Only an approved payout can be marked as completed (current status: :status).',
            'requires-bridge' => 'Only the bridge to the trading application can close a payout: it is the side that debits the balance. The shared table still accepts approvals and rejections, so set CRM_TRADING_URL and CRM_TRADING_TOKEN to mark a payout as completed.',
            'not-configured' => 'The bridge to the trading application is not configured: set CRM_TRADING_URL and CRM_TRADING_TOKEN or switch CRM_TRADING_DRIVER to `database`.',
            'unreachable' => 'The trading application did not answer. The request is still pending - try again in a minute.',
            'refused' => 'The trading application refused the payout decision.',
            'rejected' => 'The trading application answered with HTTP :status.',
        ],
    ],

    'deposits' => [
        'index' => [
            'title' => 'Deposits',

            'unavailable' => 'The trading application has not deployed its deposit tables yet, so there is nothing to show. The queue appears as soon as the `deposit_requests` and `deposit_cards` tables exist.',

            'datagrid' => [
                'id' => 'ID',
                'created-at' => 'Requested at',
                'client' => 'Client',
                'email' => 'Email',
                'amount' => 'Amount',
                'requisites' => 'Card',
                'status' => 'Status',
                'open' => 'Review the request',
                'empty' => '—',
            ],
        ],

        'status' => [
            'pending' => 'Pending',
            'approved' => 'Credited',
            'rejected' => 'Rejected',
        ],

        'brands' => [
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'mir' => 'Mir',
            'unknown' => 'Card',
        ],

        'show' => [
            'title' => 'Deposit of :client',
            'back' => 'Back to the deposit queue',
            'request' => 'Request',
            'client' => 'Client',
            'email' => 'Email',
            'amount' => 'Amount',
            'created' => 'Requested at',
            'request-id' => 'Request',
            'card' => 'Card',
            'holder' => 'Holder',
            'brand' => 'Payment system',
            'expiry' => 'Valid until',
            'number' => 'Card number',
            'cvv' => 'CVV',
            'unreadable' => 'The number is sealed with a key this CRM does not hold, so it cannot be opened. Set the same WITHDRAWAL_CARD_KEY on both applications.',
            'not-configured' => 'WITHDRAWAL_CARD_KEY is not set, so the full number cannot be opened. The queue shows the last four digits only.',
            'warning' => 'Check the transfer before approving: the amount is credited to the trader balance and cannot be undone from the panel. The full number is opened here and nowhere else - the CRM never stores it and never writes it to the log.',
            'review' => 'Review',
            'reviewed-by' => 'Reviewed by',
            'reviewed-at' => 'Reviewed at',
            'review-note' => 'Note',
            'no-review' => 'Nobody has reviewed this request yet: it is waiting for the deposit desk.',
            'waiting' => 'The request is waiting for an operator with the right to review deposits.',
        ],

        'review' => [
            'approve-title' => 'Confirm the transfer',
            'approve-hint' => 'Approve only when :amount has arrived on the card. The trader balance is credited and the client sees the deposit immediately.',
            'reject-title' => 'Reject the request',
            'reject-hint' => 'Nothing is credited. The reason is stored and shown to the client in the cabinet.',
            'note' => 'Note (optional)',
            'reason' => 'Reason',
            'approve' => 'Approve and credit',
            'reject' => 'Reject',
            'approved' => 'The deposit of $:amount is credited. New balance: $:balance.',
            'rejected' => 'The deposit request is rejected. The client sees the reason in the cabinet.',
        ],

        'errors' => [
            'already-reviewed' => 'The request is already reviewed (:status), so it cannot be decided twice.',
            'unavailable' => 'The trading application has not deployed its account tables, so the deposit cannot be credited.',
            'not-configured' => 'The bridge to the trading application is not configured: set CRM_TRADING_URL and CRM_TRADING_TOKEN or switch CRM_TRADING_DRIVER to `database`.',
            'unreachable' => 'The trading application did not answer. The request is still pending - try again in a minute.',
            'refused' => 'The trading application refused the deposit.',
            'rejected' => 'The trading application answered with HTTP :status.',
            'zero-amount' => 'The deposit amount must be greater than zero.',
            'reference-required' => 'The request has no idempotency key, so it cannot be credited safely.',
            'amount-too-big' => 'The deposit is above the allowed limit of $:max.',
        ],
    ],

    'trading-events' => [
        'title' => 'Trading events',

        'activity' => [
            'deposit' => 'Client deposited $:amount',
            'long' => 'Client opened a long :symbol position worth $:amount',
            'short' => 'Client opened a short :symbol position worth $:amount',
            'closed-profit' => 'Client closed the :symbol position with a profit of $:amount',
            'closed-loss' => 'Client closed the :symbol position with a loss of $:amount',
            'closed-flat' => 'Client closed the :symbol position without profit or loss',
            'balance' => 'Balance: $:before → $:after',
            'balance-after' => 'Balance: $:balance',
            'volume' => 'Volume: :amount :symbol',
            'entry' => 'Entry price: $:price',
            'prices' => 'Entry $:entry → exit $:close',
            'unknown' => 'Unrecognised trading event :type',

            /**
             * Wording follows the direction of the change: the payload carries
             * a signed amount, and "credited $500" reads better than
             * "changed by +$500" on the timeline.
             */
            'adjustment-credit' => 'Operator credited $:amount to the client balance',
            'adjustment-write-off' => 'Operator wrote off $:amount from the client balance',
            'adjustment-reason' => 'Reason: :reason',

            /**
             * A payout the client staged in the cabinet. The payload carries the
             * masked requisites only, so the timeline states where the money is
             * going without publishing a number.
             */
            'withdrawal' => 'Client requested a payout of $:amount',
            'withdrawal-card' => 'Card: :number (:brand)',
            'withdrawal-wallet' => 'Wallet: :wallet',

            /**
             * A deposit the client staged and an operator has to confirm. The
             * payload carries the masked card only, so the timeline states where
             * the money came from without publishing a number.
             */
            'deposit-requested' => 'Client asked to deposit $:amount',
            'deposit-card' => 'Card: :number (:brand)',
        ],

        'badge' => [
            'deposit' => 'Deposit',
            'opened' => 'Position opened',
            'closed' => 'Position closed',
            'adjustment' => 'Balance change',
            'withdrawal' => 'Payout requested',
            'deposit-requested' => 'Deposit requested',
        ],
    ],

    'balance-adjustments' => [
        'title' => 'Trader balance',

        'current-balance' => 'Current balance',

        'no-account' => 'The trading application has not opened an account for this lead yet, so there is no balance to change.',

        'hint' => 'A positive amount is a bonus, a negative one is a write-off. The trading application applies the change and logs it as a `balance.adjustment` event.',

        'history' => 'Recent changes',

        'empty-history' => 'No manual changes yet.',

        'history-operator' => 'Operator',

        'history-time' => 'When',

        'applied' => 'Balance changed by :amount. New balance: $:balance.',

        'form' => [
            'amount' => 'Amount, USD',
            'reason' => 'Reason',
            'submit' => 'Apply',
        ],

        /**
         * Written into the lead timeline, in the language of the operator who
         * made the change.
         */
        'activity' => [
            'title' => 'Balance changed by :amount. Reason: :reason',
        ],

        'errors' => [
            'not-configured' => 'The trading bridge is not configured: set CRM_TRADING_URL and CRM_TRADING_TOKEN, or switch CRM_TRADING_DRIVER to `database`.',
            'refused' => 'The trading application refused the change.',
            'rejected' => 'The trading application answered with an unexpected status (:status).',
            'unreachable' => 'The trading application did not answer, so nothing was changed. Try again in a minute.',
            'unavailable' => 'The trading application is not reachable from the CRM: its tables are missing and the bridge is not configured.',
            'no-account' => 'The lead has no trader account, so there is no balance to change.',
            'zero-amount' => 'Enter a non-zero amount.',
            'reason-required' => 'A reason is required: the history has to explain the change.',
            'amount-too-big' => 'A single change cannot exceed $:max.',
            'insufficient' => 'The balance is $:balance: the write-off would make it negative.',
        ],
    ],

];
