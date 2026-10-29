<?php

namespace Webkul\CryptoCallCenter\Services\Balance;

use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;

/**
 * Performs a signed balance change on the trading side.
 *
 * The CRM never touches `trading_accounts` directly through this contract: the
 * implementation decides whether the change travels over the HTTP bridge or is
 * written into the shared tables. Implementations must be idempotent per
 * `$adjustmentId`, because the operator can retry a request whose response was
 * lost on the way back.
 */
interface TradingBalanceGateway
{
    /**
     * Apply a signed amount to the account of one trader.
     *
     * @param  float  $amount  Positive for a bonus, negative for a write-off.
     * @param  int  $adjustmentId  CRM row id, used as the idempotency key.
     *
     * @throws TradingPlatformException When the amount is refused or the trail cannot be written.
     */
    public function adjust(int $userId, float $amount, string $reason, int $adjustmentId, ?string $operatorName = null): BalanceChange;
}
