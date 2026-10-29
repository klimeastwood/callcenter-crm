<?php

namespace Webkul\CryptoCallCenter\Support;

use Webkul\CryptoCallCenter\Services\Balance\DatabaseTradingBalanceGateway;

/**
 * Whether the CRM can reach the trading application at all.
 *
 * Two supported deployments: the bridge endpoint answers over HTTP, or both
 * applications share one database and the CRM writes into the trading tables
 * itself. A lead card shows the balance block only in the first case - in the
 * second one there is nothing to configure and no reason to hide it. Checking
 * the same condition the gateways apply keeps the block from offering a form
 * that can only fail.
 */
class TradingBridge
{
    /**
     * The configured transport, narrowed to what this build understands.
     */
    public static function driver(): string
    {
        return config('crypto_call_center.trading.driver') === 'database' ? 'database' : 'http';
    }

    /**
     * Whether a balance change has a chance to succeed.
     */
    public static function ready(): bool
    {
        if (static::driver() === 'database') {
            return DatabaseTradingBalanceGateway::available();
        }

        return filled(config('crypto_call_center.trading.base_url'))
            && filled(config('crypto_call_center.trading.token'));
    }

    /**
     * Whether a payout can be closed from the CRM.
     *
     * Only the HTTP bridge can do it: `paid` debits the trader's balance and the
     * debit lives in the trading application, so a shared database is not enough
     * here, unlike a balance adjustment. A `database` transport leaves the
     * button hidden instead of offering an action that can only be refused.
     */
    public static function canClosePayout(): bool
    {
        return static::driver() === 'http' && static::ready();
    }
}
