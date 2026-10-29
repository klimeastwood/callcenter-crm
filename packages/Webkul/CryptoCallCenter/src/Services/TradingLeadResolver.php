<?php

namespace Webkul\CryptoCallCenter\Services;

use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\LeadProxy;

/**
 * Finds the lead a trading event belongs to.
 *
 * Both applications share the `users` table: an operator signs into the trading
 * terminal and into the CRM under the same account, and a lead carries the
 * `user_id` of the operator working it. That is the only link the two sides
 * have, and it lives in the shared database rather than in a payload.
 */
class TradingLeadResolver
{
    /**
     * The lead of the account the event was written for.
     *
     * An operator can keep several leads open, so the newest one wins: it is
     * the deal the conversation is currently about. Null means the account has
     * no lead yet, and the caller leaves the event unprocessed instead of
     * writing it into a card that belongs to somebody else.
     */
    public function resolve(int $userId): ?Lead
    {
        return LeadProxy::modelClass()::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
