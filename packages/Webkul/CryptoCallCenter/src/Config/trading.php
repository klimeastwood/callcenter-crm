<?php

/**
 * How the CRM talks to the trading application.
 *
 * The trading application owns `trading_accounts.balance_usd`, so a manual
 * balance change is not a CRM write: it is a request the trading side performs
 * and mirrors into its own event log. `driver = http` sends that request over
 * the bridge endpoint; `driver = database` writes it straight into the shared
 * tables for an installation that has no bridge (or for a local demo).
 *
 * `token` must equal `TRADING_BRIDGE_TOKEN` of the trading application. When it
 * is empty the caller refuses to send the request instead of getting a 503 from
 * the other side.
 */
return [
    'driver' => env('CRM_TRADING_DRIVER', 'http'),

    'base_url' => env('CRM_TRADING_URL', ''),

    'token' => env('CRM_TRADING_TOKEN', ''),

    /**
     * Whether an unreachable bridge may be replaced by a direct database write.
     *
     * Off by default: the bridge is the only path that keeps the trading side's
     * rules (balance guard, event log) in one place. Enable it only when both
     * applications share the database and the bridge is known to be optional.
     */
    'fallback' => (bool) env('CRM_TRADING_FALLBACK', false),

    'timeout' => (int) env('CRM_TRADING_TIMEOUT', 5),

    /**
     * Upper bound for a single manual change, mirrored by the bridge.
     */
    'max_amount' => (float) env('CRM_TRADING_MAX_AMOUNT', 1000000),

    /**
     * Payouts
     *
     * The trading application stages a withdrawal request and the CRM pays it
     * out. The card number that request points at is sealed with a secret both
     * applications share: the trading side encrypts with its own `APP_KEY`,
     * which the CRM by definition cannot read, so `WITHDRAWAL_CARD_KEY` has to
     * carry the same value on both sides. Left empty, the payout screen shows
     * the last four digits and says the full number is not configured.
     */
    'withdrawal' => [
        'card_key' => env('WITHDRAWAL_CARD_KEY', ''),
    ],

    /**
     * Clients of the trading cabinet.
     *
     * A self-registered client is a CRM user carrying the role the trading
     * application creates for it - "Trading Client" by default, or an existing
     * role pinned with `TRADING_CLIENT_ROLE_ID`. Such an account is a customer,
     * not staff: it must never be offered as the Sales Owner of a lead, and it
     * must not appear in the owner filter of the lead list. The names and the
     * ids are both read because the trading side may use either strategy.
     */
    'client_roles' => [
        'names' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CRM_TRADING_CLIENT_ROLES', env('TRADING_CLIENT_ROLE_NAME', 'Trading Client'))),
        ))),

        'ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('CRM_TRADING_CLIENT_ROLE_IDS', env('TRADING_CLIENT_ROLE_ID', ''))),
        ))),
    ],
];
