<?php

namespace Webkul\CryptoCallCenter\Services\Balance;

/**
 * The outcome of one balance change on the trading side.
 *
 * `applied` is false when the trading application recognised the request as a
 * delivery of a change it already performed: the balances are the ones it
 * stored back then and nothing was credited a second time. `tradingEventId` is
 * the row of the shared event log the change was mirrored into, so the CRM can
 * mark it as already turned into an activity.
 */
final readonly class BalanceChange
{
    public function __construct(
        public float $balanceBefore,
        public float $balanceAfter,
        public ?int $tradingEventId = null,
        public bool $applied = true,
    ) {}
}
