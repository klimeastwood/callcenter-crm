<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read access to the virtual account the trading application maintains.
 *
 * The table belongs to the trading application and has no CRM model, so this
 * class works through the query builder instead of the repository base. The
 * CRM only reads the balance here; a change goes through the gateway, which
 * owns the transaction.
 */
class TradingAccountRepository
{
    /**
     * The account table owned by the trading application.
     */
    public const TABLE = 'trading_accounts';

    /**
     * Whether the trading side has been deployed.
     */
    public static function available(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /**
     * The balance of one trader, or null when no account exists yet.
     */
    public function balanceFor(int $userId): ?float
    {
        if (! static::available()) {
            return null;
        }

        $balance = DB::table(self::TABLE)->where('user_id', $userId)->value('balance_usd');

        return $balance === null ? null : (float) $balance;
    }
}
