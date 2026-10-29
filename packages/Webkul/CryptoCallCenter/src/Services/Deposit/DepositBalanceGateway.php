<?php

namespace Webkul\CryptoCallCenter\Services\Deposit;

use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Services\Balance\BalanceChange;

/**
 * Credits a deposit the operator approved in the CRM.
 *
 * A deposit is not a manual correction of the balance: the trading application
 * records it as a `deposit` event, so the client sees the money in the cabinet
 * and the lead timeline shows an incoming transfer. The implementation decides
 * whether the call travels over the HTTP bridge or is written into the shared
 * tables. Either way it is idempotent per `$reference`, because the operator can
 * retry an approval whose response was lost on the way back.
 */
interface DepositBalanceGateway
{
    /**
     * Credit one deposit request.
     *
     * @param  string  $reference  The idempotency key, `deposit:{request id}`.
     *
     * @throws TradingPlatformException When the trading side refuses or cannot be reached.
     */
    public function credit(int $userId, float $amount, string $reference, ?string $operatorName = null): BalanceChange;
}
