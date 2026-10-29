<?php

namespace Webkul\CryptoCallCenter\Services;

use Webkul\CryptoCallCenter\Events\TradingEventReceived;
use Webkul\CryptoCallCenter\Repositories\TradingEventRepository;

/**
 * Hands the new rows of the shared trading log to the listeners.
 *
 * Called by the scheduler every minute. The ledger written by the listeners
 * makes the call idempotent: a run that overlaps the previous one, a retry
 * after a failure or a manual `artisan` call all leave exactly one timeline
 * entry per trading event.
 */
class TradingEventFeed
{
    public function __construct(protected TradingEventRepository $repository) {}

    /**
     * Dispatch every event that has no ledger entry yet.
     *
     * @return int the number of rows read, reported by the command
     */
    public function dispatchPending(int $limit = 200): int
    {
        $events = $this->repository->pending($limit);

        foreach ($events as $event) {
            event(new TradingEventReceived($event));
        }

        return $events->count();
    }
}
